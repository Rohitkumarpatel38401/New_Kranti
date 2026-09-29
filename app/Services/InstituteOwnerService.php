<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\Admin;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Support\Permissions\InstitutePermissions;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Owner logins for institutes HQ created by hand. The owner sets their own password through a reset link,
 * so no password ever travels by email.
 */
class InstituteOwnerService
{
    public function createOwner(Institute $institute, string $name, string $email, ?string $phone, Admin $by): InstituteUser
    {
        $email = Str::lower(trim($email));

        $owner = DB::transaction(function () use ($institute, $name, $email, $phone, $by) {
            Institute::query()->lockForUpdate()->findOrFail($institute->id);

            if ($institute->owner()->exists()) {
                throw new DomainRuleException("{$institute->name} already has an owner login.");
            }

            if (InstituteUser::withTrashed()->where('email', $email)->exists()) {
                throw new DomainRuleException("{$email} already has an institute login.");
            }

            $owner = InstituteUser::create([
                'institute_id' => $institute->id,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => Str::password(32),
                'is_owner' => true,
                'is_active' => true,
            ]);
            $owner->assignRole(InstitutePermissions::OWNER);

            activity()->causedBy($by)->performedOn($institute)->event('institute.owner_created')->log('Owner login created');

            return $owner;
        });

        $this->sendLink($owner);

        return $owner;
    }

    public function sendResetLink(Institute $institute): void
    {
        $owner = $institute->owner()->first();

        if (! $owner) {
            throw new DomainRuleException("{$institute->name} has no owner login yet.");
        }

        $this->sendLink($owner);
    }

    private function sendLink(InstituteUser $owner): void
    {
        // Laravel's default notification links to route('password.reset'), which does not exist here;
        // the link must open the institute panel's own reset page.
        $status = Password::broker('institute_users')->sendResetLink(['email' => $owner->email], function ($user, string $token) {
            $notification = new ResetPasswordNotification($token);
            $notification->url = Filament::getPanel('institute')->getResetPasswordUrl($token, $user);
            $user->notify($notification);
        });

        if ($status !== Password::RESET_LINK_SENT) {
            throw new DomainRuleException(__($status));
        }
    }
}
