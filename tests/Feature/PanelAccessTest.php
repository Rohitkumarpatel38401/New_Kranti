<?php

use App\Enums\InstituteStatus;
use App\Models\Admin;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;

it('lets active accounts into their own panel', function () {
    $this->actingAs(Admin::factory()->create(), 'admin')->get('/admin')->assertOk();
    $this->actingAs(InstituteUser::factory()->create(), 'institute')->get('/institute')->assertOk();
    $this->actingAs(Student::factory()->withActiveEnrollment()->create(), 'student')->get('/student')->assertOk();
});

it('blocks inactive admins', function () {
    $this->actingAs(Admin::factory()->inactive()->create(), 'admin')->get('/admin')->assertForbidden();
});

it('blocks inactive institute users', function () {
    $this->actingAs(InstituteUser::factory()->inactive()->create(), 'institute')->get('/institute')->assertForbidden();
});

it('blocks institute users as soon as their institute is suspended', function () {
    $user = InstituteUser::factory()->create();
    $this->actingAs($user, 'institute')->get('/institute')->assertOk();

    $user->institute->update(['status' => InstituteStatus::Suspended]);

    $this->actingAs($user->fresh(), 'institute')->get('/institute')->assertForbidden();
});

it('blocks students without login or with a suspended institute', function () {
    $this->actingAs(Student::factory()->create(), 'student')->get('/student')->assertForbidden();

    $student = Student::factory()->withActiveEnrollment()->for(Institute::factory()->suspended())->create();
    $this->actingAs($student, 'student')->get('/student')->assertForbidden();
});

it('does not let an admin into the institute panel', function () {
    $this->actingAs(Admin::factory()->create(), 'admin')->get('/institute')->assertRedirect('/institute/login');
});

// separate test: guards keep their user for the whole test, so mixing both would leave the admin logged in
it('does not let a student into the admin panel', function () {
    $this->actingAs(Student::factory()->withActiveEnrollment()->create(), 'student')->get('/admin')->assertRedirect('/admin/login');
});
