<?php

namespace Database\Factories;

use App\Enums\BatchStatus;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Institute;
use Illuminate\Database\Eloquent\Factories\Factory;

class BatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'institute_id' => Institute::factory(),
            'course_id' => Course::factory(),
            'academic_session_id' => AcademicSession::factory(),
            'name' => 'Batch '.fake()->unique()->bothify('??##'),
            'code' => fake()->unique()->bothify('B-##??'),
            'status' => BatchStatus::Running,
        ];
    }
}
