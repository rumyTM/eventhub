"use client";

import * as React from "react";
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { getGroupedTimezones } from "@/lib/timezones";

interface TimezoneSelectProps {
  /** Name for the hidden input — feeds into FormData on submit. */
  name: string;
  value: string;
  onValueChange: (value: string) => void;
  id?: string;
}

export function TimezoneSelect({ name, value, onValueChange, id }: TimezoneSelectProps) {
  const groups = React.useMemo(() => getGroupedTimezones(), []);

  return (
    <>
      <input type="hidden" name={name} value={value} />
      <Select value={value} onValueChange={onValueChange}>
        <SelectTrigger id={id}>
          <SelectValue placeholder="Select timezone" />
        </SelectTrigger>
        <SelectContent>
          {groups.map(({ region, zones }) => (
            <SelectGroup key={region}>
              <SelectLabel>{region}</SelectLabel>
              {zones.map((tz) => (
                <SelectItem key={tz} value={tz}>
                  {tz}
                </SelectItem>
              ))}
            </SelectGroup>
          ))}
        </SelectContent>
      </Select>
    </>
  );
}
