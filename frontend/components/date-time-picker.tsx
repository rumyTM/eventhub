"use client";

import * as React from "react";
import { format, isValid } from "date-fns";
import { fromZonedTime, toZonedTime } from "date-fns-tz";
import { CalendarIcon } from "lucide-react";

import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";

interface DateTimePickerProps {
  /** Name for the hidden input — feeds into FormData on submit. */
  name: string;
  /** Initial value as a UTC ISO string (e.g. from an existing API record). */
  defaultValue?: string;
  /**
   * IANA timezone the picked date/time is wall-clock in (e.g. "Asia/Dhaka") — required so the
   * submitted value is a correct UTC instant regardless of the browser's own timezone. A vendor in
   * New York creating a "6:00 PM Asia/Dhaka" event must submit the same UTC instant a vendor in
   * Dhaka would for the same wall-clock entry.
   */
  timeZone: string;
  /** Dates after this are disabled in the calendar picker. */
  maxDate?: Date;
  className?: string;
}

/**
 * Parse a UTC ISO `defaultValue` into the wall-clock date/time it represents in `timeZone`.
 * `toZonedTime` returns a Date whose LOCAL getters (getFullYear/getHours/...) read as that wall
 * clock, so the rest of the component can keep using ordinary local Date getters — it never touches
 * the browser's own timezone.
 */
function parseDefault(val: string | undefined, timeZone: string): { date: Date | undefined; time: string } {
  if (!val) return { date: undefined, time: "00:00" };
  const zoned = toZonedTime(val, timeZone);
  if (!isValid(zoned)) return { date: undefined, time: "00:00" };
  const hh = String(zoned.getHours()).padStart(2, "0");
  const mm = String(zoned.getMinutes()).padStart(2, "0");
  return { date: zoned, time: `${hh}:${mm}` };
}

export function DateTimePicker({ name, defaultValue, timeZone, maxDate, className }: DateTimePickerProps) {
  // Computed once at mount from the initial props — intentionally NOT re-derived when `timeZone`
  // changes later (e.g. the vendor switches the timezone dropdown after picking a date/time): the
  // wall-clock numbers the vendor chose should stay put, only their UTC meaning shifts. Re-deriving
  // from `defaultValue` on every timezone change would also wipe a fresh (no-defaultValue) selection.
  const [init] = React.useState(() => parseDefault(defaultValue, timeZone));
  const [date, setDate] = React.useState<Date | undefined>(init.date);
  const [time, setTime] = React.useState(init.time);
  const [open, setOpen] = React.useState(false);

  // Combine the wall-clock date + time-of-day, then convert FROM `timeZone` TO the correct UTC
  // instant. This is the one place the actual UTC value is computed — everything else in this
  // component only ever reads/writes wall-clock numbers.
  const combined = React.useMemo(() => {
    if (!date) return "";
    const wallClock = `${format(date, "yyyy-MM-dd")}T${time}:00`;
    const utc = fromZonedTime(wallClock, timeZone);
    return isValid(utc) ? utc.toISOString() : "";
  }, [date, time, timeZone]);

  return (
    <div className={cn("flex items-center gap-2", className)}>
      {/* Hidden input carries the combined UTC datetime into FormData on submit */}
      <input type="hidden" name={name} value={combined} />

      {/* Date — shadcn Calendar inside a Popover */}
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <Button
            type="button"
            variant="outline"
            className={cn("flex-1 justify-start font-normal", !date && "text-muted-foreground")}
          >
            <CalendarIcon className="mr-2 h-4 w-4" />
            {date ? format(date, "PP") : <span>Pick date</span>}
          </Button>
        </PopoverTrigger>
        <PopoverContent className="w-auto p-0" align="start">
          <Calendar
            mode="single"
            selected={date}
            onSelect={(d: Date | undefined) => {
              setDate(d);
              setOpen(false);
            }}
            disabled={maxDate ? (day) => day > maxDate : undefined}
            initialFocus
          />
        </PopoverContent>
      </Popover>

      {/* Time — native input renders inline, no portal, safe inside Dialog */}
      <input
        type="time"
        value={time}
        onChange={(e) => setTime(e.target.value)}
        className="w-[110px] rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
      />
    </div>
  );
}
