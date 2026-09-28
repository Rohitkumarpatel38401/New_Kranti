<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Gap-tolerant business number sequences (institute codes, receipts, ...), locked per key.
 */
class NumberSequenceService
{
    public function next(string $key): int
    {
        return DB::transaction(function () use ($key) {
            // Lock first: INSERT IGNORE on an existing row takes a shared lock, and two sessions
            // both upgrading it to FOR UPDATE deadlock on MySQL/MariaDB.
            $current = $this->lockedValue($key);

            if ($current === null) {
                $this->ensure($key);
                $current = $this->lockedValue($key);
            }

            $next = $current + 1;

            DB::table('number_sequences')->where('key', $key)->update(['last_value' => $next, 'updated_at' => now()]);

            return $next;
        });
    }

    public function peek(string $key): int
    {
        return (int) DB::table('number_sequences')->where('key', $key)->value('last_value');
    }

    public function syncAtLeast(string $key, int $value): void
    {
        DB::transaction(function () use ($key, $value) {
            $this->ensure($key);

            DB::table('number_sequences')
                ->where('key', $key)
                ->where('last_value', '<', $value)
                ->update(['last_value' => $value, 'updated_at' => now()]);
        });
    }

    public function nextInstituteCode(): string
    {
        return 'KCTI'.str_pad((string) $this->next('institute_code'), 4, '0', STR_PAD_LEFT);
    }

    private function lockedValue(string $key): ?int
    {
        $value = DB::table('number_sequences')->where('key', $key)->lockForUpdate()->value('last_value');

        return $value === null ? null : (int) $value;
    }

    private function ensure(string $key): void
    {
        DB::table('number_sequences')->insertOrIgnore([
            'key' => $key,
            'last_value' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
