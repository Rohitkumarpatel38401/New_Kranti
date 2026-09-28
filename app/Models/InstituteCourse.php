<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class InstituteCourse extends Pivot
{
    protected $table = 'institute_course';

    public $incrementing = true;

    protected function casts(): array
    {
        return ['hq_fee_override' => 'decimal:2', 'is_active' => 'boolean'];
    }
}
