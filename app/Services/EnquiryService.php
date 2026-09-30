<?php

namespace App\Services;

use App\Enums\AdmissionSource;
use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Exceptions\DomainRuleException;
use App\Models\Admin;
use App\Models\Enquiry;
use App\Models\Institute;
use App\Models\Student;
use App\Support\PanelNotifier;
use Illuminate\Support\Facades\DB;

/**
 * Website enquiries: filed against the main institute, handled by HQ, and optionally converted
 * into a student of the main institute (admission source: website).
 */
class EnquiryService
{
    public function __construct(private StudentService $students) {}

    /** @param  array{type: EnquiryType|string, name: string, phone: string, email?: ?string, course_id?: ?int, message?: ?string}  $data */
    public function submit(array $data): Enquiry
    {
        $main = Institute::mainInstitute() ?? throw new DomainRuleException('The main institute is not set up yet.');

        $enquiry = Enquiry::create([
            'institute_id' => $main->id,
            'type' => $data['type'] instanceof EnquiryType ? $data['type'] : EnquiryType::from($data['type']),
            'name' => trim($data['name']),
            'phone' => preg_replace('/\D+/', '', $data['phone']),
            'email' => filled($data['email'] ?? null) ? trim($data['email']) : null,
            'course_id' => $data['course_id'] ?? null,
            'message' => filled($data['message'] ?? null) ? trim($data['message']) : null,
            'status' => EnquiryStatus::New,
        ]);

        PanelNotifier::admins('manage_enquiries', "New {$enquiry->type->value} enquiry from {$enquiry->name}", $enquiry->phone, url('/admin/enquiries'), 'heroicon-o-inbox-arrow-down');

        return $enquiry;
    }

    public function markContacted(Enquiry $enquiry, Admin $by): void
    {
        $this->move($enquiry, [EnquiryStatus::New], EnquiryStatus::Contacted, $by);
    }

    public function close(Enquiry $enquiry, Admin $by): void
    {
        $this->move($enquiry, [EnquiryStatus::New, EnquiryStatus::Contacted], EnquiryStatus::Closed, $by);
    }

    /**
     * @param  array<int, array<string, mixed>>  $guardians
     */
    public function convertToStudent(Enquiry $enquiry, array $student, array $personal, array $guardians, Admin $by): Student
    {
        $this->authorize($by);

        return DB::transaction(function () use ($enquiry, $student, $personal, $guardians, $by) {
            $locked = $this->lockOpen($enquiry, EnquiryStatus::Converted);

            $record = $this->students->create($student, $personal, $guardians, [], [], $by, $locked->institute, AdmissionSource::Website);

            $locked->update(['status' => EnquiryStatus::Converted, 'converted_student_id' => $record->id, 'handled_by_admin_id' => $by->id]);
            activity()->causedBy($by)->performedOn($locked)->event('enquiry.converted')->log("Enquiry converted to student {$record->registration_no}");

            $enquiry->refresh();

            return $record;
        });
    }

    /** @param  array<int, EnquiryStatus>  $from */
    private function move(Enquiry $enquiry, array $from, EnquiryStatus $to, Admin $by): void
    {
        $this->authorize($by);

        DB::transaction(function () use ($enquiry, $from, $to, $by) {
            $locked = Enquiry::query()->lockForUpdate()->findOrFail($enquiry->id);

            if (! in_array($locked->status, $from, true)) {
                throw new DomainRuleException("An enquiry that is {$locked->status->label()} cannot become {$to->label()}.");
            }

            $locked->update(['status' => $to, 'handled_by_admin_id' => $by->id]);
        });

        $enquiry->refresh();
    }

    private function lockOpen(Enquiry $enquiry, EnquiryStatus $to): Enquiry
    {
        $locked = Enquiry::query()->lockForUpdate()->findOrFail($enquiry->id);

        if (! $locked->isOpen()) {
            throw new DomainRuleException("An enquiry that is {$locked->status->label()} cannot become {$to->label()}.");
        }

        return $locked;
    }

    private function authorize(Admin $by): void
    {
        if (! $by->can('manage_enquiries')) {
            throw new DomainRuleException('You are not allowed to handle enquiries.');
        }
    }
}
