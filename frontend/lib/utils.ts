import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";
import { formatInTimeZone } from "date-fns-tz";
import type { OrderEventSummary } from "./api/types";

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

/** Convert integer poisha to a BDT display string, e.g. 10000 → "100.00 BDT" */
export function formatMoney(poisha: number, currency = "BDT"): string {
  return `${(poisha / 100).toFixed(2)} ${currency}`;
}

/** Format ISO-8601 string to a readable local string */
export function formatDate(iso: string | null | undefined): string {
  if (!iso) return "—";
  return new Date(iso).toLocaleString("en-BD", {
    dateStyle: "medium",
    timeStyle: "short",
  });
}

/**
 * Format an event's UTC datetime as its own authoritative wall-clock time (the timezone the vendor
 * declared) plus, when the viewer's browser is in a different zone, a "your time" conversion — so an
 * attendee browsing from another timezone isn't left guessing what the event's local time means for
 * them. Returns `userLocal: null` when the two zones match, to avoid showing the same instant twice.
 */
export function formatEventDateTime(
  iso: string | null | undefined,
  eventTimezone: string,
): { eventLocal: string; userLocal: string | null } {
  if (!iso) return { eventLocal: "—", userLocal: null };

  const eventLocal = formatInTimeZone(iso, eventTimezone, "MMM d, yyyy, h:mm a (zzz)");
  const userTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

  if (userTimeZone === eventTimezone) {
    return { eventLocal, userLocal: null };
  }

  return { eventLocal, userLocal: formatInTimeZone(iso, userTimeZone, "MMM d, yyyy, h:mm a (zzz)") };
}

/** Seconds until a future ISO-8601 date, clamped to 0 */
export function secondsUntil(iso: string): number {
  return Math.max(0, Math.floor((new Date(iso).getTime() - Date.now()) / 1000));
}

/** Join an order's distinct event titles for display, e.g. "Dhaka Tech Summit" or "Summit, +1 more" */
export function formatEventNames(events: OrderEventSummary[] | undefined | null): string {
  if (!events || events.length === 0) return "—";
  if (events.length === 1) return events[0].title;
  return `${events[0].title}, +${events.length - 1} more`;
}

/** Format MM:SS countdown */
export function fmtCountdown(totalSeconds: number): string {
  const m = Math.floor(totalSeconds / 60)
    .toString()
    .padStart(2, "0");
  const s = (totalSeconds % 60).toString().padStart(2, "0");
  return `${m}:${s}`;
}
