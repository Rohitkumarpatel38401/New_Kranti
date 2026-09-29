<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ResultOutcome: string implements HasLabel
{
    case Pass = 'pass';
    case Fail = 'fail';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return __('enums.result_outcome.'.$this->value);
    }
}
