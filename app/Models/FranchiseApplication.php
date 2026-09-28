<?php

namespace App\Models;

use App\Enums\FranchiseApplicationStatus;
use App\Models\Concerns\HasAddresses;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FranchiseApplication extends Model
{
    use HasAddresses, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => FranchiseApplicationStatus::class, 'reviewed_at' => 'datetime'];
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }

    public function convertedInstitute(): BelongsTo
    {
        return $this->belongsTo(Institute::class, 'converted_institute_id');
    }
}
