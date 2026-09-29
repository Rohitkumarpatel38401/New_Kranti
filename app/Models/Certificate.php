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

    public function verificationUrl(): string
    {
        return route('site.verify', $this->verification_token);
    }
}
