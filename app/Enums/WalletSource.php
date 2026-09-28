<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WalletSource: string implements HasLabel
{
    case Recharge = 'recharge';
    case EnrollmentFee = 'enrollment_fee';
    case EnrollmentRefund = 'enrollment_refund';
    case Adjustment = 'adjustment';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return __('enums.wallet_source.'.$this->value);
    }
}
