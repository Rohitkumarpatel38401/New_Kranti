<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BatchStatus: string implements HasLabel
{
    case Upcoming = 'upcoming';
    case Running = 'running';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return __('enums.batch_status.'.$this->value);
    }
}
