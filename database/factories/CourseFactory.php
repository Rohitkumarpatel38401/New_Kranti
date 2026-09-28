<?php

namespace Database\Factories;

use App\Enums\DurationUnit;
use App\Models\CourseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CourseFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Course '.fake()->unique()->words(3, true);

        return [
            'course_category_id' => CourseCategory::factory(),
            'code' => Str::upper(fake()->unique()->lexify('C???')),
            'name' => ['en' => Str::title($name)],
            'slug' => Str::slug($name),
            'duration_value' => 6,
            'duration_unit' => DurationUnit::Months,
            'hq_registration_fee' => 500,
            'suggested_fee' => 6000,
            'is_active' => true,
            'show_on_website' => true,
        ];
    }
}
