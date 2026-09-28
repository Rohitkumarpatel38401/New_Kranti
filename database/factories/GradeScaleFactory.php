<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class GradeScaleFactory extends Factory
{
    public function definition(): array
    {
        return ['min_percent' => 0, 'max_percent' => 100, 'grade' => fake()->unique()->lexify('?'), 'remark' => ['en' => 'Remark']];
    }
}
