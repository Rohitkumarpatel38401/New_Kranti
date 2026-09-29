<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CertificateStatus: string implements HasLabel
{
    case Issued = 'issued';
    case Revoked = 'revoked';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return __('enums.certificate_status.'.$this->value);
    }
}
