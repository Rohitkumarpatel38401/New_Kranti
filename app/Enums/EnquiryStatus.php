<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EnquiryStatus: string implements HasLabel
{
    case New = 'new';
    case Contacted = 'contacted';
    case Converted = 'converted';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return __('enums.enquiry_status.'.$this->value);
    }
}
