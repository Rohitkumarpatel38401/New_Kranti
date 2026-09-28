<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\InstituteUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * HQ reviews recharge requests (Shield keys + approve_wallet_recharge);
 * institutes create and see their own with request_wallet_recharge. Nobody edits or deletes them.
 */
class WalletRechargeRequestPolicy extends ShieldPolicy
{
    protected static string $subject = 'WalletRechargeRequest';

    public function viewAny(Authenticatable $user): bool
    {
        return $user instanceof InstituteUser ? $user->can('request_wallet_recharge') : parent::viewAny($user);
    }

    public function view(Authenticatable $user, Model $model): bool
    {
        return $user instanceof InstituteUser
            ? $user->can('request_wallet_recharge') && $model->institute_id === $user->institute_id
            : parent::view($user, $model);
    }

    public function create(Authenticatable $user): bool
    {
        return $user instanceof InstituteUser && $user->can('request_wallet_recharge');
    }

    public function review(Authenticatable $user, Model $model): bool
    {
        return $user instanceof Admin && $user->can('approve_wallet_recharge');
    }

    public function update(Authenticatable $user, Model $model): bool
    {
        return false;
    }

    public function delete(Authenticatable $user, Model $model): bool
    {
        return false;
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return false;
    }
}
