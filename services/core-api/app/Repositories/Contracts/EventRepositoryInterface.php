<?php

namespace App\Repositories\Contracts;

use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface EventRepositoryInterface
{
    /** Public catalog: published events only. */
    public function paginatePublished(int $perPage): LengthAwarePaginator;

    /** A single vendor's own events (all statuses). */
    public function paginateForVendor(string $vendorId, int $perPage): LengthAwarePaginator;

    /** Admin view: every event. */
    public function paginateAll(int $perPage): LengthAwarePaginator;

    public function create(array $attributes): Event;

    public function update(Event $event, array $attributes): Event;

    public function delete(Event $event): void;

    /** Re-read an event under a row lock (FOR UPDATE) — call inside a transaction. */
    public function lockForUpdate(string $id): Event;
}
