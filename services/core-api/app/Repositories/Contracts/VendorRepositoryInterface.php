<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use App\Models\Vendor;

interface VendorRepositoryInterface
{
    /** Create the vendor profile owned by the given user. */
    public function createForUser(User $user, array $attributes): Vendor;
}
