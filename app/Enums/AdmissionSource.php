<?php

namespace App\Enums;

enum AdmissionSource: string
{
    case Website = 'website';
    case WalkIn = 'walk_in';
    case Franchise = 'franchise';

    public function label(): string
    {
        return __('enums.admission_source.'.$this->value);
    }
}
