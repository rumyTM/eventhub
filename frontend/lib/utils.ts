import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";

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

/** Seconds until a future ISO-8601 date, clamped to 0 */
export function secondsUntil(iso: string): number {
  return Math.max(0, Math.floor((new Date(iso).getTime() - Date.now()) / 1000));
}

/** Format MM:SS countdown */
export function fmtCountdown(totalSeconds: number): string {
  const m = Math.floor(totalSeconds / 60)
    .toString()
    .padStart(2, "0");
  const s = (totalSeconds % 60).toString().padStart(2, "0");
  return `${m}:${s}`;
}
