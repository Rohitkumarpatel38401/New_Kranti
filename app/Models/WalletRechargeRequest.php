<?php

namespace App\Models;

use App\Enums\PaymentMode;
use App\Enums\RechargeStatus;
use App\Models\Concerns\BelongsToInstitute;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WalletRechargeRequest extends Model
{
    use BelongsToInstitute;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_mode' => PaymentMode::class,
            'status' => RechargeStatus::class,
            'paid_on' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    /** UTRs are compared case- and space-insensitively so a proof cannot be reused. */
    protected function utrReference(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value === null ? null : Str::upper(preg_replace('/\s+/', '', $value)));
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'requested_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }

    public function creditTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'credit_transaction_id');
    }
}
