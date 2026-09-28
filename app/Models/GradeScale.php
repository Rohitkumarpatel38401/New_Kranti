<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedText;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GradeScale extends Model
{
    use HasFactory, HasLocalizedText;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['remark' => 'array', 'min_percent' => 'decimal:2', 'max_percent' => 'decimal:2'];
    }

    public static function forPercentage(float $percent): ?self
    {
        return static::query()->where('min_percent', '<=', $percent)->where('max_percent', '>=', $percent)->first();
    }
}
