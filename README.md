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

## What an institute can do (institute panel)

- **Staff:** the owner (or anyone with `manage_staff`) adds receptionists, teachers, and accountants. The owner account cannot be removed or deactivated here.
- **Profile:** update name, phone, and password. While HQ is impersonating, email and password cannot be changed, and "Sign out" returns to the admin panel.

## Roles

- Admin guard (HQ team): `super_admin`, `manager`, `accounts`, `academic`, `support` — edited in **Admin → Roles**. Defaults: manager handles institutes and applications; academic handles the catalogue; accounts handles wallet approvals (Phase 3).
- Institute guard (franchise team): `owner`, `receptionist`, `teacher`, `accountant` — defined in `app/Support/Permissions/InstitutePermissions.php` and synced by `RolesAndPermissionsSeeder`.

## Bilingual content

Bilingual columns store `{"en": "...", "hi": "..."}`. Admin forms show English and Hindi inputs side by side; `$model->localized('name')` returns the current language with English fallback.

## Tests

```bash
./vendor/bin/pest
```

Tests run on SQLite in memory. Format code with `./vendor/bin/pint`.
