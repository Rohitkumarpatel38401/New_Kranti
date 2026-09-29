<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FeePaymentStatus: string implements HasLabel
{
    case Valid = 'valid';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return __('enums.fee_payment_status.'.$this->value);
    }
}
