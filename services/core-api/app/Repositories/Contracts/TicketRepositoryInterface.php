<?php

namespace App\Repositories\Contracts;

use App\Models\Ticket;

interface TicketRepositoryInterface
{
    /**
     * Issue one ticket. Called once per held unit on payment success.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Ticket;
}
