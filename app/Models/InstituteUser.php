<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class InstituteUser extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $guarded = ['id', 'owner_marker'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_owner' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'institute'
            && $this->is_active
            && (bool) $this->institute?->isActive();
    }
}
