<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An institute's prepaid HQ balance. Only WalletService changes it.
 */
class Wallet extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['balance' => 'decimal:2'];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }
}
