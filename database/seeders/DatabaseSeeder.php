<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Roles, permissions and academic defaults always; the full demo dataset on local
     * (or anywhere with `php artisan db:seed --class=DemoSeeder`).
     */
    public function run(): void
    {
        $this->call([RolesAndPermissionsSeeder::class, AcademicDefaultsSeeder::class]);

        if (app()->isLocal()) {
            $this->call(DemoSeeder::class);
        }
    }
}
