<?php

namespace App\Policies;

use App\Models\InstituteUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * The ledger is read-only for everyone; it is written only by WalletService.
 */
class WalletTransactionPolicy extends ShieldPolicy
{
    protected static string $subject = 'WalletTransaction';

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
        return false;
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
