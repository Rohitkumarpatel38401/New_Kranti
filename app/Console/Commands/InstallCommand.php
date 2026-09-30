<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Services\InstituteService;
use App\Services\SettingsService;
use App\Support\Permissions\AdminPermissions;
use Database\Seeders\AcademicDefaultsSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class InstallCommand extends Command
{
    protected $signature = 'kranti:install
        {--name= : Super admin name (default: Kranti Admin)}
        {--email= : Super admin email (default: admin@example.com)}
        {--password= : Super admin password, min 8 (default: admin123)}
        {--skip-migrate : Do not run migrations}
        {--skip-assets : Do not run shield, storage link, assets, or cache commands}
        {--demo : Load the demo institute, student, courses and website content (automatic on local)}
        {--no-demo : Never load demo data}';

    protected $description = 'Install or repair Kranti Computer Training Institute (KCTI)';

    public function handle(InstituteService $institutes): int
    {
        // Blank or missing answers fall back to the default HQ login (config kcti.demo).
        $defaults = [
            'name' => config('kcti.demo.admin.name'),
            'email' => config('kcti.demo.admin.email'),
            'password' => config('kcti.demo.password'),
        ];
        $ask = $this->input->isInteractive();

        $data = [
            'name' => $this->option('name') ?: ($ask ? text('Super admin name', default: $defaults['name']) : null) ?: $defaults['name'],
            'email' => $this->option('email') ?: ($ask ? text('Super admin email', default: $defaults['email']) : null) ?: $defaults['email'],
            'password' => $this->option('password') ?: ($ask ? password('Super admin password (blank = '.$defaults['password'].')') : null) ?: $defaults['password'],
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
        $this->call('db:seed', ['--class' => AcademicDefaultsSeeder::class, '--force' => true]);
        app(SettingsService::class)->seedDefaults();

        if (! $this->option('skip-assets')) {
            $this->call('shield:generate', ['--all' => true, '--panel' => 'admin', '--option' => 'permissions']);
            $this->callSilently('storage:link');
            $this->call('filament:assets');
            $this->call('optimize:clear');
        }

        [$main, $admin] = DB::transaction(function () use ($data, $institutes) {
            $main = $institutes->ensureMainInstitute();

            $admin = Admin::withTrashed()->updateOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name'], 'password' => $data['password'], 'is_active' => true, 'deleted_at' => null],
            );

            if (! $admin->hasRole(AdminPermissions::SUPER_ADMIN)) {
                $admin->assignRole(AdminPermissions::SUPER_ADMIN);
            }

            return [$main, $admin];
        });

        $demo = $this->shouldLoadDemo();

        if ($demo) {
            if (! app()->isLocal()) {
                $this->components->warn('Loading DEMO accounts with the password "'.config('kcti.demo.password').'" on a non-local environment. Change or remove them before going live.');
            }

            $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);
        }

        $this->components->twoColumnDetail('App URL', config('app.url'));
        $this->components->twoColumnDetail('Admin URL', url('/admin'));
        $this->components->twoColumnDetail('Main institute', "{$main->code} — {$main->name}");
        $this->components->twoColumnDetail('Super admin', $admin->email);

        if ($demo) {
            $password = config('kcti.demo.password');
            $this->table(['Panel', 'URL', 'Login', 'Password'], [
                ['Admin (HQ)', url('/admin'), $admin->email, $data['password'] === $password ? $password : '(as entered)'],
                ['Institute', url('/institute'), config('kcti.demo.institute.email'), $password],
                ['Student', url('/student'), config('kcti.demo.student.email'), $password],
            ]);
        }

        $this->components->info('KCTI is ready.');

        return self::SUCCESS;
    }

    /** Local installs get demo data unless --no-demo; anywhere else only with --demo. */
    private function shouldLoadDemo(): bool
    {
        if ($this->option('no-demo')) {
            return false;
        }

        return $this->option('demo') || app()->isLocal();
    }
}
