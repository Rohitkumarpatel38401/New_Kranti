<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One batch on one day. Taking attendance again for the same day edits this session.
 */
class AttendanceSession extends Model
{
    use BelongsToInstitute;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function takenBy(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'taken_by_user_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}
