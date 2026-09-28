<?php

use App\Exceptions\TenantMismatchException;
use App\Http\Middleware\ResetCurrentInstitute;
use App\Http\Middleware\SetCurrentInstitute;
use App\Models\Admin;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Support\Tenancy\CurrentInstitute;
use Filament\Facades\Filament;
use Illuminate\Routing\Router;

beforeEach(function () {
    $this->a = Institute::factory()->create();
    $this->b = Institute::factory()->create();
    Student::factory()->count(2)->for($this->a)->create();
    Student::factory()->count(3)->for($this->b)->create();
});

it('sees every row without a current institute', function () {
    expect(Student::count())->toBe(5);
});

it('scopes queries to the current institute', function () {
    app(CurrentInstitute::class)->set($this->a->id);

    expect(Student::count())->toBe(2)
        ->and(Student::pluck('institute_id')->unique()->values()->all())->toBe([$this->a->id]);
});

it('cannot find another institute row by id', function () {
    $foreign = Student::where('institute_id', $this->b->id)->first();
    app(CurrentInstitute::class)->set($this->a->id);

    expect(Student::find($foreign->id))->toBeNull();
});

it('fills institute_id on create', function () {
    app(CurrentInstitute::class)->set($this->a->id);

    $student = Student::factory()->create(['institute_id' => null]);

    expect($student->institute_id)->toBe($this->a->id);
});

it('refuses to save a row belonging to another institute', function () {
    app(CurrentInstitute::class)->set($this->a->id);

    expect(fn () => Student::factory()->create(['institute_id' => $this->b->id]))
        ->toThrow(TenantMismatchException::class);
});

it('restores the previous context after run()', function () {
    $ctx = app(CurrentInstitute::class);
    $ctx->set($this->a->id);

    $count = $ctx->run($this->b->id, fn () => Student::count());

    expect($count)->toBe(3)->and($ctx->id())->toBe($this->a->id);
});

it('scopes the institute panel request to the logged-in user institute', function () {
    $user = InstituteUser::factory()->for($this->a)->create();
    $this->actingAs($user, 'institute');
    Filament::setCurrentPanel('institute');

    $seen = null;
    app(SetCurrentInstitute::class)->handle(request(), function () use (&$seen) {
        $seen = [app(CurrentInstitute::class)->id(), Student::count()];

        return response('ok');
    });

    expect($seen)->toBe([$this->a->id, 2]);
});

it('clears a stale context at the start of a request', function () {
    app(CurrentInstitute::class)->set($this->b->id);

    $seen = null;
    app(ResetCurrentInstitute::class)->handle(request(), function () use (&$seen) {
        $seen = [app(CurrentInstitute::class)->has(), Student::count()];

        return response('ok');
    });

    expect($seen)->toBe([false, 5]);
});

it('keeps the context after the institute middleware so Livewire updates stay scoped', function () {
    // Livewire runs persistent middleware through a no-op pipeline and updates the component afterwards.
    $user = InstituteUser::factory()->for($this->a)->create();
    $this->actingAs($user, 'institute');
    Filament::setCurrentPanel('institute');

    app(SetCurrentInstitute::class)->handle(request(), fn () => response('ok'));

    expect(app(CurrentInstitute::class)->id())->toBe($this->a->id)
        ->and(Student::count())->toBe(2);
});

it('resets the context on panel and web requests', function () {
    expect(Filament::getPanel('admin')->getMiddleware())->toContain(ResetCurrentInstitute::class)
        ->and(Filament::getPanel('institute')->getMiddleware())->toContain(ResetCurrentInstitute::class)
        ->and(app(Router::class)->getMiddlewareGroups()['web'])->toContain(ResetCurrentInstitute::class);
});

it('does not scope the admin panel even when an institute guard is also logged in', function () {
    $admin = Admin::factory()->create();
    $user = InstituteUser::factory()->for($this->a)->create();

    $this->actingAs($user, 'institute')
        ->actingAs($admin, 'admin')
        ->get('/admin')
        ->assertOk();

    expect(app(CurrentInstitute::class)->has())->toBeFalse()
        ->and(Student::count())->toBe(5);
});
