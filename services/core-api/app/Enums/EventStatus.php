<?php

namespace App\Enums;

enum EventStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Ongoing => 'Ongoing',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Only published/ongoing events are purchasable. */
    public function isPurchasable(): bool
    {
        return in_array($this, [self::Published, self::Ongoing], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /** Legal lifecycle transitions. `cancelled` is a manual action from any non-terminal state. */
    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Draft => in_array($to, [self::Published, self::Cancelled], true),
            self::Published => in_array($to, [self::Ongoing, self::Cancelled], true),
            self::Ongoing => in_array($to, [self::Completed, self::Cancelled], true),
            self::Completed, self::Cancelled => false,
        };
    }
}
