<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * Authorization for order-scoped actions. An attendee may only act on their OWN order (the core
 * multi-tenant ownership guard); admins may initiate a refund for any order. Route middleware gates the
 * role; this policy is the row-level ownership check (defence in depth).
 */
class OrderPolicy
{
    /** An attendee may view their own order, or an admin can view any order. */
    public function view(User $user, Order $order): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isAttendee()
            && $user->attendee !== null
            && $order->attendee_id === $user->attendee->id;
    }

    /** An attendee may request a refund only for their own order. */
    public function refund(User $user, Order $order): bool
    {
        return $user->isAttendee()
            && $user->attendee !== null
            && $order->attendee_id === $user->attendee->id;
    }

    /** An admin may initiate/approve a refund for any order. */
    public function initiateRefund(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }
}
