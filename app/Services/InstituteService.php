<?php

namespace App\Services;

use App\Enums\AddressType;
use App\Enums\InstituteStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Admin;
use App\Models\Institute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Institute lifecycle. Rules here hold even for Super Admin, who bypasses policies.
 */
class InstituteService
{
    public function __construct(private NumberSequenceService $sequences) {}

    public function create(array $data, array $profile = [], array $address = [], ?Admin $by = null): Institute
    {
        return DB::transaction(function () use ($data, $profile, $address, $by) {
            $institute = Institute::create([
                ...$data,
                'code' => $this->sequences->nextInstituteCode(),
                'slug' => $this->uniqueSlug($data['name']),
                'is_main' => false,
                'status' => InstituteStatus::Active,
                'approved_by_admin_id' => $by?->id,
                'approved_at' => now(),
            ]);

            $institute->profile()->create($profile);

            if ($address !== []) {
                $institute->saveAddress(AddressType::Office, $address);
            }

            $this->recordStatus($institute, null, InstituteStatus::Active, 'Institute created', $by);

            return $institute->refresh();
        });
    }

    public function changeStatus(Institute $institute, InstituteStatus $to, string $reason, Admin $by): void
    {
        if ($institute->is_main && $to !== InstituteStatus::Active) {
            throw new DomainRuleException('The main institute cannot be suspended, expired, or closed.');
        }

        if (trim($reason) === '') {
            throw new DomainRuleException('A reason is required to change the institute status.');
        }

        DB::transaction(function () use ($institute, $to, $reason, $by) {
            $institute = Institute::query()->lockForUpdate()->findOrFail($institute->id);
            $from = $institute->status;

            if ($from === $to) {
                throw new DomainRuleException("The institute is already {$to->label()}.");
            }

            $institute->status = $to;

            if ($to === InstituteStatus::Active && $institute->approved_at === null) {
                $institute->approved_at = now();
                $institute->approved_by_admin_id = $by->id;
            }

            $institute->save();
            $this->recordStatus($institute, $from, $to, $reason, $by);
        });

        $institute->refresh();
    }

    public function delete(Institute $institute, Admin $by): void
    {
        if ($institute->is_main) {
            throw new DomainRuleException('The main institute cannot be deleted.');
        }

        if ($institute->students()->exists()) {
            throw new DomainRuleException('An institute with students cannot be deleted. Close it instead.');
        }

        if ($institute->users()->where('is_owner', false)->exists()) {
            throw new DomainRuleException('Remove the institute staff before deleting it.');
        }

        activity()->causedBy($by)->performedOn($institute)->event('institute.deleted')->log('Institute deleted');

        $institute->delete();
    }

    private function recordStatus(Institute $institute, ?InstituteStatus $from, InstituteStatus $to, string $reason, ?Admin $by): void
    {
        $institute->statusHistories()->create([
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'changed_by_admin_id' => $by?->id,
            'changed_at' => now(),
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'institute';
        $slug = $base;

        for ($i = 2; Institute::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
