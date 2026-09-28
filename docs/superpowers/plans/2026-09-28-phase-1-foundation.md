# KCTI Phase 1 — Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up the Laravel 13 + Filament 5 skeleton for Kranti Computer Training Institute (KCTI): three login tables/guards, three branded panels, row-level tenancy, number sequences, roles/permissions for both staff guards, an idempotent installer that creates the main institute and Super Admin, and admin → institute impersonation.

**Architecture:** One MySQL database. `admins`, `institute_users`, `students` are separate authenticatable models on guards `admin`, `institute`, `student`, each bound to its own Filament panel. Tenant models use a `BelongsToInstitute` trait whose global scope is driven by an explicit `CurrentInstitute` context that only the institute and student panels set (via persistent auth middleware), so the admin panel always sees every institute even when the same browser is also impersonating.

**Tech Stack:** PHP 8.3, Laravel 13, Filament 5, spatie/laravel-permission, bezhansalleh/filament-shield 4.3+, spatie/laravel-activitylog, Pest, MariaDB/MySQL (XAMPP) for dev, SQLite in-memory for tests.

**Spec:** `docs/superpowers/specs/2026-09-28-kranti-franchise-platform-design.md` (Phase 1 row of §12; architecture §3; tables §4.2, §4.3 `institutes`, §4.12 `number_sequences`; roles §5; installer §10; impersonation §6.8).

## Global Constraints

- Full name **Kranti Computer Training Institute**, short name **KCTI**; `APP_NAME="Kranti Computer Training Institute"`; panel brand name `KCTI`.
- Institute codes `KCTI0001`, `KCTI0002`, … (prefix `KCTI` + 4-digit zero-padded sequence `institute_code`). Main institute is `KCTI0001`.
- Timezone `Asia/Kolkata`; default locale `en`, fallback `en`.
- Money `decimal(12,2)`; statuses are PHP backed string enums stored as `varchar`.
- Panel paths/guards: `/admin` → `admin`/`admins`; `/institute` → `institute`/`institute_users`; `/student` → `student`/`students`.
- Panel theme: primary `#4f46e5`, font `Plus Jakarta Sans`, Filament built-in light/dark/system switcher left enabled.
- Tenant tables have non-null `institute_id` FK with `restrictOnDelete` and indexes starting with `institute_id`.
- Exactly one `is_main` institute (unique generated `main_marker`); at most one `is_owner` user per institute (unique generated `owner_marker`).
- Admin-guard roles: `super_admin`, `manager`, `accounts`, `academic`, `support`. Institute-guard roles: `owner`, `receptionist`, `teacher`, `accountant`.
- Admin custom permissions: `approve_franchise, impersonate_institute, suspend_institute, approve_enrollment, approve_wallet_recharge, adjust_wallet, publish_result, issue_certificate, revoke_certificate, manage_admins`.
- Institute custom permissions: `submit_enrollment, collect_fee, cancel_receipt, take_attendance, enter_marks, submit_result, request_wallet_recharge, manage_staff`.
- Every commit message ends with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- Commands are run from `D:\Laravel\New_Kranti` in Git Bash.

### Spec clarifications decided in this plan

1. Institute-guard role → permission mapping is defined in code (`App\Support\Permissions\InstitutePermissions`) and synced by the seeder. The Shield role editor is registered **only in the admin panel** and manages admin-guard roles. Reason: Spatie roles are global (teams disabled), so letting any institute owner edit the shared `owner`/`teacher` roles would change them for every franchise.
2. The institute owner gets every institute permission through the `owner` role (not a `Gate::before` bypass), so policies can still enforce same-institute ownership.
3. In Phase 1, students can access `/student` when `can_login` is true and their institute is `active`. The "has an approved enrollment" condition is added in Phase 3 when `enrollments` exists.
4. The institute panel has no profile page in Phase 1, so "block password change while impersonating" is carried to Phase 2 (where the profile page is added).

## Review Focus

1. **Admin also impersonating in the same browser** → admin panel pages must still show all institutes (no tenant scope leaks into `/admin`). Pinned in Task 6 (`CurrentInstitute` is set only by panel middleware) and Task 11.
2. **Institute suspended while its user is logged in** → next request to `/institute` returns 403. Pinned in Task 5.
3. **Installer re-run on an installed database** → no duplicate main institute, super admin updated not duplicated, roles not duplicated. Pinned in Task 10.
4. **Two requests take the same sequence at once / a brand-new key** → values are unique and start at 1. Pinned in Task 7.
5. **Student logs in with registration number in lowercase or with surrounding spaces** → login succeeds (input normalized to trimmed uppercase). Pinned in Task 8.

---

## File Structure

```text
app/
├── Enums/InstituteStatus.php, StudentStatus.php, AdmissionSource.php
├── Models/
│   ├── Concerns/BelongsToInstitute.php
│   ├── Admin.php, InstituteUser.php, Student.php, Institute.php
├── Support/
│   ├── Tenancy/CurrentInstitute.php
│   └── Permissions/AdminPermissions.php, InstitutePermissions.php
├── Services/NumberSequenceService.php, ImpersonationService.php
├── Http/
│   ├── Middleware/SetCurrentInstitute.php
│   └── Controllers/ImpersonationController.php
├── Filament/
│   ├── Admin/Resources/Institutes/InstituteResource.php (+ Pages/ListInstitutes.php)
│   ├── Student/Pages/Auth/Login.php
│   └── (Institute/, Shared/ created empty for later phases)
├── Providers/Filament/AdminPanelProvider.php, InstitutePanelProvider.php, StudentPanelProvider.php
└── Console/Commands/InstallCommand.php
config/auth.php (guards/providers/brokers), config/permission.php, config/filament-shield.php
database/migrations/… (admins, institutes, institute_users, students, number_sequences, reset tokens)
database/factories/AdminFactory.php, InstituteFactory.php, InstituteUserFactory.php, StudentFactory.php
database/seeders/RolesAndPermissionsSeeder.php, DatabaseSeeder.php
resources/views/filament/impersonation-banner.blade.php
routes/web.php
tests/Feature/… (one file per task, named below)
```

---

### Task 1: Scaffold Laravel 13, Pest, base configuration

**Files:**
- Create: whole Laravel skeleton in project root (keeps existing `docs/` and `.git/`)
- Modify: `.env`, `.env.example`, `config/app.php` (via env only), `phpunit.xml`
- Test: `tests/Feature/SmokeTest.php`

**Interfaces:**
- Produces: working `php artisan`, `vendor/bin/pest`, MySQL dev DB `new_kranti`, SQLite in-memory test DB.

- [ ] **Step 1: Create the skeleton in a temp dir and move it in**

```bash
cd /d/Laravel
composer create-project laravel/laravel:^13.0 kcti_tmp --prefer-dist --no-interaction
cp -rn kcti_tmp/. New_Kranti/
rm -rf kcti_tmp
cd New_Kranti && php artisan --version
```
Expected: `Laravel Framework 13.x.x`.

- [ ] **Step 2: Install Pest**

```bash
composer remove phpunit/phpunit --dev --no-interaction
composer require pestphp/pest pestphp/pest-plugin-laravel --dev -W --no-interaction
./vendor/bin/pest --init
```
Expected: `tests/Pest.php` exists.

- [ ] **Step 3: Configure Pest to refresh the database for feature tests**

Replace `tests/Pest.php` with:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
```

- [ ] **Step 4: Configure environment**

In `.env` and `.env.example` set:

```dotenv
APP_NAME="Kranti Computer Training Institute"
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Kolkata
APP_LOCALE=en
APP_FALLBACK_LOCALE=en

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=new_kranti
DB_USERNAME=root
DB_PASSWORD=
```

In `config/app.php` make timezone read the env: `'timezone' => env('APP_TIMEZONE', 'Asia/Kolkata'),`.

In `phpunit.xml` `<php>` ensure:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

Create the dev database (XAMPP MySQL must be running):

```bash
/c/xampp/mysql/bin/mysql.exe -uroot -e "CREATE DATABASE IF NOT EXISTS new_kranti CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

- [ ] **Step 5: Write the smoke test**

`tests/Feature/SmokeTest.php`:

```php
<?php

it('boots with KCTI configuration', function () {
    expect(config('app.name'))->toBe('Kranti Computer Training Institute')
        ->and(config('app.timezone'))->toBe('Asia/Kolkata')
        ->and(config('database.default'))->toBe('sqlite');
});
```

Delete the default `tests/Feature/ExampleTest.php` and `tests/Unit/ExampleTest.php`.

Note: `phpunit.xml` does not set `APP_NAME`, so the test reads `.env`. If the test run shows `Laravel`, add `<env name="APP_NAME" value="Kranti Computer Training Institute"/>` to `phpunit.xml`.

- [ ] **Step 6: Run tests**

Run: `./vendor/bin/pest`
Expected: 1 passed.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "chore: scaffold Laravel 13 with Pest and KCTI base config

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2: Institutes table, enum, model, factory

**Files:**
- Create: `app/Enums/InstituteStatus.php`, `app/Models/Institute.php`, `database/factories/InstituteFactory.php`, `database/migrations/2026_09_28_000100_create_institutes_table.php`
- Test: `tests/Feature/InstituteModelTest.php`

**Interfaces:**
- Produces: `App\Enums\InstituteStatus` (`Pending='pending'`, `Active='active'`, `Suspended='suspended'`, `Expired='expired'`, `Closed='closed'`; method `label(): string`); `App\Models\Institute` with casts (`status` → enum, `is_main`/`wallet_exempt` → bool, dates), scope `main()`, static `main(): ?Institute` helper named `Institute::mainInstitute()`, method `isActive(): bool`; `InstituteFactory` with state `main()` and `suspended()`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/InstituteModelTest.php`:

```php
<?php

use App\Enums\InstituteStatus;
use App\Models\Institute;
use Illuminate\Database\QueryException;

it('casts status and flags', function () {
    $institute = Institute::factory()->create();

    expect($institute->status)->toBe(InstituteStatus::Active)
        ->and($institute->is_main)->toBeFalse()
        ->and($institute->isActive())->toBeTrue();
});

it('allows only one main institute', function () {
    Institute::factory()->main()->create();

    // different code and slug, so only main_marker can block it
    expect(fn () => Institute::factory()->main()->create(['code' => 'KCTI9999', 'slug' => 'other-main']))
        ->toThrow(QueryException::class);
});

it('allows many non-main institutes', function () {
    Institute::factory()->count(3)->create();

    expect(Institute::count())->toBe(3);
});

it('finds the main institute', function () {
    Institute::factory()->create();
    $main = Institute::factory()->main()->create();

    expect(Institute::mainInstitute()?->is($main))->toBeTrue();
});

it('is not active when suspended', function () {
    expect(Institute::factory()->suspended()->create()->isActive())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/InstituteModelTest.php`
Expected: FAIL — `Class "App\Models\Institute" not found`.

- [ ] **Step 3: Write the enum**

`app/Enums/InstituteStatus.php`:

```php
<?php

namespace App\Enums;

enum InstituteStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Expired = 'expired';
    case Closed = 'closed';

    public function label(): string
    {
        return __('enums.institute_status.'.$this->value);
    }
}
```

Create `lang/en/enums.php` and `lang/hi/enums.php`:

```php
<?php
// lang/en/enums.php
return [
    'institute_status' => [
        'pending' => 'Pending', 'active' => 'Active', 'suspended' => 'Suspended',
        'expired' => 'Expired', 'closed' => 'Closed',
    ],
    'student_status' => [
        'active' => 'Active', 'completed' => 'Completed', 'dropped' => 'Dropped', 'suspended' => 'Suspended',
    ],
    'admission_source' => [
        'website' => 'Website', 'walk_in' => 'Walk-in', 'franchise' => 'Franchise',
    ],
];
```

```php
<?php
// lang/hi/enums.php
return [
    'institute_status' => [
        'pending' => 'लंबित', 'active' => 'सक्रिय', 'suspended' => 'निलंबित',
        'expired' => 'समाप्त', 'closed' => 'बंद',
    ],
    'student_status' => [
        'active' => 'सक्रिय', 'completed' => 'पूर्ण', 'dropped' => 'छोड़ दिया', 'suspended' => 'निलंबित',
    ],
    'admission_source' => [
        'website' => 'वेबसाइट', 'walk_in' => 'सीधा प्रवेश', 'franchise' => 'फ्रैंचाइज़ी',
    ],
];
```

- [ ] **Step 4: Write the migration**

`database/migrations/2026_09_28_000100_create_institutes_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_main')->default(false);
            $table->unsignedTinyInteger('main_marker')->nullable()
                ->storedAs('case when is_main = 1 then 1 else null end')
                ->unique();
            $table->string('status', 20)->default('pending')->index();
            $table->string('email')->nullable()->index();
            $table->string('phone', 20)->nullable()->index();
            $table->string('alt_phone', 20)->nullable();
            $table->string('website')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('registration_no', 100)->nullable()->unique();
            $table->string('gst_no', 20)->nullable()->unique();
            $table->string('pan_no', 20)->nullable();
            $table->date('established_on')->nullable();
            $table->date('franchise_start_on')->nullable();
            $table->date('franchise_expiry_on')->nullable()->index();
            $table->boolean('wallet_exempt')->default(false);
            $table->unsignedBigInteger('approved_by_admin_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institutes');
    }
};
```

The FK for `approved_by_admin_id` is added in Task 3 after `admins` exists.

- [ ] **Step 5: Write the model**

`app/Models/Institute.php`:

```php
<?php

namespace App\Models;

use App\Enums\InstituteStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Institute extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id', 'main_marker'];

    protected function casts(): array
    {
        return [
            'status' => InstituteStatus::class,
            'is_main' => 'boolean',
            'wallet_exempt' => 'boolean',
            'established_on' => 'date',
            'franchise_start_on' => 'date',
            'franchise_expiry_on' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function scopeMain(Builder $query): Builder
    {
        return $query->where('is_main', true);
    }

    public static function mainInstitute(): ?self
    {
        return static::query()->main()->first();
    }

    public function isActive(): bool
    {
        return $this->status === InstituteStatus::Active;
    }
}
```

- [ ] **Step 6: Write the factory**

`database/factories/InstituteFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\InstituteStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class InstituteFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company().' Computer Centre';

        return [
            'code' => 'KCTI'.str_pad((string) fake()->unique()->numberBetween(2, 9999), 4, '0', STR_PAD_LEFT),
            'name' => $name,
            'slug' => Str::slug($name),
            'is_main' => false,
            'status' => InstituteStatus::Active,
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('9#########'),
            'wallet_exempt' => false,
        ];
    }

    public function main(): static
    {
        return $this->state(fn () => [
            'code' => 'KCTI0001',
            'name' => 'Kranti Computer Training Institute',
            'slug' => 'kranti-computer-training-institute',
            'is_main' => true,
            'wallet_exempt' => true,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => InstituteStatus::Suspended]);
    }
}
```

- [ ] **Step 7: Run tests**

Run: `./vendor/bin/pest tests/Feature/InstituteModelTest.php`
Expected: 5 passed.

- [ ] **Step 8: Verify the generated column on MySQL**

```bash
php artisan migrate:fresh --force
php artisan tinker --execute="App\Models\Institute::factory()->main()->create(); try { App\Models\Institute::factory()->main()->create(['code'=>'KCTI9999','slug'=>'x']); echo 'NO ERROR'; } catch (\Throwable \$e) { echo 'BLOCKED'; }"
```
Expected output ends with `BLOCKED`. Then `php artisan migrate:fresh --force` to clean.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: add institutes table with single main institute constraint

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3: Three authenticatable tables, models, guards, and password brokers

**Files:**
- Create: `app/Enums/StudentStatus.php`, `app/Enums/AdmissionSource.php`, `app/Models/Admin.php`, `app/Models/InstituteUser.php`, `app/Models/Student.php`, factories `AdminFactory.php`, `InstituteUserFactory.php`, `StudentFactory.php`, migrations `2026_09_28_000200_create_admins_table.php`, `2026_09_28_000300_create_institute_users_table.php`, `2026_09_28_000400_create_students_table.php`, `2026_09_28_000500_create_password_reset_tables.php`
- Modify: `config/auth.php`; delete `app/Models/User.php`, `database/factories/UserFactory.php`, and the `users`/`password_reset_tokens` parts of `0001_01_01_000000_create_users_table.php` (keep the `sessions` table there)
- Test: `tests/Feature/AuthModelsTest.php`

**Interfaces:**
- Consumes: `Institute`, `InstituteStatus` (Task 2).
- Produces:
  - `App\Models\Admin` (guard `admin`), `App\Models\InstituteUser` (guard `institute`, `institute(): BelongsTo`), `App\Models\Student` (guard `student`, `institute(): BelongsTo`, accessor `full_name`).
  - `App\Enums\StudentStatus` (`Active`, `Completed`, `Dropped`, `Suspended`), `App\Enums\AdmissionSource` (`Website='website'`, `WalkIn='walk_in'`, `Franchise='franchise'`), both with `label()`.
  - Factories: `AdminFactory` (state `inactive()`), `InstituteUserFactory` (states `owner()`, `inactive()`), `StudentFactory` (states `canLogin()`; default `can_login=false`).
  - All three factories use password `password`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/AuthModelsTest.php`:

```php
<?php

use App\Models\Admin;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;

it('maps each guard to its own model', function (string $guard, string $model) {
    expect(Auth::guard($guard)->getProvider()->getModel())->toBe($model);
})->with([
    ['admin', Admin::class],
    ['institute', InstituteUser::class],
    ['student', Student::class],
]);

it('authenticates each guard against its own table', function () {
    $admin = Admin::factory()->create(['email' => 'a@kcti.test']);
    $user = InstituteUser::factory()->create(['email' => 'i@kcti.test']);
    $student = Student::factory()->canLogin()->create(['registration_no' => 'KCTI0002-26-0001']);

    expect(Auth::guard('admin')->attempt(['email' => 'a@kcti.test', 'password' => 'password']))->toBeTrue()
        ->and(Auth::guard('institute')->attempt(['email' => 'i@kcti.test', 'password' => 'password']))->toBeTrue()
        ->and(Auth::guard('student')->attempt(['registration_no' => 'KCTI0002-26-0001', 'password' => 'password']))->toBeTrue()
        ->and(Auth::guard('institute')->attempt(['email' => 'a@kcti.test', 'password' => 'password']))->toBeFalse();
});

it('allows only one owner per institute', function () {
    $institute = Institute::factory()->create();
    InstituteUser::factory()->owner()->for($institute)->create();

    expect(fn () => InstituteUser::factory()->owner()->for($institute)->create())
        ->toThrow(QueryException::class);
});

it('allows owners in different institutes and many staff', function () {
    $a = Institute::factory()->create();
    $b = Institute::factory()->create();
    InstituteUser::factory()->owner()->for($a)->create();
    InstituteUser::factory()->owner()->for($b)->create();
    InstituteUser::factory()->count(2)->for($a)->create();

    expect(InstituteUser::count())->toBe(4);
});

it('builds a student full name', function () {
    $student = Student::factory()->make(['first_name' => 'Ravi', 'middle_name' => null, 'last_name' => 'Kumar']);

    expect($student->full_name)->toBe('Ravi Kumar');
});

it('has a password broker per guard', function () {
    expect(config('auth.passwords'))->toHaveKeys(['admins', 'institute_users', 'students']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/AuthModelsTest.php`
Expected: FAIL — `Class "App\Models\Admin" not found`.

- [ ] **Step 3: Remove the default User model**

```bash
rm app/Models/User.php database/factories/UserFactory.php
```

Edit `database/migrations/0001_01_01_000000_create_users_table.php` so `up()` only creates `sessions` and `down()` only drops `sessions`. Change the `sessions.user_id` column to `$table->unsignedBigInteger('user_id')->nullable()->index();` (no FK: three user tables share sessions).

Edit `database/seeders/DatabaseSeeder.php` `run()` body to be empty for now (Task 9 fills it).

- [ ] **Step 4: Write the enums**

`app/Enums/StudentStatus.php`:

```php
<?php

namespace App\Enums;

enum StudentStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Dropped = 'dropped';
    case Suspended = 'suspended';

    public function label(): string
    {
        return __('enums.student_status.'.$this->value);
    }
}
```

`app/Enums/AdmissionSource.php`:

```php
<?php

namespace App\Enums;

enum AdmissionSource: string
{
    case Website = 'website';
    case WalkIn = 'walk_in';
    case Franchise = 'franchise';

    public function label(): string
    {
        return __('enums.admission_source.'.$this->value);
    }
}
```

- [ ] **Step 5: Write the migrations**

`2026_09_28_000200_create_admins_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->string('password');
            $table->string('avatar_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('institutes', function (Blueprint $table) {
            $table->foreign('approved_by_admin_id')->references('id')->on('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('institutes', function (Blueprint $table) {
            $table->dropForeign(['approved_by_admin_id']);
        });
        Schema::dropIfExists('admins');
    }
};
```

`2026_09_28_000300_create_institute_users_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institute_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->string('password');
            $table->boolean('is_owner')->default(false);
            $table->unsignedTinyInteger('owner_marker')->nullable()
                ->storedAs('case when is_owner = 1 then 1 else null end');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['institute_id', 'owner_marker']);
            $table->index(['institute_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institute_users');
    }
};
```

`2026_09_28_000400_create_students_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institute_id')->constrained()->restrictOnDelete();
            $table->string('registration_no', 30)->unique();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('phone', 20)->index();
            $table->string('password')->nullable();
            $table->boolean('can_login')->default(false);
            $table->boolean('must_change_password')->default(true);
            $table->string('photo_path')->nullable();
            $table->string('signature_path')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('admission_source', 20);
            $table->date('joined_on');
            $table->foreignId('created_by_user_id')->nullable()->constrained('institute_users')->nullOnDelete();
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['institute_id', 'status', 'joined_on']);
            $table->index(['institute_id', 'last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
```

`2026_09_28_000500_create_password_reset_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'admin_password_reset_tokens',
        'institute_user_password_reset_tokens',
        'student_password_reset_tokens',
    ];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            Schema::dropIfExists($name);
        }
    }
};
```

- [ ] **Step 6: Write the models**

`app/Models/Admin.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }
}
```

`app/Models/InstituteUser.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class InstituteUser extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $guarded = ['id', 'owner_marker'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_owner' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }
}
```

`app/Models/Student.php`:

```php
<?php

namespace App\Models;

use App\Enums\AdmissionSource;
use App\Enums\StudentStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Student extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'can_login' => 'boolean',
            'must_change_password' => 'boolean',
            'status' => StudentStatus::class,
            'admission_source' => AdmissionSource::class,
            'joined_on' => 'date',
            'last_login_at' => 'datetime',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => collect([$this->first_name, $this->middle_name, $this->last_name])
            ->filter()
            ->implode(' '));
    }
}
```

- [ ] **Step 7: Write the factories**

`database/factories/AdminFactory.php`:

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AdminFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
```

`database/factories/InstituteUserFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Institute;
use Illuminate\Database\Eloquent\Factories\Factory;

class InstituteUserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'institute_id' => Institute::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('9#########'),
            'password' => 'password',
            'is_owner' => false,
            'is_active' => true,
        ];
    }

    public function owner(): static
    {
        return $this->state(fn () => ['is_owner' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
```

`database/factories/StudentFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\AdmissionSource;
use App\Enums\StudentStatus;
use App\Models\Institute;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'institute_id' => Institute::factory(),
            'registration_no' => 'KCTI'.fake()->unique()->numerify('####-26-####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->numerify('9#########'),
            'password' => 'password',
            'can_login' => false,
            'must_change_password' => false,
            'status' => StudentStatus::Active,
            'admission_source' => AdmissionSource::Franchise,
            'joined_on' => now()->toDateString(),
        ];
    }

    public function canLogin(): static
    {
        return $this->state(fn () => ['can_login' => true]);
    }
}
```

- [ ] **Step 8: Configure guards, providers, brokers**

In `config/auth.php` replace `defaults`, `guards`, `providers`, `passwords` with:

```php
'defaults' => [
    'guard' => env('AUTH_GUARD', 'admin'),
    'passwords' => env('AUTH_PASSWORD_BROKER', 'admins'),
],

'guards' => [
    'admin' => ['driver' => 'session', 'provider' => 'admins'],
    'institute' => ['driver' => 'session', 'provider' => 'institute_users'],
    'student' => ['driver' => 'session', 'provider' => 'students'],
],

'providers' => [
    'admins' => ['driver' => 'eloquent', 'model' => App\Models\Admin::class],
    'institute_users' => ['driver' => 'eloquent', 'model' => App\Models\InstituteUser::class],
    'students' => ['driver' => 'eloquent', 'model' => App\Models\Student::class],
],

'passwords' => [
    'admins' => ['provider' => 'admins', 'table' => 'admin_password_reset_tokens', 'expire' => 60, 'throttle' => 60],
    'institute_users' => ['provider' => 'institute_users', 'table' => 'institute_user_password_reset_tokens', 'expire' => 60, 'throttle' => 60],
    'students' => ['provider' => 'students', 'table' => 'student_password_reset_tokens', 'expire' => 60, 'throttle' => 60],
],
```

- [ ] **Step 9: Run tests**

Run: `./vendor/bin/pest`
Expected: all pass (SmokeTest, InstituteModelTest, AuthModelsTest).

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "feat: add admin, institute user, and student auth tables with separate guards

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4: Install Filament 5 and create three branded panels

**Files:**
- Create: `app/Providers/Filament/AdminPanelProvider.php`, `InstitutePanelProvider.php`, `StudentPanelProvider.php`, `app/Filament/Admin/{Resources,Pages,Widgets}/.gitkeep`, same for `Institute/` and `Student/`
- Modify: `bootstrap/providers.php`, `bootstrap/app.php`
- Test: `tests/Feature/PanelsTest.php`

**Interfaces:**
- Consumes: guards from Task 3.
- Produces: panels with ids `admin`, `institute`, `student`; each panel's `authGuard` equals its id; `authPasswordBroker` = `admins` / `institute_users` / `students`; `App\Providers\Filament\KctiPanelDefaults::apply(Panel $panel): Panel` shared styling helper.

- [ ] **Step 1: Install Filament**

```bash
composer require filament/filament:"^5.0" -W --no-interaction
php artisan filament:install --panels --no-interaction
```
If the installer creates `app/Providers/Filament/AdminPanelProvider.php`, it will be overwritten in Step 4. If it asks for a panel id, answer `admin`.

- [ ] **Step 2: Write the failing test**

`tests/Feature/PanelsTest.php`:

```php
<?php

use Filament\Facades\Filament;

it('registers three panels with matching guards', function (string $id, string $guard, string $broker, string $path) {
    $panel = Filament::getPanel($id);

    expect($panel->getAuthGuard())->toBe($guard)
        ->and($panel->getAuthPasswordBroker())->toBe($broker)
        ->and($panel->getPath())->toBe($path)
        ->and($panel->getBrandName())->toBe('KCTI');
})->with([
    ['admin', 'admin', 'admins', 'admin'],
    ['institute', 'institute', 'institute_users', 'institute'],
    ['student', 'student', 'students', 'student'],
]);

it('shows login pages', function (string $path) {
    $this->get("/{$path}/login")->assertOk();
})->with(['admin', 'institute', 'student']);

it('redirects guests to the panel login', function (string $path) {
    $this->get("/{$path}")->assertRedirect("/{$path}/login");
})->with(['admin', 'institute', 'student']);
```

- [ ] **Step 3: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/PanelsTest.php`
Expected: FAIL (institute/student panels missing, brand name not `KCTI`).

- [ ] **Step 4: Write shared panel defaults and the three providers**

`app/Providers/Filament/KctiPanelDefaults.php`:

```php
<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class KctiPanelDefaults
{
    public static function apply(Panel $panel): Panel
    {
        return $panel
            ->brandName('KCTI')
            ->font('Plus Jakarta Sans')
            ->colors([
                'primary' => Color::hex('#4f46e5'),
                'gray' => Color::Slate,
            ])
            ->darkMode(true)
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
```

`app/Providers/Filament/AdminPanelProvider.php`:

```php
<?php

namespace App\Providers\Filament;

use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets\AccountWidget;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return KctiPanelDefaults::apply($panel)
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->authGuard('admin')
            ->authPasswordBroker('admins')
            ->passwordReset()
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->pages([Dashboard::class])
            ->widgets([AccountWidget::class]);
    }
}
```

`app/Providers/Filament/InstitutePanelProvider.php`:

```php
<?php

namespace App\Providers\Filament;

use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets\AccountWidget;

class InstitutePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return KctiPanelDefaults::apply($panel)
            ->id('institute')
            ->path('institute')
            ->login()
            ->authGuard('institute')
            ->authPasswordBroker('institute_users')
            ->passwordReset()
            ->discoverResources(in: app_path('Filament/Institute/Resources'), for: 'App\\Filament\\Institute\\Resources')
            ->discoverPages(in: app_path('Filament/Institute/Pages'), for: 'App\\Filament\\Institute\\Pages')
            ->discoverWidgets(in: app_path('Filament/Institute/Widgets'), for: 'App\\Filament\\Institute\\Widgets')
            ->pages([Dashboard::class])
            ->widgets([AccountWidget::class]);
    }
}
```

`app/Providers/Filament/StudentPanelProvider.php`:

```php
<?php

namespace App\Providers\Filament;

use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets\AccountWidget;

class StudentPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return KctiPanelDefaults::apply($panel)
            ->id('student')
            ->path('student')
            ->login()
            ->authGuard('student')
            ->authPasswordBroker('students')
            ->discoverResources(in: app_path('Filament/Student/Resources'), for: 'App\\Filament\\Student\\Resources')
            ->discoverPages(in: app_path('Filament/Student/Pages'), for: 'App\\Filament\\Student\\Pages')
            ->pages([Dashboard::class])
            ->widgets([AccountWidget::class]);
    }
}
```

Create empty discovery dirs with `.gitkeep`:

```bash
for p in Admin Institute Student; do for d in Resources Pages Widgets; do mkdir -p app/Filament/$p/$d && touch app/Filament/$p/$d/.gitkeep; done; done
```

Register all three in `bootstrap/providers.php`:

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\Filament\InstitutePanelProvider::class,
    App\Providers\Filament\StudentPanelProvider::class,
];
```

- [ ] **Step 5: Run tests**

Run: `./vendor/bin/pest tests/Feature/PanelsTest.php`
Expected: 9 passed.

If `getBrandName()` or `getAuthPasswordBroker()` is not the getter name in Filament 5, open `vendor/filament/filament/src/Panel/Concerns/HasBrandName.php` / `HasAuth.php` and use the actual getter in the test.

- [ ] **Step 6: Visual check**

```bash
php artisan migrate:fresh --force
php artisan serve
```
Open `http://localhost:8000/admin/login`, `/institute/login`, `/student/login`: each shows "KCTI" brand in indigo with Plus Jakarta Sans. Stop the server.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: add Filament 5 admin, institute, and student panels with KCTI theme

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5: Panel access rules

**Files:**
- Modify: `app/Models/Admin.php`, `app/Models/InstituteUser.php`, `app/Models/Student.php`
- Test: `tests/Feature/PanelAccessTest.php`

**Interfaces:**
- Consumes: panels (Task 4), `Institute::isActive()` (Task 2).
- Produces: all three models implement `Filament\Models\Contracts\FilamentUser::canAccessPanel(Panel $panel): bool` and `Filament\Models\Contracts\HasName` is **not** used (Admin/InstituteUser have `name`; Student exposes `getFilamentName()` via `HasName` returning `full_name`).

Rules:
- Admin: `panel id === 'admin' && is_active`.
- InstituteUser: `panel id === 'institute' && is_active && institute->isActive()`.
- Student: `panel id === 'student' && can_login && status !== Dropped && institute->isActive()`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/PanelAccessTest.php`:

```php
<?php

use App\Enums\InstituteStatus;
use App\Models\Admin;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;

it('lets active accounts into their own panel', function () {
    $this->actingAs(Admin::factory()->create(), 'admin')->get('/admin')->assertOk();
    $this->actingAs(InstituteUser::factory()->create(), 'institute')->get('/institute')->assertOk();
    $this->actingAs(Student::factory()->canLogin()->create(), 'student')->get('/student')->assertOk();
});

it('blocks inactive admins', function () {
    $this->actingAs(Admin::factory()->inactive()->create(), 'admin')->get('/admin')->assertForbidden();
});

it('blocks inactive institute users', function () {
    $this->actingAs(InstituteUser::factory()->inactive()->create(), 'institute')->get('/institute')->assertForbidden();
});

it('blocks institute users as soon as their institute is suspended', function () {
    $user = InstituteUser::factory()->create();
    $this->actingAs($user, 'institute')->get('/institute')->assertOk();

    $user->institute->update(['status' => InstituteStatus::Suspended]);

    $this->actingAs($user->fresh(), 'institute')->get('/institute')->assertForbidden();
});

it('blocks students without login or with a suspended institute', function () {
    $this->actingAs(Student::factory()->create(), 'student')->get('/student')->assertForbidden();

    $student = Student::factory()->canLogin()->for(Institute::factory()->suspended())->create();
    $this->actingAs($student, 'student')->get('/student')->assertForbidden();
});

it('does not let one guard into another panel', function () {
    $this->actingAs(Admin::factory()->create(), 'admin')->get('/institute')->assertRedirect('/institute/login');
    $this->actingAs(Student::factory()->canLogin()->create(), 'student')->get('/admin')->assertRedirect('/admin/login');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/PanelAccessTest.php`
Expected: FAIL — inactive/suspended cases return 200 (and in production mode, Filament denies non-`FilamentUser` models, so behavior differs by env).

- [ ] **Step 3: Implement canAccessPanel**

`Admin`: add `implements FilamentUser` and:

```php
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

public function canAccessPanel(Panel $panel): bool
{
    return $panel->getId() === 'admin' && $this->is_active;
}
```

`InstituteUser`: add `implements FilamentUser` and:

```php
public function canAccessPanel(Panel $panel): bool
{
    return $panel->getId() === 'institute'
        && $this->is_active
        && (bool) $this->institute?->isActive();
}
```

`Student`: add `implements FilamentUser, HasName` and:

```php
use App\Enums\StudentStatus;
use Filament\Models\Contracts\HasName;

public function canAccessPanel(Panel $panel): bool
{
    return $panel->getId() === 'student'
        && $this->can_login
        && $this->status !== StudentStatus::Dropped
        && (bool) $this->institute?->isActive();
}

public function getFilamentName(): string
{
    return $this->full_name;
}
```

Note: `InstituteUser::institute()` and `Student::institute()` are the relations from Task 3 (Task 6 later moves them into `BelongsToInstitute`). `Institute` itself is never tenant-scoped, so the relation always loads.

Why this task comes before tenancy: in the `testing` environment Filament returns 403 for any user model that does not implement `FilamentUser`, so every later test that opens `/institute` or `/student` depends on this task.

- [ ] **Step 4: Run tests**

Run: `./vendor/bin/pest`
Expected: all pass.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: enforce panel access for inactive accounts and suspended institutes

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6: Tenancy context and BelongsToInstitute trait

**Files:**
- Create: `app/Support/Tenancy/CurrentInstitute.php`, `app/Models/Concerns/BelongsToInstitute.php`, `app/Http/Middleware/SetCurrentInstitute.php`, `app/Exceptions/TenantMismatchException.php`
- Modify: `app/Providers/AppServiceProvider.php`, `app/Models/InstituteUser.php`, `app/Models/Student.php`, `app/Providers/Filament/InstitutePanelProvider.php`, `app/Providers/Filament/StudentPanelProvider.php`
- Test: `tests/Feature/TenancyTest.php`

**Interfaces:**
- Consumes: models from Task 3, panels from Task 4, panel access rules from Task 5.
- Produces:
  - `App\Support\Tenancy\CurrentInstitute` (container **scoped** singleton): `set(?int $id): void`, `id(): ?int`, `has(): bool`, `forget(): void`, `run(int $id, callable $callback): mixed` (temporarily sets id, restores previous).
  - `App\Models\Concerns\BelongsToInstitute` trait: global scope named `institute`, auto-fills `institute_id` on creating, throws `App\Exceptions\TenantMismatchException` on saving a row of another institute while a context is set, `institute(): BelongsTo`.
  - `App\Http\Middleware\SetCurrentInstitute` — reads the panel guard user (`Filament::auth()->user()`) and sets `CurrentInstitute`; registered as **persistent** auth middleware on the institute and student panels only.

- [ ] **Step 1: Write the failing test**

`tests/Feature/TenancyTest.php`:

```php
<?php

use App\Exceptions\TenantMismatchException;
use App\Models\Admin;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Support\Tenancy\CurrentInstitute;

beforeEach(function () {
    $this->a = Institute::factory()->create();
    $this->b = Institute::factory()->create();
    Student::factory()->count(2)->for($this->a)->create();
    Student::factory()->count(3)->for($this->b)->create();
});

it('sees every row without a current institute', function () {
    expect(Student::count())->toBe(5);
});

it('scopes queries to the current institute', function () {
    app(CurrentInstitute::class)->set($this->a->id);

    expect(Student::count())->toBe(2)
        ->and(Student::pluck('institute_id')->unique()->all())->toBe([$this->a->id]);
});

it('cannot find another institute row by id', function () {
    $foreign = Student::where('institute_id', $this->b->id)->first();
    app(CurrentInstitute::class)->set($this->a->id);

    expect(Student::find($foreign->id))->toBeNull();
});

it('fills institute_id on create', function () {
    app(CurrentInstitute::class)->set($this->a->id);

    $student = Student::factory()->create(['institute_id' => null]);

    expect($student->institute_id)->toBe($this->a->id);
});

it('refuses to save a row belonging to another institute', function () {
    app(CurrentInstitute::class)->set($this->a->id);

    expect(fn () => Student::factory()->create(['institute_id' => $this->b->id]))
        ->toThrow(TenantMismatchException::class);
});

it('restores the previous context after run()', function () {
    $ctx = app(CurrentInstitute::class);
    $ctx->set($this->a->id);

    $count = $ctx->run($this->b->id, fn () => Student::count());

    expect($count)->toBe(3)->and($ctx->id())->toBe($this->a->id);
});

it('scopes the institute panel to the logged-in user institute', function () {
    $user = InstituteUser::factory()->for($this->a)->create();

    $this->actingAs($user, 'institute')->get('/institute')->assertOk();

    expect(app(CurrentInstitute::class)->id())->toBe($this->a->id);
});

it('does not scope the admin panel even when an institute guard is also logged in', function () {
    $admin = Admin::factory()->create();
    $user = InstituteUser::factory()->for($this->a)->create();

    $this->actingAs($user, 'institute')
        ->actingAs($admin, 'admin')
        ->get('/admin')
        ->assertOk();

    expect(app(CurrentInstitute::class)->has())->toBeFalse()
        ->and(Student::count())->toBe(5);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/TenancyTest.php`
Expected: FAIL — `Class "App\Support\Tenancy\CurrentInstitute" not found`.

- [ ] **Step 3: Write CurrentInstitute and the exception**

`app/Support/Tenancy/CurrentInstitute.php`:

```php
<?php

namespace App\Support\Tenancy;

class CurrentInstitute
{
    private ?int $id = null;

    public function set(?int $id): void
    {
        $this->id = $id;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function has(): bool
    {
        return $this->id !== null;
    }

    public function forget(): void
    {
        $this->id = null;
    }

    public function run(int $id, callable $callback): mixed
    {
        $previous = $this->id;
        $this->id = $id;

        try {
            return $callback();
        } finally {
            $this->id = $previous;
        }
    }
}
```

`app/Exceptions/TenantMismatchException.php`:

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class TenantMismatchException extends RuntimeException
{
    public static function for(string $model, ?int $rowInstitute, int $current): self
    {
        return new self("Refusing to save {$model} of institute {$rowInstitute} while acting for institute {$current}.");
    }
}
```

In `app/Providers/AppServiceProvider.php` `register()`:

```php
$this->app->scoped(\App\Support\Tenancy\CurrentInstitute::class);
```

- [ ] **Step 4: Write the trait**

`app/Models/Concerns/BelongsToInstitute.php`:

```php
<?php

namespace App\Models\Concerns;

use App\Exceptions\TenantMismatchException;
use App\Models\Institute;
use App\Support\Tenancy\CurrentInstitute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToInstitute
{
    public static function bootBelongsToInstitute(): void
    {
        static::addGlobalScope('institute', function (Builder $query) {
            $current = app(CurrentInstitute::class);

            if ($current->has()) {
                $query->where($query->getModel()->qualifyColumn('institute_id'), $current->id());
            }
        });

        // Eloquent fires `saving` before `creating`, so fill and check in one listener.
        static::saving(function (Model $model) {
            $current = app(CurrentInstitute::class);

            if (! $current->has()) {
                return;
            }

            if ($model->institute_id === null) {
                $model->institute_id = $current->id();

                return;
            }

            if ((int) $model->institute_id !== $current->id()) {
                throw TenantMismatchException::for($model::class, (int) $model->institute_id, $current->id());
            }
        });
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }
}
```

- [ ] **Step 5: Apply the trait**

In `InstituteUser` and `Student`: add `use App\Models\Concerns\BelongsToInstitute;`, add `BelongsToInstitute` to the `use` list, and delete their own `institute()` method (the trait provides it).

- [ ] **Step 6: Write the middleware**

`app/Http/Middleware/SetCurrentInstitute.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\CurrentInstitute;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentInstitute
{
    public function __construct(private CurrentInstitute $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();

        $this->current->set($user?->institute_id);

        return $next($request);
    }
}
```

In `InstitutePanelProvider` and `StudentPanelProvider`, after `KctiPanelDefaults::apply($panel)` chain add:

```php
->authMiddleware([
    \App\Http\Middleware\SetCurrentInstitute::class,
], isPersistent: true)
```

`authMiddleware()` appends to the list set by `KctiPanelDefaults` (Authenticate stays first). If Filament 5 replaces instead of appends, pass both `Authenticate::class` and `SetCurrentInstitute::class` in this call and confirm by reading `vendor/filament/filament/src/Panel/Concerns/HasMiddleware.php`.

- [ ] **Step 7: Run tests**

Run: `./vendor/bin/pest`
Expected: all pass.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: add explicit institute context and BelongsToInstitute scope

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 7: Number sequences and institute codes

**Files:**
- Create: `database/migrations/2026_09_28_000600_create_number_sequences_table.php`, `app/Services/NumberSequenceService.php`
- Test: `tests/Feature/NumberSequenceTest.php`

**Interfaces:**
- Produces: `App\Services\NumberSequenceService`:
  - `next(string $key): int` — atomic increment, first value 1.
  - `peek(string $key): int` — current last value (0 if missing).
  - `nextInstituteCode(): string` — `'KCTI'.str_pad(next('institute_code'), 4, '0', STR_PAD_LEFT)`.
  - `syncAtLeast(string $key, int $value): void` — raises `last_value` to at least `$value` (used by installer so franchises start after `KCTI0001`).

- [ ] **Step 1: Write the failing test**

`tests/Feature/NumberSequenceTest.php`:

```php
<?php

use App\Services\NumberSequenceService;
use Illuminate\Support\Facades\DB;

it('starts a new key at 1 and increments', function () {
    $seq = app(NumberSequenceService::class);

    expect($seq->next('receipt:1'))->toBe(1)
        ->and($seq->next('receipt:1'))->toBe(2)
        ->and($seq->next('receipt:2'))->toBe(1)
        ->and($seq->peek('receipt:1'))->toBe(2)
        ->and($seq->peek('missing'))->toBe(0);
});

it('formats institute codes', function () {
    $seq = app(NumberSequenceService::class);

    expect($seq->nextInstituteCode())->toBe('KCTI0001')
        ->and($seq->nextInstituteCode())->toBe('KCTI0002');
});

it('never lowers a sequence with syncAtLeast', function () {
    $seq = app(NumberSequenceService::class);
    $seq->syncAtLeast('institute_code', 5);
    $seq->syncAtLeast('institute_code', 3);

    expect($seq->nextInstituteCode())->toBe('KCTI0006');
});

it('produces unique values across many calls inside nested transactions', function () {
    $seq = app(NumberSequenceService::class);

    $values = collect(range(1, 50))->map(fn () => DB::transaction(fn () => $seq->next('bulk')));

    expect($values->unique()->count())->toBe(50)->and($values->max())->toBe(50);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/NumberSequenceTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
```

- [ ] **Step 4: Write the service**

`app/Services/NumberSequenceService.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class NumberSequenceService
{
    public function next(string $key): int
    {
        return DB::transaction(function () use ($key) {
            $this->ensure($key);

            $current = (int) DB::table('number_sequences')->where('key', $key)->lockForUpdate()->value('last_value');
            $next = $current + 1;

            DB::table('number_sequences')->where('key', $key)->update(['last_value' => $next, 'updated_at' => now()]);

            return $next;
        });
    }

    public function peek(string $key): int
    {
        return (int) DB::table('number_sequences')->where('key', $key)->value('last_value');
    }

    public function syncAtLeast(string $key, int $value): void
    {
        DB::transaction(function () use ($key, $value) {
            $this->ensure($key);

            DB::table('number_sequences')
                ->where('key', $key)
                ->where('last_value', '<', $value)
                ->update(['last_value' => $value, 'updated_at' => now()]);
        });
    }

    public function nextInstituteCode(): string
    {
        return 'KCTI'.str_pad((string) $this->next('institute_code'), 4, '0', STR_PAD_LEFT);
    }

    private function ensure(string $key): void
    {
        DB::table('number_sequences')->insertOrIgnore([
            'key' => $key,
            'last_value' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
```

- [ ] **Step 5: Run tests**

Run: `./vendor/bin/pest tests/Feature/NumberSequenceTest.php`
Expected: 4 passed.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add locked number sequences and KCTI institute codes

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 8: Student login by registration number or email

**Files:**
- Create: `app/Filament/Student/Pages/Auth/Login.php`, `lang/en/auth_kcti.php`, `lang/hi/auth_kcti.php`
- Modify: `app/Providers/Filament/StudentPanelProvider.php` (`->login(Login::class)`)
- Test: `tests/Feature/StudentLoginTest.php`

**Interfaces:**
- Consumes: student guard (Task 3), access rules (Task 5).
- Produces: `App\Filament\Student\Pages\Auth\Login` with form field `login`; `normalizeLogin(string $value): array` returns `['email' => ...]` or `['registration_no' => ...]`.

- [ ] **Step 1: Confirm the base class API**

Open `vendor/filament/filament/src/Auth/Pages/Login.php` and confirm these protected methods exist: `getEmailFormComponent()`, `getCredentialsFromFormData(array $data): array`, `throwFailureValidationException(): never`, and that `form(Schema $schema)` lists `$this->getEmailFormComponent()`. The code below assumes those names; adapt names only if they differ.

- [ ] **Step 2: Write the failing test**

`tests/Feature/StudentLoginTest.php`:

```php
<?php

use App\Filament\Student\Pages\Auth\Login;
use App\Models\Student;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(fn () => Filament::setCurrentPanel('student'));

it('logs in with registration number', function (string $typed) {
    $student = Student::factory()->canLogin()->create(['registration_no' => 'KCTI0002-26-0007']);

    Livewire::test(Login::class)
        ->fillForm(['login' => $typed, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(auth('student')->id())->toBe($student->id);
})->with(['KCTI0002-26-0007', 'kcti0002-26-0007', '  KCTI0002-26-0007 ']);

it('logs in with email', function () {
    $student = Student::factory()->canLogin()->create(['email' => 'ravi@example.com']);

    Livewire::test(Login::class)
        ->fillForm(['login' => 'Ravi@Example.com', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(auth('student')->id())->toBe($student->id);
});

it('rejects a wrong password with an error on the login field', function () {
    Student::factory()->canLogin()->create(['registration_no' => 'KCTI0002-26-0008']);

    Livewire::test(Login::class)
        ->fillForm(['login' => 'KCTI0002-26-0008', 'password' => 'wrong'])
        ->call('authenticate')
        ->assertHasFormErrors(['login']);

    expect(auth('student')->check())->toBeFalse();
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/StudentLoginTest.php`
Expected: FAIL — class not found.

- [ ] **Step 4: Write the login page**

`app/Filament/Student/Pages/Auth/Login.php`:

```php
<?php

namespace App\Filament\Student\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('login')
            ->label(__('auth_kcti.student_login_label'))
            ->required()
            ->autocomplete('username')
            ->autofocus();
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            ...static::normalizeLogin($data['login']),
            'password' => $data['password'],
        ];
    }

    public static function normalizeLogin(string $value): array
    {
        $value = trim($value);

        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return ['email' => Str::lower($value)];
        }

        return ['registration_no' => Str::upper($value)];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.login' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }
}
```

If Filament 5's `Component` type for `getEmailFormComponent()` is a different class, copy the return type from the base method found in Step 1.

`lang/en/auth_kcti.php`: `<?php return ['student_login_label' => 'Registration No. or Email'];`
`lang/hi/auth_kcti.php`: `<?php return ['student_login_label' => 'पंजीकरण संख्या या ईमेल'];`

Student emails are stored lowercase: add to `Student` model:

```php
protected function email(): Attribute
{
    return Attribute::set(fn (?string $value) => $value === null ? null : Str::lower(trim($value)));
}
```
(with `use Illuminate\Support\Str;`). Registration numbers are always generated uppercase.

In `StudentPanelProvider` replace `->login()` with `->login(\App\Filament\Student\Pages\Auth\Login::class)`.

- [ ] **Step 5: Run tests**

Run: `./vendor/bin/pest`
Expected: all pass.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: let students log in with registration number or email

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 9: Roles and permissions for admin and institute guards

**Files:**
- Create: `app/Support/Permissions/AdminPermissions.php`, `app/Support/Permissions/InstitutePermissions.php`, `database/seeders/RolesAndPermissionsSeeder.php`
- Modify: `app/Models/Admin.php`, `app/Models/InstituteUser.php` (add `HasRoles`), `app/Providers/Filament/AdminPanelProvider.php` (Shield plugin), `config/filament-shield.php`, `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/RolesAndPermissionsTest.php`

**Interfaces:**
- Consumes: models (Task 3).
- Produces:
  - `AdminPermissions::SUPER_ADMIN = 'super_admin'`, `AdminPermissions::ROLES` (list of 5 role names), `AdminPermissions::CUSTOM` (10 permission names).
  - `InstitutePermissions::OWNER = 'owner'`, `InstitutePermissions::CUSTOM` (8 names), `InstitutePermissions::ROLE_PERMISSIONS` (`array<string role, list<string>>`; owner = all).
  - `RolesAndPermissionsSeeder` — idempotent; creates permissions with correct `guard_name`, roles, and syncs institute role permissions.
  - `Admin` and `InstituteUser` use `Spatie\Permission\Traits\HasRoles`.
  - Super admin bypass: `Gate::before` returns `true` for `Admin` with role `super_admin` (via Shield config or `AppServiceProvider`).

- [ ] **Step 1: Install packages**

```bash
composer require spatie/laravel-permission bezhansalleh/filament-shield -W --no-interaction
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --tag="filament-shield-config"
```

Then open `config/filament-shield.php` and set:
- `auth_provider_model` → `App\Models\Admin::class` (fully qualified string).
- `super_admin.enabled` → `true`, `super_admin.name` → `'super_admin'`, `super_admin.define_via_gate` → `false`, `super_admin.intercept_gate` → `'before'`.
- `panel_user.enabled` → `false`.
- the custom permissions key (named `custom_permissions` in Shield 4; confirm in the published file) → the 10 names of `AdminPermissions::CUSTOM`.
- `policies.generate` (or equivalent key) → `false` (policies are hand-written from Phase 2).

- [ ] **Step 2: Write the failing test**

`tests/Feature/RolesAndPermissionsTest.php`:

```php
<?php

use App\Models\Admin;
use App\Models\InstituteUser;
use App\Support\Permissions\AdminPermissions;
use App\Support\Permissions\InstitutePermissions;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

it('creates admin roles and permissions on the admin guard', function () {
    expect(Role::where('guard_name', 'admin')->pluck('name')->sort()->values()->all())
        ->toBe(collect(AdminPermissions::ROLES)->sort()->values()->all());

    foreach (AdminPermissions::CUSTOM as $name) {
        expect(Permission::where(['name' => $name, 'guard_name' => 'admin'])->exists())->toBeTrue();
    }
});

it('creates institute roles with mapped permissions on the institute guard', function () {
    foreach (InstitutePermissions::ROLE_PERMISSIONS as $role => $permissions) {
        $model = Role::findByName($role, 'institute');
        expect($model->permissions->pluck('name')->sort()->values()->all())
            ->toBe(collect($permissions)->sort()->values()->all());
    }

    expect(InstitutePermissions::ROLE_PERMISSIONS['owner'])->toEqualCanonicalizing(InstitutePermissions::CUSTOM);
});

it('is idempotent', function () {
    $roles = Role::count();
    $permissions = Permission::count();

    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::count())->toBe($roles)->and(Permission::count())->toBe($permissions);
});

it('lets super admin pass any ability and limits other admins', function () {
    $super = Admin::factory()->create()->assignRole('super_admin');
    $accounts = Admin::factory()->create()->assignRole('accounts');

    expect($super->can('impersonate_institute'))->toBeTrue()
        ->and($super->can('anything_at_all'))->toBeTrue()
        ->and($accounts->can('impersonate_institute'))->toBeFalse();
});

it('gives the institute owner role every institute permission', function () {
    $owner = InstituteUser::factory()->owner()->create()->assignRole('owner');
    $teacher = InstituteUser::factory()->create()->assignRole('teacher');

    expect($owner->can('manage_staff'))->toBeTrue()
        ->and($teacher->can('take_attendance'))->toBeTrue()
        ->and($teacher->can('collect_fee'))->toBeFalse();
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/RolesAndPermissionsTest.php`
Expected: FAIL — class not found.

- [ ] **Step 4: Write the permission catalogs**

`app/Support/Permissions/AdminPermissions.php`:

```php
<?php

namespace App\Support\Permissions;

final class AdminPermissions
{
    public const SUPER_ADMIN = 'super_admin';

    public const ROLES = ['super_admin', 'manager', 'accounts', 'academic', 'support'];

    public const CUSTOM = [
        'approve_franchise',
        'impersonate_institute',
        'suspend_institute',
        'approve_enrollment',
        'approve_wallet_recharge',
        'adjust_wallet',
        'publish_result',
        'issue_certificate',
        'revoke_certificate',
        'manage_admins',
    ];

    /** Default custom permissions per non-super role; resource permissions are added per phase. */
    public const ROLE_PERMISSIONS = [
        'manager' => ['approve_franchise', 'impersonate_institute', 'suspend_institute', 'approve_enrollment'],
        'accounts' => ['approve_wallet_recharge', 'adjust_wallet'],
        'academic' => ['publish_result', 'issue_certificate', 'revoke_certificate'],
        'support' => [],
    ];
}
```

`app/Support/Permissions/InstitutePermissions.php`:

```php
<?php

namespace App\Support\Permissions;

final class InstitutePermissions
{
    public const OWNER = 'owner';

    public const CUSTOM = [
        'submit_enrollment',
        'collect_fee',
        'cancel_receipt',
        'take_attendance',
        'enter_marks',
        'submit_result',
        'request_wallet_recharge',
        'manage_staff',
    ];

    public const ROLE_PERMISSIONS = [
        'owner' => self::CUSTOM,
        'receptionist' => ['submit_enrollment', 'collect_fee'],
        'teacher' => ['take_attendance', 'enter_marks', 'submit_result'],
        'accountant' => ['collect_fee', 'cancel_receipt', 'request_wallet_recharge'],
    ];
}
```

- [ ] **Step 5: Write the seeder**

`database/seeders/RolesAndPermissionsSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Support\Permissions\AdminPermissions;
use App\Support\Permissions\InstitutePermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (AdminPermissions::CUSTOM as $name) {
            Permission::findOrCreate($name, 'admin');
        }

        foreach (AdminPermissions::ROLES as $role) {
            Role::findOrCreate($role, 'admin');
        }

        foreach (AdminPermissions::ROLE_PERMISSIONS as $role => $permissions) {
            Role::findByName($role, 'admin')->givePermissionTo($permissions);
        }

        foreach (InstitutePermissions::CUSTOM as $name) {
            Permission::findOrCreate($name, 'institute');
        }

        foreach (InstitutePermissions::ROLE_PERMISSIONS as $role => $permissions) {
            Role::findOrCreate($role, 'institute')->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
```

Admin roles use `givePermissionTo` (additive) so an installer re-run never removes permissions a Super Admin granted through the UI. Institute roles use `syncPermissions` because they are code-defined (spec clarification 1).

`DatabaseSeeder::run()`:

```php
$this->call(RolesAndPermissionsSeeder::class);
```

- [ ] **Step 6: Add HasRoles and the super admin bypass**

Add `use Spatie\Permission\Traits\HasRoles;` and `HasRoles` to the traits of `Admin` and `InstituteUser`.

In `AppServiceProvider::boot()` (explicit, independent of Shield's gate interception):

```php
\Illuminate\Support\Facades\Gate::before(function ($user) {
    if ($user instanceof \App\Models\Admin && $user->hasRole(\App\Support\Permissions\AdminPermissions::SUPER_ADMIN)) {
        return true;
    }

    return null;
});
```

Set Shield `super_admin.intercept_gate` to `false`/disabled if the config offers it, so the bypass lives in one place.

- [ ] **Step 7: Register Shield in the admin panel only**

In `AdminPanelProvider` add:

```php
->plugins([
    \BezhanSalleh\FilamentShield\FilamentShieldPlugin::make(),
])
```

- [ ] **Step 8: Run tests**

Run: `./vendor/bin/pest`
Expected: all pass.

- [ ] **Step 9: Visual check of the role editor**

```bash
php artisan migrate:fresh --seed --force
php artisan tinker --execute="App\Models\Admin::factory()->create(['email'=>'dev@kcti.test'])->assignRole('super_admin');"
php artisan serve
```
Log in at `/admin` as `dev@kcti.test` / `password`. The Roles menu lists the five admin roles; the custom permissions tab shows the 10 admin permissions. Stop the server.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "feat: seed admin and institute roles with guard-specific permissions

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 10: Installer command

**Files:**
- Create: `app/Console/Commands/InstallCommand.php`
- Test: `tests/Feature/InstallCommandTest.php`

**Interfaces:**
- Consumes: `NumberSequenceService` (Task 7), `RolesAndPermissionsSeeder` (Task 9), `Institute` factory-free creation.
- Produces: `php artisan kranti:install {--name=} {--email=} {--password=} {--skip-migrate} {--skip-assets}`. Non-interactive when all three options are given. Idempotent.

Behavior:
1. Unless `--skip-migrate`: `migrate --force`.
2. Seed `RolesAndPermissionsSeeder`.
3. Unless `--skip-assets`: `shield:generate --all --panel=admin --option=permissions` (so admin resource permissions exist), `storage:link` (ignore "already exists"), `filament:assets`, `optimize:clear`.
4. Main institute: if none exists, create `code = KCTI0001`, `name = Kranti Computer Training Institute`, `slug = kranti-computer-training-institute`, `is_main = true`, `status = active`, `wallet_exempt = true`. Always call `syncAtLeast('institute_code', 1)`.
5. Super admin: `updateOrCreate` by email (name, password, `is_active = true`), `assignRole('super_admin')` if missing.
6. Print a summary table: App URL, Admin URL, Main institute code, Super admin email.

- [ ] **Step 1: Write the failing test**

`tests/Feature/InstallCommandTest.php`:

```php
<?php

use App\Models\Admin;
use App\Models\Institute;
use App\Services\NumberSequenceService;
use Illuminate\Support\Facades\Hash;

function install(array $overrides = []): void
{
    test()->artisan('kranti:install', array_merge([
        '--name' => 'Rohit',
        '--email' => 'owner@kcti.test',
        '--password' => 'secret-123',
        '--skip-migrate' => true,
        '--skip-assets' => true,
    ], $overrides))->assertSuccessful();
}

it('creates the main institute and super admin', function () {
    install();

    $main = Institute::mainInstitute();
    $admin = Admin::where('email', 'owner@kcti.test')->first();

    expect($main->code)->toBe('KCTI0001')
        ->and($main->name)->toBe('Kranti Computer Training Institute')
        ->and($main->wallet_exempt)->toBeTrue()
        ->and($main->isActive())->toBeTrue()
        ->and($admin->hasRole('super_admin'))->toBeTrue()
        ->and(Hash::check('secret-123', $admin->password))->toBeTrue();
});

it('is safe to run twice', function () {
    install();
    install(['--name' => 'Rohit Updated', '--password' => 'new-pass-456']);

    $admin = Admin::where('email', 'owner@kcti.test')->sole();

    expect(Institute::count())->toBe(1)
        ->and($admin->name)->toBe('Rohit Updated')
        ->and(Hash::check('new-pass-456', $admin->password))->toBeTrue()
        ->and($admin->roles()->count())->toBe(1);
});

it('makes the next franchise code KCTI0002', function () {
    install();

    expect(app(NumberSequenceService::class)->nextInstituteCode())->toBe('KCTI0002');
});

it('rejects an invalid email', function () {
    $this->artisan('kranti:install', [
        '--name' => 'X', '--email' => 'not-an-email', '--password' => 'secret-123',
        '--skip-migrate' => true, '--skip-assets' => true,
    ])->assertFailed();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/InstallCommandTest.php`
Expected: FAIL — command `kranti:install` not defined.

- [ ] **Step 3: Write the command**

`app/Console/Commands/InstallCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Enums\InstituteStatus;
use App\Models\Admin;
use App\Models\Institute;
use App\Services\NumberSequenceService;
use App\Support\Permissions\AdminPermissions;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class InstallCommand extends Command
{
    protected $signature = 'kranti:install
        {--name= : Super admin name}
        {--email= : Super admin email}
        {--password= : Super admin password (min 8)}
        {--skip-migrate : Do not run migrations}
        {--skip-assets : Do not run shield, storage link, assets, or cache commands}';

    protected $description = 'Install or repair Kranti Computer Training Institute (KCTI)';

    public function handle(NumberSequenceService $sequences): int
    {
        $data = [
            'name' => $this->option('name') ?? text('Super admin name', required: true),
            'email' => $this->option('email') ?? text('Super admin email', required: true),
            'password' => $this->option('password') ?? password('Super admin password', required: true),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        if (! $this->option('skip-migrate')) {
            $this->call('migrate', ['--force' => true]);
        }

        $this->call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);

        if (! $this->option('skip-assets')) {
            $this->call('shield:generate', ['--all' => true, '--panel' => 'admin', '--option' => 'permissions']);
            $this->callSilently('storage:link');
            $this->call('filament:assets');
            $this->call('optimize:clear');
        }

        [$main, $admin] = DB::transaction(function () use ($data, $sequences) {
            $main = Institute::mainInstitute() ?? Institute::create([
                'code' => 'KCTI0001',
                'name' => 'Kranti Computer Training Institute',
                'slug' => 'kranti-computer-training-institute',
                'is_main' => true,
                'status' => InstituteStatus::Active,
                'wallet_exempt' => true,
                'approved_at' => now(),
            ]);

            $sequences->syncAtLeast('institute_code', 1);

            $admin = Admin::withTrashed()->updateOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name'], 'password' => $data['password'], 'is_active' => true, 'deleted_at' => null],
            );

            if (! $admin->hasRole(AdminPermissions::SUPER_ADMIN)) {
                $admin->assignRole(AdminPermissions::SUPER_ADMIN);
            }

            return [$main, $admin];
        });

        $this->components->twoColumnDetail('App URL', config('app.url'));
        $this->components->twoColumnDetail('Admin URL', url('/admin'));
        $this->components->twoColumnDetail('Main institute', "{$main->code} — {$main->name}");
        $this->components->twoColumnDetail('Super admin', $admin->email);
        $this->components->info('KCTI is ready.');

        return self::SUCCESS;
    }
}
```

If `shield:generate` in Shield 4.3 does not accept `--option=permissions`, run `php artisan shield:generate --help` and use the flag combination that generates permissions only (no policies).

- [ ] **Step 4: Run tests**

Run: `./vendor/bin/pest`
Expected: all pass.

- [ ] **Step 5: Real install on MySQL**

```bash
php artisan migrate:fresh --force
php artisan kranti:install --name="KCTI Admin" --email=admin@kcti.test --password=admin12345
php artisan kranti:install --name="KCTI Admin" --email=admin@kcti.test --password=admin12345
```
Expected: both runs succeed; second prints "Nothing to migrate"; `select count(*) from institutes` = 1.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add idempotent kranti:install command

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 11: Admin institutes list with impersonation

**Files:**
- Create: `app/Filament/Admin/Resources/Institutes/InstituteResource.php`, `app/Filament/Admin/Resources/Institutes/Pages/ListInstitutes.php`, `app/Services/ImpersonationService.php`, `app/Http/Controllers/ImpersonationController.php`, `resources/views/filament/impersonation-banner.blade.php`
- Modify: `routes/web.php`, `bootstrap/app.php`, `app/Providers/Filament/InstitutePanelProvider.php`, `composer.json` (activitylog)
- Test: `tests/Feature/ImpersonationTest.php`

**Interfaces:**
- Consumes: `Institute`, `InstituteUser` (owner), `AdminPermissions` (`impersonate_institute`), panels.
- Produces:
  - `App\Services\ImpersonationService`: `const SESSION_KEY = 'impersonator_admin_id'`; `start(Admin $admin, Institute $institute): InstituteUser` (throws `Symfony\Component\HttpKernel\Exception\HttpException` 403/422); `stop(): ?Admin`; `impersonator(): ?Admin`; `isImpersonating(): bool`.
  - Routes: `GET /impersonate/institutes/{institute}` name `impersonation.start` (middleware `web`, `auth:admin`, `signed`); `GET /impersonation/leave` name `impersonation.leave` (middleware `web`).
  - Admin table row action `impersonate` (visible when the admin can `impersonate_institute` and the institute is active and has an active owner), opening a 5-minute temporary signed URL in a new tab.

- [ ] **Step 1: Install activity log**

```bash
composer require spatie/laravel-activitylog -W --no-interaction
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan migrate
```
Confirm the helper `activity()` exists in the installed major version (`grep -rn "function activity" vendor/spatie/laravel-activitylog/src`). If the major version renamed it, use the equivalent logger shown in its README in Step 5.

- [ ] **Step 2: Write the failing test**

`tests/Feature/ImpersonationTest.php`:

```php
<?php

use App\Filament\Admin\Resources\Institutes\Pages\ListInstitutes;
use App\Models\Admin;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->institute = Institute::factory()->create();
    $this->owner = InstituteUser::factory()->owner()->for($this->institute)->create();
    $this->admin = Admin::factory()->create()->assignRole('manager');
});

function startUrl(Institute $institute): string
{
    return URL::temporarySignedRoute('impersonation.start', now()->addMinutes(5), ['institute' => $institute]);
}

it('logs the admin in as the institute owner and keeps the admin session', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(startUrl($this->institute))
        ->assertRedirect('/institute');

    expect(auth('institute')->id())->toBe($this->owner->id)
        ->and(auth('admin')->id())->toBe($this->admin->id)
        ->and(session('impersonator_admin_id'))->toBe($this->admin->id);

    $this->get('/institute')->assertOk()->assertSee('Return to Admin');
});

it('records start and stop in the activity log', function () {
    $this->actingAs($this->admin, 'admin')->get(startUrl($this->institute));
    $this->get(route('impersonation.leave'))->assertRedirect('/admin/institutes');

    expect(auth('institute')->check())->toBeFalse()
        ->and(auth('admin')->id())->toBe($this->admin->id)
        ->and(\Spatie\Activitylog\Models\Activity::pluck('event')->all())
        ->toBe(['impersonation.started', 'impersonation.stopped']);
});

it('forbids admins without the permission', function () {
    $support = Admin::factory()->create()->assignRole('support');

    $this->actingAs($support, 'admin')->get(startUrl($this->institute))->assertForbidden();
    expect(auth('institute')->check())->toBeFalse();
});

it('rejects unsigned links', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(route('impersonation.start', $this->institute))
        ->assertForbidden();
});

it('refuses suspended institutes and institutes without an active owner', function () {
    $suspended = Institute::factory()->suspended()->create();
    InstituteUser::factory()->owner()->for($suspended)->create();
    $ownerless = Institute::factory()->create();

    $this->actingAs($this->admin, 'admin')->get(startUrl($suspended))->assertStatus(422);
    $this->actingAs($this->admin, 'admin')->get(startUrl($ownerless))->assertStatus(422);
});

it('leave without impersonation is forbidden', function () {
    $this->get(route('impersonation.leave'))->assertForbidden();
});

it('admin panel still sees all institutes while impersonating', function () {
    Student::factory()->for(Institute::factory())->create();
    Student::factory()->for($this->institute)->create();

    $this->actingAs($this->admin, 'admin')->get(startUrl($this->institute));
    $this->get('/admin/institutes')->assertOk();

    expect(Student::count())->toBe(2);
});

it('shows the impersonate action only when allowed', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs($this->admin, 'admin');

    Livewire::test(ListInstitutes::class)
        ->assertCanSeeTableRecords([$this->institute])
        ->assertTableActionVisible('impersonate', $this->institute);

    $this->actingAs(Admin::factory()->create()->assignRole('support'), 'admin');

    Livewire::test(ListInstitutes::class)
        ->assertTableActionHidden('impersonate', $this->institute);
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/ImpersonationTest.php`
Expected: FAIL — route `impersonation.start` not defined.

- [ ] **Step 4: Write the service**

`app/Services/ImpersonationService.php`:

```php
<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Institute;
use App\Models\InstituteUser;
use Illuminate\Support\Facades\Auth;

class ImpersonationService
{
    public const SESSION_KEY = 'impersonator_admin_id';

    public function start(Admin $admin, Institute $institute): InstituteUser
    {
        abort_unless($admin->can('impersonate_institute'), 403);
        abort_unless($institute->isActive(), 422, 'Institute is not active.');

        $owner = $this->ownerOf($institute);
        abort_if($owner === null, 422, 'Institute has no active owner account.');

        Auth::guard('institute')->login($owner);
        session()->put(self::SESSION_KEY, $admin->getKey());

        activity()
            ->causedBy($admin)
            ->performedOn($institute)
            ->event('impersonation.started')
            ->withProperties(['institute_user_id' => $owner->getKey()])
            ->log('Admin started impersonating institute');

        return $owner;
    }

    public function stop(): ?Admin
    {
        $admin = $this->impersonator();
        abort_if($admin === null, 403);

        $owner = Auth::guard('institute')->user();
        Auth::guard('institute')->logout();
        session()->forget(self::SESSION_KEY);

        $log = activity()->causedBy($admin)->event('impersonation.stopped');

        if ($owner?->institute) {
            $log->performedOn($owner->institute);
        }

        $log->log('Admin stopped impersonating institute');

        return $admin;
    }

    public function impersonator(): ?Admin
    {
        $id = session(self::SESSION_KEY);

        return $id ? Admin::find($id) : null;
    }

    public function isImpersonating(): bool
    {
        return session()->has(self::SESSION_KEY);
    }

    public function ownerOf(Institute $institute): ?InstituteUser
    {
        return InstituteUser::query()
            ->where('institute_id', $institute->getKey())
            ->where('is_owner', true)
            ->where('is_active', true)
            ->first();
    }
}
```

Note: `Auth::guard('institute')->logout()` only removes the institute guard's session key and remember cookie; the admin guard stays logged in. Do **not** call `session()->invalidate()` here.

- [ ] **Step 5: Write the controller and routes**

`app/Http/Controllers/ImpersonationController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Institute;
use App\Services\ImpersonationService;
use Illuminate\Http\RedirectResponse;

class ImpersonationController extends Controller
{
    public function start(Institute $institute, ImpersonationService $impersonation): RedirectResponse
    {
        $impersonation->start(auth('admin')->user(), $institute);

        return redirect('/institute');
    }

    public function leave(ImpersonationService $impersonation): RedirectResponse
    {
        $impersonation->stop();

        return redirect('/admin/institutes');
    }
}
```

`routes/web.php` (keep the default welcome route for now; Phase 6 replaces it):

```php
<?php

use App\Http\Controllers\ImpersonationController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));

Route::get('/impersonate/institutes/{institute}', [ImpersonationController::class, 'start'])
    ->middleware(['auth:admin', 'signed'])
    ->name('impersonation.start');

Route::get('/impersonation/leave', [ImpersonationController::class, 'leave'])
    ->name('impersonation.leave');
```

In `bootstrap/app.php` inside `withMiddleware`:

```php
$middleware->redirectGuestsTo(fn () => '/admin/login');
```

- [ ] **Step 6: Write the banner and register it on the institute panel**

`resources/views/filament/impersonation-banner.blade.php`:

```blade
@php($impersonator = app(\App\Services\ImpersonationService::class)->impersonator())

@if ($impersonator)
    <div style="display:flex;align-items:center;justify-content:center;gap:12px;padding:10px 16px;background:#eef2ff;border-bottom:1px solid #c7d2fe;color:#3730a3;font-size:14px;font-weight:600;">
        <span>{{ __('Viewing as :institute (admin: :admin)', ['institute' => filament()->auth()->user()?->institute?->name, 'admin' => $impersonator->name]) }}</span>
        <a href="{{ route('impersonation.leave') }}" style="display:inline-flex;align-items:center;height:32px;padding:0 12px;border-radius:8px;background:#4f46e5;color:#fff;text-decoration:none;font-size:13px;font-weight:700;">
            {{ __('Return to Admin') }}
        </a>
    </div>
@endif
```

In `InstitutePanelProvider` add:

```php
->renderHook(
    \Filament\View\PanelsRenderHook::BODY_START,
    fn (): \Illuminate\Contracts\View\View => view('filament.impersonation-banner'),
)
```

- [ ] **Step 7: Write the admin resource**

`app/Filament/Admin/Resources/Institutes/InstituteResource.php`:

```php
<?php

namespace App\Filament\Admin\Resources\Institutes;

use App\Enums\InstituteStatus;
use App\Filament\Admin\Resources\Institutes\Pages\ListInstitutes;
use App\Models\Institute;
use App\Services\ImpersonationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\URL;

class InstituteResource extends Resource
{
    protected static ?string $model = Institute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                IconColumn::make('is_main')->label('Main')->boolean(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (InstituteStatus $state) => $state->label())
                    ->color(fn (InstituteStatus $state) => match ($state) {
                        InstituteStatus::Active => 'success',
                        InstituteStatus::Pending => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('phone'),
                TextColumn::make('email')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(
                    collect(InstituteStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()
                ),
            ])
            ->recordActions([
                Action::make('impersonate')
                    ->label('Login as Institute')
                    ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
                    ->color('gray')
                    ->visible(fn (Institute $record): bool => auth('admin')->user()?->can('impersonate_institute')
                        && $record->isActive()
                        && app(ImpersonationService::class)->ownerOf($record) !== null)
                    ->url(fn (Institute $record): string => URL::temporarySignedRoute(
                        'impersonation.start', now()->addMinutes(5), ['institute' => $record]
                    ))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInstitutes::route('/'),
        ];
    }
}
```

`app/Filament/Admin/Resources/Institutes/Pages/ListInstitutes.php`:

```php
<?php

namespace App\Filament\Admin\Resources\Institutes\Pages;

use App\Filament\Admin\Resources\Institutes\InstituteResource;
use Filament\Resources\Pages\ListRecords;

class ListInstitutes extends ListRecords
{
    protected static string $resource = InstituteResource::class;
}
```

If `Heroicon::OutlinedArrowRightEndOnRectangle` or `OutlinedBuildingOffice2` is not a case in the installed enum, pick the nearest existing case from `vendor/filament/support/src/Icons/Heroicon.php`.

- [ ] **Step 8: Run tests**

Run: `./vendor/bin/pest`
Expected: all pass.

- [ ] **Step 9: Manual check**

```bash
php artisan migrate:fresh --force
php artisan kranti:install --name="KCTI Admin" --email=admin@kcti.test --password=admin12345
php artisan tinker --execute="\$i=App\Models\Institute::factory()->create(['name'=>'Demo Franchise','code'=>app(App\Services\NumberSequenceService::class)->nextInstituteCode()]); App\Models\InstituteUser::factory()->owner()->for(\$i)->create(['email'=>'owner@demo.test'])->assignRole('owner');"
php artisan serve
```
Log in to `/admin`, open Institutes, click **Login as Institute** on `KCTI0002`: a new tab opens `/institute` with the banner; **Return to Admin** returns to `/admin/institutes` and the admin is still logged in. Stop the server.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "feat: add admin institutes list with audited institute impersonation

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 12: Phase wrap-up

**Files:**
- Create: `README.md` (replace Laravel default)
- Test: full suite + Pint

- [ ] **Step 1: Format**

Run: `./vendor/bin/pint`
Expected: files formatted; re-run `./vendor/bin/pest` → all pass.

- [ ] **Step 2: Write README**

`README.md`:

````markdown
# KCTI — Kranti Computer Training Institute

Franchise management platform: public website, HQ admin panel, franchise institute panel, and student portal.

Design: `docs/superpowers/specs/2026-09-28-kranti-franchise-platform-design.md`

## Requirements

PHP 8.3+, Composer 2, Node 20+, MySQL 8 / MariaDB 10.6+.

## Install

```bash
composer install
cp .env.example .env
php artisan key:generate
# create database new_kranti, then:
php artisan kranti:install
php artisan serve
```

| Area | URL | Login table |
|---|---|---|
| Admin (HQ) | `/admin` | `admins` |
| Institute (franchise) | `/institute` | `institute_users` |
| Student | `/student` | `students` (registration no. or email) |

`kranti:install` is safe to re-run: it creates the main institute `KCTI0001` and the Super Admin once and updates them afterwards.

## Tests

```bash
./vendor/bin/pest
```
````

- [ ] **Step 3: Final verification**

```bash
./vendor/bin/pest
php artisan migrate:fresh --force && php artisan kranti:install --name="KCTI Admin" --email=admin@kcti.test --password=admin12345
```
Expected: all tests pass; installer succeeds on MySQL.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "docs: add KCTI README and format code

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```
