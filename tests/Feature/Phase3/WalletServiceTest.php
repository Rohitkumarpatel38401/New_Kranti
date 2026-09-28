<?php

use App\Enums\WalletSource;
use App\Enums\WalletTransactionType;
use App\Exceptions\DomainRuleException;
use App\Models\Admin;
use App\Models\Institute;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\InstituteService;
use App\Services\NumberSequenceService;
use App\Services\WalletService;

beforeEach(function () {
    $this->wallets = app(WalletService::class);
    $this->admin = Admin::factory()->create();
    $this->institute = Institute::factory()->create();
});

it('credits and debits with a running balance in the ledger', function () {
    $this->wallets->credit($this->institute, '1000.00', WalletSource::Recharge, null, 'Recharge', $this->admin);
    $debit = $this->wallets->debit($this->institute, '250.50', WalletSource::EnrollmentFee, null, 'Enrollment', $this->admin);

    expect($this->wallets->balance($this->institute))->toBe('749.50')
        ->and($debit->type)->toBe(WalletTransactionType::Debit)
        ->and($debit->amount)->toBe('250.50')
        ->and($debit->balance_after)->toBe('749.50')
        ->and($debit->created_by_type)->toBe($this->admin->getMorphClass())
        ->and(WalletTransaction::where('institute_id', $this->institute->id)->count())->toBe(2);
});

it('refuses a debit beyond the balance and writes nothing', function () {
    $this->wallets->credit($this->institute, '100', WalletSource::Recharge, null, 'Recharge', $this->admin);

    expect(fn () => $this->wallets->debit($this->institute, '100.01', WalletSource::EnrollmentFee, null, 'x', $this->admin))
        ->toThrow(DomainRuleException::class, 'Insufficient wallet balance');

    expect($this->wallets->balance($this->institute))->toBe('100.00')
        ->and(WalletTransaction::count())->toBe(1);
});

it('refuses zero or negative amounts', function (string $amount) {
    expect(fn () => $this->wallets->credit($this->institute, $amount, WalletSource::Adjustment, null, 'x', $this->admin))
        ->toThrow(DomainRuleException::class);
})->with(['0', '-5', '0.001', 'abc']);

it('keeps ledger rows immutable', function () {
    $row = $this->wallets->credit($this->institute, '10', WalletSource::Recharge, null, 'x', $this->admin);

    expect(fn () => $row->update(['amount' => '99']))->toThrow(LogicException::class)
        ->and(fn () => $row->delete())->toThrow(LogicException::class);
});

it('links the ledger row to its source record', function () {
    $source = Institute::factory()->create();
    $row = $this->wallets->credit($this->institute, '10', WalletSource::Adjustment, $source, 'x', $this->admin);

    expect($row->source)->toBe(WalletSource::Adjustment)
        ->and($row->sourceRecord->is($source))->toBeTrue();
});

it('gives every new institute a wallet', function () {
    app(NumberSequenceService::class)->syncAtLeast('institute_code', 1);

    $institute = app(InstituteService::class)->create(['name' => 'With Wallet'], [], [], $this->admin);

    expect(Wallet::where('institute_id', $institute->id)->sole()->balance)->toBe('0.00');
});

it('creates a wallet on first use for institutes that have none', function () {
    expect(Wallet::where('institute_id', $this->institute->id)->exists())->toBeFalse()
        ->and($this->wallets->balance($this->institute))->toBe('0.00')
        ->and(Wallet::where('institute_id', $this->institute->id)->exists())->toBeTrue();
});
