<?php

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Subject;

beforeEach(function () {
    $this->course = Course::factory()->for(CourseCategory::factory()->create(['name' => ['en' => 'Accounting']]), 'category')->create([
        'code' => 'TALLY', 'slug' => 'tally', 'name' => ['en' => 'Tally Prime with GST'], 'short_description' => ['en' => 'Business accounting.'],
        'duration_value' => 3, 'total_hours' => 120, 'suggested_fee' => 5000, 'is_featured' => true,
    ]);
    foreach (['Tally', 'GST'] as $i => $name) {
        $this->course->courseSubjects()->create(['subject_id' => Subject::factory()->create(['name' => ['en' => $name]])->id, 'sort_order' => $i, 'theory_max' => 70, 'practical_max' => 30, 'pass_marks' => 33]);
    }
});

it('shows rich course cards on the courses page', function () {
    $this->get(route('site.courses.index'))->assertOk()
        ->assertSee('kc-ccard', false)
        ->assertSee('Tally Prime with GST')->assertSee('Accounting')
        ->assertSee('3 Months')->assertSee('2 '.__('site.course.stat_subjects'))->assertSee('120')
        ->assertSee('₹5,000')
        ->assertSee(route('site.courses.show', 'tally'), false)
        ->assertSee(route('site.students.registration', ['course' => $this->course->id]), false);
});

it('uses the same cards for featured courses on the home page', function () {
    $this->get(route('site.home'))->assertOk()->assertSee('kc-ccard', false)->assertSee('₹5,000');
});

it('hides the fee line when no fee is set', function () {
    $this->course->update(['suggested_fee' => null]);

    $this->get(route('site.courses.index'))->assertDontSee('kc-ccard-price', false);
});
