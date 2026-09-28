<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WalletTransactionType: string implements HasLabel
{
    case Credit = 'credit';
    case Debit = 'debit';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return __('enums.wallet_transaction_type.'.$this->value);
    }
}
