<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Models\Concerns\BelongsToInstitute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A student's admission into one course. Status changes only through EnrollmentService.
 */
class Enrollment extends Model
{
    use BelongsToInstitute, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'admission_date' => 'date',
            'completed_on' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'course_fee' => 'decimal:2',
            'discount' => 'decimal:2',
            'net_fee' => 'decimal:2',
            'hq_fee_snapshot' => 'decimal:2',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by_admin_id');
    }

    public function debitTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'debit_transaction_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(FeeInstallment::class)->orderBy('sequence');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FeePayment::class);
    }

    public function result(): HasOne
    {
        return $this->hasOne(ExamResult::class);
    }

    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(EnrollmentStatusHistory::class)->orderBy('id');
    }
}
