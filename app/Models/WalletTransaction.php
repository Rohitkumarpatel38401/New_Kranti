<?php

namespace App\Models;

use App\Enums\WalletSource;
use App\Enums\WalletTransactionType;
use App\Models\Concerns\BelongsToInstitute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Immutable wallet ledger line. balance_after is the wallet balance right after this line.
 */
class WalletTransaction extends Model
{
    use BelongsToInstitute;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Wallet transactions are immutable.'));
        static::deleting(fn () => throw new LogicException('Wallet transactions are immutable.'));
    }

    protected function casts(): array
    {
        return [
            'type' => WalletTransactionType::class,
            'source' => WalletSource::class,
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    public function sourceRecord(): MorphTo
    {
        return $this->morphTo('source_record');
    }

    public function createdBy(): MorphTo
    {
        return $this->morphTo('created_by');
    }
}
