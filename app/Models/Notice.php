<?php

namespace App\Models;

use App\Models\Concerns\IsWebsiteContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * News and notices. Visible once published; ticker notices also scroll on the homepage.
 */
class Notice extends Model
{
    use HasFactory, IsWebsiteContent, SoftDeletes;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (Notice $notice) {
            if (blank($notice->slug)) {
                $base = Str::slug($notice->title['en'] ?? '') ?: 'notice';
                $slug = $base;

                for ($i = 2; static::withTrashed()->where('slug', $slug)->exists(); $i++) {
                    $slug = "{$base}-{$i}";
                }

                $notice->slug = $slug;
            }
        });
    }

    protected function casts(): array
    {
        return ['title' => 'array', 'body' => 'array', 'published_at' => 'datetime', 'show_in_ticker' => 'boolean', 'is_active' => 'boolean'];
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereNotNull('published_at')->where('published_at', '<=', now())
            ->orderByDesc('published_at')->orderByDesc('id');
    }
}
