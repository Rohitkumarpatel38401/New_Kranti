<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SocialCategory: string implements HasLabel
{
    case General = 'gen';
    case Obc = 'obc';
    case Sc = 'sc';
    case St = 'st';
    case Ews = 'ews';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return __('enums.social_category.'.$this->value);
    }
}
