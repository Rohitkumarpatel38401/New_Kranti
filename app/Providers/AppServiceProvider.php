<?php

namespace App\Providers;

use App\Models\Admin;
use App\Support\Permissions\AdminPermissions;
use App\Support\Tenancy\CurrentInstitute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        Gate::before(function ($user) {
            if ($user instanceof Admin && $user->hasRole(AdminPermissions::SUPER_ADMIN)) {
                return true;
            }

            return null;
        });
    }
}
