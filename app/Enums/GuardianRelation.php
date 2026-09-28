<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum GuardianRelation: string implements HasLabel
{
    case Father = 'father';
    case Mother = 'mother';
    case Guardian = 'guardian';
    case Spouse = 'spouse';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return __('enums.guardian_relation.'.$this->value);
    }
}
