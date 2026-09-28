<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EnrollmentStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Rejected = 'rejected';
    case Completed = 'completed';
    case Dropped = 'dropped';
    case Cancelled = 'cancelled';

    /** Allowed next states (spec §4.6). */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Draft => [self::PendingApproval, self::Cancelled],
            self::PendingApproval => [self::Active, self::Rejected],
            self::Rejected => [self::Draft],
            self::Active => [self::Completed, self::Dropped],
            self::Completed, self::Dropped, self::Cancelled => [],
        };
    }

    public function canMoveTo(self $to): bool
    {
        return in_array($to, $this->allowedNext(), true);
    }

    /** Statuses that block a second enrollment in the same course. */
    public static function open(): array
    {
        return [self::Draft, self::PendingApproval, self::Active];
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return __('enums.enrollment_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingApproval => 'warning',
            self::Active => 'success',
            self::Completed => 'info',
            self::Rejected, self::Dropped, self::Cancelled => 'danger',
        };
    }
}
