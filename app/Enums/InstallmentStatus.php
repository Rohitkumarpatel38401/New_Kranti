<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InstallmentStatus: string implements HasLabel
{
    case Paid = 'paid';
    case Partial = 'partial';
    case Overdue = 'overdue';
    case Upcoming = 'upcoming';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return __('enums.installment_status.'.$this->value);
    }
}
