const FALLBACK_TIMEZONES = [
  "UTC",
  "Asia/Dhaka",
  "Asia/Kolkata",
  "Asia/Kathmandu",
  "Asia/Karachi",
  "Asia/Colombo",
  "Asia/Dubai",
  "Asia/Bangkok",
  "Asia/Singapore",
  "Asia/Hong_Kong",
  "Asia/Shanghai",
  "Asia/Tokyo",
  "Asia/Seoul",
  "Europe/London",
  "Europe/Paris",
  "Europe/Berlin",
  "Africa/Cairo",
  "Australia/Sydney",
  "America/New_York",
  "America/Chicago",
  "America/Denver",
  "America/Los_Angeles",
];

/** Full IANA identifier list, newest-runtime API with a small curated fallback for older environments. */
function allTimezones(): string[] {
  if (typeof Intl.supportedValuesOf === "function") {
    try {
      return Intl.supportedValuesOf("timeZone");
    } catch {
      // fall through to the fallback list below
    }
  }
  return FALLBACK_TIMEZONES;
}

export interface TimezoneGroup {
  region: string;
  zones: string[];
}

/** IANA zones grouped by their region prefix (e.g. "Asia" from "Asia/Dhaka"), each group sorted A→Z. */
export function getGroupedTimezones(): TimezoneGroup[] {
  const groups = new Map<string, string[]>();
  for (const tz of allTimezones()) {
    const region = tz.split("/")[0];
    const list = groups.get(region);
    if (list) list.push(tz);
    else groups.set(region, [tz]);
  }
  return Array.from(groups.entries())
    .map(([region, zones]) => ({ region, zones: zones.sort() }))
    .sort((a, b) => a.region.localeCompare(b.region));
}
