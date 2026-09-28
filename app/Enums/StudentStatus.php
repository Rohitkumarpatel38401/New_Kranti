<?php

namespace App\Enums;

enum StudentStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Dropped = 'dropped';
    case Suspended = 'suspended';

    public function label(): string
    {
        return __('enums.student_status.'.$this->value);
    }
}
