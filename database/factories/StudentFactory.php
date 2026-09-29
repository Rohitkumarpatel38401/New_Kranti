<?php

namespace Database\Factories;

use App\Enums\AdmissionSource;
use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Institute;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'institute_id' => Institute::factory(),
            'registration_no' => 'KCTI'.fake()->unique()->numerify('####-26-####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->numerify('9#########'),
            'password' => 'password',
            'can_login' => false,
            'must_change_password' => false,
            'status' => StudentStatus::Active,
            'admission_source' => AdmissionSource::Franchise,
            'joined_on' => now()->toDateString(),
        ];
    }

    public function canLogin(): static
    {
        return $this->state(fn () => ['can_login' => true]);
    }

    /** A student HQ has approved, so the portal is open to them. */
    public function withActiveEnrollment(): static
    {
        return $this->canLogin()->afterCreating(function (Student $student) {
            Enrollment::create([
                'institute_id' => $student->institute_id,
                'student_id' => $student->id,
                'course_id' => Course::factory()->create()->id,
                'academic_session_id' => AcademicSession::factory()->create()->id,
                'enrollment_no' => 'KCTI/'.now()->year.'/'.fake()->unique()->numerify('9#####'),
                'admission_date' => now()->toDateString(),
                'course_fee' => 1000,
                'discount' => 0,
                'net_fee' => 1000,
                'status' => EnrollmentStatus::Active,
            ]);
        });
    }
}
