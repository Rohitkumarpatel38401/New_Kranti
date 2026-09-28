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

];
