<?php

namespace App\Policies;

use App\Models\Admin;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Enquiries arrive only from the website and are never deleted; staff with manage_enquiries
 * view them and move them along through EnquiryService actions.
 */
class EnquiryPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $user instanceof Admin && $user->can('manage_enquiries');
    }

    public function view(Authenticatable $user): bool
    {
        return $this->viewAny($user);
    }

    public function create(): bool
    {
        return false;
    }

    public function update(): bool
    {
        return false;
    }

    public function delete(): bool
    {
        return false;
    }

    public function deleteAny(): bool
    {
        return false;
    }
}
