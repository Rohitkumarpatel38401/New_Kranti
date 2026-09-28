<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum VerificationStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return __('enums.verification_status.'.$this->value);
    }
}
