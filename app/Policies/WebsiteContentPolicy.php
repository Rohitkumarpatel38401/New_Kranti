<?php

namespace App\Policies;

use App\Models\Admin;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Every website CMS item (sliders, notices, gallery, faculty, testimonials, downloads) needs manage_website.
 */
class WebsiteContentPolicy
{
    public function before(Authenticatable $user): bool
    {
        return $user instanceof Admin && $user->can('manage_website');
    }

    public function viewAny(): bool
    {
        return false;
    }

    public function view(): bool
    {
        return false;
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

    public function restore(): bool
    {
        return false;
    }

    public function restoreAny(): bool
    {
        return false;
    }

    public function forceDelete(): bool
    {
        return false;
    }

    public function forceDeleteAny(): bool
    {
        return false;
    }

    public function replicate(): bool
    {
        return false;
    }

    public function reorder(): bool
    {
        return false;
    }
}
