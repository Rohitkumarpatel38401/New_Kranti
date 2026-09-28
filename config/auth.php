<?php

use App\Models\Admin;
use App\Models\InstituteUser;
use App\Models\Student;

return [

    /*
    | Three separate login tables: HQ admins, franchise institute users, and students.
    | Each Filament panel authenticates with its own guard.
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'admin'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'admins'),
    ],

    'guards' => [
        'admin' => ['driver' => 'session', 'provider' => 'admins'],
        'institute' => ['driver' => 'session', 'provider' => 'institute_users'],
        'student' => ['driver' => 'session', 'provider' => 'students'],
    ],

    'providers' => [
        'admins' => ['driver' => 'eloquent', 'model' => Admin::class],
        'institute_users' => ['driver' => 'eloquent', 'model' => InstituteUser::class],
        'students' => ['driver' => 'eloquent', 'model' => Student::class],
    ],

    'passwords' => [
        'admins' => ['provider' => 'admins', 'table' => 'admin_password_reset_tokens', 'expire' => 60, 'throttle' => 60],
        'institute_users' => ['provider' => 'institute_users', 'table' => 'institute_user_password_reset_tokens', 'expire' => 60, 'throttle' => 60],
        'students' => ['provider' => 'students', 'table' => 'student_password_reset_tokens', 'expire' => 60, 'throttle' => 60],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
