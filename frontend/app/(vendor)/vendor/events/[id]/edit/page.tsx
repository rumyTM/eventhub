"use client";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { eventsApi, ApiError } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { LoadingSpinner } from "@/components/loading-spinner";
import { ErrorDisplay } from "@/components/error-display";
import { DateTimePicker } from "@/components/date-time-picker";
import { TimezoneSelect } from "@/components/timezone-select";
import { toast } from "sonner";
import { useState } from "react";

export default function EditEventPage({ params }: { params: { id: string } }) {
  const router = useRouter();
  const qc = useQueryClient();
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [timezone, setTimezone] = useState<string | null>(null);

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ["event", params.id],
    queryFn: () => eventsApi.show(params.id),
  });

  const mutation = useMutation({
    mutationFn: (vals: Parameters<typeof eventsApi.update>[1]) => eventsApi.update(params.id, vals),
    onSuccess: () => {
      toast.success("Event updated");
      qc.invalidateQueries({ queryKey: ["event", params.id] });
      qc.invalidateQueries({ queryKey: ["vendor-events"] });
      router.push(`/vendor/events/${params.id}`);
    },
    onError: (err) => {
      if (err instanceof ApiError && err.errors) {
        const flat: Record<string, string> = {};
        for (const [f, msgs] of Object.entries(err.errors)) flat[f] = msgs[0];
        setErrors(flat);
      } else {
        toast.error(err instanceof ApiError ? err.message : "Failed to update");
      }
    },
  });

  if (isLoading) return <LoadingSpinner />;
  if (error) return <ErrorDisplay error={error} retry={refetch} />;
  if (!data) return null;

  const evt = data.event;
  const currentTimezone = timezone ?? evt.timezone;

  function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setErrors({});
    const fd = new FormData(e.currentTarget);
    mutation.mutate({
      title: fd.get("title") as string,
      description: fd.get("description") as string,
      timezone: fd.get("timezone") as string,
      starts_at: fd.get("starts_at") as string,
      ends_at: fd.get("ends_at") as string,
      capacity: Number(fd.get("capacity")),
    });
  }

  return (
    <Card className="max-w-2xl">
      <CardHeader><CardTitle>Edit Event</CardTitle></CardHeader>
      <form onSubmit={handleSubmit}>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="title">Title</Label>
            <Input id="title" name="title" defaultValue={evt.title} required />
            {errors.title && <p className="text-xs text-destructive">{errors.title}</p>}
          </div>
          <div className="space-y-2">
            <Label htmlFor="description">Description</Label>
            <Textarea id="description" name="description" defaultValue={evt.description} rows={3} />
          </div>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label>Starts at</Label>
              <DateTimePicker name="starts_at" defaultValue={evt.starts_at} timeZone={currentTimezone} />
              {errors.starts_at && <p className="text-xs text-destructive">{errors.starts_at}</p>}
            </div>
            <div className="space-y-2">
              <Label>Ends at</Label>
              <DateTimePicker name="ends_at" defaultValue={evt.ends_at} timeZone={currentTimezone} />
              {errors.ends_at && <p className="text-xs text-destructive">{errors.ends_at}</p>}
            </div>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="timezone">Timezone</Label>
              <TimezoneSelect id="timezone" name="timezone" value={currentTimezone} onValueChange={setTimezone} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="capacity">Capacity</Label>
              <Input id="capacity" name="capacity" type="number" min={1} defaultValue={evt.capacity} required />
            </div>
          </div>
          <div className="flex gap-3 pt-2">
            <Button type="submit" disabled={mutation.isPending}>
              {mutation.isPending ? "Saving…" : "Save Changes"}
            </Button>
            <Button type="button" variant="outline" onClick={() => router.back()}>Cancel</Button>
          </div>
        </CardContent>
      </form>
    </Card>
  );
}
