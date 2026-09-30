<?php

return [

    /*
    | Where franchises send wallet recharges. Shown on the recharge form.
    | Moves to the Settings screen with the website CMS.
    */
    'payment' => [
        'upi_id' => env('KCTI_PAYMENT_UPI_ID'),
        'account_name' => env('KCTI_PAYMENT_ACCOUNT_NAME', 'Kranti Computer Training Institute'),
        'account_number' => env('KCTI_PAYMENT_ACCOUNT_NUMBER'),
        'ifsc' => env('KCTI_PAYMENT_IFSC'),
        'bank_name' => env('KCTI_PAYMENT_BANK_NAME'),
    ],

    /*
    | Demo accounts created by `kranti:install` (local, or --demo) and DemoSeeder.
    | On local only, the three login forms are prefilled with these (turn off with KCTI_DEMO_PREFILL=false).
    */
    'demo' => [
        'prefill' => env('KCTI_DEMO_PREFILL', true),
        'password' => 'admin123',
        'admin' => ['name' => 'Kranti Admin', 'email' => 'admin@example.com'],
        'institute' => ['name' => 'Demo Computer Centre', 'email' => 'institue@example.com'],
        'student' => ['email' => 'student@example.com'],
    ],

];
