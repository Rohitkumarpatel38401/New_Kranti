<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class FacultyMemberFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->name(), 'designation' => ['en' => 'Faculty'], 'experience_years' => 5, 'sort_order' => 0, 'is_active' => true];
    }
}
