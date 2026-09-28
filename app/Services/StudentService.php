<?php

namespace App\Services;

use App\Enums\AddressType;
use App\Enums\AdmissionSource;
use App\Enums\GuardianRelation;
use App\Enums\StudentStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Admin;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Models\StudentPersonalDetail;
use Illuminate\Support\Facades\DB;

/**
 * Admits a student into one institute with college-style normalized records.
 */
class StudentService
{
    public function __construct(private NumberSequenceService $sequences) {}

    /**
     * @param  array<int, array<string, mixed>>  $guardians
     * @param  array<int, array<string, mixed>>  $qualifications
     */
    public function create(array $student, array $personal, array $guardians, array $address, array $qualifications, InstituteUser|Admin $by, ?Institute $institute = null, ?AdmissionSource $source = null): Student
    {
        $institute = $this->resolveInstitute($by, $institute);
        $source ??= $by instanceof Admin ? AdmissionSource::WalkIn : AdmissionSource::Franchise;
        $personal = $this->preparePersonal($personal);
        $this->assertGuardians($guardians);

        return DB::transaction(function () use ($student, $personal, $guardians, $address, $qualifications, $by, $institute, $source) {
            $record = Student::create([
                ...$student,
                'institute_id' => $institute->id,
                'registration_no' => $this->nextRegistrationNo($institute),
                'status' => StudentStatus::Active,
                'admission_source' => $source,
                'joined_on' => $student['joined_on'] ?? now()->toDateString(),
                'can_login' => false,
                'must_change_password' => true,
                'created_by_user_id' => $by instanceof InstituteUser ? $by->id : null,
            ]);

            $this->saveDetails($record, $personal, $guardians, $address, $qualifications);

            return $record->refresh();
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $guardians
     * @param  array<int, array<string, mixed>>  $qualifications
     */
    public function update(Student $record, array $student, array $personal, array $guardians, array $address, array $qualifications): Student
    {
        $personal = $this->preparePersonal($personal, $record);
        $this->assertGuardians($guardians);

        return DB::transaction(function () use ($record, $student, $personal, $guardians, $address, $qualifications) {
            unset($student['institute_id'], $student['registration_no'], $student['admission_source'], $student['password'], $student['can_login']);
            $record->update($student);

            $record->guardians()->delete();
            $record->qualifications()->delete();
            $this->saveDetails($record, $personal, $guardians, $address, $qualifications);

            return $record->refresh();
        });
    }

    private function saveDetails(Student $record, array $personal, array $guardians, array $address, array $qualifications): void
    {
        $record->personal()->updateOrCreate([], $personal);

        foreach ($guardians as $guardian) {
            $record->guardians()->create([...$guardian, 'is_primary' => (bool) ($guardian['is_primary'] ?? false)]);
        }

        if (array_filter($address) !== []) {
            $record->saveAddress(AddressType::Permanent, $address);
        }

        foreach ($qualifications as $qualification) {
            $record->qualifications()->create($qualification);
        }
    }

    private function resolveInstitute(InstituteUser|Admin $by, ?Institute $institute): Institute
    {
        if ($by instanceof InstituteUser) {
            if ($institute && $institute->id !== $by->institute_id) {
                throw new DomainRuleException('You can only admit students to your own institute.');
            }

            return $by->institute;
        }

        return $institute ?? throw new DomainRuleException('Choose the institute this student is admitted to.');
    }

    private function preparePersonal(array $personal, ?Student $existing = null): array
    {
        if (! array_key_exists('aadhaar', $personal)) {
            return $personal;
        }

        $digits = StudentPersonalDetail::normalizeAadhaar($personal['aadhaar']);

        if ($digits === null) {
            return $personal;
        }

        if (! StudentPersonalDetail::isValidAadhaar($digits)) {
            throw new DomainRuleException('Enter a valid 12-digit Aadhaar number.');
        }

        $taken = StudentPersonalDetail::query()
            ->where('aadhaar_hash', StudentPersonalDetail::hashAadhaar($digits))
            ->when($existing, fn ($q) => $q->where('student_id', '!=', $existing->id))
            ->exists();

        if ($taken) {
            throw new DomainRuleException('This Aadhaar number is already registered with KCTI. Search for the existing student instead of creating a new one.');
        }

        return [...$personal, 'aadhaar' => $digits];
    }

    /** @param  array<int, array<string, mixed>>  $guardians */
    private function assertGuardians(array $guardians): void
    {
        if (count(array_filter($guardians, fn ($g) => (bool) ($g['is_primary'] ?? false))) !== 1) {
            throw new DomainRuleException('Mark exactly one guardian as the primary contact.');
        }

        $relations = array_map(fn ($g) => $g['relation'] instanceof GuardianRelation ? $g['relation']->value : $g['relation'], $guardians);

        foreach (array_count_values($relations) as $relation => $count) {
            if ($count > 1 && $relation !== GuardianRelation::Guardian->value) {
                throw new DomainRuleException("Add only one {$relation}.");
            }
        }
    }

    private function nextRegistrationNo(Institute $institute): string
    {
        $yy = now()->format('y');

        return sprintf('%s-%s-%04d', $institute->code, $yy, $this->sequences->next("registration:{$institute->id}:{$yy}"));
    }
}
