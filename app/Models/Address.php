<?php

namespace App\Models;

use App\Enums\AddressType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Address extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['type' => AddressType::class];
    }

    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }

    public function oneLine(): string
    {
        return collect([$this->line_1, $this->line_2, $this->landmark, $this->city, $this->district, $this->state, $this->pincode])
            ->filter()
            ->unique()
            ->implode(', ');
    }
}
