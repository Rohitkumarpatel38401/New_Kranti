<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class GalleryItemFactory extends Factory
{
    public function definition(): array
    {
        return ['title' => ['en' => fake()->words(2, true)], 'category' => 'campus', 'image_path' => 'gallery/demo.jpg', 'sort_order' => 0, 'is_active' => true];
    }
}
