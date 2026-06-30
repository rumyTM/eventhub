<?php

namespace App\Repositories\Contracts;

use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

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

    /**
     * Published or ongoing events whose starts_at falls in [$from, $to].
     * Used by SendEventReminders to find events needing a reminder dispatch.
     *
     * @return Collection<int, Event>
     */
    public function findStartingInWindow(Carbon $from, Carbon $to): Collection;
}
