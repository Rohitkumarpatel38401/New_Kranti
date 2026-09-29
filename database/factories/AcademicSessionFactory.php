<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AcademicSessionFactory extends Factory
{
    public function definition(): array
    {
        // Far-future years so factory sessions never collide with the seeded current session.
        $year = fake()->unique()->numberBetween(2100, 2999);

        return [
            'name' => $year.'-'.substr((string) ($year + 1), 2),
            'code' => $year.'-'.substr((string) ($year + 1), 2),
            'starts_on' => "{$year}-04-01",
            'ends_on' => ($year + 1).'-03-31',
            'is_current' => false,
        ];
    }
}
