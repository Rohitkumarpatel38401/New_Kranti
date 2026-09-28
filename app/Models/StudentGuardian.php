<?php

namespace App\Models;

use App\Enums\GuardianRelation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentGuardian extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['relation' => GuardianRelation::class, 'is_primary' => 'boolean', 'annual_income' => 'decimal:2'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
