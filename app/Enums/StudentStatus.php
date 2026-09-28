<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StudentStatus: string implements HasLabel
{
    case Active = 'active';
    case Completed = 'completed';
    case Dropped = 'dropped';
    case Suspended = 'suspended';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return __('enums.student_status.'.$this->value);
    }
}
