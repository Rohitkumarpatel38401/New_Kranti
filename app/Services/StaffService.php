<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\InstituteUser;
use App\Support\Permissions\InstitutePermissions;
use Illuminate\Support\Facades\DB;

/**
 * Institute staff accounts, managed by the institute itself. Owners are never removed here.
 */
class StaffService
{
    /** @return list<string> */
    public static function assignableRoles(): array
    {
        return array_values(array_diff(array_keys(InstitutePermissions::ROLE_PERMISSIONS), [InstitutePermissions::OWNER]));
    }

    public function create(array $data, string $role, InstituteUser $by): InstituteUser
    {
        $this->assertAssignable($role);

        return DB::transaction(function () use ($data, $role, $by) {
            $user = InstituteUser::create([
                ...$data,
                'institute_id' => $by->institute_id,
                'is_owner' => false,
            ]);
            $user->syncRoles([$role]);

            return $user;
        });
    }

    public function update(InstituteUser $user, array $data, ?string $role, InstituteUser $by): InstituteUser
    {
        $this->assertSameInstitute($user, $by);

        if (array_key_exists('is_active', $data) && ! $data['is_active'] && ($user->is_owner || $user->is($by))) {
            throw new DomainRuleException('The owner and your own account cannot be deactivated.');
        }

        if ($role !== null) {
            if ($user->is_owner) {
                throw new DomainRuleException('The owner role cannot be changed here.');
            }

            $this->assertAssignable($role);
        }

        return DB::transaction(function () use ($user, $data, $role) {
            if (($data['password'] ?? null) === null || $data['password'] === '') {
                unset($data['password']);
            }

            unset($data['is_owner'], $data['institute_id']);
            $user->update($data);

            if ($role !== null) {
                $user->syncRoles([$role]);
            }

            return $user->refresh();
        });
    }

    public function delete(InstituteUser $user, InstituteUser $by): void
    {
        $this->assertSameInstitute($user, $by);

        if ($user->is_owner || $user->is($by)) {
            throw new DomainRuleException('The owner and your own account cannot be removed.');
        }

        $user->delete();
    }

    private function assertAssignable(string $role): void
    {
        if (! in_array($role, self::assignableRoles(), true)) {
            throw new DomainRuleException("The role [{$role}] cannot be given to institute staff.");
        }
    }

    private function assertSameInstitute(InstituteUser $user, InstituteUser $by): void
    {
        if ($user->institute_id !== $by->institute_id) {
            throw new DomainRuleException('This person belongs to another institute.');
        }
    }
}
