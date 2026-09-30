<?php

namespace App\Support;

/**
 * Demo credentials for prefilling the login forms. Only ever on the local environment.
 */
final class DemoLogin
{
    /** @return array{login: string, password: string}|null */
    public static function credentials(string $panel): ?array
    {
        if (! app()->isLocal() || ! config('kcti.demo.prefill')) {
            return null;
        }

        $login = match ($panel) {
            'admin' => config('kcti.demo.admin.email'),
            'institute' => config('kcti.demo.institute.email'),
            'student' => config('kcti.demo.student.email'),
            default => null,
        };

        return $login ? ['login' => $login, 'password' => config('kcti.demo.password')] : null;
    }
}
