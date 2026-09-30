<?php

namespace App\Filament\Institute\Pages;

use App\Filament\Institute\Resources\Enrollments\EnrollmentResource;
use App\Filament\Shared\AdmissionForm;
use App\Models\Enrollment;
use App\Models\Student;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * One-form admission: new student + course, submitted to HQ at once (the wallet is charged on submit).
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
        $user = auth('institute')->user();

        return (bool) $user?->can('create', Student::class) && $user->can('create', Enrollment::class);
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components(AdmissionForm::components(pickInstitute: false));
    }

    public function save(): void
    {
        $enrollment = AdmissionForm::save($this->form->getState(), auth('institute')->user());

        Notification::make()->success()
            ->title("Admission submitted for {$enrollment->student->full_name}")
            ->body('HQ will approve it; the enrollment number is given on approval.')
            ->send();

        $this->redirect(EnrollmentResource::getUrl('index'));
    }
}
