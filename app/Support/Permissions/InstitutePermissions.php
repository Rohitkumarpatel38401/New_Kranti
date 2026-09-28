<?php

namespace App\Support\Permissions;

/**
 * Institute-guard (franchise team) roles. Defined in code and synced by the seeder,
 * because Spatie roles are shared by every institute.
 */
final class InstitutePermissions
{
    public const OWNER = 'owner';

    public const CUSTOM = [
        'submit_enrollment',
        'collect_fee',
        'cancel_receipt',
        'take_attendance',
        'enter_marks',
        'submit_result',
        'request_wallet_recharge',
        'manage_staff',
    ];

    public const ROLE_PERMISSIONS = [
        'owner' => self::CUSTOM,
        'receptionist' => ['submit_enrollment', 'collect_fee'],
        'teacher' => ['take_attendance', 'enter_marks', 'submit_result'],
        'accountant' => ['collect_fee', 'cancel_receipt', 'request_wallet_recharge'],
    ];
}
