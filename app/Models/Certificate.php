<?php

namespace App\Models;

use App\Enums\CertificateStatus;
use App\Models\Concerns\BelongsToInstitute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An issued course certificate. Never deleted; revoked with a reason instead.
 */
class Certificate extends Model
{
    use BelongsToInstitute;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new \LogicException('Certificates are revoked, never deleted.'));
    }

    protected function casts(): array
    {
        return ['status' => CertificateStatus::class, 'issued_on' => 'date', 'revoked_at' => 'datetime'];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'issued_by_admin_id');
    }

    public function isValid(): bool
    {
        return $this->status === CertificateStatus::Issued;
    }

    /** Printed in the QR code, so always the public site (APP_URL), never the host of the current request. */
    public function verificationUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/verify/'.$this->verification_token;
    }
}
