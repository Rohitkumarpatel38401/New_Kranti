<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * Admin-managed website content: active items in sort order, with public-disk media URLs.
 */
trait IsWebsiteContent
{
    use HasLocalizedText;

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function mediaUrl(string $attribute = 'image_path'): ?string
    {
        $path = $this->getAttribute($attribute);

        return $path ? Storage::disk('public')->url($path) : null;
    }
}
