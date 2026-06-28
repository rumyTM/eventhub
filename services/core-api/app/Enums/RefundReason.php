<?php

namespace App\Enums;

/**
 * Why a refund was raised — the category that drives the policy, NOT free text. An attendee-requested
 * refund follows the time-based 100/50/0% window (CLAUDE.md §F Refunds); an event-cancelled refund is
 * policy-overridden to 100% and funded by the vendor (ADR-23). Snapshotted onto the refund row so the
 * decision stays reproducible.
 */
enum RefundReason: string
{
    case AttendeeRequested = 'attendee_requested';
    case EventCancelled = 'event_cancelled';

    public function label(): string
    {
        return match ($this) {
            self::AttendeeRequested => 'Attendee requested',
            self::EventCancelled => 'Event cancelled',
        };
    }

    /** Cancellation overrides the time-based window with a flat 100% refund (ADR-23). */
    public function isCancellation(): bool
    {
        return $this === self::EventCancelled;
    }
}
