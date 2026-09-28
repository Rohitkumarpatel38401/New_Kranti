<?php

namespace App\Models\Concerns;

/**
 * Reads bilingual JSON columns shaped {"en": "...", "hi": "..."} (cast as array),
 * falling back to English when the requested language is missing.
 */
trait HasLocalizedText
{
    public function localized(string $attribute, ?string $locale = null): ?string
    {
        $values = $this->getAttribute($attribute);

        if (! is_array($values)) {
            return null;
        }

        foreach ([$locale ?? app()->getLocale(), 'en'] as $key) {
            $value = $values[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }
}
