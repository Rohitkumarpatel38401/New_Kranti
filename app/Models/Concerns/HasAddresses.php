<?php

namespace App\Models\Concerns;

use App\Enums\AddressType;
use App\Models\Address;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasAddresses
{
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function address(AddressType $type): ?Address
    {
        return $this->addresses->firstWhere('type', $type);
    }

    public function saveAddress(AddressType $type, array $data): Address
    {
        $address = $this->addresses()->updateOrCreate(['type' => $type], $data);
        $this->unsetRelation('addresses');

        return $address;
    }
}
