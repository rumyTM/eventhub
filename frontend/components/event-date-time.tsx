import { formatEventDateTime } from "@/lib/utils";

interface Props {
  iso: string | null | undefined;
  timezone: string;
  className?: string;
}

/**
 * Renders an event's UTC datetime in its own declared timezone (the authoritative wall-clock time),
 * plus a "Your time" conversion when the viewer's browser is in a different zone. Shared across every
 * page that shows an event's starts_at/ends_at so the conversion logic lives in exactly one place.
 */
export function EventDateTime({ iso, timezone, className }: Props) {
  const { eventLocal, userLocal } = formatEventDateTime(iso, timezone);

  return (
    <span className={className}>
      {eventLocal}
      {userLocal && (
        <span className="block text-xs text-muted-foreground">Your time: {userLocal}</span>
      )}
    </span>
  );
}
