<?php

namespace App\Providers;

use App\Models\Admin;
use App\Policies\RolePolicy;
use App\Support\Permissions\AdminPermissions;
use App\Support\Tenancy\CurrentInstitute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentInstitute::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Super Admin passes every ability. Institute owners get theirs through the owner role instead,
        // so policies can still enforce same-institute ownership.
        Gate::policy(Role::class, RolePolicy::class);

        Gate::before(function ($user) {
            if ($user instanceof Admin && $user->hasRole(AdminPermissions::SUPER_ADMIN)) {
                return true;
            }

            return null;
        });
    }
}
