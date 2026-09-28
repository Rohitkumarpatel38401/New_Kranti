<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InstituteStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Expired = 'expired';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return __('enums.institute_status.'.$this->value);
    }
}
