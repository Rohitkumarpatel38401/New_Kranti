<?php

namespace App\Models;

use App\Enums\InstituteStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Immutable audit of institute status changes.
 */
class InstituteStatusHistory extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Institute status history is immutable.'));
        static::deleting(fn () => throw new LogicException('Institute status history is immutable.'));
    }

    protected function casts(): array
    {
        return [
            'from_status' => InstituteStatus::class,
            'to_status' => InstituteStatus::class,
            'changed_at' => 'datetime',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'changed_by_admin_id');
    }
}
