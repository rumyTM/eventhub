"use client";
import { useRouter } from "next/navigation";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { eventsApi, ApiError } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { toast } from "sonner";
import { useState } from "react";

export default function NewEventPage() {
  const router = useRouter();
  const qc = useQueryClient();
  const [errors, setErrors] = useState<Record<string, string>>({});

  const mutation = useMutation({
    mutationFn: eventsApi.create,
    onSuccess: ({ event }) => {
      toast.success("Event created");
      qc.invalidateQueries({ queryKey: ["vendor-events"] });
      router.push(`/vendor/events/${event.id}`);
    },
    onError: (err) => {
      if (err instanceof ApiError) {
        if (err.errors) {
          const flat: Record<string, string> = {};
          for (const [f, msgs] of Object.entries(err.errors)) flat[f] = msgs[0];
          setErrors(flat);
        } else {
          toast.error(err.message);
        }
      }
    },
  });

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
      <CardHeader><CardTitle>Create New Event</CardTitle></CardHeader>
      <form onSubmit={handleSubmit}>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="title">Title</Label>
            <Input id="title" name="title" placeholder="Summer Concert 2026" required />
            {errors.title && <p className="text-xs text-destructive">{errors.title}</p>}
          </div>
          <div className="space-y-2">
            <Label htmlFor="description">Description</Label>
            <Textarea id="description" name="description" rows={3} />
            {errors.description && <p className="text-xs text-destructive">{errors.description}</p>}
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="starts_at">Starts at</Label>
              <Input id="starts_at" name="starts_at" type="datetime-local" required />
              {errors.starts_at && <p className="text-xs text-destructive">{errors.starts_at}</p>}
            </div>
            <div className="space-y-2">
              <Label htmlFor="ends_at">Ends at</Label>
              <Input id="ends_at" name="ends_at" type="datetime-local" required />
              {errors.ends_at && <p className="text-xs text-destructive">{errors.ends_at}</p>}
            </div>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="timezone">Timezone</Label>
              <Input id="timezone" name="timezone" defaultValue="Asia/Dhaka" required />
              {errors.timezone && <p className="text-xs text-destructive">{errors.timezone}</p>}
            </div>
            <div className="space-y-2">
              <Label htmlFor="capacity">Capacity</Label>
              <Input id="capacity" name="capacity" type="number" min={1} defaultValue={100} required />
              {errors.capacity && <p className="text-xs text-destructive">{errors.capacity}</p>}
            </div>
          </div>
          <div className="flex gap-3 pt-2">
            <Button type="submit" disabled={mutation.isPending}>
              {mutation.isPending ? "Creating…" : "Create Event"}
            </Button>
            <Button type="button" variant="outline" onClick={() => router.back()}>Cancel</Button>
          </div>
        </CardContent>
      </form>
    </Card>
  );
}
