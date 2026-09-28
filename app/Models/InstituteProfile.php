<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstituteProfile extends Model
{
    use HasLocalizedText;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['about' => 'array'];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }
}
