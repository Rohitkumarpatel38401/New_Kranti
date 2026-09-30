<?php

namespace App\Filament\Shared;

use App\Exceptions\DomainRuleException;
use App\Models\AcademicSession;
use App\Models\Admin;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Services\EnrollmentService;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Validation\ValidationException;

/**
 * The single-form "New admission" used by HQ (with an institute picker) and by institutes:
 * student details + course, saved through EnrollmentService::admitDirectly.
 */
class AdmissionForm
{
    /** @return array<int, mixed> */
    public static function components(bool $pickInstitute): array
    {
        $institute = function (Get $get) use ($pickInstitute): ?Institute {
            return $pickInstitute
                ? ($get('institute_id') ? Institute::query()->find($get('institute_id')) : null)
                : auth('institute')->user()?->institute;
        };

        return [
            ...($pickInstitute ? [
                Section::make('Institute')->schema([
                    Select::make('institute_id')->label('Institute')->required()->searchable()->live()
                        ->options(fn () => Institute::query()->where('status', 'active')->orderByDesc('is_main')->orderBy('code')
                            ->get()->mapWithKeys(fn (Institute $i) => [$i->id => "{$i->code} — {$i->name}".($i->is_main ? ' (main)' : '')]))
                        ->default(fn () => Institute::mainInstitute()?->id)
                        ->afterStateUpdated(fn (Set $set) => $set('course_id', null)),
                ]),
            ] : []),
            Section::make('Student')->schema(StudentForm::basic()),
            Section::make('Personal')->schema(StudentForm::personal())->collapsible(),
            Section::make('Guardians')->schema([StudentForm::guardians()])->collapsible(),
            Section::make('Course')->schema([
                Grid::make(3)->schema([
                    Select::make('course_id')->label('Course')->required()->live()
                        ->options(fn (Get $get) => $institute($get)?->authorizedCourses()->orderBy('code')->get()
                            ->mapWithKeys(fn (Course $c) => [$c->id => "{$c->code} — {$c->localized('name')}"])->all() ?? [])
                        ->afterStateUpdated(function (?string $state, Set $set) {
                            $set('batch_id', null);
                            $set('course_fee', $state ? Course::find($state)?->suggested_fee : null);
                        }),
                    Select::make('batch_id')->label('Batch')
                        ->options(fn (Get $get) => ($i = $institute($get)) && $get('course_id')
                            ? Batch::query()->withoutGlobalScopes()->where('institute_id', $i->id)->where('course_id', $get('course_id'))->pluck('name', 'id')
                            : []),
                    Select::make('academic_session_id')->label('Session')->required()
                        ->options(fn () => AcademicSession::query()->orderByDesc('starts_on')->pluck('name', 'id'))
                        ->default(fn () => AcademicSession::current()?->id),
                    DatePicker::make('admission_date')->default(now())->maxDate(now())->required(),
                    TextInput::make('course_fee')->label('Course fee (₹)')->required()->numeric()->minValue(0),
                    TextInput::make('discount')->label('Discount (₹)')->numeric()->minValue(0)->default(0)
                        ->rules([fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get) {
                            if ((float) $value > (float) $get('course_fee')) {
                                $fail('The discount cannot be more than the course fee.');
                            }
                        }]),
                ]),
            ]),
        ];
    }

    /** Saves the form state; rule failures come back as a form error on the course field. */
    public static function save(array $data, Admin|InstituteUser $by): Enrollment
    {
        [$student, $personal, $guardians] = StudentForm::split($data);
        $institute = $by instanceof Admin ? Institute::query()->findOrFail($data['institute_id']) : null;
        $batch = filled($data['batch_id'] ?? null) ? Batch::query()->withoutGlobalScopes()->find($data['batch_id']) : null;

        try {
            return app(EnrollmentService::class)->admitDirectly(
                $student, $personal, $guardians, [],
                Course::query()->findOrFail($data['course_id']),
                $batch,
                AcademicSession::query()->findOrFail($data['academic_session_id']),
                (string) $data['course_fee'],
                (string) ($data['discount'] ?? '0'),
                $by,
                $institute,
                $data['admission_date'] ?? null,
            );
        } catch (DomainRuleException $e) {
            throw ValidationException::withMessages(['data.course_id' => $e->getMessage()]);
        }
    }
}
