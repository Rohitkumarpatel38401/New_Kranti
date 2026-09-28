# KCTI Phase 2 — Institutes & HQ Academic Implementation Plan

> **For agentic workers:** Execute inline with superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax. Every task is test-first: write the listed tests, watch them fail, implement, watch them pass, commit.

**Goal:** HQ can run the franchise network: full institute management (profile, address, documents, status lifecycle with history), franchise applications converted into live institutes with an owner login, HQ team management, the academic catalogue (sessions, categories, courses, subjects, course subjects, grade scales), which courses each franchise may teach, and institute-side staff management with roles.

**Architecture:** Builds on Phase 1 (branch `phase-1-foundation`). Business rules live in `app/Services/*` and run inside DB transactions; Filament resources call services. Every admin resource has a policy (the admin panel uses strict authorization) based on Shield keys `Action:Model`. Bilingual text is a JSON column with `en`/`hi` keys read through `HasLocalizedText`.

**Tech Stack:** Laravel 13, Filament 5, Spatie permission + Shield, Pest. No new packages.

**Spec:** `docs/superpowers/specs/2026-09-28-kranti-franchise-platform-design.md` — §4.3, §4.4, §5, §6.1, §7 (institute view), §12 row 2.

## Global Constraints

- Everything from Phase 1's Global Constraints still applies (KCTI codes, guards, money, enums, commit trailer).
- New institute codes come from `NumberSequenceService::nextInstituteCode()`; franchise application numbers from key `franchise_application` formatted `KCTI/FA/{yyyy}/{seq5}`.
- Admin resource permission keys: `ViewAny|View|Create|Update|Delete|DeleteAny|Restore|RestoreAny:{Model}` (Shield pascal format).
- Institute-panel resources are tenant-scoped by `BelongsToInstitute`; institute permissions stay code-defined in `InstitutePermissions`.
- Translatable columns store `{"en": "...", "hi": "..."}`; English required, Hindi optional; reads fall back to English.

### Decisions (rulings made while planning)

1. **No `spatie/laravel-translatable`.** Filament's `TextInput::make('name.en')` binds directly to an `array`-cast JSON column, giving side-by-side EN/HI inputs (spec §8.3) without the package's locale-switcher UX. `HasLocalizedText::localized('name')` gives the current-locale value with English fallback.
2. **Owners are never soft-deleted.** Ownership must be transferred first (policy + service guard). This resolves the Phase 1 finding that a soft-deleted owner would block a new owner via `owner_marker`. Emails stay globally unique including trashed rows (restore instead of recreate).
3. **Main institute protection is enforced in `InstituteService`,** not only policies, because Super Admin bypasses policies via `Gate::before`.
4. **Wallet creation on conversion is deferred to Phase 3** (the `wallets` table arrives there; Phase 3 backfills existing institutes).
5. **Institute panel "Sign out" while impersonating** ends the impersonation (returns to admin) instead of invalidating the whole session.

## Review Focus

1. **Converting the same franchise application twice (double click / two tabs)** → exactly one institute and one owner; the second attempt fails cleanly. Pinned in Task 6.
2. **Suspending or closing the main institute, even as Super Admin** → refused with a clear message. Pinned in Task 2.
3. **Institute owner tries to delete/deactivate themselves or give a staff member a role from the admin guard** → refused. Pinned in Task 8.
4. **Two grade-scale ranges overlap or two sessions are marked current** → refused / previous current is unset. Pinned in Task 4.
5. **An institute user reaching another institute's staff through the Livewire table (search/pagination)** → never sees them. Pinned in Task 8 (end-to-end Livewire isolation test).

---

## File Structure

```text
app/Models/Concerns/HasLocalizedText.php, HasAddresses.php
app/Models/Address.php, InstituteProfile.php, InstituteDocument.php, InstituteStatusHistory.php,
          AcademicSession.php, CourseCategory.php, Course.php, Subject.php, CourseSubject.php, GradeScale.php,
          FranchiseApplication.php
app/Enums/AddressType.php, DocumentType.php, VerificationStatus.php, DurationUnit.php, FranchiseApplicationStatus.php
app/Services/InstituteService.php, FranchiseOnboardingService.php, AcademicSessionService.php, GradeScaleService.php, StaffService.php
app/Exceptions/DomainRuleException.php
app/Policies/ShieldPolicy.php (abstract) + one policy per admin model; InstituteUserPolicy (institute panel)
app/Notifications/InstituteOwnerCredentials.php
app/Filament/Shared/LocalizedFields.php
app/Filament/Admin/Resources/{Institutes,FranchiseApplications,Admins,AcademicSessions,CourseCategories,Courses,Subjects,GradeScales}/…
app/Filament/Institute/Resources/Staff/…, app/Filament/Institute/Pages/Auth/EditProfile.php
database/migrations/2026_09_29_*.php, factories, seeders update
tests/Feature/Phase2/*.php
```

---

### Task 1: Localized text + shared helpers

**Files:** `app/Models/Concerns/HasLocalizedText.php`, `app/Filament/Shared/LocalizedFields.php`, `app/Exceptions/DomainRuleException.php`, `app/Policies/ShieldPolicy.php`; tests `tests/Feature/Phase2/LocalizedTextTest.php`, `tests/Feature/Phase2/ShieldPolicyTest.php`.

**Interfaces (produces):**
- `HasLocalizedText::localized(string $attribute, ?string $locale = null): ?string` — value for `$locale ?? app()->getLocale()`, falling back to `en`, then null.
- `LocalizedFields::make(string $name, string $label, bool $textarea = false, bool $required = true): array` — returns `[TextInput|Textarea "{$name}.en" (required when $required), TextInput|Textarea "{$name}.hi"]` labelled "{label} (English)" / "{label} (हिन्दी)".
- `DomainRuleException extends RuntimeException` — message shown to users; Filament actions catch it and send a danger notification.
- `abstract ShieldPolicy` — `protected static string $subject` (e.g. `'Course'`); implements `viewAny, view, create, update, delete, deleteAny, restore, restoreAny` as `$user instanceof Admin && $user->can("{Action}:{subject}")`; `forceDelete*, replicate, reorder` return false.

**Tests:**
- localized returns hi when locale hi and present; falls back to en when hi missing/blank; returns null when column null.
- LocalizedFields makes two components with state paths `name.en` / `name.hi`, first required.
- a concrete test policy (`subject = 'Institute'`) allows an admin with `ViewAny:Institute`, denies one without, denies an `InstituteUser` holding a same-named permission.

**Also:** refactor Phase 1 `InstitutePolicy` to extend `ShieldPolicy` (keep `delete` main-institute rule). Existing tests must stay green.

---

### Task 2: Institute detail tables and InstituteService

**Files:** migrations `addresses`, `institute_profiles`, `institute_documents`, `institute_status_histories`; models `Address`, `InstituteProfile`, `InstituteDocument`, `InstituteStatusHistory`; `HasAddresses` trait; enums `AddressType` (office/current/permanent), `DocumentType` (owner_aadhaar, owner_pan, gst_certificate, rent_agreement, center_photo, other), `VerificationStatus` (pending/verified/rejected); `app/Services/InstituteService.php`; tests `tests/Feature/Phase2/InstituteServiceTest.php`.

Columns exactly as spec §4.3 (addresses unique `(addressable_type, addressable_id, type)`; profile unique `institute_id`; status history immutable — model throws on update/delete).

**Interfaces (produces):**
- `Institute`: `profile(): HasOne`, `documents(): HasMany`, `statusHistories(): HasMany`, `users(): HasMany(InstituteUser)`, `owner(): HasOne` (is_owner), `courses(): BelongsToMany` (added Task 5), plus `HasAddresses` (`addresses(): MorphMany`, `address(AddressType $type): ?Address`, `saveAddress(AddressType $type, array $data): Address`).
- `InstituteService`:
  - `create(array $data, array $profile = [], array $address = [], ?Admin $by = null): Institute` — code from sequence, slug from name (unique, suffixed), status `active`, `approved_*` set, profile + office address saved, history row `null → active`.
  - `changeStatus(Institute $institute, InstituteStatus $to, string $reason, Admin $by): void` — throws `DomainRuleException` when institute is main and `$to` ≠ active, when `$to` equals current status, or reason blank; writes history row; sets `approved_at/by` the first time it becomes active.
  - `delete(Institute $institute, Admin $by): void` — refuses main institute and institutes with users other than the owner or with students; otherwise soft deletes.

**Tests:** create assigns next code and writes history; slug collision gets suffix; changeStatus writes from/to/reason/actor; main institute cannot be suspended/closed even by super admin (Review Focus 2); same-status change refused; blank reason refused; history rows cannot be updated or deleted; delete refuses main and institutes with students.

---

### Task 3: Admin institute management UI

**Files:** replace `app/Filament/Admin/Resources/Institutes/*` with full resource: `InstituteResource` (form, table, infolist), pages `ListInstitutes`, `CreateInstitute`, `EditInstitute`, `ViewInstitute`; relation managers `DocumentsRelationManager`, `UsersRelationManager` (read-only list + "reset owner password" deferred), `StatusHistoriesRelationManager` (read-only); tests `tests/Feature/Phase2/AdminInstituteResourceTest.php`.

- Create page calls `InstituteService::create` (override `handleRecordCreation`), edit page saves institute + profile + office address.
- Table: code, name, city (office address), status badge, main icon, owner name, filters status + state; row actions: Login as Institute (Phase 1), Suspend / Activate / Close (modal with required reason → `changeStatus`, visible per permission `suspend_institute`, hidden for main institute).
- Document upload to `local` disk under `institutes/{id}/documents`, max 5 MB, pdf/jpg/png; verify/reject actions set `verification_status`, `verified_by_admin_id`, `verified_at`.

**Tests:** manager (with Create/Update:Institute) creates an institute via Livewire → code `KCTI0002`, profile & address saved; list shows city and owner; suspend action requires reason and writes history; suspend action hidden for main institute; support admin gets 403 on create page; view page shows status history.

---

### Task 4: Academic catalogue (HQ)

**Files:** migrations `academic_sessions`, `course_categories`, `courses`, `subjects`, `course_subjects`, `grade_scales` (spec §4.4 columns); enums `DurationUnit`; models + factories; `AcademicSessionService` (`makeCurrent(AcademicSession)` unsets others in a transaction), `GradeScaleService` (`assertNoOverlap(float $min, float $max, ?int $ignoreId)`); admin resources `AcademicSessions`, `CourseCategories`, `Courses` (with `SubjectsRelationManager` for course_subjects), `Subjects`, `GradeScales`; policies extending `ShieldPolicy`; tests `tests/Feature/Phase2/AcademicCatalogueTest.php`.

- Course/category/subject names, descriptions, eligibility use `LocalizedFields`; models use `HasLocalizedText`.
- Course `code` stored uppercase; slug from English name.
- Course subject form: subject select (active subjects), term_no, sort_order, theory_max, practical_max, pass_marks (≤ theory_max + practical_max).

**Tests:** makeCurrent leaves exactly one current session (Review Focus 4); creating a session with is_current=true via the resource unsets the old one; overlapping grade scale refused, touching boundaries (0–39.99 and 40–100) allowed; course code uppercased and unique; course subject duplicate refused; pass_marks > total refused; course name shows Hindi under `hi` locale with English fallback; academic role can manage courses, manager cannot.

---

### Task 5: Authorized courses per institute

**Files:** migration `institute_course` (spec §4.3); `Institute::courses()` BelongsToMany with pivot `hq_fee_override`, `is_active`, timestamps; `Institute::authorizedCourses()` (pivot active + course active); admin `CoursesRelationManager` on institute (attach with fee override, toggle active, detach); tests `tests/Feature/Phase2/AuthorizedCoursesTest.php`.

**Interfaces (produces):** `Institute::hqFeeFor(Course $course): string` — override when set else `courses.hq_registration_fee` (decimal string); throws `DomainRuleException` when the course is not authorized.

**Tests:** attach/detach via relation manager; duplicate attach refused; inactive pivot or inactive course excluded from `authorizedCourses`; `hqFeeFor` uses override then default; unauthorized course throws.

---

### Task 6: Franchise applications and conversion

**Files:** migration `franchise_applications` (spec §4.3) + enum `FranchiseApplicationStatus` (new/contacted/approved/rejected); model + factory (with office address via `HasAddresses`); `FranchiseOnboardingService`; notification `InstituteOwnerCredentials` (mail; includes login URL and temporary password); admin resource `FranchiseApplications` (list/view; actions Mark contacted, Reject (remarks required), Convert to Institute (modal: institute name prefilled, owner email/phone prefilled)); tests `tests/Feature/Phase2/FranchiseOnboardingTest.php`.

**Interfaces (produces):**
- `FranchiseOnboardingService::submit(array $data, array $address): FranchiseApplication` — number `KCTI/FA/{yyyy}/{seq5}`, status new. (Website form in Phase 6 calls this.)
- `markContacted(FranchiseApplication, Admin)`, `reject(FranchiseApplication, string $remarks, Admin)`.
- `convert(FranchiseApplication $application, Admin $by, array $overrides = []): Institute` — locks the application row (`lockForUpdate`), refuses unless status is new/contacted; calls `InstituteService::create` with profile (owner name/phone/email, computers_count, area_sqft) and the application's address; creates owner `InstituteUser` (`is_owner`, random 12-char password, role `owner`); sets application approved + reviewer + `converted_institute_id`; sends `InstituteOwnerCredentials` after commit.

**Tests:** convert creates institute, profile, address copy, owner with owner role, application approved; mail sent to owner (Notification::fake); converting twice (sequential second call) throws and leaves one institute (Review Focus 1); converting a rejected application refused; owner email already used by another institute user → refused with a clear message and nothing created; reject requires remarks; permission `approve_franchise` required for convert action.

---

### Task 7: HQ team (admins) management

**Files:** admin resource `Admins` (name, email, phone, password on create / optional on edit, is_active, roles select limited to admin guard); `AdminPolicy` (all abilities require `manage_admins`); service rules in `app/Services/AdminTeamService.php`; tests `tests/Feature/Phase2/AdminTeamTest.php`.

**Rules:** cannot deactivate or delete yourself; cannot remove the `super_admin` role from, deactivate, or delete the last active super admin; `super_admin` role cannot be deleted (RolePolicy delete returns false for it and `Gate::before` is bypassed by checking in `EditRole`/`DeleteAction` via service — implement as a `Role` model `deleting` listener that throws `DomainRuleException` for `super_admin`).

**Tests:** manage_admins admin creates an admin with role accounts; without manage_admins → 403; last super admin cannot be deactivated/deleted/demoted; self-deactivation refused; deleting the super_admin role throws even for a super admin; password is hashed and optional on edit.

---

### Task 8: Institute staff, profile, and impersonation sign-out

**Files:** institute resource `Staff` (model `InstituteUser`, tenant-scoped): name, email, phone, password (create; optional on edit), is_active, role select from institute-guard roles except `owner`; `InstituteUserPolicy` (viewAny/view: any active institute user; create/update/delete: `manage_staff`; delete/deactivate refused for owner and self); `StaffService` (`create`, `update`, `delete` enforcing rules and syncing a single institute role); `app/Filament/Institute/Pages/Auth/EditProfile.php` (name, phone, password; password/email fields disabled while impersonating); institute panel `->profile(EditProfile::class)`; custom logout for the institute panel: when impersonating, "Sign out" redirects to `impersonation.leave`; tests `tests/Feature/Phase2/InstituteStaffTest.php`.

**Tests:** owner creates a teacher → role teacher on institute guard, institute_id auto-filled; assigning `owner` or an admin-guard role refused (Review Focus 3); receptionist (no manage_staff) cannot create; owner cannot delete/deactivate self or the owner record; staff list via Livewire (`Livewire::test(ListStaff::class)` with search and pagination) never includes another institute's users (Review Focus 5); profile page hides password change while impersonating; logout while impersonating keeps the admin logged in and ends impersonation.

---

### Task 9: Seed defaults, permissions for new resources, wrap-up

**Files:** `AdminPermissions::ROLE_PERMISSIONS` gains resource keys (manager: Institute*, FranchiseApplication*; academic: AcademicSession*, CourseCategory*, Course*, Subject*, GradeScale*); `AcademicDefaultsSeeder` (grade scales A+…F per a standard table, current session `{yyyy}-{yy+1}`) called by installer; README update; tests `tests/Feature/Phase2/SeedingTest.php`.

**Tests:** seeder idempotent; installer creates one current session and non-overlapping grade scales; manager/academic roles can open their resources, support cannot.

Default grade scale: A+ 90–100, A 75–89.99, B 60–74.99, C 45–59.99, D 33–44.99, F 0–32.99 (remarks en/hi: Outstanding/उत्कृष्ट, Excellent/श्रेष्ठ, Very Good/बहुत अच्छा, Good/अच्छा, Pass/उत्तीर्ण, Fail/अनुत्तीर्ण).

Final: full suite, Pint, MariaDB `migrate:fresh` + `kranti:install`, final whole-branch review.
