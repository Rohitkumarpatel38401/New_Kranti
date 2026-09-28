<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\GradeScale;

class GradeScaleService
{
    /** Ranges are inclusive, so 0–39.99 and 40–100 can coexist but 30–45 cannot. */
    public function assertNoOverlap(float $min, float $max, ?int $ignoreId = null): void
    {
        if ($min > $max) {
            throw new DomainRuleException('Minimum percentage must not be greater than maximum.');
        }

        $clash = GradeScale::query()
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->where('min_percent', '<=', $max)
            ->where('max_percent', '>=', $min)
            ->first();

        if ($clash) {
            throw new DomainRuleException("This range overlaps grade {$clash->grade} ({$clash->min_percent}–{$clash->max_percent}%).");
        }
    }
}
