<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DurationUnit: string implements HasLabel
{
    case Hours = 'hours';
    case Weeks = 'weeks';
    case Months = 'months';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return __('enums.duration_unit.'.$this->value);
    }
}
