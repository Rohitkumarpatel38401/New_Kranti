<?php

namespace Database\Factories;

use App\Enums\AdmissionSource;
use App\Enums\StudentStatus;
use App\Models\Institute;
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
}
