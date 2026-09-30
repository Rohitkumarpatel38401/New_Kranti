<?php

namespace App\Services;

use App\Enums\AddressType;
use App\Enums\FranchiseApplicationStatus;
use App\Exceptions\DomainRuleException;
use App\Filament\Shared\AddressFields;
use App\Models\Admin;
use App\Models\FranchiseApplication;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Notifications\InstituteOwnerCredentials;
use App\Support\PanelNotifier;
use App\Support\Permissions\InstitutePermissions;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Website franchise enquiries → reviewed by HQ → converted into a live institute with an owner login.
 */
class FranchiseOnboardingService
{
    public function __construct(
        private NumberSequenceService $sequences,
        private InstituteService $institutes,
    ) {}

    public function submit(array $data, array $address): FranchiseApplication
    {
        return DB::transaction(function () use ($data, $address) {
            $number = sprintf('KCTI/FA/%d/%05d', now()->year, $this->sequences->next('franchise_application:'.now()->year));

            $application = FranchiseApplication::create([
                ...$data,
                'application_no' => $number,
                'status' => FranchiseApplicationStatus::New,
            ]);

            $application->saveAddress(AddressType::Office, $address);

            PanelNotifier::admins('approve_franchise', 'New franchise application',
                "{$application->proposed_center_name} · {$application->applicant_name} · {$application->phone}", url('/admin/franchise-applications'), 'heroicon-o-building-storefront');

            return $application;
        });
    }

    public function markContacted(FranchiseApplication $application, Admin $by): void
    {
        $this->review($application, FranchiseApplicationStatus::Contacted, null, $by);
    }

    public function reject(FranchiseApplication $application, string $remarks, Admin $by): void
    {
        if (trim($remarks) === '') {
            throw new DomainRuleException('Remarks are required to reject an application.');
        }

        $this->review($application, FranchiseApplicationStatus::Rejected, $remarks, $by);
    }

    /**
     * @param  array{institute_name?: string, owner_name?: string, owner_email?: string, owner_phone?: string}  $overrides
     */
    public function convert(FranchiseApplication $application, Admin $by, array $overrides = []): Institute
    {
        return $this->convertWithOutcome($application, $by, $overrides)['institute'];
    }

    /**
     * Converts, then emails the owner. A mail failure is reported but never undoes the conversion.
     *
     * @return array{institute: Institute, owner: InstituteUser, emailed: bool}
     */
    public function convertWithOutcome(FranchiseApplication $application, Admin $by, array $overrides = []): array
    {
        $password = Str::password(12, symbols: false);

        [$institute, $owner] = DB::transaction(function () use ($application, $by, $overrides, $password) {
            $application = FranchiseApplication::query()->lockForUpdate()->findOrFail($application->id);

            if (! $application->status->isOpen()) {
                throw new DomainRuleException("Application {$application->application_no} is already {$application->status->label()}.");
            }

            $ownerEmail = Str::lower(trim($overrides['owner_email'] ?? $application->email));
            $ownerName = $overrides['owner_name'] ?? $application->applicant_name;
            $ownerPhone = $overrides['owner_phone'] ?? $application->phone;

            if (InstituteUser::withTrashed()->where('email', $ownerEmail)->exists()) {
                throw new DomainRuleException("{$ownerEmail} already has an institute login. Use a different owner email.");
            }

            $institute = $this->institutes->create(
                [
                    'name' => $overrides['institute_name'] ?? $application->proposed_center_name,
                    'email' => $application->email,
                    'phone' => $application->phone,
                    'franchise_start_on' => now()->toDateString(),
                ],
                [
                    'owner_name' => $ownerName,
                    'owner_email' => $ownerEmail,
                    'owner_phone' => $ownerPhone,
                    'computers_count' => $application->computers_count,
                    'area_sqft' => $application->area_sqft,
                ],
                $application->address(AddressType::Office)?->only(AddressFields::KEYS) ?? [],
                $by,
            );

            try {
                $owner = InstituteUser::create([
                    'institute_id' => $institute->id,
                    'name' => $ownerName,
                    'email' => $ownerEmail,
                    'phone' => $ownerPhone,
                    'password' => $password,
                    'is_owner' => true,
                    'is_active' => true,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Another request took the email after our check; the transaction rolls back the institute too.
                throw new DomainRuleException("{$ownerEmail} already has an institute login. Use a different owner email.");
            }
            $owner->assignRole(InstitutePermissions::OWNER);

            $application->update([
                'status' => FranchiseApplicationStatus::Approved,
                'reviewed_by_admin_id' => $by->id,
                'reviewed_at' => now(),
                'converted_institute_id' => $institute->id,
            ]);

            return [$institute, $owner];
        });

        try {
            $owner->notify(new InstituteOwnerCredentials($institute, $password));
            $emailed = true;
        } catch (Throwable $e) {
            report($e);
            $emailed = false;
        }

        return ['institute' => $institute, 'owner' => $owner, 'emailed' => $emailed];
    }

    private function review(FranchiseApplication $application, FranchiseApplicationStatus $to, ?string $remarks, Admin $by): void
    {
        DB::transaction(function () use ($application, $to, $remarks, $by) {
            $locked = FranchiseApplication::query()->lockForUpdate()->findOrFail($application->id);

            if (! $locked->status->isOpen()) {
                throw new DomainRuleException("Application {$locked->application_no} is already {$locked->status->label()}.");
            }

            $locked->update([
                'status' => $to,
                'review_remarks' => $remarks ?? $locked->review_remarks,
                'reviewed_by_admin_id' => $by->id,
                'reviewed_at' => now(),
            ]);
        });

        $application->refresh();
    }
}
