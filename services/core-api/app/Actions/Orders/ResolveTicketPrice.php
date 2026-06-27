<?php

namespace App\Actions\Orders;

use App\Models\TicketType;

/**
 * Resolves the per-unit price (in integer minor units / poisha) for a checkout line, applying the
 * group-bundle discount when it applies. Pure and side-effect free so it is unit-testable in isolation.
 *
 * Group-bundle rule (exact):
 *   - If the ticket type has `group_size` set (not null) AND the line `quantity` >= `group_size`,
 *     every unit on the line is discounted: unit_price = round(price * (1 - group_discount)).
 *   - The discount applies to ALL units on the line once the threshold is met (it is not limited to
 *     whole multiples of group_size). `group_discount` is a fraction in [0, 1).
 *   - Otherwise unit_price = price (no discount).
 *   - Rounding is half-up to the nearest poisha; the result is clamped at >= 0.
 *
 * The discount is applied with INTEGER arithmetic (basis points), not float multiplication, so the stored
 * `unit_price` is exact even for large prices or repeating-decimal discounts (no IEEE-754 drift).
 */
final class ResolveTicketPrice
{
    public function handle(TicketType $ticketType, int $quantity): int
    {
        $price = (int) $ticketType->price;
        $groupSize = $ticketType->group_size;

        // group_discount is a 4-dp decimal (string from the DB); convert to basis points (0..9999).
        $basisPoints = $ticketType->group_discount !== null
            ? (int) round((float) $ticketType->group_discount * 10000)
            : 0;

        if ($groupSize !== null && $basisPoints > 0 && $quantity >= $groupSize) {
            // unit = price * (10000 - bp) / 10000, rounded half-up, via integer math only.
            $discounted = intdiv($price * (10000 - $basisPoints) + 5000, 10000);

            return max(0, $discounted);
        }

        return $price;
    }
}
