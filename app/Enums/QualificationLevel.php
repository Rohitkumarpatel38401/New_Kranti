<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum QualificationLevel: string implements HasLabel
{
    case Tenth = 'tenth';
    case Twelfth = 'twelfth';
    case Diploma = 'diploma';
    case Graduation = 'graduation';
    case PostGraduation = 'post_graduation';
    case Other = 'other';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return __('enums.qualification_level.'.$this->value);
    }
}
