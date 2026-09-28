<?php

namespace App\Enums;

enum InstituteStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Expired = 'expired';
    case Closed = 'closed';

    public function label(): string
    {
        return __('enums.institute_status.'.$this->value);
    }
}
