<?php

namespace App\Providers;

use App\Exceptions\DomainRuleException;
use App\Models\Admin;
use App\Models\Download;
use App\Models\FacultyMember;
use App\Models\GalleryItem;
use App\Models\Notice;
use App\Models\Slider;
use App\Models\Testimonial;
use App\Policies\RolePolicy;
use App\Policies\WebsiteContentPolicy;
use App\Services\SettingsService;
use App\Support\Permissions\AdminPermissions;
use App\Support\Tenancy\CurrentInstitute;
use Filament\Actions\Exports\Models\Export;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
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
        // Panels: every dropdown and dropdown filter is a searchable Filament select, not a plain one.
        Select::configureUsing(fn (Select $select) => $select->searchable());
        SelectFilter::configureUsing(fn (SelectFilter $filter) => $filter->searchable());

        // Every public website view reads settings through $site.
        View::composer(['site.*'], fn ($view) => $view->with('site', app(SettingsService::class)));

        // Public website forms (contact, admission, franchise): 5 posts a minute per visitor, shared across the forms.
        RateLimiter::for('site-forms', fn (Request $request) => Limit::perMinute(5)->by('site-forms|'.$request->ip()));

        // Super Admin passes every ability. Institute owners get theirs through the owner role instead,
        // so policies can still enforce same-institute ownership.
        Gate::policy(Role::class, RolePolicy::class);

        foreach ([Slider::class, Notice::class, GalleryItem::class, FacultyMember::class, Testimonial::class, Download::class] as $content) {
            Gate::policy($content, WebsiteContentPolicy::class);
        }

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
