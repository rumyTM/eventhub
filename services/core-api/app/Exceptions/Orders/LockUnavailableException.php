<?php

namespace App\Exceptions\Orders;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when the short-lived per-ticket_type cache lock (the Redis "distributed front" of ADR-07)
 * could not be acquired within the wait window — i.e. another checkout is mid-flight for that ticket
 * type. Maps to HTTP 409; the client may retry. Correctness never depends on this lock (the DB row
 * lock is authoritative); it only reduces FOR UPDATE contention.
 */
final class LockUnavailableException extends HttpException
{
    public function __construct()
    {
        parent::__construct(statusCode: 409, message: __('api.orders.lock_unavailable'));
    }
}
