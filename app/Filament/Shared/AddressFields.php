<?php

namespace App\Filament\Shared;

use Filament\Forms\Components\TextInput;

/**
 * Indian postal address inputs stored under one form key (e.g. "office_address.city").
 */
class AddressFields
{
    /**
     * @return array<TextInput>
     */
    public static function make(string $prefix, bool $required = true): array
    {
        return [
            TextInput::make("{$prefix}.line_1")->label('Address line 1')->required($required)->maxLength(255),
            TextInput::make("{$prefix}.line_2")->label('Address line 2')->maxLength(255),
            TextInput::make("{$prefix}.landmark")->label('Landmark')->maxLength(255),
            TextInput::make("{$prefix}.city")->label('City / Town')->required($required)->maxLength(100),
            TextInput::make("{$prefix}.district")->label('District')->required($required)->maxLength(100),
            TextInput::make("{$prefix}.state")->label('State')->required($required)->maxLength(100),
            TextInput::make("{$prefix}.pincode")->label('PIN code')->required($required)->regex('/^[1-9][0-9]{5}$/'),
        ];
    }

    /** Keys an address form sends, for filling and saving. */
    public const KEYS = ['line_1', 'line_2', 'landmark', 'city', 'district', 'state', 'pincode'];
}
