<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DocumentType: string implements HasLabel
{
    case OwnerAadhaar = 'owner_aadhaar';
    case OwnerPan = 'owner_pan';
    case GstCertificate = 'gst_certificate';
    case RentAgreement = 'rent_agreement';
    case CenterPhoto = 'center_photo';
    case Other = 'other';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return __('enums.document_type.'.$this->value);
    }
}
