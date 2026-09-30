# Phase 7: Student Portal & Dashboards Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Students log in to their own read-only portal (profile, enrollments, fees and receipts, attendance %, results, document downloads) with a forced first-login password change. HQ and institute dashboards show the stats, approval queues and charts from spec §7.

**Architecture:** Student access is policy-driven. Every existing policy the documents and receipts use gets a `Student` branch: a student may view only rows where `student_id` is their own, and documents obey the same status rules as for staff. The student panel is read-only. It has one resource per list (enrollments, payments) whose queries are scoped to `auth('student')->id()`, plus a profile page. A middleware in the student panel sends a student with `must_change_password` to a Change-password page until the password is changed. Dashboards are Filament widgets reading aggregate queries; the charts use Filament `ChartWidget`.

**Tech Stack:** Laravel 13, Filament 5.9 (widgets, ChartWidget, infolists), Pest 4.

**Spec:** docs/superpowers/specs/2026-09-28-kranti-franchise-platform-design.md (§5.3 Student guard, §6.9 Student portal, §7 Admin panel experience, §8.4 panels)

## Global Constraints

- Student guard has no roles; "Access is limited by policies to the student's own records" (§5.3).
- "Login by `registration_no` or email + password. First login forces a password change." (§6.9)
- Portal pages: profile (read-only), enrollments, fee dues and receipts, attendance percentage per enrollment, results, downloads (certificate, marksheet, ID card, admit card) (§6.9).
- ID card: active or completed enrollment; admit card: active only; marksheet: published result; certificate: issued, not revoked. These are the same rules as for staff (Phase 5).
- HQ dashboard: approval queues (pending enrollments, pending recharges, submitted results, new franchise applications, new enquiries); stats (institutes by status, active students, enrollments this month, wallet credits this month, results pending); chart of monthly enrollments by source (§7).
- Institute dashboard: students, active enrollments, wallet balance (low-balance warning), today's attendance, fees collected this month, dues (§7).
- Every widget respects permissions: a stat is only shown to an admin who can view that model.
- Money is shown from string/decimal values and never through float arithmetic.

## Review Focus

1. **Student A opens student B's receipt/marksheet/ID-card URL by changing the id** → 403.
2. **Student with `must_change_password` deep-links to any student page or Livewire endpoint** → redirected to Change password; the new password cannot equal the old one or the default.
3. **Revoked certificate, unpublished/returned result, dropped enrollment** → no download link and a direct URL is refused.
4. **Deactivated student (`can_login` false) or a student whose institute is suspended, still holding a session** → logged out or refused on the next request.
5. **Dashboards with zero data and for limited-permission admins** → no errors, no division by zero, and no stat or chart for models they cannot view.

---

### Task 1: Student access rules and forced password change

**Files:**
- Modify: `app/Policies/EnrollmentPolicy.php`, `FeePaymentPolicy.php`, `ExamResultPolicy.php`, `CertificatePolicy.php` (add a `Student` branch to `view`)
- Modify: `routes/web.php` (receipts and documents also accept the `student` guard: `auth:admin,institute,student`)
- Create: `app/Http/Middleware/EnsureStudentPasswordChanged.php`, `app/Filament/Student/Pages/ChangePassword.php` + view, `app/Services/StudentAccountService.php` (`changePassword(Student, string $current, string $new)`)
- Modify: `app/Providers/Filament/StudentPanelProvider.php` (register the middleware as persistent auth middleware)
- Test: `tests/Feature/Phase7/StudentAccessTest.php`

**Interfaces:**
- Produces: `StudentAccountService::changePassword(Student $s, string $current, string $new): void` (throws DomainRuleException on a wrong current password, or a new password that equals the current one or is shorter than 8 characters; clears `must_change_password`)
- Produces: the policy rule "student sees own": `$user instanceof Student && $model->student_id === $user->id` (+ status rules for documents)

- [ ] Tests first: a student downloads their own receipt, published marksheet, issued certificate and ID card (200); another student's (403); a revoked certificate (403), an unpublished result (404/403), an admit card for a completed enrollment (404); `must_change_password` redirects every student panel URL to Change password; the change works and clears the flag; wrong current password or same password is refused.
- [ ] Run → RED. Implement. Run → GREEN. Full suite. Commit `feat: student access to own documents and forced password change`.

### Task 2: Student portal pages

**Files:**
- Create: `app/Filament/Student/Resources/MyEnrollments/MyEnrollmentResource.php` (+ `Pages/ListMyEnrollments`, `Pages/ViewMyEnrollment`): query `Enrollment::withoutGlobalScopes()->where('student_id', auth('student')->id())`. The list shows course, institute, session, status and admission date. The view infolist has sections for: fee summary (from `FeeService::summary`) and instalments (`FeeService::plan`); attendance % (`AttendanceService::percentage`); the result, published only (marks, total, percentage, grade, outcome); and download actions for the documents the rules allow.
- Create: `app/Filament/Student/Resources/MyPayments/MyPaymentResource.php` (+ `Pages/ListMyPayments`): receipts with number, date, amount, mode, and status (cancelled shown as struck). A receipt link opens the PDF.
- Create: `app/Filament/Student/Pages/MyProfile.php` + view: read-only registration no., name, photo, DOB, guardians (name/relation only), address, institute.
- Create: `app/Filament/Student/Widgets/StudentOverviewWidget.php`: active courses, total due, attendance % of the latest active enrollment.
- Modify: `lang/{en,hi}/portal.php` for the labels.
- Test: `tests/Feature/Phase7/StudentPortalTest.php`

- [ ] Tests first:
  - A student sees only their own enrollments and payments, including across institutes. Global scopes are off, but the `student_id` filter holds.
  - The view page shows the fee summary, attendance % and the published result.
  - A draft or submitted result is not shown.
  - Download actions follow the rules.
  - Record URLs of other students give 404.
  - The profile shows no Aadhaar.
- [ ] RED → implement → GREEN → full suite → commit `feat: read-only student portal`.

### Task 3: HQ dashboard

**Files:**
- Modify: `app/Filament/Admin/Widgets/PendingApprovalsWidget.php`: add "Results to review" (submitted results) and "New enquiries".
- Create: `app/Filament/Admin/Widgets/HqStatsWidget.php`: institutes by status (active / pending / suspended); active students; enrollments this month; wallet credits this month (sum of recharge credits, as a string); results pending publication.
- Create: `app/Filament/Admin/Widgets/EnrollmentsBySourceChart.php`: a `ChartWidget` of monthly enrollments for the last 12 months, one line each for main institute and franchise. Months with no enrollments show 0. Grouping is done in PHP so it works on SQLite and MariaDB alike.
- Test: `tests/Feature/Phase7/HqDashboardTest.php`

- [ ] Tests first:
  - The numbers are correct for seeded data.
  - An admin without `ViewAny:Enrollment` sees neither the enrollment stats nor the chart (`canView()`).
  - An empty database renders the dashboard.
  - The chart returns 12 labels with zero-filled data.
- [ ] RED → implement → GREEN → full suite → commit `feat: HQ dashboard stats, queues and enrollment chart`.

### Task 4: Institute dashboard

**Files:**
- Modify: `app/Filament/Institute/Widgets/InstituteStatsWidget.php`: add "Students" (all students of the institute) and "Active enrollments" next to the existing wallet, fees, dues and attendance stats.
- Create: `app/Filament/Institute/Widgets/MonthlyAdmissionsChart.php`: this institute's enrollments over the last 6 months.
- Test: `tests/Feature/Phase7/InstituteDashboardTest.php`

- [ ] Tests first:
  - The stats are scoped to the current institute; another institute's data is never counted.
  - The chart is scoped the same way.
  - An empty institute renders the dashboard.
- [ ] RED → implement → GREEN → full suite → commit `feat: institute dashboard students, enrollments and admissions chart`.

### Task 5: Wrap-up

- README "Student portal" and "Dashboards" sections.
- Final: full suite, Pint, MariaDB smoke of `/student` login with a provisioned student, fresh-reviewer final review.
