<?php

namespace App\Filament\Shared;

use App\Enums\AddressType;
use App\Enums\Gender;
use App\Enums\GuardianRelation;
use App\Enums\QualificationLevel;
use App\Enums\SocialCategory;
use App\Models\Student;
use App\Services\StudentService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Arr;

/**
 * Student admission fields, grouped the way a college admission form is: form data is keyed
 * student.*, personal.*, guardians[], address.*, qualifications[] and saved through StudentService.
 */
class StudentForm
{
    /** @return array<string, array> label => components; the create wizard and the edit tabs share them. */
    public static function sections(): array
    {
        return [
            'Student' => self::basic(),
            'Personal' => self::personal(),
            'Guardians' => [self::guardians()],
            'Address' => [Grid::make(2)->schema(AddressFields::make('address'))],
            'Education' => [self::qualifications()],
        ];
    }

    /** @return array<Step> */
    public static function steps(): array
    {
        return collect(self::sections())->map(fn (array $components, string $label) => Step::make($label)->schema($components))->values()->all();
    }

    /** @return array<Tab> */
    public static function tabs(): array
    {
        return collect(self::sections())->map(fn (array $components, string $label) => Tab::make($label)->schema($components))->values()->all();
    }

    public static function basic(): array
    {
        return [
            Grid::make(3)->schema([
                TextInput::make('student.first_name')->label('First name')->required()->maxLength(100),
                TextInput::make('student.middle_name')->label('Middle name')->maxLength(100),
                TextInput::make('student.last_name')->label('Last name')->maxLength(100),
                TextInput::make('student.phone')->label('Mobile')->required()->tel()->regex('/^[6-9]\d{9}$/'),
                TextInput::make('student.email')->label('Email')->email()->maxLength(255)
                    ->unique(table: 'students', column: 'email', ignorable: fn (?Student $record) => $record),
                DatePicker::make('student.joined_on')->label('Admission date')->required()->default(now())->maxDate(now()),
                FileUpload::make('student.photo_path')->label('Photo')->image()->disk('local')->visibility('private')
                    ->directory('students/photos')->maxSize(2048)->imageEditor(),
                FileUpload::make('student.signature_path')->label('Signature')->image()->disk('local')->visibility('private')
                    ->directory('students/signatures')->maxSize(1024),
            ]),
        ];
    }

    public static function personal(): array
    {
        return [
            Grid::make(3)->schema([
                DatePicker::make('personal.dob')->label('Date of birth')->required()->maxDate(now()->subYears(8)),
                Select::make('personal.gender')->label('Gender')->options(Gender::class)->required(),
                Select::make('personal.category')->label('Category')->options(SocialCategory::class),
                Select::make('personal.blood_group')->label('Blood group')
                    ->options(array_combine($g = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], $g)),
                TextInput::make('personal.religion')->label('Religion')->maxLength(50),
                TextInput::make('personal.nationality')->label('Nationality')->default('Indian')->maxLength(50),
                TextInput::make('personal.mother_tongue')->label('Mother tongue')->maxLength(50),
                TextInput::make('personal.aadhaar')->label('Aadhaar number')
                    ->placeholder(fn (?Student $record) => $record?->personal?->aadhaar_masked ?? 'XXXX XXXX XXXX')
                    ->helperText(fn (?Student $record) => $record?->personal?->aadhaar ? 'Leave empty to keep the saved number.' : null)
                    ->regex('/^\s*[2-9]\d{3}\s?\d{4}\s?\d{4}\s*$/'),
                TextInput::make('personal.whatsapp_phone')->label('WhatsApp')->tel()->maxLength(20),
                TextInput::make('personal.alternate_phone')->label('Alternate phone')->tel()->maxLength(20),
                TextInput::make('personal.disability')->label('Disability (if any)')->maxLength(255),
            ]),
        ];
    }

    public static function guardians(): Repeater
    {
        return Repeater::make('guardians')
            ->label('Parents / guardians')
            ->minItems(1)
            ->maxItems(4)
            ->defaultItems(1)
            ->columns(3)
            ->schema([
                Select::make('relation')->options(GuardianRelation::class)->required()->distinct(),
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('phone')->tel()->maxLength(20),
                TextInput::make('occupation')->maxLength(100),
                TextInput::make('annual_income')->numeric()->minValue(0),
                Toggle::make('is_primary')->label('Primary contact')->inline(false)->fixIndistinctState(),
            ]);
    }

    public static function qualifications(): Repeater
    {
        return Repeater::make('qualifications')
            ->label('Previous education')
            ->defaultItems(0)
            ->columns(3)
            ->schema([
                Select::make('level')->options(QualificationLevel::class)->required(),
                TextInput::make('board_university')->label('Board / university')->maxLength(255),
                TextInput::make('institution')->label('School / college')->maxLength(255),
                TextInput::make('passing_year')->integer()->minValue(1970)->maxValue((int) now()->year),
                Select::make('marks_type')->options(['percentage' => 'Percentage', 'cgpa' => 'CGPA'])->default('percentage'),
                TextInput::make('score')->numeric()->minValue(0)->maxValue(100),
            ]);
    }

    /** Splits form state into StudentService arguments. */
    public static function split(array $data): array
    {
        return [
            Arr::get($data, 'student', []),
            Arr::get($data, 'personal', []),
            array_values(Arr::get($data, 'guardians', [])),
            Arr::get($data, 'address', []),
            array_values(Arr::get($data, 'qualifications', [])),
        ];
    }

    /** Loads a student into form state for editing (Aadhaar is never sent back to the browser). */
    public static function fill(Student $student): array
    {
        $student->loadMissing(['personal', 'guardians', 'qualifications', 'addresses']);
        $keep = fn ($model, array $except = []) => $model ? Arr::except($model->attributesToArray(), ['id', 'student_id', 'created_at', 'updated_at', ...$except]) : [];

        return [
            'student' => Arr::only($student->attributesToArray(), ['first_name', 'middle_name', 'last_name', 'phone', 'email', 'joined_on', 'photo_path', 'signature_path']),
            'personal' => [...$keep($student->personal, ['aadhaar_encrypted', 'aadhaar_hash']), 'aadhaar' => null],
            'guardians' => $student->guardians->map(fn ($g) => $keep($g))->all(),
            'address' => $student->address(AddressType::Permanent)?->only(AddressFields::KEYS) ?? [],
            'qualifications' => $student->qualifications->map(fn ($q) => $keep($q))->all(),
        ];
    }

    public static function service(): StudentService
    {
        return app(StudentService::class);
    }
}
