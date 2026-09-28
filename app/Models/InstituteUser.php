<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitute;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class InstituteUser extends Authenticatable implements FilamentUser
{
    use BelongsToInstitute, HasFactory, HasRoles, Notifiable, SoftDeletes;

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

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'institute'
            && $this->is_active
            && (bool) $this->institute?->isActive();
    }
}
