<?php

use App\Filament\Admin\Resources\Notices\Pages\ManageNotices;
use App\Filament\Admin\Resources\Sliders\Pages\ManageSliders;
use App\Models\Admin;
use App\Models\Download;
use App\Models\FacultyMember;
use App\Models\GalleryItem;
use App\Models\Notice;
use App\Models\Slider;
use App\Models\Testimonial;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
});

it('shows only active content, in order', function (string $model) {
    $hidden = $model::factory()->create(['is_active' => false]);
    $second = $model::factory()->create(['sort_order' => 2]);
    $first = $model::factory()->create(['sort_order' => 1]);

    expect($model::query()->visible()->pluck('id')->all())->toBe([$first->id, $second->id]);
})->with([Slider::class, GalleryItem::class, FacultyMember::class, Testimonial::class, Download::class]);

it('shows only published notices, newest first, and ticker notices separately', function () {
    $old = Notice::factory()->create(['published_at' => now()->subDays(3)]);
    $new = Notice::factory()->create(['published_at' => now()->subDay(), 'show_in_ticker' => true]);
    Notice::factory()->create(['published_at' => now()->addDay()]);
    Notice::factory()->create(['is_active' => false]);

    expect(Notice::query()->visible()->pluck('id')->all())->toBe([$new->id, $old->id])
        ->and(Notice::query()->visible()->where('show_in_ticker', true)->pluck('id')->all())->toBe([$new->id]);
});

it('lets the support role add a bilingual slider with an image', function () {
    Storage::fake('public');
    $this->actingAs(Admin::factory()->create()->assignRole('support'), 'admin');

    Livewire::test(ManageSliders::class)
        ->callAction('create', data: [
            'title' => ['en' => 'Admissions open', 'hi' => 'प्रवेश जारी'],
            'image_path' => UploadedFile::fake()->image('hero.jpg', 1600, 700),
            'button_url' => '/students/registration',
            'is_active' => true,
        ])
        ->assertHasNoActionErrors();

    $slider = Slider::sole();
    Storage::disk('public')->assertExists($slider->image_path);
    expect($slider->localized('title', 'hi'))->toBe('प्रवेश जारी');
});

it('creates notices with a unique slug', function () {
    $this->actingAs(Admin::factory()->create()->assignRole('support'), 'admin');

    foreach ([1, 2] as $i) {
        Livewire::test(ManageNotices::class)
            ->callAction('create', data: ['title' => ['en' => 'New batch'], 'body' => ['en' => 'Details'], 'published_at' => now()->toDateTimeString(), 'is_active' => true])
            ->assertHasNoActionErrors();
    }

    expect(Notice::pluck('slug')->all())->toBe(['new-batch', 'new-batch-2']);
});

it('keeps website content away from admins without manage_website', function () {
    $this->actingAs(Admin::factory()->create()->assignRole('accounts'), 'admin')->get('/admin/sliders')->assertForbidden();
});
