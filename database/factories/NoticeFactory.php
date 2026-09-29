<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class NoticeFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'title' => ['en' => $title],
            'slug' => Str::slug($title),
            'body' => ['en' => fake()->paragraph()],
            'published_at' => now()->subHour(),
            'show_in_ticker' => false,
            'is_active' => true,
        ];
    }
}
