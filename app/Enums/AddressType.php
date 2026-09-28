<?php

namespace App\Enums;

enum AddressType: string
{
    case Office = 'office';
    case Current = 'current';
    case Permanent = 'permanent';

    public function label(): string
    {
        return __('enums.address_type.'.$this->value);
    }
}
