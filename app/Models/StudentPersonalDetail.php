<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\SocialCategory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sensitive personal details. Aadhaar is encrypted at rest; aadhaar_hash (keyed HMAC) enforces uniqueness.
 */
class StudentPersonalDetail extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['aadhaar_encrypted', 'aadhaar_hash'];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'gender' => Gender::class,
            'category' => SocialCategory::class,
            'aadhaar_encrypted' => 'encrypted',
        ];
    }

    public static function normalizeAadhaar(?string $value): ?string
    {
        $digits = preg_replace('/\s+|-/', '', (string) $value);

        return $digits === '' ? null : $digits;
    }

    public static function isValidAadhaar(string $digits): bool
    {
        return (bool) preg_match('/^[2-9]\d{11}$/', $digits);
    }

    public static function hashAadhaar(string $digits): string
    {
        return hash_hmac('sha256', $digits, (string) config('app.key'));
    }

    /** Plain Aadhaar digits (decrypted); setting it also updates the hash. */
    protected function aadhaar(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->aadhaar_encrypted,
            set: function (?string $value) {
                $digits = self::normalizeAadhaar($value);

                return [
                    'aadhaar_encrypted' => $digits === null ? null : $this->castAttributeAsEncryptedString('aadhaar_encrypted', $digits),
                    'aadhaar_hash' => $digits === null ? null : self::hashAadhaar($digits),
                ];
            },
        );
    }

    protected function aadhaarMasked(): Attribute
    {
        return Attribute::get(fn () => $this->aadhaar ? 'XXXX-XXXX-'.substr($this->aadhaar, -4) : null);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
