<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ResultStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Published = 'published';
    case Returned = 'returned';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return __('enums.result_status.'.$this->value);
    }
}
