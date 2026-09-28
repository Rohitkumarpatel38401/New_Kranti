<?php

namespace App\Policies;

class AcademicSessionPolicy extends ShieldPolicy
{
    protected static string $subject = 'AcademicSession';
}
