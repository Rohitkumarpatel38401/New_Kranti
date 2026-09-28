<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FranchiseApplicationStatus: string implements HasLabel
{
    case New = 'new';
    case Contacted = 'contacted';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return __('enums.franchise_application_status.'.$this->value);
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::New, self::Contacted], true);
    }
}
