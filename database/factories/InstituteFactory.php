<?php

namespace Database\Factories;

use App\Enums\InstituteStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class InstituteFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company().' Computer Centre';

        return [
            'code' => 'KCTI'.str_pad((string) fake()->unique()->numberBetween(2, 9999), 4, '0', STR_PAD_LEFT),
            'name' => $name,
            'slug' => Str::slug($name),
            'is_main' => false,
            'status' => InstituteStatus::Active,
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('9#########'),
            'wallet_exempt' => false,
        ];
    }

    public function main(): static
    {
        return $this->state(fn () => [
            'code' => 'KCTI0001',
            'name' => 'Kranti Computer Training Institute',
            'slug' => 'kranti-computer-training-institute',
            'is_main' => true,
            'wallet_exempt' => true,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => InstituteStatus::Suspended]);
    }
}
