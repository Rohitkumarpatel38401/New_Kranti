<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SubjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('S????')),
            'name' => ['en' => Str::title(fake()->unique()->words(2, true))],
            'is_active' => true,
        ];
    }
}
