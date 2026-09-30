<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * Site-wide settings as "group.key" => value, cached as one array. Unknown keys fall back to DEFAULTS.
 */
class SettingsService
{
    private const CACHE_KEY = 'kcti.settings';

    public const DEFAULTS = [
        'site.name' => 'Kranti Computer Training Institute',
        'site.short_name' => 'KCTI',
        'site.tagline' => ['en' => 'Learn . Build . Succeed', 'hi' => 'सीखें . बनाएं . आगे बढ़ें'],
        'site.established' => '2016',
        'site.stats' => ['students' => '2000+', 'courses' => '50+', 'placement' => '95%', 'years' => '8+'],
    ];

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->all(), $key, self::DEFAULTS[$key] ?? $default);
    }

    /** Saves a value; a real change is written to the activity log with the old and new value and who made it. */
    public function set(string $key, mixed $value): void
    {
        [$group, $name] = explode('.', $key, 2);

        $setting = Setting::query()->firstOrNew(['group' => $group, 'key' => $name]);
        $old = $setting->exists ? $setting->value : null;

        // Unchanged (including a never-saved key left blank): nothing to store or log.
        if ($old === $value || (! $setting->exists && blank($value))) {
            return;
        }

        $setting->value = $value;
        $setting->save();
        Cache::forget(self::CACHE_KEY);

        activity()->causedBy(auth()->user())->performedOn($setting)->event('setting.changed')
            ->withProperties(['key' => $key, 'old' => $old, 'new' => $value])
            ->log("Setting {$key} changed");
    }

    /** Store DEFAULTS for keys that were never saved. Existing values (admin edits) are left alone. */
    public function seedDefaults(): void
    {
        foreach (self::DEFAULTS as $key => $value) {
            [$group, $name] = explode('.', $key, 2);

            Setting::query()->firstOrCreate(['group' => $group, 'key' => $name], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    /** @param  array<string, mixed>  $values  "group.key" => value */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    /** @return array<string, mixed> Nested: ['site' => ['name' => ...]] */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->get()
            ->reduce(function (array $carry, Setting $s) {
                $carry[$s->group][$s->key] = $s->value;

                return $carry;
            }, []));
    }

    /** wa.me link for the WhatsApp number (falls back to the main phone); Indian 10-digit numbers get +91. */
    public function whatsappUrl(?string $text = null): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) ($this->get('site.whatsapp') ?: $this->get('site.phone')));

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10) {
            $digits = '91'.$digits;
        }

        return 'https://wa.me/'.$digits.($text ? '?text='.rawurlencode($text) : '');
    }

    /** Current-language text for a bilingual setting, falling back to English. */
    public function text(string $key): ?string
    {
        $value = $this->get($key);

        if (! is_array($value)) {
            return $value;
        }

        $text = $value[app()->getLocale()] ?? null;

        return filled($text) ? $text : ($value['en'] ?? null);
    }
}
