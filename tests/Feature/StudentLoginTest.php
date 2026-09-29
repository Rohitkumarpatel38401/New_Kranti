<?php

use App\Filament\Student\Pages\Auth\Login;
use App\Models\Student;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(fn () => Filament::setCurrentPanel('student'));

it('logs in with registration number', function (string $typed) {
    $student = Student::factory()->withActiveEnrollment()->create(['registration_no' => 'KCTI0002-26-0007']);

    Livewire::test(Login::class)
        ->fillForm(['login' => $typed, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(auth('student')->id())->toBe($student->id);
})->with(['KCTI0002-26-0007', 'kcti0002-26-0007', '  KCTI0002-26-0007 ']);

it('logs in with email', function () {
    $student = Student::factory()->withActiveEnrollment()->create(['email' => 'ravi@example.com']);

    Livewire::test(Login::class)
        ->fillForm(['login' => 'Ravi@Example.com', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(auth('student')->id())->toBe($student->id);
});

it('stores student emails lowercase', function () {
    expect(Student::factory()->create(['email' => ' Ravi@Example.COM '])->email)->toBe('ravi@example.com');
});

it('rejects a wrong password with an error on the login field', function () {
    Student::factory()->withActiveEnrollment()->create(['registration_no' => 'KCTI0002-26-0008']);

    Livewire::test(Login::class)
        ->fillForm(['login' => 'KCTI0002-26-0008', 'password' => 'wrong'])
        ->call('authenticate')
        ->assertHasFormErrors(['login']);

    expect(auth('student')->check())->toBeFalse();
});
