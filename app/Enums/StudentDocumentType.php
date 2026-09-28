<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StudentDocumentType: string implements HasLabel
{
    case Photo = 'photo';
    case Signature = 'signature';
    case Aadhaar = 'aadhaar';
    case TenthMarksheet = 'tenth_marksheet';
    case TwelfthMarksheet = 'twelfth_marksheet';
    case Other = 'other';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return __('enums.student_document_type.'.$this->value);
    }
}
