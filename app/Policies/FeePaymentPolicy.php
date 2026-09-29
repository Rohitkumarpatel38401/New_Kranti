<?php

namespace App\Policies;

use App\Models\InstituteUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Institutes collect (collect_fee) and cancel (cancel_receipt) their own receipts;
 * HQ uses Shield keys. Receipts are never edited or deleted.
 */
class FeePaymentPolicy extends InstituteScopedPolicy
{
    protected static string $subject = 'FeePayment';

    protected static string $managePermission = 'collect_fee';

    public function update(Authenticatable $user, Model $model): bool
    {
        return false;
    }

    public function cancel(Authenticatable $user, Model $model): bool
    {
        return $user instanceof InstituteUser
            ? $this->ownsRecord($user, $model) && $user->can('cancel_receipt')
            : parent::update($user, $model);
    }
}
