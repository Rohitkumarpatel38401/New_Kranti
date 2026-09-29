<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EnquiryType: string implements HasLabel
{
    case Contact = 'contact';
    case Admission = 'admission';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return __('enums.enquiry_type.'.$this->value);
    }
}
