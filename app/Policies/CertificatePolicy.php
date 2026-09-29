<?php

namespace App\Policies;

use App\Models\Admin;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Institutes download their own certificates; only HQ issues or revokes them.
 */
class CertificatePolicy extends InstituteScopedPolicy
{
    protected static string $subject = 'Certificate';

    protected static string $managePermission = 'enter_marks';

    public function create(Authenticatable $user): bool
    {
        return $user instanceof Admin && $user->can('issue_certificate');
    }

    public function update(Authenticatable $user, Model $model): bool
    {
        return false;
    }

    public function revoke(Authenticatable $user, Model $model): bool
    {
        return $user instanceof Admin && $user->can('revoke_certificate');
    }
}
