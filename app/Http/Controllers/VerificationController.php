<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Public certificate verification (no login). Shows only what a certificate already prints.
 */
class VerificationController extends Controller
{
    public function form(): View
    {
        return view('site.verification.form');
    }

    /** Find by certificate number, or by enrollment number + date of birth (both must match). */
    public function lookup(Request $request): View
    {
        $data = $request->validate([
            'certificate_no' => ['nullable', 'string', 'max:40', 'required_without:enrollment_no'],
            'enrollment_no' => ['nullable', 'string', 'max:40', 'required_without:certificate_no'],
            'dob' => ['nullable', 'date', 'required_with:enrollment_no'],
        ]);

        $query = Certificate::query();

        if (filled($data['certificate_no'] ?? null)) {
            $query->where('certificate_no', Str::upper(trim($data['certificate_no'])));
        } else {
            $query->whereHas('enrollment', fn ($e) => $e->where('enrollment_no', Str::upper(trim($data['enrollment_no'])))
                ->whereHas('student.personal', fn ($p) => $p->whereDate('dob', $data['dob'])));
        }

        $certificate = $query->first();

        return view('site.verification.result', ['certificate' => $certificate, 'data' => $certificate ? $this->publicData($certificate) : null]);
    }

    public function show(string $token): View
    {
        $certificate = Certificate::query()->where('verification_token', $token)->first();

        return view('site.verification.result', ['certificate' => $certificate, 'data' => $certificate ? $this->publicData($certificate) : null]);
    }

    /** @return array<string, ?string> */
    protected function publicData(Certificate $certificate): array
    {
        $enrollment = $certificate->enrollment()->with(['student.guardians', 'course', 'institute', 'result'])->first();

        // Spec §6.7: name, father's name, course, grade, issue date, institute, status. Nothing else.
        return [
            'valid' => $certificate->isValid() ? 'yes' : 'no',
            'certificate_no' => $certificate->certificate_no,
            'issued_on' => $certificate->issued_on->format('d M Y'),
            'student' => $enrollment->student->full_name,
            'father' => $enrollment->student->fatherName(),
            'course' => $enrollment->course->code.' — '.$enrollment->course->localized('name', 'en'),
            'grade' => $enrollment->result?->grade,
            'institute' => $enrollment->institute->name.' ('.$enrollment->institute->code.')',
        ];
    }
}
