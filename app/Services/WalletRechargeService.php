<?php

namespace App\Services;

use App\Enums\RechargeStatus;
use App\Enums\WalletSource;
use App\Exceptions\DomainRuleException;
use App\Models\Admin;
use App\Models\InstituteUser;
use App\Models\WalletRechargeRequest;
use App\Support\PanelNotifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Franchise pays HQ outside the system, uploads proof; HQ approves and the wallet is credited once.
 */
class WalletRechargeService
{
    public function __construct(
        private NumberSequenceService $sequences,
        private WalletService $wallets,
    ) {}

    public function request(array $data, InstituteUser $by): WalletRechargeRequest
    {
        $utr = Str::upper(preg_replace('/\s+/', '', (string) ($data['utr_reference'] ?? '')));

        if ($utr === '') {
            throw new DomainRuleException('The UTR / reference number is required.');
        }

        if (WalletService::toPaise((string) $data['amount']) <= 0) {
            throw new DomainRuleException('The amount must be greater than zero with at most two decimals.');
        }

        if (WalletRechargeRequest::query()->withoutGlobalScopes()->where('utr_reference', $utr)->exists()) {
            throw new DomainRuleException("UTR {$utr} has already been submitted.");
        }

        try {
            return DB::transaction(function () use ($data, $utr, $by) {
                $year = now()->year;

                $request = WalletRechargeRequest::create([
                    ...$data,
                    'utr_reference' => $utr,
                    'institute_id' => $by->institute_id,
                    'request_no' => sprintf('KCTI/WR/%d/%05d', $year, $this->sequences->next("wallet_recharge:{$year}")),
                    'status' => RechargeStatus::Pending,
                    'requested_by_user_id' => $by->id,
                ]);

                PanelNotifier::admins('approve_wallet_recharge', 'Wallet recharge to verify',
                    "{$by->institute?->name} · ₹{$request->amount} · UTR {$request->utr_reference}", url('/admin/wallet-recharge-requests'), 'heroicon-o-banknotes');

                return $request;
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainRuleException("UTR {$utr} has already been submitted.");
        }
    }

    public function approve(WalletRechargeRequest $request, Admin $by): void
    {
        DB::transaction(function () use ($request, $by) {
            $locked = $this->lockPending($request);

            $credit = $this->wallets->credit(
                $locked->institute, (string) $locked->amount, WalletSource::Recharge, $locked,
                "Recharge {$locked->request_no} (UTR {$locked->utr_reference})", $by,
            );

            $locked->update([
                'status' => RechargeStatus::Approved,
                'reviewed_by_admin_id' => $by->id,
                'reviewed_at' => now(),
                'credit_transaction_id' => $credit->id,
            ]);
        });

        $request->refresh();
        PanelNotifier::institute($request->institute_id, 'request_wallet_recharge', 'Wallet recharge approved',
            "₹{$request->amount} added to your wallet ({$request->request_no}).", url('/institute/wallet-recharges'), 'heroicon-o-check-circle');
    }

    public function reject(WalletRechargeRequest $request, string $remarks, Admin $by): void
    {
        if (trim($remarks) === '') {
            throw new DomainRuleException('Remarks are required to reject a recharge request.');
        }

        DB::transaction(function () use ($request, $remarks, $by) {
            $this->lockPending($request)->update([
                'status' => RechargeStatus::Rejected,
                'reviewed_by_admin_id' => $by->id,
                'reviewed_at' => now(),
                'admin_remarks' => $remarks,
            ]);
        });

        $request->refresh();
        PanelNotifier::institute($request->institute_id, 'request_wallet_recharge', 'Wallet recharge rejected',
            "{$request->request_no}: {$request->admin_remarks}", url('/institute/wallet-recharges'), 'heroicon-o-x-circle');
    }

    private function lockPending(WalletRechargeRequest $request): WalletRechargeRequest
    {
        $locked = WalletRechargeRequest::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($request->id);

        if ($locked->status !== RechargeStatus::Pending) {
            throw new DomainRuleException("Request {$locked->request_no} is already {$locked->status->label()}.");
        }

        return $locked;
    }
}
