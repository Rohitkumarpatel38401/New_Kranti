<?php

namespace App\Models;

use App\Enums\QualificationLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentQualification extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['level' => QualificationLevel::class, 'score' => 'decimal:2'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
