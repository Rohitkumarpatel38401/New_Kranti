<?php

namespace App\Policies;

use App\Models\InstituteUser;
use App\Models\Student;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Institutes collect (collect_fee) and cancel (cancel_receipt) their own receipts; only those
 * people see receipts at all. HQ uses Shield keys. Receipts are never edited or deleted.
 */
class FeePaymentPolicy extends InstituteScopedPolicy
{
    protected static string $subject = 'FeePayment';

    protected static string $managePermission = 'collect_fee';

    public function viewAny(Authenticatable $user): bool
    {
        return $user instanceof InstituteUser ? $user->is_active && $this->handlesFees($user) : parent::viewAny($user);
    }

    public function view(Authenticatable $user, Model $model): bool
    {
        return match (true) {
            $user instanceof InstituteUser => $this->ownsRecord($user, $model) && $this->handlesFees($user),
            // Students see their own receipts, cancelled ones included (they show as cancelled).
            $user instanceof Student => self::studentAccountOk($user) && $model->student_id === $user->id,
            default => parent::view($user, $model),
        };
    }

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

    private function handlesFees(InstituteUser $user): bool
    {
        return $user->can('collect_fee') || $user->can('cancel_receipt');
    }
}
