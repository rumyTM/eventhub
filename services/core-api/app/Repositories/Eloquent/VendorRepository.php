<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Models\Vendor;
use App\Repositories\Contracts\VendorRepositoryInterface;

final class VendorRepository implements VendorRepositoryInterface
{
    public function createForUser(User $user, array $attributes): Vendor
    {
        return $user->vendor()->create($attributes);
    }
}
