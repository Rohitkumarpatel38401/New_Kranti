<?php

namespace App\Filament\Student\Resources\MyEnrollments\Pages;

use App\Enums\EnrollmentStatus;
use App\Enums\ResultStatus;
use App\Filament\Student\Resources\MyEnrollments\MyEnrollmentResource;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\ExamResult;
use App\Models\FeePayment;
use App\Services\AttendanceService;
use App\Services\FeeService;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * One enrollment for the student: fees and instalments, receipts, attendance %, the published result,
 * and the documents they may download (same rules as the policies and DocumentController).
 */
class ViewMyEnrollment extends Page
{
    use InteractsWithRecord;

    protected static string $resource = MyEnrollmentResource::class;

    protected string $view = 'filament.student.pages.view-my-enrollment';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->course?->localized('name') ?? $this->record->enrollment_no;
    }

    protected function getViewData(): array
    {
        /** @var Enrollment $enrollment */
        $enrollment = $this->record;
        $fees = app(FeeService::class);

        $result = ExamResult::query()->where('enrollment_id', $enrollment->id)
            ->where('status', ResultStatus::Published)->with('marks.courseSubject.subject')->first();
        $certificate = Certificate::query()->where('enrollment_id', $enrollment->id)->first();

        return [
            'enrollment' => $enrollment,
            'summary' => $fees->summary($enrollment),
            'plan' => $fees->plan($enrollment),
            'payments' => FeePayment::query()->where('enrollment_id', $enrollment->id)->orderByDesc('paid_on')->orderByDesc('id')->get(),
            'attendance' => app(AttendanceService::class)->percentage($enrollment),
            'result' => $result,
            'certificate' => $certificate?->isValid() ? $certificate : null,
            'canIdCard' => in_array($enrollment->status, [EnrollmentStatus::Active, EnrollmentStatus::Completed], true),
            'canAdmitCard' => $enrollment->status === EnrollmentStatus::Active,
        ];
    }

    public static function money(string $amount): string
    {
        return '₹'.number_format((float) $amount, 2);
    }
}
