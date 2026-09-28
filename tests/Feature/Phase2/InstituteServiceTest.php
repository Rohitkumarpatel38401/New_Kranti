<?php

use App\Enums\AddressType;
use App\Enums\InstituteStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Admin;
use App\Models\Institute;
use App\Models\InstituteStatusHistory;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Services\InstituteService;
use App\Services\NumberSequenceService;

beforeEach(function () {
    $this->service = app(InstituteService::class);
    $this->admin = Admin::factory()->create();
    app(NumberSequenceService::class)->syncAtLeast('institute_code', 1);
});

function createInstitute(array $data = []): Institute
{
    return test()->service->create(
        array_merge(['name' => 'Shiv Computer Centre', 'email' => 'shiv@example.com', 'phone' => '9876543210'], $data),
        ['owner_name' => 'Shiv Kumar', 'computers_count' => 12],
        ['line_1' => 'Civil Lines', 'city' => 'Prayagraj', 'district' => 'Prayagraj', 'state' => 'Uttar Pradesh', 'pincode' => '211001'],
        test()->admin,
    );
}

it('creates an institute with the next code, profile, office address, and history', function () {
    $institute = createInstitute();

    expect($institute->code)->toBe('KCTI0002')
        ->and($institute->slug)->toBe('shiv-computer-centre')
        ->and($institute->status)->toBe(InstituteStatus::Active)
        ->and($institute->approved_by_admin_id)->toBe($this->admin->id)
        ->and($institute->profile->owner_name)->toBe('Shiv Kumar')
        ->and($institute->address(AddressType::Office)->city)->toBe('Prayagraj')
        ->and($institute->statusHistories)->toHaveCount(1)
        ->and($institute->statusHistories->first()->to_status)->toBe(InstituteStatus::Active);
});

it('suffixes a colliding slug', function () {
    createInstitute();
    $second = createInstitute(['email' => 'other@example.com']);

    expect($second->slug)->toBe('shiv-computer-centre-2');
});

it('records every status change with reason and actor', function () {
    $institute = createInstitute();

    $this->service->changeStatus($institute, InstituteStatus::Suspended, 'Fees overdue', $this->admin);

    $history = $institute->statusHistories()->latest('id')->first();
    expect($institute->fresh()->status)->toBe(InstituteStatus::Suspended)
        ->and($history->from_status)->toBe(InstituteStatus::Active)
        ->and($history->to_status)->toBe(InstituteStatus::Suspended)
        ->and($history->reason)->toBe('Fees overdue')
        ->and($history->changed_by_admin_id)->toBe($this->admin->id);
});

it('never suspends or closes the main institute, even for a super admin', function (InstituteStatus $to) {
    $main = Institute::factory()->main()->create();

    expect(fn () => $this->service->changeStatus($main, $to, 'test', $this->admin))
        ->toThrow(DomainRuleException::class);
    expect($main->fresh()->status)->toBe(InstituteStatus::Active);
})->with([InstituteStatus::Suspended, InstituteStatus::Closed, InstituteStatus::Expired, InstituteStatus::Pending]);

it('refuses a change to the same status or without a reason', function () {
    $institute = createInstitute();

    expect(fn () => $this->service->changeStatus($institute, InstituteStatus::Active, 'x', $this->admin))
        ->toThrow(DomainRuleException::class)
        ->and(fn () => $this->service->changeStatus($institute, InstituteStatus::Suspended, '  ', $this->admin))
        ->toThrow(DomainRuleException::class);
});

it('keeps status history immutable', function () {
    $history = createInstitute()->statusHistories->first();

    expect(fn () => $history->update(['reason' => 'edited']))->toThrow(LogicException::class)
        ->and(fn () => $history->delete())->toThrow(LogicException::class)
        ->and(InstituteStatusHistory::count())->toBe(1);
});

it('refuses to delete the main institute or one with students or staff', function () {
    $main = Institute::factory()->main()->create();
    $withStudent = createInstitute();
    Student::factory()->for($withStudent)->create();
    $withStaff = createInstitute(['email' => 'b@example.com']);
    InstituteUser::factory()->for($withStaff)->create();

    foreach ([$main, $withStudent, $withStaff] as $institute) {
        expect(fn () => $this->service->delete($institute, $this->admin))->toThrow(DomainRuleException::class);
    }
});

it('soft deletes an institute that only has its owner', function () {
    $institute = createInstitute();
    InstituteUser::factory()->owner()->for($institute)->create();

    $this->service->delete($institute, $this->admin);

    expect(Institute::find($institute->id))->toBeNull()
        ->and(Institute::withTrashed()->find($institute->id))->not->toBeNull();
});
