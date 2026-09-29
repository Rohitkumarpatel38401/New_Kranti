# KCTI Phase 5 — Exams & Certificates Implementation Plan

> **For agentic workers:** Execute inline with superpowers:executing-plans. Every task is test-first: write the listed tests, watch them fail, implement, watch them pass, commit.

**Goal:** Institutes enter subject-wise marks for approved enrollments and submit them; HQ publishes (grade + pass/fail computed) or returns them; HQ issues numbered certificates with a QR code that anyone can verify on the website; institutes (and later students) download marksheets, certificates, ID cards, and admit cards.

**Architecture:** Builds on Phase 4. `ResultService` owns marks and the draft → submitted → published / returned workflow; publishing completes the enrollment through `EnrollmentService::complete`. `CertificateService` issues and revokes. All PDFs are rendered on demand from database rows (dompdf); QR codes are PNG data URIs from `chillerlan/php-qrcode`. Public verification uses a random ULID token or certificate number + DOB, rate-limited.

**Tech Stack:** Laravel 13, Filament 5, dompdf, chillerlan/php-qrcode ^6, Pest.

**Spec:** §4.10, §6.7, §8.2 (`/students/verification`, `/verify/{token}`), §12 row 5.

## Global Constraints

- Earlier phases' constraints apply.
- Certificate number `KCTI/C/{yyyy}/{seq6}` from key `certificate:{yyyy}`; verification token = ULID.
- Percentage rounded to 2 decimals before grade lookup (grade bands have 0.01 gaps).
- Pass rule: every subject not absent and subject total ≥ its `pass_marks`.
- Marks: 0 ≤ theory ≤ theory_max, 0 ≤ practical ≤ practical_max; absent means both empty.
- Only `active` enrollments get marks; publishing moves the enrollment to `completed`.
- A certificate needs a published **pass** result; one certificate per enrollment; revoked certificates verify as "Revoked".
- Public verification never shows Aadhaar, phone, email, or address.

### Decisions

1. **PDFs on demand** (no stored pdf_path): certificates and marksheets always reflect revocation state.
2. **Returned results reopen for editing** (status `returned` → institute edits → submits again).
3. **Admit card** available for active enrollments; **ID card** for active/completed enrollments; both use the student photo if present.
4. Verification pages are minimal Blade pages in Phase 5; Phase 6 restyles them inside the website layout.

## Review Focus

1. **Marks for another institute's enrollment or tampered subject ids** → refused.
2. **Publish twice / edit after publish / certificate for a failed or unpublished result** → refused.
3. **Percentage exactly on a band edge (e.g. 89.995) and absent subjects** → correct grade and fail.
4. **Verification brute force or a revoked certificate** → throttled; shows Revoked.
5. **Institute downloading another institute's certificate/ID/admit card** → 403/404.

---

### Task 1: Results core
Files: migrations `exam_results`, `exam_result_marks`; enums `ResultStatus` (draft/submitted/published/returned), `ResultOutcome` (pass/fail); models; `ResultService` (`saveMarks`, `submit`, `publish`, `return`); tests `tests/Feature/Phase5/ResultServiceTest.php`.

### Task 2: Institute marks entry
Files: institute enrollment action "Enter marks" (one row per course subject), institute `Results` resource (list, submit, returned remarks); policy; tests `tests/Feature/Phase5/InstituteResultsTest.php`.

### Task 3: HQ results queue
Files: admin `Results` resource (filters institute/course/status, view marks, Publish / Return with remarks, export); tests `tests/Feature/Phase5/AdminResultsTest.php`.

### Task 4: Certificates
Files: migration `certificates`; enum `CertificateStatus`; model; `CertificateService` (`issue`, `revoke`); `Qr` support class; certificate + marksheet PDF views; `DocumentController` routes `documents.certificate`, `documents.marksheet` (auth:admin,institute + policy); admin Certificates resource (issue from result, revoke); tests `tests/Feature/Phase5/CertificateTest.php`.

### Task 5: Public verification
Files: `VerificationController` (`form`, `lookup`, `show`), routes `/students/verification` (GET/POST, throttle), `/verify/{token}`; Blade views; tests `tests/Feature/Phase5/VerificationTest.php`.

### Task 6: ID card & admit card, wrap-up
Files: ID card and admit card PDF views + routes; institute enrollment actions; permissions (teacher: enter_marks, submit_result; academic: publish_result, issue_certificate, revoke_certificate + Shield keys ExamResult/Certificate); README; tests `tests/Feature/Phase5/StudentDocumentsTest.php`.

Final: full suite, Pint, MariaDB fresh install, fresh-reviewer final review.
