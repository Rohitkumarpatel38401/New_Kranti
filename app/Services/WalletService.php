<?php

namespace App\Services;

use App\Enums\WalletSource;
use App\Enums\WalletTransactionType;
use App\Exceptions\DomainRuleException;
use App\Models\Institute;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The only way money moves in or out of an institute wallet.
 * Each change locks the wallet row and appends one immutable ledger line.
 */
class WalletService
{
    public function balance(Institute $institute): string
    {
        return self::format($this->walletFor($institute)->refresh()->balance);
    }

    public function credit(Institute $institute, string $amount, WalletSource $source, ?Model $record, string $description, Model $actor): WalletTransaction
    {
        return $this->move($institute, WalletTransactionType::Credit, $amount, $source, $record, $description, $actor);
    }

    public function debit(Institute $institute, string $amount, WalletSource $source, ?Model $record, string $description, Model $actor): WalletTransaction
    {
        return $this->move($institute, WalletTransactionType::Debit, $amount, $source, $record, $description, $actor);
    }

    private function move(Institute $institute, WalletTransactionType $type, string $amount, WalletSource $source, ?Model $record, string $description, Model $actor): WalletTransaction
    {
        $paise = self::toPaise($amount);

        if ($paise <= 0) {
            throw new DomainRuleException('The amount must be greater than zero with at most two decimals.');
        }

        $this->walletFor($institute);

        return DB::transaction(function () use ($institute, $type, $paise, $source, $record, $description, $actor) {
            $wallet = Wallet::query()->where('institute_id', $institute->id)->lockForUpdate()->firstOrFail();
            $balance = self::toPaise((string) $wallet->balance);
            $after = $type === WalletTransactionType::Credit ? $balance + $paise : $balance - $paise;

            if ($after < 0) {
                throw new DomainRuleException(sprintf('Insufficient wallet balance: ₹%s available, ₹%s needed. Please recharge the wallet.',
                    self::fromPaise($balance), self::fromPaise($paise)));
            }

            $wallet->update(['balance' => self::fromPaise($after)]);

            return WalletTransaction::create([
                'institute_id' => $institute->id,
                'wallet_id' => $wallet->id,
                'type' => $type,
                'amount' => self::fromPaise($paise),
                'balance_after' => self::fromPaise($after),
                'source' => $source,
                'source_record_type' => $record?->getMorphClass(),
                'source_record_id' => $record?->getKey(),
                'description' => $description,
                'created_by_type' => $actor->getMorphClass(),
                'created_by_id' => $actor->getKey(),
                'created_at' => now(),
            ]);
        });
    }

    private function walletFor(Institute $institute): Wallet
    {
        DB::table('wallets')->insertOrIgnore([
            'institute_id' => $institute->id,
            'balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Wallet::query()->where('institute_id', $institute->id)->firstOrFail();
    }

    /** Parses "123", "123.4", "123.45" into paise; anything else (including 3 decimals) becomes -1. */
    public static function toPaise(string $amount): int
    {
        $amount = trim($amount);

        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $amount, $m)) {
            return -1;
        }

        return ((int) $m[1]) * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }

    public static function fromPaise(int $paise): string
    {
        return sprintf('%d.%02d', intdiv($paise, 100), $paise % 100);
    }

    public static function format(mixed $amount): string
    {
        return self::fromPaise(max(self::toPaise((string) $amount), 0));
    }
}
