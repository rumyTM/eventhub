"use client";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { eventsApi, ApiError } from "@/lib/api";
import { LoadingSpinner } from "@/components/loading-spinner";
import { ErrorDisplay } from "@/components/error-display";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { formatDate, formatMoney } from "@/lib/utils";
import Link from "next/link";
import { TicketTypesSection } from "./ticket-types-section";
import { toast } from "sonner";
import { useAuth } from "@/lib/auth-context";

export default function VendorEventDetailPage({ params }: { params: { id: string } }) {
  const qc = useQueryClient();
  const { user } = useAuth();
  const kycPending = user?.vendor?.kyc_status?.value === "pending";

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ["event", params.id],
    queryFn: () => eventsApi.show(params.id),
  });

  const { data: ttData, isLoading: ttLoading, refetch: ttRefetch } = useQuery({
    queryKey: ["event-ticket-types", params.id],
    queryFn: () => eventsApi.listTicketTypes(params.id),
  });

  const statusMutation = useMutation({
    mutationFn: (status: string) => eventsApi.update(params.id, { status }),
    onSuccess: (res) => {
      toast.success(`Event ${res.event.status.label.toLowerCase()}.`);
      qc.invalidateQueries({ queryKey: ["event", params.id] });
      qc.invalidateQueries({ queryKey: ["vendor-events"] });
    },
    onError: (err) => {
      toast.error(err instanceof ApiError ? err.message : "Failed to update event status.");
    },
  });

  if (isLoading) return <LoadingSpinner />;
  if (error) return <ErrorDisplay error={error} retry={refetch} />;
  if (!data) return null;

  const evt = data.event;
  const ticketTypes = ttData?.ticket_types ?? [];
  const totalSold = ticketTypes.reduce((s, tt) => s + tt.quantity_sold, 0);
  const totalRevenue = ticketTypes.reduce((s, tt) => s + tt.quantity_sold * tt.price, 0);

  const statusValue = evt.status.value;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">{evt.title}</h1>
          <p className="text-muted-foreground">{evt.description}</p>
        </div>
        <div className="flex items-center gap-3">
          <Badge>{evt.status.label}</Badge>

          {statusValue === "draft" && (
            <div title={kycPending ? "KYC approval required before publishing" : undefined}>
              <Button
                onClick={() => statusMutation.mutate("published")}
                disabled={statusMutation.isPending || kycPending}
              >
                {statusMutation.isPending ? "Publishing…" : "Publish"}
              </Button>
            </div>
          )}

          {(statusValue === "published" || statusValue === "ongoing") && (
            <Button
              variant="destructive"
              onClick={() => {
                if (confirm("Cancel this event? All attendees will be refunded.")) {
                  statusMutation.mutate("cancelled");
                }
              }}
              disabled={statusMutation.isPending}
            >
              Cancel Event
            </Button>
          )}

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
        eventStartsAt={evt.starts_at}
        ticketTypes={ticketTypes}
        loading={ttLoading}
        onRefresh={ttRefetch}
      />
    </div>
  );
}
