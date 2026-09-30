<?php

namespace App\Filament\Institute\Pages\Auth;

use App\Enums\AddressType;
use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Services\ImpersonationService;
use App\Services\WalletService;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The institute user's single profile page (full panel layout): the institute's details as HQ keeps them
 * (read-only) and the user's own account. An impersonating HQ admin may fix the owner's name or phone,
 * but never their email or password.
 */
class EditProfile extends BaseEditProfile
{
    public static function getLabel(): string
    {
        return 'My profile';
    }

    public function form(Schema $schema): Schema
    {
        $impersonating = app(ImpersonationService::class)->isImpersonating();

        return $schema->components([
            Section::make('Institute')
                ->description('Kept by KCTI head office. Ask HQ to change these details.')
                ->columns(3)
                ->schema($this->instituteEntries()),
            Section::make('My account')
                ->columns(2)
                ->schema([
                    $this->getNameFormComponent(),
                    TextInput::make('phone')->tel()->maxLength(20),
                    $this->getEmailFormComponent()->disabled($impersonating),
                    TextEntry::make('role')->label('Role')
                        ->state(fn () => $this->user()->getRoleNames()->map(fn ($r) => ucfirst($r))->implode(', ') ?: '—'),
                ]),
            Section::make('Change password')
                ->columns(3)
                ->hidden($impersonating)
                ->schema([
                    $this->getPasswordFormComponent(),
                    $this->getPasswordConfirmationFormComponent(),
                    $this->getCurrentPasswordFormComponent(),
                ]),
        ]);
    }

    /** @return array<int, TextEntry> */
    private function instituteEntries(): array
    {
        $institute = fn (): Institute => $this->user()->institute;

        return [
            TextEntry::make('institute_code')->label('Code')->state(fn () => $institute()->code),
            TextEntry::make('institute_name')->label('Name')->state(fn () => $institute()->name),
            TextEntry::make('institute_status')->label('Status')->badge()->state(fn () => $institute()->status->label()),
            TextEntry::make('institute_email')->label('Email')->state(fn () => $institute()->email ?: '—'),
            TextEntry::make('institute_phone')->label('Phone')->state(fn () => $institute()->phone ?: '—'),
            TextEntry::make('institute_owner')->label('Owner')->state(fn () => $institute()->profile?->owner_name ?: '—'),
            TextEntry::make('institute_address')->label('Address')->columnSpan(3)
                ->state(fn () => ($a = $institute()->address(AddressType::Office))
                    ? collect([$a->line_1, $a->line_2, $a->city, $a->district, $a->state, $a->pincode])->filter()->implode(', ')
                    : '—'),
            TextEntry::make('franchise_start')->label('Franchise since')->state(fn () => $institute()->franchise_start_on?->format('d M Y') ?? '—'),
            TextEntry::make('franchise_expiry')->label('Franchise valid till')->state(fn () => $institute()->franchise_expiry_on?->format('d M Y') ?? '—'),
            TextEntry::make('wallet_balance')->label('Wallet balance')
                ->state(fn () => $institute()->wallet_exempt ? 'Not required (main institute)' : '₹'.number_format((float) app(WalletService::class)->balance($institute()), 2)),
            TextEntry::make('courses_count')->label('Courses you can teach')->state(fn () => $institute()->authorizedCourses()->count()),
            TextEntry::make('staff_count')->label('Staff accounts')->state(fn () => InstituteUser::query()->where('institute_id', $institute()->id)->count()),
            TextEntry::make('students_count')->label('Students')
                ->state(fn () => Student::query()->where('institute_id', $institute()->id)->count().' ('.Enrollment::query()->where('institute_id', $institute()->id)->where('status', EnrollmentStatus::Active)->count().' active enrollments)'),
        ];
    }

    private function user(): InstituteUser
    {
        return auth('institute')->user();
    }
}
