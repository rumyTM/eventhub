<?php

namespace App\Repositories\Eloquent;

use App\Models\Ticket;
use App\Repositories\Contracts\TicketRepositoryInterface;

final class TicketRepository implements TicketRepositoryInterface
{
    public function create(array $attributes): Ticket
    {
        return Ticket::create($attributes);
    }
}
