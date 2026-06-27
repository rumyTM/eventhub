<?php

namespace App\Models;

use Database\Factories\EventReminderFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Idempotent reminder marker: one row per (event_id, type) so re-running SendEventReminders never
 * double-dispatches. Per-recipient delivery is tracked in the notification-service.
 */
class EventReminder extends Model
{
    /** @use HasFactory<EventReminderFactory> */
    use HasFactory, HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'event_id',
        'type',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
