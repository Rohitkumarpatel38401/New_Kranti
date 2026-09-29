<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamResultMark extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['theory' => 'integer', 'practical' => 'integer', 'total' => 'integer', 'is_absent' => 'boolean'];
    }

    public function courseSubject(): BelongsTo
    {
        return $this->belongsTo(CourseSubject::class);
    }
}
