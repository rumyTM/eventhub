<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects a datetime string with no UTC offset (e.g. "2026-09-20T18:00"). Laravel's `date` rule
 * accepts these, and Carbon silently interprets them using the app timezone (UTC) — silently
 * corrupting an event/ticket-type time a client meant as wall-clock in a different IANA zone (the
 * exact bug: a vendor picks "6:00 PM" in the event's "Asia/Dhaka" timezone, but an offset-less
 * "18:00" gets stored as 18:00 UTC, six hours off). Every datetime in this service's API contract is
 * documented with an offset-bearing example (e.g. "2026-09-20T18:00:00+06:00"); this rule enforces
 * that contract at the boundary instead of trusting the client to always send one.
 */
final class IsoDateTimeWithOffset implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return; // the sibling `date` rule reports the type error
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/', $value)) {
            $fail(__('api.validation.datetime_offset'));
        }
    }
}
