# Demo install, "Login as" student, and direct admission — Design

**Date:** 2026-10-02 · **Builds on:** docs/superpowers/specs/2026-09-28-kranti-franchise-platform-design.md (Phases 1–7)

## 1. Intent

The owner wants a fresh install to be usable immediately: sensible default HQ credentials, a complete demo institute and student, and enough courses and website content that every page of the site and all three panels shows real-looking data. HQ should reach any institute or student in one click, and both HQ and institutes should admit a student from a single form.

Decisions made with the user:
- Blank installer answers default to **Kranti Admin / admin@example.com / admin123**.
- Demo data loads automatically when `APP_ENV=local`. On other environments it loads only with `--demo`, which prints a warning. `--no-demo` always skips it.
- "Auto fill" means the **login forms are prefilled with the demo credentials, on local only**.
- Login as: Admin → Institute (exists), **Admin → Student**, **Institute owner → own student**.
- Admin direct admission is **active immediately**, and the franchise wallet is debited the normal HQ fee; the main institute is wallet-exempt.
- Institute direct admission uses one form (student + course) and is **submitted to HQ at once**. It stays pending HQ approval (the HQ-central model). The wallet is debited on submit, as today.

## 2. Demo accounts and data (`DemoSeeder`, idempotent)

| What | Details |
|---|---|
| HQ admin | created by the installer (defaults above), super_admin |
| Demo institute | "Demo Computer Centre", active franchise, owner **institue@example.com / admin123**, plus a teacher and a receptionist (same password), wallet credited ₹10,000 |
| Demo student | **student@example.com / admin123**, `must_change_password = false`, in the demo institute. One **completed** enrollment (fees paid, attendance, published result, certificate) and one **active** enrollment (instalment plan, part-paid receipt). |
| Main institute students | 3 students with active enrollments (website admissions) |
| Academic | the current session (from AcademicDefaultsSeeder), 4 categories, 9 courses with subjects and marks (DCA, ADCA, PGDCA, CCC, O Level, Tally Prime, Web Design, Python, Data Entry), EN + HI names, featured flags, one batch per demo institute |
| Website | site settings (contact, address, announcement + coupon, socials, stats, about), 3 sliders, 5 notices (3 in the ticker), gallery (images copied from `public/images/site` to the public disk), 3 faculty, 4 testimonials, 2 downloads |
| Workflows | 2 enquiries (contact + admission), 1 new franchise application, 1 pending wallet recharge |

**Idempotency:** everything is keyed by stable values (emails, course codes, slugs, slider titles). A second run adds nothing, and it never overwrites data the admin has changed.

**Business records** (enrollments, receipts, results, certificates) are created **through the services**, so wallet debits, numbering and the audit trail stay consistent.

## 3. Installer

`kranti:install` gets these options: `--demo`, `--no-demo`, and `--defaults` (take default credentials without prompting).

- In an interactive run, each prompt shows its default.
- In a non-interactive run, missing values fall back to the defaults.
- After the demo data loads, the installer prints a table of the demo logins.

## 4. Login-form prefill

Admin, institute and student login pages call `DemoLogin::credentials(panel)`. It returns the demo email and password only when `app()->isLocal()` and `config('kcti.demo.prefill')` is true (the default). The page fills its form with those values in `mount()`. Production is never prefilled.

## 5. Login as student

- **Service:** `StudentImpersonationService::start(Admin|InstituteUser $by, Student $student)` and `stop()`, plus the session keys `impersonator_type` and `impersonator_id`. It mirrors the institute impersonation.
- **Who may start it:**
  - An admin needs `impersonate_student` (new admin permission; manager role gets it).
  - An institute user needs `impersonate_student` (new institute permission; owner role gets it) and the same `institute_id` as the student.
  - In both cases the student must satisfy `portalOpen()`.
- **Routes:** start is a signed GET that expires in 5 minutes. Leave returns the impersonator to their own panel.
- **Banner and password:** a render hook in the student panel shows the "Viewing as … — Leave" banner. `EnsureStudentPasswordChanged` and `studentAccountOk` skip the must-change rule while impersonating.
- **Audit:** start and stop are activity-logged.

## 6. Direct admission

- **Service:** `EnrollmentService::admitDirectly(array $student, array $personal, array $guardians, Course, ?Batch, AcademicSession, fees, Admin|InstituteUser $by, ?Institute $institute)`. It runs in one transaction:
  1. `StudentService::create`
  2. `createDraft`
  3. `submit`
  4. `approve`, only when `$by` is an Admin
- **Result:**
  - An admin gets an Active enrollment. The student gets the default password and the must-change flag, as in normal approval.
  - An institute user gets a PendingApproval enrollment.
  - If the wallet is short, the whole transaction rolls back with a clear message.
- **UI:** a "New admission" page in both panels. The admin version adds an Institute select. The form fields are student essentials (name, phone, email, DOB, gender, primary guardian) plus course, session, batch (optional), course fee and discount.

## 7. Testing

- **DemoSeeder:** runs twice without duplicates; the three demo logins work; key pages render with the demo data.
- **Installer:** defaults are used when values are blank; `--no-demo` loads no demo data; non-local runs refuse demo data without `--demo`.
- **Prefill:** local only.
- **Login as student:** permission and tenant checks (another institute's student → 403), the banner appears, leave works, the must-change rule is skipped only while impersonating.
- **Admit directly:** admin → Active with the wallet debited; institute → Pending; a short wallet rolls everything back.
