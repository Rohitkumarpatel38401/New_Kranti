# KCTI — Kranti Computer Training Institute

Franchise management platform: public website, HQ admin panel, franchise institute panel, and student portal.

- Design: `docs/superpowers/specs/2026-09-28-kranti-franchise-platform-design.md`
- Plans: `docs/superpowers/plans/`

## Requirements

PHP 8.3+, Composer 2, Node 20+, MySQL 8 / MariaDB 10.6+ (XAMPP MariaDB 10.4 works for local development).

## Install

```bash
composer install
cp .env.example .env
php artisan key:generate
# create the database named in DB_DATABASE (default new_kranti), then:
php artisan kranti:install
php artisan serve
```

`kranti:install` asks for the Super Admin name, email, and password (or takes `--name`, `--email`, `--password`). It is safe to re-run: it creates the main institute `KCTI0001` and the Super Admin once and updates them afterwards.

| Area | URL | Login table |
|---|---|---|
| Admin (HQ) | `/admin` | `admins` |
| Institute (franchise) | `/institute` | `institute_users` |
| Student | `/student` | `students` (registration no. or email) |

## How tenancy works

- Every franchise-owned table has an `institute_id` and its model uses `App\Models\Concerns\BelongsToInstitute`.
- The institute and student panels set `App\Support\Tenancy\CurrentInstitute` for the logged-in user, so their queries only see that institute's rows.
- The admin panel never sets it, so HQ sees every institute. Every request starts with the context cleared (`ResetCurrentInstitute`).
- Admins with `impersonate_institute` can open an institute's panel as its owner from **Institutes → Login as Institute**; "Return to Admin" ends it. Both are recorded in the activity log.

## What HQ can do (admin panel)

| Menu | Purpose |
|---|---|
| Franchise Network → Institutes | Create/edit institutes (profile, office address), upload and verify documents, authorize courses with an optional HQ fee, change status with a reason (history kept), log in as the institute |
| Franchise Network → Franchise applications | Review website applications, mark contacted, reject with remarks, or convert into a live institute (owner login is emailed) |
| Academics | Sessions (one current), course categories, courses (bilingual, with subjects and marks), subjects, grade scale |
| HQ Team | Team members and roles |

The main institute `KCTI0001` can never be suspended, closed, or deleted. At least one active Super Admin always remains.

## Students, enrollment, and wallet

1. **Wallet:** every institute has a prepaid HQ wallet. The institute pays HQ (UPI/bank), then submits **Wallet → Recharge requests** with the UTR and a screenshot. HQ **Wallets → Recharge requests** approves (wallet credited once) or rejects with remarks. Set the payment details shown to institutes with `KCTI_PAYMENT_*` in `.env`.
2. **Admission:** the institute adds a student through a 5-step form (student, personal, guardians, address, education). A registration number like `KCTI0007-26-0045` is assigned. Aadhaar is stored encrypted, shown masked, and cannot be registered twice anywhere.
3. **Enrollment:** the institute enrolls the student in a course HQ has authorized, then **Submit to HQ** debits the course's HQ fee from the wallet (the main institute is never charged).
4. **Approval:** HQ **Students → Enrollments** approves (issues `KCTI/2026/000123` and opens the student portal; first password = date of birth `DDMMYYYY`, changed on first login) or rejects with a reason (fee refunded automatically).

HQ sees every student and enrollment with Institute and Main/Franchise filters and can export them (exports run on the queue: keep `php artisan queue:work` running). **Institutes → Adjust wallet** records manual credits/debits with a reason; **Create owner login** and **Email owner a password link** manage franchise owner access.

## Fees and attendance

- **Fee plan:** on an approved enrollment, **Fee plan** splits the course fee into instalments (they must add up to the fee). Without a plan the whole fee is due on the admission date.
- **Collect fee:** **Collect fee** on the enrollment issues a receipt such as `KCTI0007/R/26/00012` (printable PDF). A payment can never exceed the due. Mistakes are **cancelled with a reason** (needs `cancel_receipt`), never deleted; the amount becomes due again.
- **Dues:** **Fees → Fee dues** lists every approved enrollment that still owes money, with the next instalment and an Overdue badge. HQ sees the same across institutes, plus all receipts with a collected total and export.
- **Attendance:** **Attendance → Take attendance** — pick a batch and a day, mark Present / Absent / Late / Leave (or "All present"), save. Opening the same day again edits it; future dates are refused. Attendance % = (present + late) ÷ (present + late + absent).
- **Dashboard:** wallet balance (low-balance warning under ₹500), active students, fees collected this month, fees due, today's attendance.

## What an institute can do (institute panel)

- **Staff:** the owner (or anyone with `manage_staff`) adds receptionists, teachers, and accountants. The owner account cannot be removed or deactivated here.
- **Profile:** update name, phone, and password. While HQ is impersonating, email and password cannot be changed, and "Sign out" returns to the admin panel.

## Roles

- Admin guard (HQ team): `super_admin`, `manager`, `accounts`, `academic`, `support` — edited in **Admin → Roles**. Defaults: manager handles institutes and applications; academic handles the catalogue; accounts handles wallet approvals (Phase 3).
- Institute guard (franchise team): `owner`, `receptionist`, `teacher`, `accountant` — defined in `app/Support/Permissions/InstitutePermissions.php` and synced by `RolesAndPermissionsSeeder`.

## Deployment warning: never change APP_KEY

Aadhaar numbers are encrypted with `APP_KEY`, and their duplicate check uses an HMAC keyed with it. Changing the key makes stored Aadhaar numbers unreadable (`APP_PREVIOUS_KEYS` can rescue decryption) and breaks duplicate detection (nothing rescues the hash). Back up `.env` with the database.

## Bilingual content

Bilingual columns store `{"en": "...", "hi": "..."}`. Admin forms show English and Hindi inputs side by side; `$model->localized('name')` returns the current language with English fallback.

## Tests

```bash
./vendor/bin/pest
```

Tests run on SQLite in memory. Format code with `./vendor/bin/pint`.
