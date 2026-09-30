<?php

use App\Filament\Admin\Pages\PaymentSettings;
use App\Filament\Admin\Pages\SiteSettings;
use App\Filament\Admin\Resources\Courses\Pages\CreateCourse;
use App\Filament\Admin\Resources\Sliders\Pages\ManageSliders;
use App\Models\Admin;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Notice;
use App\Models\Slider;
use App\Services\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
});

// Important 1: javascript: links in slider buttons
it('refuses a slider button link that is not http(s) or a site path', function () {
    $this->actingAs(Admin::factory()->create()->assignRole('support'), 'admin');

    Livewire::test(ManageSliders::class)
        ->callAction('create', data: ['title' => ['en' => 'X'], 'button_url' => 'javascript:alert(1)//', 'is_active' => true])
        ->assertHasActionErrors(['button_url']);
});

it('never renders a javascript: slider link that is already stored', function () {
    Slider::factory()->create(['title' => ['en' => 'Old slide'], 'button_url' => 'javascript:alert(1)']);

    $this->get(route('site.home'))->assertSee('Old slide')->assertDontSee('javascript:', false);
});

it('accepts only http(s) for the online courses link', function () {
    $this->actingAs(Admin::factory()->create()->assignRole('support'), 'admin');

    Livewire::test(SiteSettings::class)
        ->fillForm(['site' => ['name' => 'KCTI', 'online_courses_url' => 'data:text/html,<script>alert(1)</script>']])
        ->call('save')
        ->assertHasFormErrors(['site.online_courses_url']);
});

// Important 2: inactive / deleted categories
it('hides courses whose category is inactive or deleted', function () {
    $inactive = CourseCategory::factory()->create(['is_active' => false]);
    $deleted = CourseCategory::factory()->create();
    Course::factory()->for($inactive, 'category')->create(['name' => ['en' => 'Hidden Cat Course'], 'slug' => 'hidden-cat', 'is_featured' => true]);
    $gone = Course::factory()->for($deleted, 'category')->create(['name' => ['en' => 'Deleted Cat Course'], 'slug' => 'deleted-cat']);
    $deleted->courses()->update(['course_category_id' => $deleted->id]);
    CourseCategory::query()->whereKey($deleted->id)->update(['deleted_at' => now()]);

    $this->get(route('site.courses.index'))->assertDontSee('Hidden Cat Course')->assertDontSee('Deleted Cat Course');
    $this->get(route('site.home'))->assertDontSee('Hidden Cat Course');
    $this->get(route('site.courses.show', 'hidden-cat'))->assertNotFound();
    $this->get(route('site.students.registration'))->assertDontSee('Deleted Cat Course');
    expect($gone->exists)->toBeTrue();
});

// Important 3: ticker toggle
it('shows only ticker notices in the homepage news ticker', function () {
    Notice::factory()->create(['title' => ['en' => 'Ticker news'], 'show_in_ticker' => true, 'published_at' => now()->subHour()]);
    Notice::factory()->create(['title' => ['en' => 'Not in ticker'], 'show_in_ticker' => false, 'published_at' => now()->subHour()]);

    $this->get(route('site.home'))->assertSee('Ticker news')->assertDontSee('Not in ticker');
});

// Important 4: payment details
it('keeps wallet payment details away from the support role', function () {
    $this->actingAs(Admin::factory()->create()->assignRole('support'), 'admin');

    $this->get(PaymentSettings::getUrl())->assertForbidden();
    Livewire::test(SiteSettings::class)->assertDontSee('UPI ID');
});

it('lets accounts change payment details and logs old and new values', function () {
    $accounts = Admin::factory()->create()->assignRole('accounts');
    app(SettingsService::class)->set('payment.upi_id', 'old@upi');
    $this->actingAs($accounts, 'admin');

    Livewire::test(PaymentSettings::class)
        ->fillForm(['payment' => ['upi_id' => 'new@upi']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(SettingsService::class)->get('payment.upi_id'))->toBe('new@upi');
    $log = Activity::query()->where('event', 'setting.changed')->latest('id')->first();
    expect($log->causer_id)->toBe($accounts->id)
        ->and($log->properties['key'])->toBe('payment.upi_id')
        ->and($log->properties['old'])->toBe('old@upi')
        ->and($log->properties['new'])->toBe('new@upi');
});

// Re-graded Important: SVG thumbnails
it('refuses an SVG course thumbnail', function () {
    Storage::fake('public');
    $this->actingAs(Admin::factory()->create()->assignRole('academic'), 'admin');

    Livewire::test(CreateCourse::class)
        ->fillForm(['thumbnail_path' => UploadedFile::fake()->create('evil.svg', 2, 'image/svg+xml')])
        ->call('create')
        ->assertHasFormErrors(['thumbnail_path']);
});
