<?php

namespace App\Filament\Institute\Resources\Enrollments\Pages;

use App\Exceptions\DomainRuleException;
use App\Filament\Institute\Resources\Enrollments\EnrollmentResource;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Student;
use App\Services\EnrollmentService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateEnrollment extends CreateRecord
{
    protected static string $resource = EnrollmentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(EnrollmentService::class)->createDraft(
                Student::findOrFail($data['student_id']),
                Course::findOrFail($data['course_id']),
                filled($data['batch_id'] ?? null) ? Batch::findOrFail($data['batch_id']) : null,
                AcademicSession::findOrFail($data['academic_session_id']),
                number_format((float) $data['course_fee'], 2, '.', ''),
                number_format((float) ($data['discount'] ?? 0), 2, '.', ''),
                auth('institute')->user(),
                $data['admission_date'] ?? null,
            );
        } catch (DomainRuleException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
