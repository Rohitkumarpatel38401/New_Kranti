<?php

namespace App\Models;

use App\Enums\InstituteStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Concerns\HasAddresses;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
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

    /** Courses HQ allows this institute to teach (pivot: hq_fee_override, is_active). */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'institute_course')
            ->using(InstituteCourse::class)
            ->withPivot(['hq_fee_override', 'is_active'])
            ->withTimestamps();
    }

    /** The main institute (HQ's own centre) may teach every active course. */
    public function authorizedCourses(): BelongsToMany|Builder
    {
        if ($this->is_main) {
            return Course::query()->where('courses.is_active', true);
        }

        return $this->courses()->wherePivot('is_active', true)->where('courses.is_active', true);
    }

    public function isAuthorizedFor(Course $course): bool
    {
        return $this->authorizedCourses()->whereKey($course->getKey())->exists();
    }

    /** The HQ fee debited per enrollment in this course; the course must be authorized. */
    public function hqFeeFor(Course $course): string
    {
        if ($this->is_main) {
            return $this->isAuthorizedFor($course) ? '0.00'
                : throw new DomainRuleException("{$course->code} is not active.");
        }

        $authorized = $this->authorizedCourses()->whereKey($course->getKey())->first();

        if (! $authorized) {
            throw new DomainRuleException("{$this->name} is not authorized to teach {$course->code}.");
        }

        $fee = $authorized->pivot->hq_fee_override ?? $course->hq_registration_fee;

        return number_format((float) $fee, 2, '.', '');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by_admin_id');
    }
}
