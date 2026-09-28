<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AcademicSessionFactory extends Factory
{
    public function definition(): array
    {
        $year = fake()->unique()->numberBetween(2020, 2060);

        return [
            'name' => $year.'-'.substr((string) ($year + 1), 2),
            'code' => $year.'-'.substr((string) ($year + 1), 2),
            'starts_on' => "{$year}-04-01",
            'ends_on' => ($year + 1).'-03-31',
            'is_current' => false,
        ];
    }
}
