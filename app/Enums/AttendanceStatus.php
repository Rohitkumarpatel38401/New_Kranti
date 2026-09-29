<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AttendanceStatus: string implements HasLabel
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Leave = 'leave';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return __('enums.attendance_status.'.$this->value);
    }
}
