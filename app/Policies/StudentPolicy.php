<?php

namespace App\Policies;

class StudentPolicy extends InstituteScopedPolicy
{
    protected static string $subject = 'Student';

    protected static string $managePermission = 'submit_enrollment';
}
