"use client";
import { useQuery } from "@tanstack/react-query";
import { eventsApi } from "@/lib/api";
import { LoadingSpinner } from "@/components/loading-spinner";
import { ErrorDisplay } from "@/components/error-display";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { formatDate, formatMoney } from "@/lib/utils";
import Link from "next/link";
import { TicketTypesSection } from "./ticket-types-section";

export default function VendorEventDetailPage({ params }: { params: { id: string } }) {
  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ["event", params.id],
    queryFn: () => eventsApi.show(params.id),
  });

  const { data: ttData, isLoading: ttLoading, refetch: ttRefetch } = useQuery({
    queryKey: ["event-ticket-types", params.id],
    queryFn: () => eventsApi.listTicketTypes(params.id),
  });

  if (isLoading) return <LoadingSpinner />;
  if (error) return <ErrorDisplay error={error} retry={refetch} />;
  if (!data) return null;

  const evt = data.event;
  const ticketTypes = ttData?.ticket_types ?? [];
  const totalSold = ticketTypes.reduce((s, tt) => s + tt.quantity_sold, 0);
  const totalRevenue = ticketTypes.reduce((s, tt) => s + tt.quantity_sold * tt.price, 0);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">{evt.title}</h1>
          <p className="text-muted-foreground">{evt.description}</p>
        </div>
        <div className="flex items-center gap-3">
          <Badge>{evt.status.label}</Badge>
          <Button asChild variant="outline">
            <Link href={`/vendor/events/${evt.id}/edit`}>Edit</Link>
          </Button>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <Card><CardHeader><CardTitle className="text-xs text-muted-foreground">Starts</CardTitle></CardHeader>
          <CardContent className="pt-0 text-sm">{formatDate(evt.starts_at)}</CardContent></Card>
        <Card><CardHeader><CardTitle className="text-xs text-muted-foreground">Ends</CardTitle></CardHeader>
          <CardContent className="pt-0 text-sm">{formatDate(evt.ends_at)}</CardContent></Card>
        <Card><CardHeader><CardTitle className="text-xs text-muted-foreground">Tickets Sold</CardTitle></CardHeader>
          <CardContent className="pt-0 text-2xl font-bold">{totalSold}</CardContent></Card>
        <Card><CardHeader><CardTitle className="text-xs text-muted-foreground">Revenue</CardTitle></CardHeader>
          <CardContent className="pt-0 text-2xl font-bold">{formatMoney(totalRevenue)}</CardContent></Card>
      </div>

      <TicketTypesSection
        eventId={evt.id}
        ticketTypes={ticketTypes}
        loading={ttLoading}
        onRefresh={ttRefetch}
      />
    </div>
  );
}
