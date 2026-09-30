<?php

namespace App\Models;

use App\Enums\AdmissionSource;
use App\Enums\EnrollmentStatus;
use App\Enums\GuardianRelation;
use App\Enums\StudentStatus;
use App\Models\Concerns\BelongsToInstitute;
use App\Models\Concerns\HasAddresses;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class Student extends Authenticatable implements FilamentUser, HasName
{
    use BelongsToInstitute, HasAddresses, HasFactory, Notifiable, SoftDeletes;

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

    protected function email(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value === null ? null : Str::lower(trim($value)));
    }

    protected function registrationNo(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value === null ? null : Str::upper(trim($value)));
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => collect([$this->first_name, $this->middle_name, $this->last_name])
            ->filter()
            ->implode(' '));
    }

    public function personal(): HasOne
    {
        return $this->hasOne(StudentPersonalDetail::class);
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(StudentGuardian::class);
    }

    public function qualifications(): HasMany
    {
        return $this->hasMany(StudentQualification::class)->orderBy('passing_year');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StudentDocument::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'created_by_user_id');
    }

    public function primaryGuardian(): ?StudentGuardian
    {
        return $this->guardians->firstWhere('is_primary', true) ?? $this->guardians->first();
    }

    /** Printed on certificates: the father, else the primary guardian. */
    public function fatherName(): ?string
    {
        return $this->guardians->firstWhere('relation', GuardianRelation::Father)?->name ?? $this->primaryGuardian()?->name;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'student' && $this->portalOpen();
    }

    /**
     * The student account may use the portal and its documents: login allowed, not dropped, institute active,
     * and HQ has approved at least one enrollment. Checked by the panel and by every student policy branch.
     */
    public function portalOpen(): bool
    {
        return $this->can_login
            && $this->status !== StudentStatus::Dropped
            && (bool) $this->institute?->isActive()
            // The portal opens once HQ has approved at least one enrollment.
            && $this->enrollments()->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])->exists();
    }

    public function getFilamentName(): string
    {
        return $this->full_name;
    }
}
