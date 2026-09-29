<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DownloadFactory extends Factory
{
    public function definition(): array
    {
        return ['title' => ['en' => fake()->words(3, true)], 'category' => 'forms', 'path' => 'downloads/demo.pdf', 'sort_order' => 0, 'is_active' => true];
    }
}
