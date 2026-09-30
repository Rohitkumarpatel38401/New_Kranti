<?php

namespace App\Services;

use App\Enums\CertificateStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\ResultOutcome;
use App\Enums\ResultStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Admin;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\ExamResult;
use App\Models\Student;
use App\Support\PanelNotifier;
use App\Support\Qr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Certificates for passed, published results. The QR code points at the public verification page.
 */
class CertificateService
{
    public function __construct(private NumberSequenceService $sequences) {}

    public function issue(Enrollment $enrollment, Admin $by): Certificate
    {
        return DB::transaction(function () use ($enrollment, $by) {
            $locked = Enrollment::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($enrollment->id);

            if ($locked->status !== EnrollmentStatus::Completed) {
                throw new DomainRuleException("Certificates are only issued for completed enrollments (this one is {$locked->status->label()}).");
            }
            $result = ExamResult::query()->withoutGlobalScopes()->where('enrollment_id', $locked->id)->first();

            if (! $result || $result->status !== ResultStatus::Published || $result->result !== ResultOutcome::Pass) {
                throw new DomainRuleException('A certificate needs a published result with a pass.');
            }

            if (Certificate::query()->withoutGlobalScopes()->where('enrollment_id', $locked->id)->exists()) {
                throw new DomainRuleException('A certificate has already been issued for this enrollment.');
            }

            $year = now()->year;
            $certificate = Certificate::create([
                'institute_id' => $locked->institute_id,
                'enrollment_id' => $locked->id,
                'certificate_no' => sprintf('KCTI/C/%d/%06d', $year, $this->sequences->next("certificate:{$year}")),
                'verification_token' => (string) Str::ulid(),
                'issued_on' => now()->toDateString(),
                'issued_by_admin_id' => $by->id,
                'status' => CertificateStatus::Issued,
            ]);

            activity()->causedBy($by)->performedOn($certificate)->event('certificate.issued')->log("Certificate {$certificate->certificate_no} issued");

            if ($student = Student::query()->withoutGlobalScopes()->find($locked->student_id)) {
                PanelNotifier::student($student, 'Your certificate is ready', "Certificate {$certificate->certificate_no}", url('/student/my-courses/'.$locked->id), 'heroicon-o-trophy');
            }

            return $certificate;
        });
    }

    public function revoke(Certificate $certificate, string $reason, Admin $by): void
    {
        if (trim($reason) === '') {
            throw new DomainRuleException('A reason is required to revoke a certificate.');
        }

        DB::transaction(function () use ($certificate, $reason, $by) {
            $locked = Certificate::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($certificate->id);

            if (! $locked->isValid()) {
                throw new DomainRuleException("Certificate {$locked->certificate_no} is already revoked.");
            }

            $locked->update([
                'status' => CertificateStatus::Revoked,
                'revoked_at' => now(),
                'revoked_by_admin_id' => $by->id,
                'revoked_reason' => $reason,
            ]);

            activity()->causedBy($by)->performedOn($locked)->event('certificate.revoked')->log("Certificate {$locked->certificate_no} revoked: {$reason}");
        });

        $certificate->refresh();
    }

    /** Everything the certificate PDF needs. */
    public function viewData(Certificate $certificate): array
    {
        $enrollment = $certificate->enrollment()->withoutGlobalScopes()->with(['student.guardians', 'course', 'institute'])->firstOrFail();
        $result = ExamResult::query()->withoutGlobalScopes()->where('enrollment_id', $enrollment->id)->first();

        return [
            'certificate' => $certificate,
            'enrollment' => $enrollment,
            'student' => $enrollment->student()->withoutGlobalScopes()->with('guardians')->first(),
            'result' => $result,
            'qr' => Qr::dataUri($certificate->verificationUrl()),
        ];
    }
}
