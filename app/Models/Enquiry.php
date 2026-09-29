<?php

namespace App\Models;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A contact or admission enquiry from the public website. HQ-only; filed against the main institute.
 */
class Enquiry extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['type' => EnquiryType::class, 'status' => EnquiryStatus::class];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'handled_by_admin_id');
    }

    public function convertedStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'converted_student_id')->withoutGlobalScopes();
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [EnquiryStatus::New, EnquiryStatus::Contacted], true);
    }
}
