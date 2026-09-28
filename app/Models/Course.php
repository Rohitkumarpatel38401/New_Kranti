<?php

namespace App\Models;

use App\Enums\DurationUnit;
use App\Models\Concerns\HasLocalizedText;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Course extends Model
{
    use HasFactory, HasLocalizedText, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'short_description' => 'array',
            'description' => 'array',
            'eligibility' => 'array',
            'duration_unit' => DurationUnit::class,
            'hq_registration_fee' => 'decimal:2',
            'suggested_fee' => 'decimal:2',
            'is_featured' => 'boolean',
            'show_on_website' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected function code(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value === null ? null : Str::upper(trim($value)));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, 'course_category_id');
    }

    public function courseSubjects(): HasMany
    {
        return $this->hasMany(CourseSubject::class)->orderBy('term_no')->orderBy('sort_order');
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'course_subjects')->withTimestamps();
    }

    public function durationLabel(): string
    {
        return "{$this->duration_value} {$this->duration_unit->label()}";
    }
}
