<?php

namespace App\Models;

use App\Exceptions\DomainRuleException;
use App\Models\Concerns\HasLocalizedText;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Subject extends Model
{
    use HasFactory, HasLocalizedText, SoftDeletes;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // Soft deletes skip the FK, so guard here: courses and later marks depend on the subject.
        static::deleting(function (Subject $subject) {
            if (CourseSubject::query()->where('subject_id', $subject->id)->exists()) {
                throw new DomainRuleException("{$subject->code} is used by a course. Remove it from those courses or mark it inactive.");
            }
        });
    }

    protected function casts(): array
    {
        return ['name' => 'array', 'is_active' => 'boolean'];
    }

    protected function code(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value === null ? null : Str::upper(trim($value)));
    }
}
