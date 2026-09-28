<?php

namespace App\Models\Concerns;

use App\Exceptions\TenantMismatchException;
use App\Models\Institute;
use App\Support\Tenancy\CurrentInstitute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToInstitute
{
    public static function bootBelongsToInstitute(): void
    {
        static::addGlobalScope('institute', function (Builder $query) {
            $current = app(CurrentInstitute::class);

            if ($current->has()) {
                $query->where($query->getModel()->qualifyColumn('institute_id'), $current->id());
            }
        });

        // Eloquent fires `saving` before `creating`, so fill and check in one listener.
        static::saving(function (Model $model) {
            $current = app(CurrentInstitute::class);

            if (! $current->has()) {
                return;
            }

            if ($model->institute_id === null) {
                $model->institute_id = $current->id();

                return;
            }

            if ((int) $model->institute_id !== $current->id()) {
                throw TenantMismatchException::for($model::class, (int) $model->institute_id, $current->id());
            }
        });
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }
}
