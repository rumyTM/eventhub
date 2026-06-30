"use client";
import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { eventsApi, ApiError } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { LoadingSpinner } from "@/components/loading-spinner";
import { ErrorDisplay } from "@/components/error-display";
import { EmptyState } from "@/components/empty-state";
import { EventDateTime } from "@/components/event-date-time";
import Link from "next/link";
import { toast } from "sonner";
import { Trash2 } from "lucide-react";

const STATUS_VARIANT: Record<string, "default" | "secondary" | "success" | "warning" | "destructive" | "outline"> = {
  draft: "secondary",
  published: "success",
  ongoing: "default",
  completed: "outline",
  cancelled: "destructive",
};

export default function VendorEventsPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ["vendor-events", page],
    queryFn: () => eventsApi.list(page),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => eventsApi.destroy(id),
    onSuccess: () => {
      toast.success("Event deleted");
      qc.invalidateQueries({ queryKey: ["vendor-events"] });
    },
    onError: (err) => {
      toast.error(err instanceof ApiError ? err.message : "Failed to delete event");
    },
  });

  if (isLoading) return <LoadingSpinner />;
  if (error) return <ErrorDisplay error={error} retry={refetch} />;

  const events = data?.events ?? [];
  const pagination = data?.pagination;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">My Events</h1>
        <Button asChild><Link href="/vendor/events/new">+ New Event</Link></Button>
      </div>

      {events.length === 0 ? (
        <EmptyState message="No events yet. Create your first one!" />
      ) : (
        <div className="overflow-x-auto rounded-lg border">
          <table className="w-full text-sm">
            <thead className="bg-muted">
              <tr>
                <th className="px-4 py-2 text-left">Title</th>
                <th className="px-4 py-2 text-left">Status</th>
                <th className="px-4 py-2 text-left">Starts</th>
                <th className="px-4 py-2 text-left">Capacity</th>
                <th className="px-4 py-2"></th>
              </tr>
            </thead>
            <tbody>
              {events.map((evt) => (
                <tr key={evt.id} className="border-t hover:bg-muted/30">
                  <td className="px-4 py-2 font-medium">
                    <Link href={`/vendor/events/${evt.id}`} className="hover:underline">{evt.title}</Link>
                  </td>
                  <td className="px-4 py-2">
                    <Badge variant={STATUS_VARIANT[evt.status.value] ?? "secondary"}>
                      {evt.status.label}
                    </Badge>
                  </td>
                  <td className="px-4 py-2 text-muted-foreground">
                    <EventDateTime iso={evt.starts_at} timezone={evt.timezone} />
                  </td>
                  <td className="px-4 py-2">{evt.capacity}</td>
                  <td className="px-4 py-2 text-right">
                    <div className="flex items-center justify-end gap-2">
                      <Link href={`/vendor/events/${evt.id}/edit`}>
                        <Button variant="outline" size="sm">Edit</Button>
                      </Link>
                      <Button
                        variant="ghost"
                        size="icon"
                        className="text-destructive"
                        onClick={() => {
                          if (confirm("Delete this event?")) deleteMutation.mutate(evt.id);
                        }}
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {pagination && pagination.last_page > 1 && (
        <div className="flex justify-end gap-2">
          <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage(p => p - 1)}>
            Previous
          </Button>
          <span className="self-center text-sm">{page} / {pagination.last_page}</span>
          <Button variant="outline" size="sm" disabled={page >= pagination.last_page} onClick={() => setPage(p => p + 1)}>
            Next
          </Button>
        </div>
      )}
    </div>
  );
}
