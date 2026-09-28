<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Institute;
use App\Models\InstituteUser;
use Illuminate\Support\Facades\Auth;

/**
 * Lets an HQ admin sign in to the institute panel as an institute's owner
 * while staying signed in to the admin panel. Every start and stop is audited.
 */
class ImpersonationService
{
    public const SESSION_KEY = 'impersonator_admin_id';

    public function start(Admin $admin, Institute $institute): InstituteUser
    {
        // auth:admin does not check is_active, and a signed link stays valid for 5 minutes.
        abort_unless($admin->is_active && $admin->can('impersonate_institute'), 403);
        abort_unless($institute->isActive(), 422, 'Institute is not active.');

        $owner = $this->ownerOf($institute);
        abort_if($owner === null, 422, 'Institute has no active owner account.');

        Auth::guard('institute')->login($owner);
        session()->put(self::SESSION_KEY, $admin->getKey());

        activity()
            ->causedBy($admin)
            ->performedOn($institute)
            ->event('impersonation.started')
            ->withProperties(['institute_user_id' => $owner->getKey()])
            ->log('Admin started impersonating institute');

        return $owner;
    }

    public function stop(): Admin
    {
        $admin = $this->impersonator();
        abort_if($admin === null, 403);

        // Logging out one guard keeps the admin guard's session intact; never invalidate the session here.
        // logoutCurrentDevice() leaves the owner's remember_token alone, so their own devices stay signed in.
        $owner = Auth::guard('institute')->user();
        Auth::guard('institute')->logoutCurrentDevice();
        session()->forget(self::SESSION_KEY);

        $log = activity()->causedBy($admin)->event('impersonation.stopped');

        if ($owner?->institute) {
            $log->performedOn($owner->institute);
        }

        $log->log('Admin stopped impersonating institute');

        return $admin;
    }

    public function impersonator(): ?Admin
    {
        $id = session(self::SESSION_KEY);

        return $id ? Admin::find($id) : null;
    }

    public function isImpersonating(): bool
    {
        return session()->has(self::SESSION_KEY);
    }

    public function ownerOf(Institute $institute): ?InstituteUser
    {
        return InstituteUser::query()
            ->where('institute_id', $institute->getKey())
            ->where('is_owner', true)
            ->where('is_active', true)
            ->first();
    }
}
