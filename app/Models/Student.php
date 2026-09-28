<?php

namespace App\Models;

use App\Enums\AdmissionSource;
use App\Enums\StudentStatus;
use App\Models\Concerns\BelongsToInstitute;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Student extends Authenticatable implements FilamentUser, HasName
{
    use BelongsToInstitute, HasFactory, Notifiable, SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'can_login' => 'boolean',
            'must_change_password' => 'boolean',
            'status' => StudentStatus::class,
            'admission_source' => AdmissionSource::class,
            'joined_on' => 'date',
            'last_login_at' => 'datetime',
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => collect([$this->first_name, $this->middle_name, $this->last_name])
            ->filter()
            ->implode(' '));
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'student'
            && $this->can_login
            && $this->status !== StudentStatus::Dropped
            && (bool) $this->institute?->isActive();
    }

    public function getFilamentName(): string
    {
        return $this->full_name;
    }
}
