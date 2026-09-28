<?php

namespace App\Models;

use App\Enums\InstituteStatus;
use App\Models\Concerns\HasAddresses;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Institute extends Model
{
    use HasAddresses, HasFactory, SoftDeletes;

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

    public function profile(): HasOne
    {
        return $this->hasOne(InstituteProfile::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(InstituteDocument::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(InstituteStatusHistory::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(InstituteUser::class);
    }

    public function owner(): HasOne
    {
        return $this->hasOne(InstituteUser::class)->where('is_owner', true);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by_admin_id');
    }
}
