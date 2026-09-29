<?php

namespace App\Console\Commands;

use App\Enums\InstituteStatus;
use App\Models\Admin;
use App\Models\Institute;
use App\Services\NumberSequenceService;
use App\Services\SettingsService;
use App\Support\Permissions\AdminPermissions;
use Database\Seeders\AcademicDefaultsSeeder;
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
        $this->call('db:seed', ['--class' => AcademicDefaultsSeeder::class, '--force' => true]);
        app(SettingsService::class)->seedDefaults();

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
