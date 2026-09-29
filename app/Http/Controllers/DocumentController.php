<?php

namespace App\Http\Controllers;

use App\Enums\ResultStatus;
use App\Models\Certificate;
use App\Models\ExamResult;
use App\Services\CertificateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
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
