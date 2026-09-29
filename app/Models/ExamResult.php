<?php

namespace App\Models;

use App\Enums\ResultOutcome;
use App\Enums\ResultStatus;
use App\Models\Concerns\BelongsToInstitute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One result per enrollment. Marks change only while draft or returned; ResultService owns the workflow.
 */
class ExamResult extends Model
{
    use BelongsToInstitute;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
            'status' => ResultStatus::class,
            'result' => ResultOutcome::class,
            'percentage' => 'decimal:2',
            'total_obtained' => 'integer',
            'total_max' => 'integer',
            'submitted_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function marks(): HasMany
    {
        return $this->hasMany(ExamResultMark::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'submitted_by_user_id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'published_by_admin_id');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [ResultStatus::Draft, ResultStatus::Returned], true);
    }
}
