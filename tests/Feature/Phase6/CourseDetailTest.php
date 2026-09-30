<?php

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\FacultyMember;
use App\Models\Subject;
use App\Models\Testimonial;
use App\Services\SettingsService;

beforeEach(function () {
    app(SettingsService::class)->setMany(['site.phone' => '9026043415']);
    $this->category = CourseCategory::factory()->create(['name' => ['en' => 'Diploma Courses']]);
    $this->course = Course::factory()->for($this->category, 'category')->create([
        'code' => 'DCA', 'slug' => 'dca', 'name' => ['en' => 'Diploma in Computer Applications'],
        'short_description' => ['en' => 'Office and internet skills.'],
        'eligibility' => ['en' => '10th pass'], 'duration_value' => 6, 'total_hours' => 240, 'suggested_fee' => 9000,
    ]);
    foreach (['MS Word', 'MS Excel'] as $i => $name) {
        $this->course->courseSubjects()->create(['subject_id' => Subject::factory()->create(['name' => ['en' => $name]])->id, 'term_no' => 1, 'sort_order' => $i, 'theory_max' => 70, 'practical_max' => 30, 'pass_marks' => 33]);
    }
});

it('shows a rich course page: hero stats, what you learn, curriculum, eligibility, certificate, FAQs', function () {
    $this->get(route('site.courses.show', 'dca'))->assertOk()
        ->assertSee('Diploma in Computer Applications')
        ->assertSee('Diploma Courses')
        ->assertSee('6 Months')
        ->assertSee('240')
        ->assertSee(__('site.course.learn_title'))
        ->assertSee('MS Word')->assertSee('MS Excel')
        ->assertSee(__('site.course.curriculum_title'))
        ->assertSee('10th pass')
        ->assertSee(__('site.course.certificate_title'))
        ->assertSee(__('site.course.faq_title'))
        ->assertSee('9,000')
        ->assertSee(route('site.students.registration', ['course' => $this->course->id]), false)
        ->assertSee('https://wa.me/919026043415', false);
});

it('shows faculty, matching alumni and related courses', function () {
    FacultyMember::factory()->create(['name' => 'Ramesh Tiwari']);
    Testimonial::factory()->create(['name' => 'Placed Priya', 'course_name' => 'DCA']);
    Course::factory()->for($this->category, 'category')->create(['name' => ['en' => 'ADCA Advanced'], 'slug' => 'adca']);

    $this->get(route('site.courses.show', 'dca'))
        ->assertSee('Ramesh Tiwari')->assertSee('Placed Priya')->assertSee('ADCA Advanced')->assertSee(route('site.courses.show', 'adca'), false);
});

it('renders the course page in Hindi', function () {
    $this->get(route('site.locale', 'hi'));

    $this->get(route('site.courses.show', 'dca'))->assertOk()->assertSee(__('site.course.learn_title', [], 'hi'));
});
