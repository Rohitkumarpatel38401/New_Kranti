<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AddressType: string implements HasLabel
{
    case Office = 'office';
    case Current = 'current';
    case Permanent = 'permanent';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return __('enums.address_type.'.$this->value);
    }
}
