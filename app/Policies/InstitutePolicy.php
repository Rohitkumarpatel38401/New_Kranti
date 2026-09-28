<?php

namespace App\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class InstitutePolicy extends ShieldPolicy
{
    protected static string $subject = 'Institute';

    public function delete(Authenticatable $user, Model $model): bool
    {
        return ! $model->is_main && parent::delete($user, $model);
    }
}
