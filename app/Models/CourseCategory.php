<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedText;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseCategory extends Model
{
    use HasFactory, HasLocalizedText, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['name' => 'array', 'is_active' => 'boolean'];
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}
