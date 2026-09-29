<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SliderFactory extends Factory
{
    public function definition(): array
    {
        return ['title' => ['en' => fake()->sentence(3)], 'image_path' => 'sliders/demo.jpg', 'sort_order' => 0, 'is_active' => true];
    }
}
