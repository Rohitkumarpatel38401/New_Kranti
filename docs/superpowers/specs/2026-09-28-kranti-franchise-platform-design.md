# Kranti Franchise Platform — Design Spec

- **Date:** 2026-09-28
- **Status:** Approved in brainstorming, pending written-spec review
- **Project directory:** `D:\Laravel\New_Kranti` (new project)
- **UI reference:** `D:\Laravel\Kranti` (existing prototype — website UI, colors, and page set are reused as-is)

---

## 1. Purpose and intent

Kranti Computer Training Institute (**KCTI**) is an IT training institute that grants franchises to smaller institutes. The platform must:

1. Run Kranti's public website (same UI and colors as the reference project), bilingual (English/Hindi) with light/dark themes.
2. Give Kranti HQ (**Admin**) full control over all data, with institute-wise filtering everywhere.
3. Give each franchise (**Institute**) its own panel to manage its students and study-related records, isolated from other institutes.
4. Give every **Student** a portal to see their own records and download documents.
5. Keep all students under Kranti: student → institute → admin.

### Decisions made during brainstorming

| Topic | Decision |
|---|---|
| Business model | **HQ-central.** HQ defines courses; franchises enroll students only in authorized courses; HQ approves enrollments, publishes results, and issues certificates. |
| Franchise payment to HQ | **Prepaid wallet.** Per-enrollment HQ fee is debited on submission; refunded on rejection. Recharge is **manual with proof** (UTR + screenshot), approved by admin. No gateway in v1. |
| Hierarchy | **2-level:** Kranti HQ → franchise institutes → students. No sub-franchises. |
| Main institute | One default institute with `is_main = true` represents Kranti's own center. Website admissions and walk-ins go there. Wallet-exempt. |
| Tenancy | **Single database, row-level `institute_id`**, enforced by a `BelongsToInstitute` trait (global scope + auto-fill). Not Filament URL tenancy, not database-per-tenant. |
| Authentication | **Three separate tables and guards:** `admins`, `institute_users`, `students`. |
| Roles | HQ staff have roles/permissions (admin guard). Institute owners manage their own staff roles (institute guard). HQ staff see all institutes (no regional scoping in v1). |
| Impersonation | Admin can log in as an institute from the institute row (custom, cross-guard, audited). |
| Website content | Dynamic from admin CMS; translatable fields stored as JSON (en/hi). |
| V1 modules | Core (institutes, academic, students, enrollment, wallet, fees, CMS), Exams & Certificates, Attendance, Student portal. |

---

## 1.1 Branding

- Full name: **Kranti Computer Training Institute**; short name **KCTI**. Used in `APP_NAME`, website title/logo text, panel brand name, PDFs (certificates, marksheets, receipts, ID cards), and emails.
- All generated codes use the `KCTI` prefix: main institute `KCTI0001`, franchises `KCTI0002`…, registration `KCTI0007-26-0045`, enrollment `KCTI/2026/000123`, certificate `KCTI/C/2026/000123`.

---

## 2. Stack

| Concern | Choice |
|---|---|
| Framework | Laravel 13.x |
| Admin UI | Filament 5.x (three panels) |
| PHP | 8.3 |
| Database | MySQL 8 (production/dev); SQLite in-memory for tests |
| Roles/permissions | `spatie/laravel-permission` + `bezhansalleh/filament-shield` (multi-guard) |
| Audit | `spatie/laravel-activitylog` |
| Translations (DB) | `spatie/laravel-translatable` (+ Filament translatable field UI) |
| Export | `pxlrbt/filament-excel` |
| PDFs | `barryvdh/laravel-dompdf` |
| QR codes | `simplesoftwareio/simple-qrcode` |
| Charts | `leandrocfe/filament-apex-charts` |
| Tests | Pest |

Package versions are pinned at implementation time to the latest stable releases compatible with Filament 5 (Shield 4.3+ and the other plugins declare `^4.0|^5.0`).

---

## 3. Architecture

### 3.1 Panels and guards

| Panel | Path | Guard | Auth table | Data scope |
|---|---|---|---|---|
| Admin | `/admin` | `admin` | `admins` | All institutes; every tenant resource has an **Institute** filter |
| Institute | `/institute` | `institute` | `institute_users` | Only the logged-in user's institute (global scope) |
| Student | `/student` | `student` | `students` | Only the logged-in student's own records |

Each guard has its own password broker. Panel access is denied when:

- the account is inactive (`is_active = false` / `can_login = false`);
- for institute users, the institute status is not `active`;
- for students, the institute is suspended or the student has no approved enrollment.

### 3.2 Tenancy

- `App\Support\Tenancy\CurrentInstitute` resolves the active institute id:
  - `institute` guard authenticated → that user's `institute_id`;
  - `student` guard authenticated → that student's `institute_id`;
  - otherwise (admin, console, public website) → none.
- `App\Models\Concerns\BelongsToInstitute` trait:
  - adds a global scope `where institute_id = current` **only when** `CurrentInstitute` has a value;
  - fills `institute_id` on `creating` from `CurrentInstitute` when not already set;
  - rejects saving a model whose `institute_id` differs from the current institute (defense in depth);
  - defines `institute()` relation.
- Admin panel queries run without a current institute, so they see all rows. Admin forms explicitly pick an institute where needed.
- Queued jobs that act on tenant data receive the institute id explicitly and never rely on the ambient context.
- Student-portal resources additionally scope by `student_id = auth('student')->id()`.

### 3.3 Main institute

- Exactly one institute with `is_main = true`, code `KCTI0001`, created by the installer.
- Enforced by a unique nullable generated column `main_marker` (`1` when `is_main`, otherwise `NULL`).
- Cannot be deleted or suspended; `wallet_exempt = true`.
- Receives website admission enquiries and walk-in admissions.
- Can be operated from the admin panel directly or through its own `institute_users`.
- Admin student lists show a source badge: **Main** or **Franchise: <name>**.

### 3.4 Code layout

```text
app/
├── Enums/                      Status/type enums (backed string enums, with labels in en/hi)
├── Filament/
│   ├── Admin/                  Resources, pages, widgets for /admin
│   ├── Institute/              Resources, pages, widgets for /institute
│   ├── Student/                Resources, pages for /student
│   └── Shared/                 Shared form schemas, table columns, login page, impersonation banner
├── Http/
│   ├── Controllers/Site/       Public website controllers
│   ├── Controllers/            ImpersonationController, VerificationController
│   └── Middleware/             SetLocale, EnsureActiveInstitute, ForcePasswordChange
├── Models/
│   └── Concerns/               BelongsToInstitute, HasAddresses, HasStatusHistory
├── Policies/                   One per model, guard-aware
├── Providers/Filament/         AdminPanelProvider, InstitutePanelProvider, StudentPanelProvider
├── Services/                   WalletService, EnrollmentService, ResultService, CertificateService,
│                               FranchiseOnboardingService, ImpersonationService, NumberSequenceService,
│                               FeeService, AttendanceService
└── Support/Tenancy/            CurrentInstitute
resources/views/site/           Website layout, partials, pages (ported from reference main_site)
resources/views/pdf/            Receipt, certificate, marksheet, ID card, admit card templates
lang/en, lang/hi                UI strings
public/css/site, public/js/site Ported website CSS/JS (+ dark theme variables)
```

Business rules live in services, not in Filament resources. Resources call services; services own transactions and status transitions.

---

## 4. Database

### 4.1 Conventions

- Primary keys: `bigint unsigned` auto-increment. Public-facing tokens use ULIDs.
- Money: `decimal(12,2)`. Currency is INR (single currency in v1).
- Calendar dates: `date`. Events: `timestamp`. App timezone `Asia/Kolkata`.
- **Tenant tables** carry non-null `institute_id` (FK → `institutes`, restrict on delete) and use `BelongsToInstitute`. Composite indexes begin with `institute_id`.
- Business numbers come from `number_sequences` using `SELECT … FOR UPDATE` inside a transaction.
- Soft deletes on master data only. Financial, academic-history, and status-history rows are never deleted: they are cancelled, reversed, or revoked. FKs to them use `restrictOnDelete`.
- Translatable columns are `json` (`{"en": "...", "hi": "..."}`); fallback locale `en`.
- Actor columns name their table explicitly (`approved_by_admin_id` → `admins`, `received_by_user_id` → `institute_users`). Generic actor columns use a morph pair (`created_by_type`, `created_by_id`). Full change history is in `activity_log`.
- Files are stored on the `local` (private) disk unless public by nature (CMS images, course thumbnails → `public` disk). Rows store `disk`, `path`, `mime_type`, `size`.
- All enums below are PHP backed enums stored as `varchar`.

### 4.2 Identity

**`admins`** — HQ team
`id, name, email (unique), phone (nullable), password, avatar_path (nullable), is_active (default true), last_login_at, last_login_ip, remember_token, timestamps, deleted_at`

**`institute_users`** — franchise owners and staff (tenant)
`id, institute_id, name, email (unique, global), phone, password, is_owner (bool), is_active, last_login_at, last_login_ip, remember_token, timestamps, deleted_at`
Rule: at most one `is_owner = true` per institute (unique generated column `owner_marker` on `(institute_id, owner_marker)`).

**`students`** — see §4.5 (also an auth table).

**Spatie permission tables** (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`) with `guard_name` in `{admin, institute}`.

**Password reset tables:** one per broker (`admin_password_reset_tokens`, `institute_user_password_reset_tokens`, `student_password_reset_tokens`).

### 4.3 Institute / franchise

**`institutes`**
`id, code (unique, e.g. KCTI0001), name, slug (unique), is_main (bool), main_marker (generated, unique nullable), status (pending|active|suspended|expired|closed), email, phone, alt_phone, website, logo_path, registration_no (nullable unique), gst_no (nullable unique), pan_no (nullable), established_on, franchise_start_on, franchise_expiry_on, wallet_exempt (bool), approved_by_admin_id, approved_at, timestamps, deleted_at`
Indexes: `(status)`, `(franchise_expiry_on)`.

**`institute_profiles`** (1:1, unique `institute_id`)
`institute_id, owner_name, owner_phone, owner_email, owner_photo_path, owner_qualification, about (json), area_sqft, computers_count, classrooms_count, opening_time, closing_time, timestamps`

**`addresses`** (polymorphic, used by institutes, students, franchise applications)
`id, addressable_type, addressable_id, type (office|current|permanent), line_1, line_2, landmark, city, district, state, pincode, country_code (default IN), timestamps`
Unique `(addressable_type, addressable_id, type)`. Index `(state, district)`, `(pincode)`.

**`institute_documents`** (tenant)
`id, institute_id, type (owner_aadhaar|owner_pan|gst_certificate|rent_agreement|center_photo|other), number, disk, path, mime_type, size, verification_status (pending|verified|rejected), verified_by_admin_id, verified_at, remarks, timestamps, deleted_at`

**`institute_status_histories`** (immutable)
`id, institute_id, from_status, to_status, reason, changed_by_admin_id, changed_at`

**`institute_course`** — authorized courses per franchise
`id, institute_id, course_id, hq_fee_override (decimal nullable), is_active, timestamps` — unique `(institute_id, course_id)`.

**`franchise_applications`**
`id, application_no (unique), applicant_name, email, phone, proposed_center_name, computers_count, area_sqft, experience_years, message, status (new|contacted|approved|rejected), reviewed_by_admin_id, reviewed_at, review_remarks, converted_institute_id (nullable), timestamps` (+ address via `addresses`, type `office`). Index `(status, created_at)`.

### 4.4 Academic (HQ-owned, global — no `institute_id`)

**`academic_sessions`**: `id, name, code (unique), starts_on, ends_on, is_current, timestamps`. One current session enforced by a service.

**`course_categories`**: `id, name (json), slug (unique), sort_order, is_active, timestamps, deleted_at`

**`courses`**
`id, course_category_id, code (unique, e.g. DCA), name (json), slug (unique), short_description (json), description (json), duration_value, duration_unit (hours|weeks|months), total_hours, eligibility (json), hq_registration_fee (decimal), suggested_fee (decimal), thumbnail_path, is_featured, show_on_website, is_active, sort_order, timestamps, deleted_at`

**`subjects`**: `id, code (unique), name (json), is_active, timestamps, deleted_at`

**`course_subjects`**: `id, course_id, subject_id, term_no (default 1), sort_order, theory_max, practical_max, pass_marks, timestamps` — unique `(course_id, subject_id)`.

**`grade_scales`**: `id, min_percent, max_percent, grade, remark (json), sort_order, timestamps` — ranges must not overlap (validated in service).

**`batches`** (tenant)
`id, institute_id, course_id, academic_session_id, name, code, start_time, end_time, days (json array of weekdays), capacity, starts_on, ends_on, teacher_user_id (nullable → institute_users), status (upcoming|running|completed|cancelled), timestamps, deleted_at`
Unique `(institute_id, code)`. Index `(institute_id, course_id, status)`.

### 4.5 Student

**`students`** (tenant, authenticatable)
`id, institute_id, registration_no (unique global, format {institute_code}-{yy}-{seq4}, e.g. KCTI0007-26-0045), first_name, middle_name, last_name, email (nullable, unique when present), phone, password (nullable until enabled), can_login (bool), must_change_password (bool), photo_path, signature_path, status (active|completed|dropped|suspended), admission_source (website|walk_in|franchise), joined_on, created_by_user_id (nullable → institute_users), remember_token, last_login_at, timestamps, deleted_at`
Indexes: `(institute_id, status, joined_on)`, `(institute_id, last_name, first_name)`, `(phone)`.

**`student_personal_details`** (1:1, unique `student_id`)
`student_id, dob, gender (male|female|other), blood_group, category (gen|obc|sc|st|ews), religion, nationality (default Indian), mother_tongue, marital_status, aadhaar_encrypted (encrypted cast), aadhaar_hash (unique nullable, SHA-256 of normalized number), whatsapp_phone, alternate_phone, disability (nullable text), timestamps`

**`student_guardians`**
`id, student_id, relation (father|mother|guardian|spouse), name, phone, email, occupation, annual_income (decimal nullable), is_primary, timestamps` — unique `(student_id, relation)`. The father's name printed on certificates comes from here (fallback: primary guardian).

**`student_qualifications`**
`id, student_id, level (tenth|twelfth|diploma|graduation|post_graduation|other), board_university, institution, passing_year, roll_no, marks_type (percentage|cgpa), score, timestamps` — index `(student_id, passing_year)`.

**`student_documents`**
`id, student_id, type (photo|signature|aadhaar|tenth_marksheet|twelfth_marksheet|other), disk, path, mime_type, size, verification_status (pending|verified|rejected), verified_at, remarks, timestamps, deleted_at`

### 4.6 Enrollment

**`enrollments`** (tenant)
`id, institute_id, student_id, course_id, batch_id (nullable), academic_session_id, enrollment_no (nullable until approved, unique global, format KCTI/{yyyy}/{seq6}), admission_date, course_fee, discount, net_fee, hq_fee_snapshot, status (draft|pending_approval|active|rejected|completed|dropped|cancelled), submitted_at, approved_by_admin_id, approved_at, rejection_reason, completed_on, debit_transaction_id (nullable → wallet_transactions), remarks, timestamps`
Indexes: `(institute_id, status)`, `(institute_id, course_id, academic_session_id)`, `(student_id)`.
Rule: a student cannot have two enrollments in the same course with status in `{draft, pending_approval, active}`.

**`enrollment_status_histories`** (immutable)
`id, enrollment_id, from_status, to_status, reason, actor_type, actor_id, changed_at`

Allowed transitions:

```text
draft → pending_approval | cancelled
pending_approval → active | rejected
rejected → draft            (institute fixes and resubmits; a new debit happens on resubmit)
active → completed | dropped
```

### 4.7 Wallet

**`wallets`**: `id, institute_id (unique), balance (decimal, default 0, never negative), timestamps`

**`wallet_transactions`** (immutable ledger, tenant)
`id, institute_id, wallet_id, type (credit|debit), amount (> 0), balance_after, source (recharge|enrollment_fee|enrollment_refund|adjustment), source_type, source_id, description, created_by_type, created_by_id, created_at`
Index `(institute_id, created_at)`, `(source_type, source_id)`.
Invariant: for each wallet, the latest `balance_after` equals `wallets.balance`.

**`wallet_recharge_requests`** (tenant)
`id, institute_id, request_no (unique), amount, payment_mode (upi|bank_transfer|cash|cheque), utr_reference (unique), paid_on, proof_disk, proof_path, status (pending|approved|rejected), requested_by_user_id, reviewed_by_admin_id, reviewed_at, admin_remarks, credit_transaction_id (nullable), timestamps`

### 4.8 Student fees

**`fee_installments`** (tenant): `id, institute_id, enrollment_id, sequence, due_on, amount, timestamps` — unique `(enrollment_id, sequence)`. Sum of amounts must equal `enrollments.net_fee`.

**`fee_payments`** (tenant)
`id, institute_id, receipt_no (unique per institute), student_id, enrollment_id, amount, mode (cash|upi|bank_transfer|cheque|card), reference_no, paid_on, received_by_user_id (nullable), received_by_admin_id (nullable), status (valid|cancelled), cancelled_at, cancelled_by_type, cancelled_by_id, cancel_reason, remarks, timestamps`
Unique `(institute_id, receipt_no)`. Index `(institute_id, paid_on)`, `(enrollment_id, status)`.

Derived values (computed in `FeeService`, not stored): `paid = sum(valid payments)`, `due = net_fee − paid`, installment status by applying paid total to installments in sequence order.

### 4.9 Attendance

**`attendance_sessions`** (tenant): `id, institute_id, batch_id, date, taken_by_user_id, timestamps` — unique `(batch_id, date)`.

**`attendance_records`**: `id, attendance_session_id, enrollment_id, status (present|absent|late|leave), remarks, timestamps` — unique `(attendance_session_id, enrollment_id)`.

### 4.10 Exams and certificates

**`exam_results`** (tenant)
`id, institute_id, enrollment_id (unique), exam_date, status (draft|submitted|published|returned), total_obtained, total_max, percentage, grade, result (pass|fail), return_remarks, submitted_by_user_id, submitted_at, published_by_admin_id, published_at, timestamps`

**`exam_result_marks`**: `id, exam_result_id, course_subject_id, theory, practical, total, is_absent, timestamps` — unique `(exam_result_id, course_subject_id)`.

**`certificates`** (tenant, immutable except revocation)
`id, institute_id, enrollment_id (unique), certificate_no (unique, format KCTI/C/{yyyy}/{seq6}), verification_token (ULID, unique), issued_on, issued_by_admin_id, pdf_disk, pdf_path, status (issued|revoked), revoked_at, revoked_by_admin_id, revoked_reason, timestamps`

ID card, admit card, and marksheet PDFs are rendered on demand from existing data (no tables).

### 4.11 Website CMS and settings (global)

| Table | Columns |
|---|---|
| `sliders` | `id, title (json), subtitle (json), image_path, button_text (json), button_url, sort_order, is_active, timestamps` |
| `notices` | `id, title (json), slug (unique), body (json), image_path, published_at, show_in_ticker, is_active, timestamps, deleted_at` |
| `gallery_items` | `id, title (json), category, image_path, sort_order, is_active, timestamps` |
| `faculty_members` | `id, name, designation (json), qualification, photo_path, sort_order, is_active, timestamps` |
| `testimonials` | `id, name, course_name, company, position, photo_path, quote (json), sort_order, is_active, timestamps` |
| `downloads` | `id, title (json), category, disk, path, sort_order, is_active, timestamps` |
| `enquiries` | `id, institute_id (default main), type (contact|admission), name, phone, email, course_id (nullable), message, status (new|contacted|converted|closed), handled_by_admin_id, converted_student_id, timestamps` — index `(type, status, created_at)` |
| `settings` | `id, group, key, value (json), timestamps` — unique `(group, key)` |

Settings groups: `site` (name, tagline, logo, announcement text/coupon, phone, email, address, map embed, social links), `payment` (UPI id, QR image, bank details shown on recharge page), `numbering` (formats), `academic` (default password rule).

### 4.12 Infrastructure

`number_sequences` (`id, key (unique), last_value, timestamps`), `activity_log` (Spatie), `notifications`, `jobs`, `job_batches`, `failed_jobs`, `cache`, `cache_locks`, `sessions`.

### 4.13 Relationship overview

```text
admins ──< approvals / reviews / publications (actor FKs)

institutes ──1 institute_profiles
           ──1 wallets ──< wallet_transactions
           ──< wallet_recharge_requests
           ──< institute_users
           ──< institute_documents, institute_status_histories
           ──< institute_course >── courses
           ──< batches >── courses, academic_sessions
           ──< students ──1 student_personal_details
                        ──< student_guardians, student_qualifications, student_documents
                        ──< enrollments ──< enrollment_status_histories
                                        ──< fee_installments, fee_payments
                                        ──< attendance_records
                                        ──1 exam_results ──< exam_result_marks >── course_subjects
                                        ──1 certificates
           ──< enquiries

course_categories ──< courses ──< course_subjects >── subjects
addresses >── institutes | students | franchise_applications  (morph)
```

### 4.14 Out of scope (YAGNI)

Hostel, transport, payroll/HR, library, shared guardian entities, payment-to-installment allocation tables, multi-currency, per-subject exam scheduling.

---

## 5. Roles and permissions

### 5.1 Admin guard (HQ team)

Default roles (seeded; editable by Super Admin except Super Admin itself):

| Role | Scope |
|---|---|
| Super Admin | Everything (Shield super-admin bypass). Cannot be deleted or deactivated if it is the last one. |
| Manager | Institutes, franchise applications, enrollments, students |
| Accounts | Wallet recharge approvals, wallet adjustments, fee reports |
| Academic | Sessions, courses, subjects, grade scales, results, certificates |
| Support / CMS | Website CMS, enquiries |

Permissions = Shield-generated resource permissions + custom actions:
`approve_franchise, impersonate_institute, suspend_institute, approve_enrollment, approve_wallet_recharge, adjust_wallet, publish_result, issue_certificate, revoke_certificate, manage_admins`.

The admin panel has a **Team** section (Admins resource + Roles resource).

### 5.2 Institute guard (franchise team)

Default roles, created per system (shared definitions, assigned per user): **Owner, Receptionist, Teacher, Accountant.**
Custom permissions: `submit_enrollment, collect_fee, cancel_receipt, take_attendance, enter_marks, submit_result, request_wallet_recharge, manage_staff`.
The owner always has all institute permissions. Only users with `manage_staff` can create staff, and can assign only institute-guard roles.

### 5.3 Student guard

No roles. Access is limited by policies to the student's own records.

---

## 6. Workflows

All workflows below are implemented in services, wrapped in a DB transaction. Money and status changes lock the affected rows (`lockForUpdate`). Invalid transitions throw a domain exception that Filament shows as a danger notification.

### 6.1 Franchise onboarding (`FranchiseOnboardingService`)
1. Website `/franchise/apply` creates `franchise_applications` (`new`) + office address.
2. Admin with `approve_franchise` may mark `contacted`, `rejected` (with remarks), or **Convert to Institute**.
3. Convert creates, in one transaction: `institutes` (`active`, next code), `institute_profiles`, address, `wallets` (0), owner `institute_users` (role Owner, random password), status history row; sets application `approved` + `converted_institute_id`. Credentials are emailed to the owner.
4. Admin assigns authorized courses (`institute_course`).
5. Suspend/reactivate/close writes `institute_status_histories`. Suspended institutes cannot log in; their students cannot log in.

### 6.2 Wallet recharge (`WalletService`)
1. Institute submits a request (amount, mode, UTR, paid_on, proof). Payment instructions from `settings.payment` are shown on the form.
2. Admin with `approve_wallet_recharge` approves → wallet locked, balance credited, `wallet_transactions` credit row, request `approved`, institute notified. Reject requires remarks.
3. `adjust_wallet` allows a manual credit/debit with mandatory reason (debit cannot make the balance negative).

### 6.3 Student admission and HQ approval (`EnrollmentService`)
1. Institute creates the student (wizard: basic → personal → guardians → address → qualifications → documents). `registration_no` is assigned on create.
2. Institute creates an enrollment in `draft`: course (only active `institute_course` rows), batch, fee, discount, installments.
3. **Submit** (`submit_enrollment`): lock wallet; fee = `hq_fee_override ?? courses.hq_registration_fee`; if the institute is not wallet-exempt and balance < fee → fail with "insufficient wallet balance"; otherwise debit, store `hq_fee_snapshot` and `debit_transaction_id`, status `pending_approval`.
4. **Approve** (`approve_enrollment`): assign `enrollment_no`, status `active`; if the student has no password yet, set it from DOB (`DDMMYYYY`), `can_login = true`, `must_change_password = true`.
5. **Reject**: status `rejected` with reason; refund credit equal to `hq_fee_snapshot` (if debited).
6. Every transition writes `enrollment_status_histories`.

### 6.4 Website admission → main institute
1. `/students/registration` creates an `enquiries` row (`admission`, main institute).
2. Staff "Convert to Student" pre-fills the student wizard; `admission_source = website`. The enrollment flow then runs without wallet debit.

### 6.5 Fees (`FeeService`)
Collect payment → receipt number from `number_sequences` key `receipt:{institute_id}` → printable PDF. Payment amount may not exceed current due. Cancellation requires `cancel_receipt` and a reason; cancelled receipts remain visible.

### 6.6 Attendance (`AttendanceService`)
Pick batch + date → grid of active enrollments → mark statuses → save. Re-opening the same batch/date edits the existing session. Dates in the future are rejected.

### 6.7 Exam → result → certificate (`ResultService`, `CertificateService`)
1. Institute enters marks for each course subject of an active enrollment → submit (`submitted`).
2. Admin with `publish_result` publishes → compute totals, percentage, grade from `grade_scales`, pass/fail (every non-absent subject ≥ `pass_marks` and not absent); enrollment → `completed`. Or **Return** with remarks → institute edits → resubmit.
3. Admin with `issue_certificate` issues a certificate for a passed result → number + ULID token → PDF with QR code pointing to `/verify/{token}`.
4. Public verification (`/students/verification`): by certificate number, or enrollment number + DOB. Shows student name, father's name, course, grade, issue date, institute, and status (valid/revoked).
5. ID card and admit card PDFs are downloadable from the institute and student panels for active enrollments.

### 6.8 Impersonation (`ImpersonationService`)
1. "Login as Institute" row action (permission `impersonate_institute`) on the institutes table.
2. Stores `impersonator_admin_id` in session, logs in the institute owner on the `institute` guard, opens `/institute` in a new tab with a "Return to Admin" banner.
3. Start and end are recorded in `activity_log`. While impersonating, changing the password or email is blocked.
4. `/impersonation/leave` logs out the institute guard and returns to `/admin/institutes`.

### 6.9 Student portal
Login by `registration_no` or email + password. First login forces a password change. Pages: profile (read-only), enrollments, fee dues and receipts, attendance percentage per enrollment, results, downloads (certificate, marksheet, ID card, admit card).

---

## 7. Admin panel experience

- Every tenant resource (students, enrollments, batches, payments, attendance, results, certificates, recharge requests, wallet transactions, staff) has an **Institute** select filter and an **Institute** column, plus a **Source** filter/badge (Main / Franchise) on students and enrollments.
- Common filters: course, session, status, date range.
- Exports via Filament Excel on students, enrollments, payments, wallet transactions.
- Institute view page with relation tabs: profile, documents, staff, students, enrollments, wallet ledger, status history.
- Approval queues as dashboard widgets: pending enrollments, pending recharges, submitted results, new franchise applications, new enquiries.
- Dashboard stats: institutes by status, active students, enrollments this month, wallet credits this month, results pending; charts for monthly enrollments by source.

Institute dashboard: students, active enrollments, wallet balance (low-balance warning), today's attendance, fees collected this month, dues.

---

## 8. Website

### 8.1 UI parity
Port from the reference project: `resources/views/main_site/**` → `resources/views/site/**`, `public/css/main_site/{kc-main,ms-main,ms-styles}.css` → `public/css/site/`, `public/js/main_site/kc-navbar.js`, and `public/images/**`. Keep colors (`#4f46e5 → #7c3aed` gradient, `--kc-*` variables), Plus Jakarta Sans, Swiper slider, and section design.

### 8.2 Pages and data

| Route | Name | Data source |
|---|---|---|
| `/` | `site.home` | sliders, notices (ticker + recent), featured courses, testimonials, faculty, gallery, settings |
| `/about` | `site.about` | settings / static translated content |
| `/courses`, `/courses/{slug}` | `site.courses.index/show` | courses + categories (`show_on_website`) |
| `/placement` | `site.placement` | testimonials |
| `/gallery` | `site.gallery` | gallery_items |
| `/download` | `site.download` | downloads |
| `/contact` (GET/POST) | `site.contact` | settings; POST → enquiries (`contact`) |
| `/students/registration` (GET/POST) | `site.students.registration` | POST → enquiries (`admission`) |
| `/students/verification` | `site.students.verification` | certificates |
| `/verify/{token}` | `site.verify` | certificates |
| `/franchise/info` | `site.franchise.info` | static translated content |
| `/franchise/apply` (GET/POST) | `site.franchise.apply` | POST → franchise_applications |
| `/locale/{locale}` | `site.locale` | sets session locale, redirects back |

Forms: CSRF, server-side validation, `throttle:5,1`, honeypot field.

### 8.3 Language
- UI strings in `lang/en/*.php` and `lang/hi/*.php`. DB content through spatie translatable.
- Navbar switcher **EN | हिं**; `SetLocale` middleware reads session (set by `/locale/{locale}` or `?lang=`), default `en`, fallback `en`.
- Admin CMS forms show English and Hindi inputs for each translatable field.
- Panels use the logged-in user's session locale; Filament ships Hindi translations.

### 8.4 Theme
- Website: CSS variables duplicated under `[data-theme="dark"]`; ☀/☾ toggle in the navbar; initial value from `prefers-color-scheme`; persisted in `localStorage` (wrapped in try/catch); inline script in `<head>` applies it before paint.
- Panels: Filament's built-in light/dark/system switcher; primary color indigo `#4f46e5`; font Plus Jakarta Sans; same logo/brand on all three panels.

---

## 9. Security

- Separate guards and session keys; policies check guard and ownership on every tenant model.
- Private disk for documents, proofs, and certificates; downloads go through authorized controller routes.
- Aadhaar stored encrypted plus a hash for uniqueness; never shown in full (masked `XXXX-XXXX-1234`).
- Uploads: images ≤ 2 MB (jpg/png/webp), documents ≤ 5 MB (pdf/jpg/png); mime validated.
- Login throttling per guard. Rate limits on public forms.
- Activity log for approvals, wallet changes, status changes, impersonation, certificate issue/revoke.

---

## 10. Installer

`php artisan kranti:install` (idempotent): checks environment, runs migrations, generates Shield permissions for both guards, seeds roles, grade scales, default settings, current academic session, creates the main institute (`KCTI0001`, `is_main`, wallet-exempt, with wallet), prompts for Super Admin name/email/password, links storage, and caches.

---

## 11. Testing strategy (Pest)

1. **Tenant isolation (highest priority):** for every tenant model, an institute user of institute A cannot list, view, edit, or create records for institute B (via Filament pages and directly via the model scope). Student can see only their own records.
2. **Guards/access:** admin cannot open `/institute` without impersonation; student cannot open `/admin` or `/institute`; suspended institute users and their students are blocked; inactive accounts are blocked.
3. **Wallet:** insufficient balance blocks submit; debit and refund amounts; `balance_after` chain equals wallet balance; concurrent submits never drive balance negative; wallet-exempt main institute is never debited.
4. **Workflows:** enrollment transition table (allowed and rejected transitions); result computation (grade, pass/fail, absent); certificate verification valid/revoked; franchise conversion creates all records; impersonation start/stop and audit.
5. **Numbering:** sequences are unique and gap-tolerant under concurrency.
6. **Website:** every route returns 200 in `en` and `hi`; forms validate and persist; honeypot rejects bots.

Tests run on SQLite in-memory; a MySQL smoke run validates generated columns and locking.

---

## 12. Delivery phases

Each phase ends with passing tests and a runnable demo. Each phase gets its own implementation plan.

| # | Phase | Contents |
|---|---|---|
| 1 | Foundation | Laravel 13 + Filament 5 setup; three guards/tables; three panels with theme; Shield for both guards; `CurrentInstitute` + `BelongsToInstitute`; number sequences; installer; impersonation |
| 2 | Institutes & HQ academic | Institutes (profile, documents, status history), franchise applications → convert, admin Team/roles, sessions, categories, courses, subjects, course subjects, grade scales, `institute_course`, institute staff + roles |
| 3 | Students, enrollment, wallet | Student wizard (normalized tables), batches, enrollment + approval, wallet + recharge + ledger, admin filters/badges/exports |
| 4 | Fees & attendance | Installments, payments/receipts (PDF), dues, attendance grid, reports |
| 5 | Exams & certificates | Marks entry → publish, certificate/marksheet/ID/admit PDFs with QR, public verification |
| 6 | Website | UI port, CMS resources, enquiries → student conversion, i18n, dark mode |
| 7 | Student portal & dashboards | Student panel, admin/institute dashboard widgets and charts |

## 13. Future (not in v1)

Regional scoping of HQ staff (`admin_institute` pivot), Razorpay wallet recharge, SMS/WhatsApp gateway, online exams, multi-level franchise hierarchy, multi-currency.
