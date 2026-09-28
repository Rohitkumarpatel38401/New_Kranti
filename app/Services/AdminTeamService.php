<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\Admin;
use App\Support\Permissions\AdminPermissions;
use Illuminate\Support\Facades\DB;

/**
 * HQ team accounts. Guarantees at least one active Super Admin and no self-lockout.
 */
class AdminTeamService
{
    /** @param  list<string>  $roles */
    public function create(array $data, array $roles, Admin $by): Admin
    {
        $this->assertMayManageSuperAdmin(null, $roles, $by);

        return DB::transaction(function () use ($data, $roles, $by) {
            $admin = Admin::create($data);
            $admin->syncRoles($roles);

            activity()->causedBy($by)->performedOn($admin)->event('admin.created')->log('HQ admin created');

            return $admin;
        });
    }

    /** @param  list<string>|null  $roles  null keeps the current roles */
    public function update(Admin $admin, array $data, ?array $roles, Admin $by): Admin
    {
        $this->assertMayManageSuperAdmin($admin, $roles ?? [], $by);

        return DB::transaction(function () use ($admin, $data, $roles, $by) {
            $deactivating = array_key_exists('is_active', $data) && ! $data['is_active'] && $admin->is_active;
            $losingSuper = $roles !== null && $admin->hasRole(AdminPermissions::SUPER_ADMIN)
                && ! in_array(AdminPermissions::SUPER_ADMIN, $roles, true);

            if ($deactivating && $admin->is($by)) {
                throw new DomainRuleException('You cannot deactivate your own account.');
            }

            if (($deactivating || $losingSuper) && $this->isLastActiveSuperAdmin($admin)) {
                throw new DomainRuleException('At least one active Super Admin must remain.');
            }

            if (($data['password'] ?? null) === null || $data['password'] === '') {
                unset($data['password']);
            }

            $admin->update($data);

            if ($roles !== null) {
                $admin->syncRoles($roles);
            }

            return $admin->refresh();
        });
    }

    public function delete(Admin $admin, Admin $by): void
    {
        if ($admin->is($by)) {
            throw new DomainRuleException('You cannot delete your own account.');
        }

        $this->assertMayManageSuperAdmin($admin, [], $by);

        if ($this->isLastActiveSuperAdmin($admin)) {
            throw new DomainRuleException('At least one active Super Admin must remain.');
        }

        activity()->causedBy($by)->performedOn($admin)->event('admin.deleted')->log('HQ admin deleted');

        $admin->delete();
    }

    /**
     * manage_admins can be delegated, so only a Super Admin may grant the role or touch a Super Admin account.
     *
     * @param  list<string>  $roles
     */
    private function assertMayManageSuperAdmin(?Admin $target, array $roles, Admin $by): void
    {
        if ($by->hasRole(AdminPermissions::SUPER_ADMIN)) {
            return;
        }

        if (in_array(AdminPermissions::SUPER_ADMIN, $roles, true) || $target?->hasRole(AdminPermissions::SUPER_ADMIN)) {
            throw new DomainRuleException('Only a Super Admin can grant the Super Admin role or change a Super Admin.');
        }
    }

    private function isLastActiveSuperAdmin(Admin $admin): bool
    {
        if (! $admin->is_active || ! $admin->hasRole(AdminPermissions::SUPER_ADMIN)) {
            return false;
        }

        return Admin::role(AdminPermissions::SUPER_ADMIN, 'admin')
            ->where('is_active', true)
            ->whereKeyNot($admin->getKey())
            ->doesntExist();
    }
}
