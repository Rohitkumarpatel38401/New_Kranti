<?php

namespace App\Policies;

class BatchPolicy extends InstituteScopedPolicy
{
    protected static string $subject = 'Batch';

    protected static string $managePermission = 'submit_enrollment';
}
