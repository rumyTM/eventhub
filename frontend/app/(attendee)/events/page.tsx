"use client";
import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { eventsApi } from "@/lib/api";
import { LoadingSpinner } from "@/components/loading-spinner";
import { ErrorDisplay } from "@/components/error-display";
import { EmptyState } from "@/components/empty-state";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { formatDate } from "@/lib/utils";
import Link from "next/link";
import { CalendarDays, MapPin } from "lucide-react";

export default function EventListingPage() {
  const [page, setPage] = useState(1);

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ["events", page],
    queryFn: () => eventsApi.list(page),
  });

  if (isLoading) return <LoadingSpinner />;
  if (error) return <ErrorDisplay error={error} retry={refetch} />;

  const events = (data?.events ?? []).filter(
    (e) => e.status.value === "published" || e.status.value === "ongoing",
  );
  const pagination = data?.pagination;

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Browse Events</h1>

      {events.length === 0 ? (
        <EmptyState message="No events available right now." />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {events.map((evt) => {
            const minPrice = evt.ticket_types?.length
              ? Math.min(...evt.ticket_types.map((tt) => tt.price))
              : null;
            const available = evt.ticket_types?.some(
              (tt) => tt.quantity_total - tt.quantity_sold > 0,
            );
            return (
              <Card key={evt.id} className="flex flex-col">
                <CardHeader>
                  <div className="flex items-start justify-between gap-2">
                    <CardTitle className="text-base">{evt.title}</CardTitle>
                    <Badge variant={evt.status.value === "ongoing" ? "default" : "success"}>
                      {evt.status.label}
                    </Badge>
                  </div>
                  <p className="text-sm text-muted-foreground line-clamp-2">{evt.description}</p>
                </CardHeader>
                <CardContent className="flex-1 space-y-2">
                  <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <CalendarDays className="h-4 w-4" />
                    <span>{formatDate(evt.starts_at)}</span>
                  </div>
                  <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <MapPin className="h-4 w-4" />
                    <span>{evt.timezone}</span>
                  </div>
                  {minPrice !== null && (
                    <p className="text-sm font-medium">
                      From {(minPrice / 100).toFixed(2)} BDT
                    </p>
                  )}
                </CardContent>
                <CardFooter>
                  <Button asChild className="w-full" disabled={!available}>
                    <Link href={`/events/${evt.id}`}>
                      {available ? "View Tickets" : "Sold Out"}
                    </Link>
                  </Button>
                </CardFooter>
              </Card>
            );
          })}
        </div>
      )}

      {pagination && pagination.last_page > 1 && (
        <div className="flex justify-center gap-2">
          <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
            Previous
          </Button>
          <span className="self-center text-sm">{page} / {pagination.last_page}</span>
          <Button variant="outline" size="sm" disabled={page >= pagination.last_page} onClick={() => setPage((p) => p + 1)}>
            Next
          </Button>
        </div>
      )}
    </div>
  );
}
