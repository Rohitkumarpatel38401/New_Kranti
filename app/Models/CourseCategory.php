<?php

namespace App\Models;

use App\Exceptions\DomainRuleException;
use App\Models\Concerns\HasLocalizedText;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseCategory extends Model
{
    use HasFactory, HasLocalizedText, SoftDeletes;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::deleting(function (CourseCategory $category) {
            if ($category->courses()->withTrashed()->exists()) {
                throw new DomainRuleException('This category has courses. Move them to another category or mark it inactive.');
            }
        });
    }

    protected function casts(): array
    {
        return ['name' => 'array', 'is_active' => 'boolean'];
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}
