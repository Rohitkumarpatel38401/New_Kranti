<?php

use App\Models\Admin;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;

it('maps each guard to its own model', function (string $guard, string $model) {
    expect(Auth::guard($guard)->getProvider()->getModel())->toBe($model);
})->with([
    ['admin', Admin::class],
    ['institute', InstituteUser::class],
    ['student', Student::class],
]);

it('authenticates each guard against its own table', function () {
    Admin::factory()->create(['email' => 'a@kcti.test']);
    InstituteUser::factory()->create(['email' => 'i@kcti.test']);
    Student::factory()->canLogin()->create(['registration_no' => 'KCTI0002-26-0001']);

    expect(Auth::guard('admin')->attempt(['email' => 'a@kcti.test', 'password' => 'password']))->toBeTrue()
        ->and(Auth::guard('institute')->attempt(['email' => 'i@kcti.test', 'password' => 'password']))->toBeTrue()
        ->and(Auth::guard('student')->attempt(['registration_no' => 'KCTI0002-26-0001', 'password' => 'password']))->toBeTrue()
        ->and(Auth::guard('institute')->attempt(['email' => 'a@kcti.test', 'password' => 'password']))->toBeFalse();
});

it('allows only one owner per institute', function () {
    $institute = Institute::factory()->create();
    InstituteUser::factory()->owner()->for($institute)->create();

    expect(fn () => InstituteUser::factory()->owner()->for($institute)->create())
        ->toThrow(QueryException::class);
});

it('allows owners in different institutes and many staff', function () {
    $a = Institute::factory()->create();
    $b = Institute::factory()->create();
    InstituteUser::factory()->owner()->for($a)->create();
    InstituteUser::factory()->owner()->for($b)->create();
    InstituteUser::factory()->count(2)->for($a)->create();

    expect(InstituteUser::count())->toBe(4);
});

it('builds a student full name', function () {
    $student = Student::factory()->make(['first_name' => 'Ravi', 'middle_name' => null, 'last_name' => 'Kumar']);

    expect($student->full_name)->toBe('Ravi Kumar');
});

it('has a password broker per guard', function () {
    expect(config('auth.passwords'))->toHaveKeys(['admins', 'institute_users', 'students']);
});
