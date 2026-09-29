<?php

namespace App\Policies;

class AttendanceSessionPolicy extends InstituteScopedPolicy
{
    protected static string $subject = 'AttendanceSession';

    protected static string $managePermission = 'take_attendance';
}
