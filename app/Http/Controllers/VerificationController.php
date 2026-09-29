<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Contracts\View\View;

/**
 * Public certificate verification (no login). Shows only what a certificate already prints.
 */
class VerificationController extends Controller
{
    public function show(string $token): View
    {
        $certificate = Certificate::query()->where('verification_token', $token)->first();

        return view('site.verification.result', ['certificate' => $certificate, 'data' => $certificate ? $this->publicData($certificate) : null]);
    }

    /** @return array<string, ?string> */
    protected function publicData(Certificate $certificate): array
    {
        $enrollment = $certificate->enrollment()->with(['student.guardians', 'course', 'institute', 'result'])->first();

        return [
            'status' => $certificate->status->label(),
            'valid' => $certificate->isValid() ? 'yes' : 'no',
            'certificate_no' => $certificate->certificate_no,
            'issued_on' => $certificate->issued_on->format('d M Y'),
            'student' => $enrollment->student->full_name,
            'father' => $enrollment->student->fatherName(),
            'enrollment_no' => $enrollment->enrollment_no,
            'course' => $enrollment->course->code.' — '.$enrollment->course->localized('name', 'en'),
            'grade' => $enrollment->result?->grade,
            'percentage' => $enrollment->result?->percentage,
            'institute' => $enrollment->institute->name.' ('.$enrollment->institute->code.')',
            'revoked_reason' => $certificate->isValid() ? null : $certificate->revoked_reason,
        ];
    }
}
