<?php

namespace App\Providers;

use App\Exceptions\DomainRuleException;
use App\Models\Admin;
use App\Policies\RolePolicy;
use App\Support\Permissions\AdminPermissions;
use App\Support\Tenancy\CurrentInstitute;
use Filament\Actions\Exports\Models\Export;
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

        // Exports belong to an Admin (there is no users table).
        Export::polymorphicUserRelationship();

        // Enforced on the model because Super Admin bypasses policies.
        Role::deleting(function (Role $role) {
            if ($role->name === AdminPermissions::SUPER_ADMIN) {
                throw new DomainRuleException('The Super Admin role cannot be deleted.');
            }
        });

        Gate::before(function ($user) {
            if ($user instanceof Admin && $user->hasRole(AdminPermissions::SUPER_ADMIN)) {
                return true;
            }

            return null;
        });
    }
}
