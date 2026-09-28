<?php

use App\Enums\PaymentMode;
use App\Enums\RechargeStatus;
use App\Exceptions\DomainRuleException;
use App\Filament\Admin\Resources\Institutes\Pages\ListInstitutes;
use App\Filament\Admin\Resources\WalletRechargeRequests\Pages\ListWalletRechargeRequests;
use App\Filament\Institute\Resources\WalletRecharges\Pages\CreateWalletRecharge;
use App\Filament\Institute\Resources\WalletRecharges\Pages\ListWalletRecharges;
use App\Models\Admin;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\WalletRechargeRequest;
use App\Models\WalletTransaction;
use App\Services\WalletRechargeService;
use App\Services\WalletService;
use App\Support\Tenancy\CurrentInstitute;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
    $this->institute = Institute::factory()->create();
    $this->owner = InstituteUser::factory()->owner()->for($this->institute)->create()->assignRole('owner');
    $this->accounts = Admin::factory()->create()->assignRole('accounts');
    $this->service = app(WalletRechargeService::class);
});

function rechargeData(array $overrides = []): array
{
    return array_merge([
        'amount' => '5000',
        'payment_mode' => PaymentMode::Upi->value,
        'utr_reference' => 'UTR'.fake()->unique()->numerify('#########'),
        'paid_on' => now()->toDateString(),
        'proof_path' => UploadedFile::fake()->image('proof.jpg')->store('wallet-proofs', 'local'),
    ], $overrides);
}

it('numbers a pending request', function () {
    $request = $this->service->request(rechargeData(), $this->owner);

    expect($request->request_no)->toBe('KCTI/WR/'.now()->year.'/00001')
        ->and($request->status)->toBe(RechargeStatus::Pending)
        ->and($request->institute_id)->toBe($this->institute->id)
        ->and($request->requested_by_user_id)->toBe($this->owner->id);
});

it('refuses a reused UTR', function () {
    $this->service->request(rechargeData(['utr_reference' => 'UTR123']), $this->owner);

    expect(fn () => $this->service->request(rechargeData(['utr_reference' => ' utr123 ']), $this->owner))
        ->toThrow(DomainRuleException::class, 'UTR');
});

it('credits the wallet once on approval', function () {
    $request = $this->service->request(rechargeData(['amount' => '2500.50']), $this->owner);

    $this->service->approve($request, $this->accounts);

    expect(app(WalletService::class)->balance($this->institute))->toBe('2500.50')
        ->and($request->fresh()->status)->toBe(RechargeStatus::Approved)
        ->and($request->fresh()->credit_transaction_id)->not->toBeNull()
        ->and(fn () => $this->service->approve($request, $this->accounts))->toThrow(DomainRuleException::class);

    expect(WalletTransaction::count())->toBe(1);
});

it('rejects with remarks and credits nothing', function () {
    $request = $this->service->request(rechargeData(), $this->owner);

    expect(fn () => $this->service->reject($request, '', $this->accounts))->toThrow(DomainRuleException::class);
    $this->service->reject($request, 'UTR not found in bank statement', $this->accounts);

    expect($request->fresh()->status)->toBe(RechargeStatus::Rejected)
        ->and(app(WalletService::class)->balance($this->institute))->toBe('0.00')
        ->and(fn () => $this->service->approve($request, $this->accounts))->toThrow(DomainRuleException::class);
});

it('lets the owner submit a request with proof from the institute panel', function () {
    $this->actingAs($this->owner, 'institute');
    app(CurrentInstitute::class)->set($this->institute->id);
    Filament::setCurrentPanel('institute');

    Livewire::test(CreateWalletRecharge::class)
        ->fillForm([
            'amount' => 3000,
            'payment_mode' => 'upi',
            'utr_reference' => 'UTR777',
            'paid_on' => now()->toDateString(),
            'proof_path' => UploadedFile::fake()->image('proof.png'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $request = WalletRechargeRequest::sole();
    Storage::disk('local')->assertExists($request->proof_path);
    expect($request->amount)->toBe('3000.00');
});

it('forbids recharge requests for staff without request_wallet_recharge', function () {
    $teacher = InstituteUser::factory()->for($this->institute)->create()->assignRole('teacher');

    $this->actingAs($teacher, 'institute')->get('/institute/wallet-recharges/create')->assertForbidden();
});

it('never shows another institute requests on the Livewire update endpoint', function () {
    $other = Institute::factory()->create();
    $otherOwner = InstituteUser::factory()->owner()->for($other)->create()->assignRole('owner');
    $this->service->request(rechargeData(['utr_reference' => 'FOREIGNUTR']), $otherOwner);
    $this->service->request(rechargeData(['utr_reference' => 'MYUTR']), $this->owner);
    $this->actingAs($this->owner, 'institute');

    $html = $this->get('/institute/wallet-recharges')->assertOk()->assertSee('MYUTR')->assertDontSee('FOREIGNUTR')->getContent();
    expect(livewireUpdate($this, $html, ListWalletRecharges::class, ['tableSearch' => 'UTR']))->not->toContain('FOREIGNUTR');
});

it('lets accounts approve from the admin table and forbids support', function () {
    $request = $this->service->request(rechargeData(), $this->owner);
    Filament::setCurrentPanel('admin');

    $this->actingAs($this->accounts, 'admin');
    Livewire::test(ListWalletRechargeRequests::class)->callTableAction('approve', $request)->assertHasNoTableActionErrors();
    expect($request->fresh()->status)->toBe(RechargeStatus::Approved);
});

it('forbids the recharge queue to support admins', function () {
    $this->actingAs(Admin::factory()->create()->assignRole('support'), 'admin')->get('/admin/wallet-recharge-requests')->assertForbidden();
});

it('adjusts a wallet with a reason and never below zero', function () {
    $super = Admin::factory()->create()->assignRole('super_admin');
    $this->actingAs($super, 'admin');
    Filament::setCurrentPanel('admin');

    Livewire::test(ListInstitutes::class)
        ->callTableAction('adjustWallet', $this->institute, data: ['type' => 'credit', 'amount' => 100, 'reason' => 'Opening balance'])
        ->assertHasNoTableActionErrors();

    Livewire::test(ListInstitutes::class)
        ->callTableAction('adjustWallet', $this->institute, data: ['type' => 'debit', 'amount' => 500, 'reason' => 'Too much'])
        ->assertNotified();

    expect(app(WalletService::class)->balance($this->institute))->toBe('100.00');
});
