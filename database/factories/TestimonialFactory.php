<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TestimonialFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->name(), 'course_name' => 'ADCA', 'position' => 'Developer', 'quote' => ['en' => fake()->sentence()], 'sort_order' => 0, 'is_active' => true];
    }
}
