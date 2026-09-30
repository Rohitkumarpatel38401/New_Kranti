<?php

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Enums\FranchiseApplicationStatus;
use App\Http\Controllers\SiteController;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\FranchiseApplication;
use App\Models\Institute;

beforeEach(function () {
    $this->main = Institute::factory()->main()->create();
});

it('files a contact enquiry', function () {
    $this->post(route('site.contact.submit'), ['name' => 'Seema', 'phone' => '98765 43210', 'email' => 'seema@example.com', 'message' => 'Timings?'])
        ->assertRedirect(route('site.contact'))
        ->assertSessionHas('status');

    $e = Enquiry::sole();
    expect($e->type)->toBe(EnquiryType::Contact)
        ->and($e->status)->toBe(EnquiryStatus::New)
        ->and($e->phone)->toBe('9876543210')
        ->and($e->institute_id)->toBe($this->main->id);
});

it('files an admission enquiry for a chosen website course', function () {
    $course = Course::factory()->create();

    $this->get(route('site.students.registration', ['course' => $course->id]))->assertSee('value="'.$course->id.'" selected', false);

    $this->post(route('site.students.registration.submit'), ['name' => 'Arun', 'phone' => '9876543210', 'course_id' => $course->id])
        ->assertRedirect(route('site.students.registration'));

    expect(Enquiry::sole())->type->toBe(EnquiryType::Admission)->course_id->toBe($course->id);
});

it('rejects a course that is not on the website', function () {
    $hidden = Course::factory()->create(['show_on_website' => false]);

    $this->post(route('site.students.registration.submit'), ['name' => 'Arun', 'phone' => '9876543210', 'course_id' => $hidden->id])
        ->assertSessionHasErrors('course_id');
    expect(Enquiry::count())->toBe(0);
});

it('validates required fields and phone format', function () {
    $this->post(route('site.contact.submit'), ['name' => '', 'phone' => '12'])
        ->assertSessionHasErrors(['name', 'phone', 'message']);
    expect(Enquiry::count())->toBe(0);
});

it('silently drops submissions that fill the honeypot', function () {
    $this->post(route('site.contact.submit'), ['name' => 'Bot', 'phone' => '9876543210', 'message' => 'spam', SiteController::HONEYPOT => 'http://spam.example'])
        ->assertRedirect(route('site.contact'))
        ->assertSessionHas('status');

    expect(Enquiry::count())->toBe(0);
});

it('throttles form posts to five a minute', function () {
    foreach (range(1, 5) as $i) {
        $this->post(route('site.contact.submit'), ['name' => "N{$i}", 'phone' => '9876543210', 'message' => 'Hi'])->assertRedirect();
    }

    $this->post(route('site.contact.submit'), ['name' => 'N6', 'phone' => '9876543210', 'message' => 'Hi'])->assertStatus(429);
});

it('submits a franchise application with its office address', function () {
    $this->post(route('site.franchise.apply.submit'), [
        'applicant_name' => 'Vikas Yadav', 'email' => 'vikas@example.com', 'phone' => '9876543210',
        'proposed_center_name' => 'Yadav Computer Centre', 'computers_count' => 10, 'area_sqft' => 400, 'experience_years' => 3,
        'message' => 'Ready to start', 'line_1' => 'Main Road', 'city' => 'Handia', 'district' => 'Prayagraj', 'state' => 'Uttar Pradesh', 'pincode' => '221503',
    ])->assertRedirect(route('site.franchise.apply'))->assertSessionHas('status');

    $app = FranchiseApplication::sole();
    expect($app->status)->toBe(FranchiseApplicationStatus::New)
        ->and($app->proposed_center_name)->toBe('Yadav Computer Centre')
        ->and($app->addresses()->first()->pincode)->toBe('221503');
});

it('requires the franchise address and a valid pincode', function () {
    $this->post(route('site.franchise.apply.submit'), [
        'applicant_name' => 'Vikas', 'email' => 'not-an-email', 'phone' => '9876543210', 'proposed_center_name' => 'X', 'pincode' => '12',
    ])->assertSessionHasErrors(['email', 'line_1', 'city', 'district', 'state', 'pincode']);

    expect(FranchiseApplication::count())->toBe(0);
});

// Bug: browsers autofill a field named "website", so real visitors were silently dropped as bots.
it('saves a real admission even when the browser autofilled a website field', function () {
    $this->post(route('site.students.registration.submit'), ['name' => 'Real Person', 'phone' => '9876543210', 'website' => 'https://my-site.example'])
        ->assertRedirect(route('site.students.registration'));

    expect(Enquiry::count())->toBe(1);
});

it('does not use an autofill-prone name for the spam trap', function () {
    $this->get(route('site.students.registration'))
        ->assertDontSee('name="website"', false)
        ->assertSee('name="'.SiteController::HONEYPOT.'"', false);
});
