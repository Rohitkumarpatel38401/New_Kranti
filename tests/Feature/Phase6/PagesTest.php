<?php

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Download;
use App\Models\FacultyMember;
use App\Models\GalleryItem;
use App\Models\Notice;
use App\Models\Slider;
use App\Models\Subject;
use App\Models\Testimonial;
use App\Services\SettingsService;

it('renders every public page', function (string $route) {
    $this->get(route($route))->assertOk()->assertSee('kc-main-nav', false);
})->with([
    'site.home', 'site.about', 'site.courses.index', 'site.placement', 'site.gallery', 'site.download',
    'site.contact', 'site.students.registration', 'site.students.verification', 'site.franchise.info', 'site.franchise.apply',
]);

it('renders every public page in Hindi', function (string $route) {
    $this->get(route('site.locale', 'hi'));

    $this->get(route($route))->assertOk()->assertSee('lang="hi"', false)->assertSee('होम');
})->with(['site.home', 'site.about', 'site.courses.index', 'site.placement', 'site.gallery', 'site.download',
    'site.contact', 'site.students.registration', 'site.students.verification', 'site.franchise.info', 'site.franchise.apply']);

it('fills the home page from the CMS', function () {
    Slider::factory()->create(['title' => ['en' => 'Admissions open for 2027', 'hi' => '2027 के प्रवेश']]);
    Course::factory()->create(['name' => ['en' => 'Advanced Diploma XYZ'], 'is_featured' => true]);
    Testimonial::factory()->create(['name' => 'Anita Verma']);
    FacultyMember::factory()->create(['name' => 'Ramesh Tiwari']);
    Notice::factory()->create(['title' => ['en' => 'O Level batch at 11 AM'], 'published_at' => now()->subHour()]);

    $this->get(route('site.home'))
        ->assertSee('Admissions open for 2027')
        ->assertSee('Advanced Diploma XYZ')
        ->assertSee('Anita Verma')
        ->assertSee('Ramesh Tiwari')
        ->assertSee('O Level batch at 11 AM');
});

it('shows hidden, inactive and future content nowhere', function () {
    Slider::factory()->create(['title' => ['en' => 'Hidden slide'], 'is_active' => false]);
    Course::factory()->create(['name' => ['en' => 'Secret Course'], 'show_on_website' => false, 'is_featured' => true]);
    Notice::factory()->create(['title' => ['en' => 'Future notice'], 'published_at' => now()->addDay()]);

    $this->get(route('site.home'))->assertDontSee('Hidden slide')->assertDontSee('Secret Course')->assertDontSee('Future notice');
    $this->get(route('site.courses.index'))->assertDontSee('Secret Course');
});

it('lists website courses by category and filters by category', function () {
    $diploma = CourseCategory::factory()->create(['name' => ['en' => 'Diplomas'], 'slug' => 'diplomas']);
    $short = CourseCategory::factory()->create(['name' => ['en' => 'Short Courses'], 'slug' => 'short']);
    Course::factory()->for($diploma, 'category')->create(['name' => ['en' => 'ADCA Plus']]);
    Course::factory()->for($short, 'category')->create(['name' => ['en' => 'Tally Basics']]);

    $this->get(route('site.courses.index'))->assertSee('ADCA Plus')->assertSee('Tally Basics')->assertSee('Diplomas');
    $this->get(route('site.courses.index', ['category' => 'short']))->assertSee('Tally Basics')->assertDontSee('ADCA Plus');
});

it('shows a course with its subjects and an admission link, in Hindi when chosen', function () {
    $course = Course::factory()->create(['name' => ['en' => 'Office Automation', 'hi' => 'ऑफिस ऑटोमेशन'], 'slug' => 'office-automation']);
    $course->courseSubjects()->create(['subject_id' => Subject::factory()->create(['name' => ['en' => 'MS Word']])->id, 'theory_max' => 70, 'practical_max' => 30, 'pass_marks' => 33]);

    $this->get(route('site.courses.show', 'office-automation'))
        ->assertOk()->assertSee('Office Automation')->assertSee('MS Word')
        ->assertSee(route('site.students.registration', ['course' => $course->id]), false);

    $this->get(route('site.locale', 'hi'));
    $this->get(route('site.courses.show', 'office-automation'))->assertSee('ऑफिस ऑटोमेशन');
});

it('returns 404 for a course not on the website', function () {
    Course::factory()->create(['slug' => 'hidden-course', 'show_on_website' => false]);

    $this->get(route('site.courses.show', 'hidden-course'))->assertNotFound();
    $this->get(route('site.courses.show', 'no-such-course'))->assertNotFound();
});

it('lists alumni, gallery and downloads', function () {
    Testimonial::factory()->create(['name' => 'Placed Student', 'company' => 'Infosys']);
    GalleryItem::factory()->create(['title' => ['en' => 'Computer Lab 2']]);
    Download::factory()->create(['title' => ['en' => 'Admission Form PDF']]);

    $this->get(route('site.placement'))->assertSee('Placed Student')->assertSee('Infosys');
    $this->get(route('site.gallery'))->assertSee('Computer Lab 2');
    $this->get(route('site.download'))->assertSee('Admission Form PDF')->assertSee('downloads/demo.pdf', false);
});

it('shows the about text and contact details from settings', function () {
    app(SettingsService::class)->setMany([
        'site.about' => ['en' => 'We teach computers since 2016.', 'hi' => 'हम 2016 से कंप्यूटर सिखाते हैं।'],
        'site.address' => 'Near Main Chauraha, Prayagraj',
        'site.map_embed_url' => 'https://www.google.com/maps/embed?pb=abc',
    ]);

    $this->get(route('site.about'))->assertSee('We teach computers since 2016.');
    $this->get(route('site.contact'))->assertSee('Near Main Chauraha, Prayagraj')->assertSee('https://www.google.com/maps/embed?pb=abc', false);
});

it('ignores a map URL that is not a Google Maps embed', function () {
    app(SettingsService::class)->set('site.map_embed_url', 'https://evil.example/phish');

    $this->get(route('site.contact'))->assertDontSee('evil.example', false);
});

it('shows certificate verification inside the site layout', function () {
    $this->get(route('site.students.verification'))->assertSee('kc-main-nav', false)->assertSee('Certificate number');
});
