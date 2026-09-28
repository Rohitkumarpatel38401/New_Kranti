<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMode: string implements HasLabel
{
    case Upi = 'upi';
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case Cheque = 'cheque';
    case Card = 'card';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return __('enums.payment_mode.'.$this->value);
    }
}
