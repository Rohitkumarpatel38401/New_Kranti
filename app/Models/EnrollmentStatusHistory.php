<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class EnrollmentStatusHistory extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Enrollment history is immutable.'));
        static::deleting(fn () => throw new LogicException('Enrollment history is immutable.'));
    }

    protected function casts(): array
    {
        return ['from_status' => EnrollmentStatus::class, 'to_status' => EnrollmentStatus::class, 'changed_at' => 'datetime'];
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
