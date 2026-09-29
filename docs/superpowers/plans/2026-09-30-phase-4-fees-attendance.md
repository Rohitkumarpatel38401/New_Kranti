# KCTI Phase 4 — Fees & Attendance Implementation Plan

> **For agentic workers:** Execute inline with superpowers:executing-plans. Every task is test-first: write the listed tests, watch them fail, implement, watch them pass, commit.

**Goal:** Institutes plan each enrollment's fee in installments, collect payments with numbered printable receipts, cancel mistaken receipts with a reason, and see dues; teachers mark daily batch attendance. HQ sees every institute's collections, dues, and attendance with filters and exports.

**Architecture:** Builds on Phase 3. Fee money is the institute's own (not the HQ wallet), so it lives in `fee_installments` / `fee_payments` and never touches `WalletService`. Dues are derived (net fee − valid payments), never stored. `FeeService` and `AttendanceService` own all writes inside transactions with the enrollment / session row locked.

**Tech Stack:** Laravel 13, Filament 5, `barryvdh/laravel-dompdf` ^3.1 (receipts), Pest.

**Spec:** §4.8, §4.9, §6.5, §6.6, §7 (institute dashboard figures), §12 row 4.

## Global Constraints

- Earlier phases' constraints apply.
- Receipt number `{institute_code}/R/{yy}/{seq5}` from key `receipt:{institute_id}:{yy}` (e.g. `KCTI0007/R/26/00012`), unique per institute.
- A payment never exceeds the current due; amounts are rupees with at most two decimals (paise math via `WalletService::toPaise`).
- Installment amounts must sum exactly to the enrollment's net fee; due dates must not go backwards.
- Only enrollments in `active` or `completed` status take payments; attendance is taken only for `active` enrollments of the batch.
- Cancelled receipts stay visible, marked cancelled, with who/when/why.
- Attendance cannot be marked for a future date; re-saving a batch/date edits the same session.

### Decisions

1. **Receipts are generated on demand** (PDF rendered from the payment row) instead of stored files, so a cancelled receipt always prints with a CANCELLED watermark.
2. **Default fee plan:** when none exists, the enrollment is treated as one installment due on the admission date.
3. **Installment status is derived** by applying valid payments in sequence order (paid / partial / overdue / upcoming).
4. **Receipts are English-only** in v1 (dompdf does not shape Devanagari conjuncts reliably); the rest of the UI stays bilingual.

## Review Focus

1. **Two staff collect the same due at once** → total collected never exceeds the net fee. (MariaDB probe.)
2. **A receipt cancelled twice, or a payment on a dropped/rejected enrollment** → refused; due restored exactly once.
3. **Institute A downloading Institute B's receipt PDF, or marking attendance for B's batch/enrollment** → 403/404.
4. **Installments edited after payments exist** so that paid amounts exceed the new plan → refused.
5. **Attendance for a student not in that batch, or for a future date** → refused.

---

### Task 1: Fee plan (installments)

Files: migration `fee_installments`; model `FeeInstallment`; enum `InstallmentStatus` (paid/partial/overdue/upcoming); `FeeService::setPlan(Enrollment, array $rows, Model $by)`, `plan(Enrollment): Collection` (derived statuses), `summary(Enrollment): array{net, paid, due}`; tests `tests/Feature/Phase4/FeePlanTest.php`.

Tests: plan sums must equal net fee; dates ascending; default single installment; derived statuses with partial payments (after Task 2) ; replacing a plan below already-paid total refused.

### Task 2: Payments and receipts

Files: migration `fee_payments`; model `FeePayment`; enum `FeePaymentStatus` (valid/cancelled); `FeeService::collect(...)`, `cancel(FeePayment, string $reason, Model $by)`; receipt Blade view `resources/views/pdf/receipt.blade.php`; `ReceiptController@show` (route `receipts.show`, guards admin|institute, authorization through `FeePaymentPolicy::view`); install dompdf; tests `tests/Feature/Phase4/FeePaymentTest.php`.

Tests: receipt numbering per institute/year; over-payment refused; payment on non-active enrollment refused; cancel needs `cancel_receipt` + reason, restores due once, second cancel refused; receipt PDF downloads for own institute and HQ, 404/403 for another institute; cancelled receipt PDF shows CANCELLED; MariaDB probe: concurrent collections never exceed net fee.

### Task 3: Institute fee screens

Files: institute `FeePayments` resource (list, cancel action, receipt link); enrollment table actions "Fee plan" (repeater) and "Collect fee"; `Dues` page (enrollments with due > 0, next due date, overdue badge); policies (`collect_fee`, `cancel_receipt`); tests `tests/Feature/Phase4/InstituteFeesTest.php`.

Tests: collect from the enrollment row issues a receipt and shows the link; teacher cannot collect; accountant can cancel; dues page lists only own institute (Livewire endpoint); overdue marked.

### Task 4: HQ fee views

Files: admin `FeePayments` resource (all institutes, filters institute/date/mode/status, totals summary, export), admin `Dues` page; Shield keys; tests `tests/Feature/Phase4/AdminFeesTest.php`.

Tests: HQ sees all institutes with filter; totals exclude cancelled; export works; accounts role allowed, support forbidden.

### Task 5: Attendance

Files: migrations `attendance_sessions`, `attendance_records`; models; enum `AttendanceStatus` (present/absent/late/leave); `AttendanceService::save(Batch, Carbon $date, array $marks, InstituteUser $by)`, `percentage(Enrollment): ?float`; institute page `TakeAttendance` (batch + date, grid of active enrollments, bulk "all present"); institute `AttendanceSessions` resource (history, edit by reopening); tests `tests/Feature/Phase4/AttendanceTest.php`.

Tests: save creates one session per batch/date and re-save updates it; future date refused; enrollment from another batch/institute refused; only `take_attendance` users; percentage counts present + late as attended, leave excluded from the total; Livewire endpoint isolation for the page's batch options.

### Task 6: HQ attendance view, dashboards figures, wrap-up

Files: admin `AttendanceSessions` read-only resource with institute/batch/date filters; institute dashboard stats (today's attendance, fees collected this month, total dues, wallet balance with low-balance warning); permissions/seeds; README; tests `tests/Feature/Phase4/DashboardsTest.php`.

Tests: institute stats scoped to own institute; low-balance warning under ₹500; HQ attendance list filters by institute.

Final: full suite, Pint, MariaDB fresh install + probes, fresh-reviewer final review.
