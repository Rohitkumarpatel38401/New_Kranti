<?php

namespace App\Models;

use App\Enums\InstituteStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Institute extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id', 'main_marker'];

    protected function casts(): array
    {
        return [
            'status' => InstituteStatus::class,
            'is_main' => 'boolean',
            'wallet_exempt' => 'boolean',
            'established_on' => 'date',
            'franchise_start_on' => 'date',
            'franchise_expiry_on' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function scopeMain(Builder $query): Builder
    {
        return $query->where('is_main', true);
    }

    public static function mainInstitute(): ?self
    {
        return static::query()->main()->first();
    }

    public function isActive(): bool
    {
        return $this->status === InstituteStatus::Active;
    }
}
