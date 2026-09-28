<?php

namespace App\Models;

use App\Enums\BatchStatus;
use App\Models\Concerns\BelongsToInstitute;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Batch extends Model
{
    use BelongsToInstitute, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['days' => 'array', 'starts_on' => 'date', 'ends_on' => 'date', 'status' => BatchStatus::class];
    }

    protected function code(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value === null ? null : Str::upper(trim($value)));
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'teacher_user_id');
    }
}
