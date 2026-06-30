<?php

namespace App\Services\Orders;

use App\Models\User;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
    ) {}

    /**
     * List orders by role: admin can list all (optionally filtered by status); attendee lists own orders.
     */
    public function list(User $user, int $perPage, ?string $status = null): LengthAwarePaginator
    {
        if ($user->isAdmin()) {
            return $this->orders->paginateAll($perPage, $status);
        }

        $attendeeId = $user->attendee?->id;
        if ($attendeeId === null) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        return $this->orders->paginateForAttendee($attendeeId, $perPage);
    }
}
