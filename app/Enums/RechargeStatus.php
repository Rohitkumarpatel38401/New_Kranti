<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RechargeStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return __('enums.recharge_status.'.$this->value);
    }
}
