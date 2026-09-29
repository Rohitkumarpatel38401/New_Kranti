<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeInstallment extends Model
{
    use BelongsToInstitute;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'amount' => 'decimal:2'];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
