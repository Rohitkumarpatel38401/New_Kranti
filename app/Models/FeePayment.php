<?php

namespace App\Models;

use App\Enums\FeePaymentStatus;
use App\Enums\PaymentMode;
use App\Models\Concerns\BelongsToInstitute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A fee receipt. Never deleted: a mistake is cancelled with a reason.
 */
class FeePayment extends Model
{
    use BelongsToInstitute;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new \LogicException('Fee receipts are cancelled, never deleted.'));
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'mode' => PaymentMode::class,
            'status' => FeePaymentStatus::class,
            'paid_on' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'received_by_user_id');
    }

    public function receivedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'received_by_admin_id');
    }

    public function cancelledBy(): MorphTo
    {
        return $this->morphTo('cancelled_by');
    }

    public function isValid(): bool
    {
        return $this->status === FeePaymentStatus::Valid;
    }
}
