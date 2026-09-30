<?php

namespace App\Policies;

use App\Models\InstituteUser;
use App\Models\Student;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * For records owned by an institute: HQ admins use Shield keys ("View:Student"),
 * institute users see their own institute's rows and change them with one institute permission.
 * Students may only open their own records, where a policy allows it (studentMayView).
 * Deleting is off by default for institute users; history is cancelled, not deleted.
 */
abstract class InstituteScopedPolicy extends ShieldPolicy
{
    /** Institute permission needed to create or change records. */
    protected static string $managePermission;

    public function viewAny(Authenticatable $user): bool
    {
        return $user instanceof InstituteUser ? $user->is_active : parent::viewAny($user);
    }

    public function view(Authenticatable $user, Model $model): bool
    {
        return match (true) {
            $user instanceof InstituteUser => $this->ownsRecord($user, $model),
            $user instanceof Student => self::studentAccountOk($user) && $this->studentMayView($user, $model),
            default => parent::view($user, $model),
        };
    }

    public function create(Authenticatable $user): bool
    {
        return $user instanceof InstituteUser ? $user->can(static::$managePermission) : parent::create($user);
    }

    public function update(Authenticatable $user, Model $model): bool
    {
        return $user instanceof InstituteUser
            ? $this->ownsRecord($user, $model) && $user->can(static::$managePermission)
            : parent::update($user, $model);
    }

    public function delete(Authenticatable $user, Model $model): bool
    {
        return $user instanceof InstituteUser ? false : parent::delete($user, $model);
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return $user instanceof InstituteUser ? false : parent::deleteAny($user);
    }

    /**
     * Documents and receipts are served outside the student panel, so the panel's account rules are
     * repeated here: portal open (login allowed, not dropped, institute active) and the first password changed.
     */
    public static function studentAccountOk(Student $student): bool
    {
        return $student->portalOpen() && ! $student->must_change_password;
    }

    /** Students see nothing unless a policy says which of their own records they may open. */
    protected function studentMayView(Student $student, Model $model): bool
    {
        return false;
    }

    protected function ownsRecord(InstituteUser $user, Model $model): bool
    {
        return $user->is_active && $model->institute_id === $user->institute_id;
    }
}
