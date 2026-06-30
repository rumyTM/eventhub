"use client";

import * as React from "react";
import { format, isValid } from "date-fns";
import { CalendarIcon } from "lucide-react";

import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";

interface DateTimePickerProps {
  /** Name for the hidden input — feeds into FormData on submit. */
  name: string;
  /** Initial ISO string value (e.g. from an existing API record). */
  defaultValue?: string;
  /** Dates after this are disabled in the calendar picker. */
  maxDate?: Date;
  className?: string;
}

function parseDefault(val?: string): { date: Date | undefined; time: string } {
  if (!val) return { date: undefined, time: "00:00" };
  const d = new Date(val);
  if (!isValid(d)) return { date: undefined, time: "00:00" };
  const hh = String(d.getHours()).padStart(2, "0");
  const mm = String(d.getMinutes()).padStart(2, "0");
  return { date: d, time: `${hh}:${mm}` };
}

export function DateTimePicker({ name, defaultValue, maxDate, className }: DateTimePickerProps) {
  const init = parseDefault(defaultValue);
  const [date, setDate] = React.useState<Date | undefined>(init.date);
  const [time, setTime] = React.useState(init.time);
  const [open, setOpen] = React.useState(false);

  const combined = date ? `${format(date, "yyyy-MM-dd")}T${time}` : "";

  return (
    <div className={cn("flex items-center gap-2", className)}>
      {/* Hidden input carries the combined datetime into FormData on submit */}
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
