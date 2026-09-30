# Demo Install, Login-as Student, Direct Admission — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans. The user asked to implement straight after the spec.

**Goal:** One-command install with default credentials and a full demo dataset, login-form prefill on local, one-click "Login as student" for HQ and institute owners, and a single-form direct admission in both panels.

**Spec:** docs/superpowers/specs/2026-10-02-demo-install-login-as-admission-design.md

## Global Constraints

- Default admin: `Kranti Admin` / `admin@example.com` / `admin123`.
- Demo institute owner: `institue@example.com` / `admin123`.
- Demo student: `student@example.com` / `admin123`.
- Demo data auto-loads only when local; any other environment needs `--demo`. Prefill happens only when local.
- Business records are created through the services; the seeder can safely run twice.

## Review Focus

1. Running the installer or `DemoSeeder` twice → no duplicates, no crash.
2. A production install without `--demo` → no demo accounts, and no prefill ever.
3. An institute owner tries to log in as another institute's student → 403.
4. An admin admits a student into a franchise whose wallet is short → nothing is saved and a clear error is shown.
5. Leaving an impersonation returns you to the right panel with your own session intact.

### Task 1: Installer defaults + DemoSeeder + prefill
Files: `app/Console/Commands/InstallCommand.php`, `database/seeders/DemoSeeder.php` (+ `DemoContentSeeder` for website), `config/kcti.php` (demo creds + prefill flag), `app/Support/DemoLogin.php`, admin/institute custom Login pages, student Login mount. Tests: `tests/Feature/Demo/InstallDemoTest.php`, `tests/Feature/Demo/DemoSeederTest.php`, `tests/Feature/Demo/PrefillTest.php`.

### Task 2: Login as student
Files: `app/Services/StudentImpersonationService.php`, controller + routes, permissions (`impersonate_student` admin + institute), actions on the admin and institute student tables, student panel banner, and the must-change skip. Test: `tests/Feature/Demo/StudentImpersonationTest.php`.

### Task 3: Direct admission
Files: `EnrollmentService::admitDirectly`, `app/Filament/Admin/Pages/NewAdmission.php`, `app/Filament/Institute/Pages/NewAdmission.php` (a shared form builder). Test: `tests/Feature/Demo/DirectAdmissionTest.php`.

### Task 4: Wrap-up
README "Quick start / demo logins", a full suite run, a MariaDB fresh install with the three logins, and a final review.
