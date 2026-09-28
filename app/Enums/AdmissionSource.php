<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AdmissionSource: string implements HasLabel
{
    case Website = 'website';
    case WalkIn = 'walk_in';
    case Franchise = 'franchise';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return __('enums.admission_source.'.$this->value);
    }
}
