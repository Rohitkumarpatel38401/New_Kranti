<?php

namespace Database\Seeders;

use App\Enums\AdmissionSource;
use App\Enums\BatchStatus;
use App\Enums\EnquiryType;
use App\Enums\WalletSource;
use App\Models\AcademicSession;
use App\Models\Admin;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Enrollment;
use App\Models\FranchiseApplication;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Models\WalletRechargeRequest;
use App\Services\AttendanceService;
use App\Services\CertificateService;
use App\Services\EnquiryService;
use App\Services\EnrollmentService;
use App\Services\FeeService;
use App\Services\FranchiseOnboardingService;
use App\Services\InstituteService;
use App\Services\ResultService;
use App\Services\StudentService;
use App\Services\WalletRechargeService;
use App\Services\WalletService;
use App\Support\Permissions\AdminPermissions;
use Illuminate\Database\Seeder;

/**
 * A complete demo: HQ admin, a franchise with owner/teacher/receptionist, a demo student with a completed
 * (certified) and an active course, main-institute students, and website + workflow data.
 *
 * Logins (password admin123): admin@example.com, institue@example.com, student@example.com.
 * Business records go through the services (wallet, numbering, audit). Safe to run again: every block is
 * keyed by a stable email/code and skipped when it already exists.
 */
class DemoSeeder extends Seeder
{
    private Admin $admin;

    private Institute $main;

    private AcademicSession $session;

    public function run(): void
    {
        $this->call([RolesAndPermissionsSeeder::class, AcademicDefaultsSeeder::class, DemoCatalogSeeder::class, DemoWebsiteSeeder::class]);

        $this->main = app(InstituteService::class)->ensureMainInstitute();
        $this->admin = $this->hqAdmin();
        $this->session = AcademicSession::current() ?? AcademicSession::query()->latest('starts_on')->firstOrFail();

        [$institute, $owner, $teacher] = $this->demoInstitute();
        $this->demoStudent($institute, $owner, $teacher);
        $this->mainInstituteStudents();
        $this->workflows($owner);
    }

    private function password(): string
    {
        return config('kcti.demo.password');
    }

    private function hqAdmin(): Admin
    {
        $admin = Admin::role(AdminPermissions::SUPER_ADMIN)->where('is_active', true)->first();

        if ($admin) {
            return $admin;
        }

        $admin = Admin::query()->firstOrCreate(
            ['email' => config('kcti.demo.admin.email')],
            ['name' => config('kcti.demo.admin.name'), 'password' => $this->password(), 'is_active' => true],
        );

        return $admin->assignRole(AdminPermissions::SUPER_ADMIN);
    }

    /** @return array{Institute, InstituteUser, InstituteUser} */
    private function demoInstitute(): array
    {
        $owner = InstituteUser::withTrashed()->where('email', config('kcti.demo.institute.email'))->first();

        if (! $owner) {
            $institute = app(InstituteService::class)->create(
                ['name' => config('kcti.demo.institute.name'), 'email' => config('kcti.demo.institute.email'), 'phone' => '9811100001',
                    'established_on' => now()->subYears(3)->toDateString(), 'franchise_start_on' => now()->subYear()->toDateString(),
                    'franchise_expiry_on' => now()->addYears(2)->toDateString()],
                ['owner_name' => 'Ramesh Gupta', 'owner_phone' => '9811100001', 'owner_email' => config('kcti.demo.institute.email'),
                    'computers_count' => 20, 'classrooms_count' => 3, 'area_sqft' => 800, 'opening_time' => '08:00', 'closing_time' => '19:00'],
                ['line_1' => 'Station Road', 'city' => 'Handia', 'district' => 'Prayagraj', 'state' => 'Uttar Pradesh', 'pincode' => '221503'],
                $this->admin,
            );

            $owner = InstituteUser::create([
                'institute_id' => $institute->id, 'name' => 'Ramesh Gupta', 'email' => config('kcti.demo.institute.email'),
                'phone' => '9811100001', 'password' => $this->password(), 'is_owner' => true, 'is_active' => true,
            ]);
            $owner->assignRole('owner');

            $institute->courses()->syncWithoutDetaching(
                Course::query()->where('is_active', true)->pluck('id')->mapWithKeys(fn ($id) => [$id => ['is_active' => true]])->all()
            );
            app(WalletService::class)->credit($institute, '10000.00', WalletSource::Adjustment, null, 'Demo opening balance', $this->admin);
        }

        $institute = $owner->institute;

        $staff = [];

        foreach (['teacher' => ['Sunita Sharma', 'teacher.demo@example.com'], 'receptionist' => ['Amit Kumar', 'reception.demo@example.com']] as $role => [$name, $email]) {
            $user = InstituteUser::withTrashed()->where('email', $email)->first();

            if (! $user) {
                $user = InstituteUser::create([
                    'institute_id' => $institute->id, 'name' => $name, 'email' => $email,
                    'phone' => '98111000'.($role === 'teacher' ? '02' : '03'), 'password' => $this->password(), 'is_active' => true,
                ]);
                $user->assignRole($role);
            }

            $staff[$role] = $user;
        }

        return [$institute, $owner, $staff['teacher']];
    }

    private function batch(Institute $institute, string $courseCode, string $name, string $code, ?InstituteUser $teacher): Batch
    {
        $course = Course::query()->where('code', $courseCode)->firstOrFail();

        return Batch::query()->withoutGlobalScopes()->firstOrCreate(
            ['institute_id' => $institute->id, 'code' => $code],
            ['course_id' => $course->id, 'academic_session_id' => $this->session->id, 'name' => $name,
                'start_time' => '09:00', 'end_time' => '11:00', 'days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'],
                'capacity' => 30, 'starts_on' => now()->subMonths(5)->toDateString(), 'teacher_user_id' => $teacher?->id,
                'status' => BatchStatus::Running],
        );
    }

    private function demoStudent(Institute $institute, InstituteUser $owner, InstituteUser $teacher): void
    {
        if (Student::withoutGlobalScopes()->where('email', config('kcti.demo.student.email'))->exists()) {
            return;
        }

        $cccBatch = $this->batch($institute, 'CCC', 'CCC Morning', 'CCC-M', $teacher);
        $dcaBatch = $this->batch($institute, 'DCA', 'DCA Evening', 'DCA-E', $teacher);

        $student = app(StudentService::class)->create(
            ['first_name' => 'Rahul', 'last_name' => 'Verma', 'email' => config('kcti.demo.student.email'), 'phone' => '9876500001',
                'joined_on' => now()->subMonths(5)->toDateString()],
            ['dob' => '2004-05-15', 'gender' => 'male', 'category' => 'gen', 'nationality' => 'Indian'],
            [['relation' => 'father', 'name' => 'Suresh Verma', 'phone' => '9876500002', 'occupation' => 'Farmer', 'is_primary' => true],
                ['relation' => 'mother', 'name' => 'Sunita Verma', 'is_primary' => false]],
            ['line_1' => 'Village Barauna', 'city' => 'Handia', 'district' => 'Prayagraj', 'state' => 'Uttar Pradesh', 'pincode' => '221503'],
            [],
            $owner,
        );

        // 1) Completed, certified course: CCC.
        $ccc = $this->enrol($student, 'CCC', $cccBatch, '3000.00', '0.00', $owner, now()->subMonths(5)->toDateString());
        app(FeeService::class)->collect($ccc, '3000.00', 'upi', 'UPI-DEMO-001', now()->subMonths(4)->toDateString(), $owner);
        $this->attendance($cccBatch, $ccc, $owner, 12);
        $this->publishResult($ccc, $owner, ['theory' => 58, 'practical' => 27]);
        app(CertificateService::class)->issue($ccc->fresh(), $this->admin);

        // 2) Active course with an instalment plan: DCA.
        $dca = $this->enrol($student, 'DCA', $dcaBatch, '9000.00', '1000.00', $owner, now()->subMonth()->toDateString());
        $start = now()->subMonth();
        app(FeeService::class)->setPlan($dca, [
            ['due_on' => $start->toDateString(), 'amount' => '2700.00'],
            ['due_on' => $start->copy()->addMonth()->toDateString(), 'amount' => '2700.00'],
            ['due_on' => $start->copy()->addMonths(2)->toDateString(), 'amount' => '2600.00'],
        ], $owner);
        app(FeeService::class)->collect($dca, '2700.00', 'cash', null, $start->toDateString(), $owner);
        $this->attendance($dcaBatch, $dca, $owner, 8);

        // Portal ready with the demo password (approval set a date-of-birth password).
        $student->forceFill(['password' => $this->password(), 'must_change_password' => false, 'can_login' => true])->save();
    }

    private function mainInstituteStudents(): void
    {
        $people = [
            ['Anjali', 'Mishra', 'anjali.demo@example.com', '9876500011', 'female', 'TALLY'],
            ['Vikas', 'Yadav', 'vikas.demo@example.com', '9876500012', 'male', 'ADCA'],
            ['Pooja', 'Singh', 'pooja.demo@example.com', '9876500013', 'female', 'WEB'],
        ];

        foreach ($people as $i => [$first, $last, $email, $phone, $gender, $course]) {
            if (Student::withoutGlobalScopes()->where('email', $email)->exists()) {
                continue;
            }

            $student = app(StudentService::class)->create(
                ['first_name' => $first, 'last_name' => $last, 'email' => $email, 'phone' => $phone],
                ['dob' => (2003 + $i).'-0'.($i + 2).'-10', 'gender' => $gender],
                [['relation' => 'father', 'name' => "Mr. {$last}", 'phone' => '98765001'.(20 + $i), 'is_primary' => true]],
                ['line_1' => 'Civil Lines', 'city' => 'Prayagraj', 'district' => 'Prayagraj', 'state' => 'Uttar Pradesh', 'pincode' => '211001'],
                [],
                $this->admin,
                $this->main,
                AdmissionSource::Website,
            );

            $fee = (string) Course::query()->where('code', $course)->value('suggested_fee');
            $enrollment = $this->enrol($student, $course, null, $fee, '0.00', $this->admin, now()->subDays(20 + $i * 5)->toDateString());
            app(FeeService::class)->collect($enrollment, '1000.00', 'cash', null, now()->subDays(15)->toDateString(), $this->admin);
        }
    }

    private function workflows(InstituteUser $owner): void
    {
        if (Enquiry::query()->doesntExist()) {
            $enquiries = app(EnquiryService::class);
            $enquiries->submit(['type' => EnquiryType::Contact, 'name' => 'Neha Tiwari', 'phone' => '9876500031', 'email' => 'neha@example.com', 'message' => 'What are the batch timings for Tally?']);
            $enquiries->submit(['type' => EnquiryType::Admission, 'name' => 'Arjun Pandey', 'phone' => '9876500032',
                'course_id' => Course::query()->where('code', 'ADCA')->value('id'), 'message' => 'I want to join ADCA from next month.']);
        }

        if (FranchiseApplication::query()->doesntExist()) {
            app(FranchiseOnboardingService::class)->submit(
                ['applicant_name' => 'Manoj Kesarwani', 'email' => 'manoj.franchise@example.com', 'phone' => '9876500041',
                    'proposed_center_name' => 'Kesarwani Computer Centre', 'computers_count' => 12, 'area_sqft' => 500,
                    'experience_years' => 4, 'message' => 'I already run a small computer shop and want to start classes.'],
                ['line_1' => 'Main Market', 'city' => 'Phulpur', 'district' => 'Prayagraj', 'state' => 'Uttar Pradesh', 'pincode' => '212402'],
            );
        }

        if (WalletRechargeRequest::query()->withoutGlobalScopes()->where('institute_id', $owner->institute_id)->doesntExist()) {
            app(WalletRechargeService::class)->request(
                ['amount' => '5000.00', 'payment_mode' => 'upi', 'utr_reference' => 'DEMOUTR'.now()->format('ymd').'01', 'paid_on' => now()->toDateString()],
                $owner,
            );
        }
    }

    private function enrol(Student $student, string $courseCode, ?Batch $batch, string $fee, string $discount, InstituteUser|Admin $by, string $date): Enrollment
    {
        $enrollments = app(EnrollmentService::class);
        $course = Course::query()->where('code', $courseCode)->firstOrFail();

        $enrollment = $enrollments->createDraft($student, $course, $batch, $this->session, $fee, $discount, $by, $date);
        $enrollments->submit($enrollment, $by);
        $enrollments->approve($enrollment->fresh(), $this->admin);

        return $enrollment->fresh();
    }

    private function attendance(Batch $batch, Enrollment $enrollment, InstituteUser $by, int $days): void
    {
        $service = app(AttendanceService::class);
        $statuses = ['present', 'present', 'present', 'late', 'present', 'absent'];

        foreach (range($days, 1) as $i => $ago) {
            $service->save($batch, now()->subDays($ago)->startOfDay(), [$enrollment->id => $statuses[$i % count($statuses)]], $by);
        }
    }

    /** @param  array{theory: int, practical: int}  $marks  applied to every subject */
    private function publishResult(Enrollment $enrollment, InstituteUser $by, array $marks): void
    {
        $results = app(ResultService::class);
        $rows = $enrollment->course->courseSubjects()->pluck('id')->mapWithKeys(fn ($id) => [$id => $marks])->all();

        $result = $results->saveMarks($enrollment, $rows, now()->subMonth()->toDateString(), $by);
        $results->submit($result, $by);
        $results->publish($result, $this->admin);
    }
}
