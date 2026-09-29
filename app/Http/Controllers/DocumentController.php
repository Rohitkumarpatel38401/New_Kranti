<?php

namespace App\Http\Controllers;

use App\Enums\EnrollmentStatus;
use App\Enums\ResultStatus;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\ExamResult;
use App\Services\CertificateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Printable student documents for the owning institute and HQ (policy-checked).
 */
class DocumentController extends Controller
{
    public function certificate(Certificate $certificate, CertificateService $certificates): Response
    {
        Gate::forUser(auth()->user())->authorize('view', $certificate);

        return Pdf::loadView('pdf.certificate', $certificates->viewData($certificate))
            ->setPaper('a4', 'landscape')
            ->stream(str_replace('/', '-', $certificate->certificate_no).'.pdf');
    }

    public function idCard(Enrollment $enrollment): Response
    {
        return $this->studentCard($enrollment, 'pdf.id-card', [EnrollmentStatus::Active, EnrollmentStatus::Completed], [0, 0, 243, 386], 'id-card');
    }

    public function admitCard(Enrollment $enrollment): Response
    {
        return $this->studentCard($enrollment, 'pdf.admit-card', [EnrollmentStatus::Active], 'a4', 'admit-card');
    }

    /** @param  list<EnrollmentStatus>  $allowed */
    private function studentCard(Enrollment $enrollment, string $view, array $allowed, string|array $paper, string $name): Response
    {
        Gate::forUser(auth()->user())->authorize('view', $enrollment);
        abort_unless(in_array($enrollment->status, $allowed, true), 404);

        $enrollment->load(['student.guardians', 'course', 'institute', 'academicSession']);
        $photo = $enrollment->student->photo_path && Storage::disk('local')->exists($enrollment->student->photo_path)
            ? 'data:'.Storage::disk('local')->mimeType($enrollment->student->photo_path).';base64,'.base64_encode(Storage::disk('local')->get($enrollment->student->photo_path))
            : null;

        return Pdf::loadView($view, ['enrollment' => $enrollment, 'photo' => $photo])
            ->setPaper($paper)
            ->stream($name.'-'.$enrollment->student->registration_no.'.pdf');
    }

    public function marksheet(ExamResult $result): Response
    {
        Gate::forUser(auth()->user())->authorize('view', $result);
        abort_unless($result->status === ResultStatus::Published, 404);

        $result->load(['enrollment.student.guardians', 'enrollment.course', 'enrollment.institute', 'marks.courseSubject.subject']);

        return Pdf::loadView('pdf.marksheet', ['result' => $result])
            ->setPaper('a4')
            ->stream('marksheet-'.str_replace('/', '-', (string) $result->enrollment->enrollment_no).'.pdf');
    }
}
