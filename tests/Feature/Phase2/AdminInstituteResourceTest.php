<?php

use App\Enums\AddressType;
use App\Enums\InstituteStatus;
use App\Enums\VerificationStatus;
use App\Filament\Admin\Resources\Institutes\Pages\CreateInstitute;
use App\Filament\Admin\Resources\Institutes\Pages\EditInstitute;
use App\Filament\Admin\Resources\Institutes\Pages\ListInstitutes;
use App\Filament\Admin\Resources\Institutes\Pages\ViewInstitute;
use App\Filament\Admin\Resources\Institutes\RelationManagers\DocumentsRelationManager;
use App\Filament\Admin\Resources\Institutes\RelationManagers\StatusHistoriesRelationManager;
use App\Models\Admin;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Services\InstituteService;
use App\Services\NumberSequenceService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    foreach (['Create', 'Update', 'View', 'ViewAny'] as $action) {
        Permission::findOrCreate("{$action}:Institute", 'admin');
    }
    app(NumberSequenceService::class)->syncAtLeast('institute_code', 1);
    $this->main = Institute::factory()->main()->create();
    $this->admin = Admin::factory()->create()->givePermissionTo(['ViewAny:Institute', 'View:Institute', 'Create:Institute', 'Update:Institute', 'suspend_institute']);
    $this->actingAs($this->admin, 'admin');
    Filament::setCurrentPanel('admin');
});

function sampleInstituteForm(): array
{
    return [
        'name' => 'Ganga Computer Academy',
        'email' => 'ganga@example.com',
        'phone' => '9811111111',
        'profile' => ['owner_name' => 'Ramesh Yadav', 'owner_phone' => '9822222222', 'computers_count' => 15],
        'office_address' => ['line_1' => 'Main Road', 'city' => 'Varanasi', 'district' => 'Varanasi', 'state' => 'Uttar Pradesh', 'pincode' => '221001'],
    ];
}

it('creates an institute with profile and office address through the form', function () {
    Livewire::test(CreateInstitute::class)
        ->fillForm(sampleInstituteForm())
        ->call('create')
        ->assertHasNoFormErrors();

    $institute = Institute::where('email', 'ganga@example.com')->sole();
    expect($institute->code)->toBe('KCTI0002')
        ->and($institute->profile->owner_name)->toBe('Ramesh Yadav')
        ->and($institute->address(AddressType::Office)->city)->toBe('Varanasi')
        ->and($institute->statusHistories)->toHaveCount(1);
});

it('validates required fields and pincode format', function () {
    Livewire::test(CreateInstitute::class)
        ->fillForm(['name' => '', 'office_address' => ['pincode' => '12']])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'office_address.pincode', 'office_address.city' => 'required']);
});

it('edits profile and address', function () {
    $institute = app(InstituteService::class)->create(['name' => 'Old Name'], ['owner_name' => 'A'], sampleInstituteForm()['office_address'], $this->admin);

    Livewire::test(EditInstitute::class, ['record' => $institute->getRouteKey()])
        ->assertSchemaStateSet(['profile.owner_name' => 'A', 'office_address.city' => 'Varanasi'])
        ->fillForm(['name' => 'New Name', 'profile.owner_name' => 'B', 'office_address.city' => 'Mirzapur'])
        ->call('save')
        ->assertHasNoFormErrors();

    $institute->refresh();
    expect($institute->name)->toBe('New Name')
        ->and($institute->profile->owner_name)->toBe('B')
        ->and($institute->address(AddressType::Office)->city)->toBe('Mirzapur');
});

it('lists institutes with city and owner', function () {
    $institute = app(InstituteService::class)->create(['name' => 'Listed'], [], sampleInstituteForm()['office_address'], $this->admin);
    InstituteUser::factory()->owner()->for($institute)->create(['name' => 'Owner Person']);

    Livewire::test(ListInstitutes::class)
        ->assertCanSeeTableRecords([$institute])
        ->assertSee('Varanasi')
        ->assertSee('Owner Person');
});

it('changes status with a required reason and records history', function () {
    $institute = app(InstituteService::class)->create(['name' => 'To Suspend'], [], [], $this->admin);

    Livewire::test(ListInstitutes::class)
        ->callTableAction('changeStatus', $institute, data: ['status' => InstituteStatus::Suspended->value, 'reason' => ''])
        ->assertHasTableActionErrors(['reason' => 'required']);

    Livewire::test(ListInstitutes::class)
        ->callTableAction('changeStatus', $institute, data: ['status' => InstituteStatus::Suspended->value, 'reason' => 'Unpaid dues'])
        ->assertHasNoTableActionErrors();

    expect($institute->fresh()->status)->toBe(InstituteStatus::Suspended)
        ->and($institute->statusHistories()->latest('id')->first()->reason)->toBe('Unpaid dues');
});

it('hides the status action for the main institute and without permission', function () {
    Livewire::test(ListInstitutes::class)->assertTableActionHidden('changeStatus', $this->main);

    $other = Institute::factory()->create();
    $this->actingAs(Admin::factory()->create()->givePermissionTo('ViewAny:Institute'), 'admin');
    Livewire::test(ListInstitutes::class)->assertTableActionHidden('changeStatus', $other);
});

it('forbids the create page without Create:Institute', function () {
    $this->actingAs(Admin::factory()->create()->givePermissionTo('ViewAny:Institute'), 'admin')
        ->get('/admin/institutes/create')
        ->assertForbidden();
});

it('shows status history on the view page', function () {
    $institute = app(InstituteService::class)->create(['name' => 'Viewed'], [], [], $this->admin);

    $this->get("/admin/institutes/{$institute->getRouteKey()}")->assertOk()->assertSee('Viewed');
    Livewire::test(StatusHistoriesRelationManager::class, ['ownerRecord' => $institute, 'pageClass' => ViewInstitute::class])
        ->assertSee('Institute created');
});

it('uploads and verifies institute documents', function () {
    Storage::fake('local');
    $institute = Institute::factory()->create();

    Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $institute, 'pageClass' => ViewInstitute::class])
        ->callTableAction('create', data: [
            'type' => 'owner_pan',
            'number' => 'ABCDE1234F',
            'path' => UploadedFile::fake()->create('pan.pdf', 100, 'application/pdf'),
        ])
        ->assertHasNoTableActionErrors();

    $document = $institute->documents()->sole();
    Storage::disk('local')->assertExists($document->path);

    Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $institute, 'pageClass' => ViewInstitute::class])
        ->callTableAction('verify', $document);

    expect($document->fresh()->verification_status)->toBe(VerificationStatus::Verified)
        ->and($document->fresh()->verified_by_admin_id)->toBe($this->admin->id);
});
