<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vendor;

/**
 * A vendor may submit KYC only for its own profile; only admins may review (list pending / verify / reject).
 * Review routes are also gated by role:admin middleware — this policy is defence in depth.
 */
class VendorPolicy
{
    public function submitKyc(User $user, Vendor $vendor): bool
    {
        return $user->isVendor()
            && $user->vendor !== null
            && $user->vendor->id === $vendor->id;
    }

    public function reviewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function review(User $user, Vendor $vendor): bool
    {
        return $user->isAdmin();
    }
}
