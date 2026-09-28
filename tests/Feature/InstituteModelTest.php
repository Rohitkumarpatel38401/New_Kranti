<?php

use App\Enums\InstituteStatus;
use App\Models\Institute;
use Illuminate\Database\QueryException;

it('casts status and flags', function () {
    $institute = Institute::factory()->create();

    expect($institute->status)->toBe(InstituteStatus::Active)
        ->and($institute->is_main)->toBeFalse()
        ->and($institute->isActive())->toBeTrue();
});

it('allows only one main institute', function () {
    Institute::factory()->main()->create();

    // different code and slug, so only main_marker can block it
    expect(fn () => Institute::factory()->main()->create(['code' => 'KCTI9999', 'slug' => 'other-main']))
        ->toThrow(QueryException::class);
});

it('allows many non-main institutes', function () {
    Institute::factory()->count(3)->create();

    expect(Institute::count())->toBe(3);
});

it('finds the main institute', function () {
    Institute::factory()->create();
    $main = Institute::factory()->main()->create();

    expect(Institute::mainInstitute()?->is($main))->toBeTrue();
});

it('is not active when suspended', function () {
    expect(Institute::factory()->suspended()->create()->isActive())->toBeFalse();
});
