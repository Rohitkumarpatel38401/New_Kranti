<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\Enrollments\EnrollmentResource;
use App\Filament\Shared\AdmissionForm;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * HQ direct admission into any active institute: the enrollment is approved at once
 * (franchise wallets are charged the HQ fee as usual).
 */
class NewAdmission extends Page
{
    protected string $view = 'filament.shared.new-admission';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static string|UnitEnum|null $navigationGroup = 'Admissions';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'New admission';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth('admin')->user()?->can('approve_enrollment');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components(AdmissionForm::components(pickInstitute: true));
    }

    public function save(): void
    {
        $enrollment = AdmissionForm::save($this->form->getState(), auth('admin')->user());

        Notification::make()->success()
            ->title("Admitted {$enrollment->student->full_name}")
            ->body("Enrollment {$enrollment->enrollment_no} is active. Registration no. {$enrollment->student->registration_no}.")
            ->send();

        $this->redirect(EnrollmentResource::getUrl('index'));
    }
}
