# KCTI Phase 3 — Students, Enrollment & Wallet Implementation Plan

> **For agentic workers:** Execute inline with superpowers:executing-plans. Every task is test-first: write the listed tests, watch them fail, implement, watch them pass, commit.

**Goal:** Franchises admit students (normalized, college-style records) and enroll them in authorized courses; each submission debits the franchise's prepaid HQ wallet and waits for HQ approval, which issues the enrollment number and opens the student portal. Franchises recharge wallets with payment proof that HQ approves. HQ sees every student and enrollment with institute/source filters and exports.

**Architecture:** Builds on Phase 2 (branch `phase-2-institutes-academic`). New tenant models use `BelongsToInstitute`. Money moves only through `WalletService` inside DB transactions with the wallet row locked; the ledger is immutable. Enrollment status changes only through `EnrollmentService`, which records history.

**Tech Stack:** Laravel 13, Filament 5 (native `ExportAction`), Spatie permission, Pest. No new packages.

**Spec:** `docs/superpowers/specs/2026-09-28-kranti-franchise-platform-design.md` — §4.4 batches, §4.5, §4.6, §4.7, §6.2, §6.3, §7, §9 (Aadhaar), §12 row 3.

## Global Constraints

- Phase 1 and Phase 2 constraints still apply.
- Registration number `{institute_code}-{yy}-{seq4}` from key `registration:{institute_id}:{yy}` (e.g. `KCTI0007-26-0045`), assigned on student create, stored uppercase.
- Enrollment number `KCTI/{yyyy}/{seq6}` from key `enrollment:{yyyy}`, assigned on HQ approval.
- Recharge request number `KCTI/WR/{yyyy}/{seq5}` from key `wallet_recharge:{yyyy}`.
- Wallet balance is never negative; every change writes one `wallet_transactions` row whose `balance_after` equals the new balance.
- Enrollment transitions (spec §4.6): draft → pending_approval | cancelled; pending_approval → active | rejected; rejected → draft; active → completed | dropped.
- Aadhaar: stored with the `encrypted` cast plus `aadhaar_hash` (SHA-256 of the 12 digits, unique); displayed masked `XXXX-XXXX-1234`.
- Student portal login additionally requires at least one enrollment with status `active` or `completed`.

### Decisions (rulings made while planning)

1. **Fee installments move to Phase 4** with fee collection; Phase 3 enrollments store course_fee / discount / net_fee only.
2. **Payment instructions** shown on the recharge form come from `config('kcti.payment')` (env-driven) until the Settings CMS lands in Phase 6.
3. **Owner logins for hand-created institutes** (Phase 2 follow-up): admin action "Create owner login" and "Send password reset link" (institute password broker) — no plaintext password emailed for these.
4. **Exports** use Filament's native `ExportAction` (queued; requires the `exports` table and database notifications).
5. Phase 2 follow-ups folded in: lock in `AcademicSessionService::makeCurrent`; unique-violation → clean message in franchise conversion; Course search on `name->en`/`name->hi`; uppercase setter on `registration_no`.

## Review Focus

1. **Two submissions at once drain the wallet below zero** → one succeeds, the other is refused; balance never negative (MariaDB probe). Task 1/Task 6.
2. **Rejecting an enrollment twice, or rejecting after approval** → exactly one refund; invalid transitions refused. Task 6.
3. **Institute A opening, enrolling, or submitting Institute B's student** (URL, Livewire update endpoint, relationship selects) → never. Tasks 4–6.
4. **Recharge approved twice (double click) or a reused UTR** → one credit; duplicate UTR refused. Task 2.
5. **A student whose enrollment is still pending, or whose institute is suspended, tries to log in** → refused. Task 8.

---

### Task 1: Wallet core

Files: migration `wallets`, `wallet_transactions`; models `Wallet`, `WalletTransaction` (immutable); enum `WalletTransactionType` (credit/debit), `WalletSource` (recharge/enrollment_fee/enrollment_refund/adjustment); `WalletService`; `InstituteService::create` also creates the wallet; backfill migration creating wallets for existing institutes; tests `tests/Feature/Phase3/WalletServiceTest.php`.

Interfaces: `WalletService::credit(Institute, string $amount, WalletSource, ?Model $source, string $description, Model $actor): WalletTransaction`; `debit(...)` same signature, throws `DomainRuleException('Insufficient wallet balance…')` when balance < amount; `balance(Institute): string`. Amounts are decimal strings compared with `bccomp`-free integer paise math (`(int) round($amount * 100)`).

Tests: credit/debit update balance and ledger `balance_after`; debit beyond balance refused, nothing written; zero/negative amounts refused; ledger rows cannot be updated or deleted; new institutes get a wallet; backfill creates missing wallets; MariaDB probe: 8 processes × 20 debits of 10 against balance 1000 → exactly 100 succeed, balance 0, no negative.

### Task 2: Wallet recharge requests

Files: migration `wallet_recharge_requests`; model + enum `RechargeStatus`; `WalletRechargeService` (`request`, `approve`, `reject`); `config/kcti.php` (`payment` block); institute resource `WalletRecharges` (create with proof upload to private disk, list own requests) + institute page `Wallet` (balance + ledger table); admin resource `WalletRechargeRequests` (approve / reject with remarks, proof download) + admin `WalletTransactions` resource (read-only ledger, institute filter) + institute table action "Adjust wallet" (`adjust_wallet`, reason required); permissions (`request_wallet_recharge`, `approve_wallet_recharge`, Shield keys `ViewAny:WalletRechargeRequest`, `ViewAny:WalletTransaction` for accounts role); tests `tests/Feature/Phase3/WalletRechargeTest.php`.

Tests: request numbered, pending, proof stored privately; duplicate UTR refused; approve credits once (second approve refused); reject needs remarks and credits nothing; institute sees only its own requests (Livewire endpoint); accounts role can approve, support cannot; adjustment debit cannot go negative.

### Task 3: Owner login management

Files: `InstituteOwnerService` (`createOwner(Institute, name, email, phone, Admin)`, `sendResetLink(Institute)`); admin institute table/view actions; tests `tests/Feature/Phase3/OwnerLoginTest.php`.

Tests: create owner for an institute without one (role owner, reset link sent via `Password::broker('institute_users')`, no plaintext mail); refused when an owner exists or email taken; reset link sent to the existing owner; actions need `Update:Institute`.

### Task 4: Student records

Files: migrations `student_personal_details`, `student_guardians`, `student_qualifications`, `student_documents`; models; enums `Gender`, `SocialCategory`, `GuardianRelation`, `QualificationLevel`; `StudentService::create(array $student, array $personal, array $guardians, array $address, array $qualifications, InstituteUser|Admin $by, ?Institute $institute = null)` and `update(...)`; `Student` relations, `registration_no` setter, masked Aadhaar accessor; tests `tests/Feature/Phase3/StudentServiceTest.php`.

Tests: registration number format and per-institute/year sequence; Aadhaar encrypted at rest, hash unique across institutes (duplicate refused with clear message), masked display; primary guardian required; one guardian per relation; admin creating for an institute sets that institute; institute user cannot create for another institute.

### Task 5: Institute panel — students and batches

Files: institute resources `Students` (wizard: basic → personal → guardians repeater → address → qualifications repeater → documents), view page with enrollments; `Batches` (course from authorized courses, teacher from own staff); policies `StudentPolicy`, `BatchPolicy` (institute guard: any active user views; create/update need `submit_enrollment` or owner; admin guard: Shield keys); tests `tests/Feature/Phase3/InstituteStudentsTest.php`.

Tests: owner creates a student through the wizard (all related rows saved); another institute's student URL → 404; Livewire endpoint search never returns another institute's students; batch course options limited to authorized courses; teacher options limited to own staff.

### Task 6: Enrollment workflow

Files: migrations `enrollments`, `enrollment_status_histories`; enum `EnrollmentStatus`; models; `EnrollmentService` (`createDraft`, `submit`, `approve`, `reject`, `cancel`, `reopen` (rejected → draft), `complete`, `drop`); institute `Enrollments` resource (create draft from student, submit, cancel, reopen); tests `tests/Feature/Phase3/EnrollmentServiceTest.php`, `tests/Feature/Phase3/InstituteEnrollmentTest.php`.

Tests: draft only for authorized course and own student; duplicate open enrollment in the same course refused; submit debits `hqFeeFor` and stores snapshot + transaction; insufficient balance refused with no state change; main institute (wallet_exempt) submits without debit; approve assigns `KCTI/{yyyy}/{seq6}`, enables student login with DOB password and `must_change_password`; reject refunds exactly once; reopen then resubmit debits again; every transition writes history; invalid transitions refused; MariaDB probe: concurrent submits never overdraw.

### Task 7: HQ views — students, enrollments, approval queue, exports

Files: admin resources `Students` (read-only view + filters institute, source badge Main/Franchise, course, session, status), `Enrollments` (filters + Approve / Reject actions with `approve_enrollment`), dashboard widget "Pending approvals" count; exporters `StudentExporter`, `EnrollmentExporter`, `WalletTransactionExporter`; migrations for `exports`, `notifications`; tests `tests/Feature/Phase3/AdminStudentsEnrollmentsTest.php`.

Tests: admin sees students of all institutes; institute filter and source badge; approve/reject from the table call the service; manager can approve, academic cannot; export action runs (queue sync in tests) and creates an export record.

### Task 8: Student login gate, follow-ups, seeds, wrap-up

Files: `Student::canAccessPanel` requires an active/completed enrollment; Phase 2 follow-ups (session lock, conversion unique violation, course JSON search); role permission defaults (manager: Student/Enrollment view + approve; accounts: recharge + ledger); README; tests `tests/Feature/Phase3/StudentAccessTest.php`, additions to existing tests.

Tests: pending-only student refused; active enrollment allowed; suspended institute still refused; makeCurrent under lock; conversion with an email taken concurrently yields DomainRuleException; Hindi course search matches.

Final: full suite, Pint, MariaDB fresh install + probes, fresh-reviewer final review.
