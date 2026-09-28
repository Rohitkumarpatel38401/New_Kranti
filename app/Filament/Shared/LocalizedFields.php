<?php

namespace App\Filament\Shared;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * Side-by-side English and Hindi inputs for a bilingual JSON column.
 */
class LocalizedFields
{
    /**
     * @return array{0: TextInput|Textarea, 1: TextInput|Textarea}
     */
    public static function make(string $name, string $label, bool $textarea = false, bool $required = true): array
    {
        $component = $textarea ? Textarea::class : TextInput::class;

        $english = $component::make("{$name}.en")->label("{$label} (English)")->required($required);
        $hindi = $component::make("{$name}.hi")->label("{$label} (हिन्दी)");

        if (! $textarea) {
            $english->maxLength(255);
            $hindi->maxLength(255);
        }

        return [$english, $hindi];
    }
}
