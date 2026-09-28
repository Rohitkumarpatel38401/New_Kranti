<?php

namespace Database\Factories;

use App\Enums\FranchiseApplicationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class FranchiseApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'application_no' => 'KCTI/FA/'.now()->year.'/'.fake()->unique()->numerify('#####'),
            'applicant_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('9#########'),
            'proposed_center_name' => fake()->company().' Computers',
            'status' => FranchiseApplicationStatus::New,
        ];
    }
}
