"use client";
import { useState } from "react";
import { useQuery, useMutation } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { eventsApi, ordersApi, ApiError } from "@/lib/api";
import { LoadingSpinner } from "@/components/loading-spinner";
import { ErrorDisplay } from "@/components/error-display";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { formatEventDateTime, formatMoney } from "@/lib/utils";
import { EventDateTime } from "@/components/event-date-time";
import { toast } from "sonner";
import { CalendarDays } from "lucide-react";

function generateIdempotencyKey() {
  return `checkout-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

export default function EventDetailPage({ params }: { params: { id: string } }) {
  const router = useRouter();
  const [quantities, setQuantities] = useState<Record<string, number>>({});

  const { data: eventData, isLoading, error, refetch } = useQuery({
    queryKey: ["event-detail", params.id],
    queryFn: async () => {
      const [eventRes, ttRes] = await Promise.all([
        eventsApi.show(params.id),
        eventsApi.listTicketTypes(params.id),
      ]);
      return { event: eventRes.event, ticketTypes: ttRes.ticket_types };
    },
  });

  const checkoutMutation = useMutation({
    mutationFn: () => {
      const items = Object.entries(quantities)
        .filter(([, qty]) => qty > 0)
        .map(([ticket_type_id, quantity]) => ({ ticket_type_id, quantity }));
      if (items.length === 0) throw new Error("Please select at least one ticket.");
      return ordersApi.checkout(items, generateIdempotencyKey());
    },
    onSuccess: ({ order }) => {
      toast.success("Order created! Proceed to complete your payment.");
      router.push(`/checkout/${order.id}`);
    },
    onError: (err) => {
      if (err instanceof ApiError) {
        if (err.status === 409) toast.error("Not enough tickets available.");
        else if (err.isRateLimited) toast.error(`Rate limited. Try again in ${err.retryAfter}s.`);
        else toast.error(err.message);
      } else {
        toast.error((err as Error).message);
      }
    },
  });

  if (isLoading) return <LoadingSpinner />;
  if (error) return <ErrorDisplay error={error} retry={refetch} />;
  if (!eventData) return null;

  const { event: evt, ticketTypes } = eventData;
  const totalPoisha = Object.entries(quantities).reduce((sum, [ttId, qty]) => {
    const tt = ticketTypes.find((t) => t.id === ttId);
    return sum + (tt?.price ?? 0) * qty;
  }, 0);

  const isPurchasable = evt.status.value === "published" || evt.status.value === "ongoing";

  function isSalesOpen(tt: (typeof ticketTypes)[number]): boolean {
    const now = new Date();
    if (tt.sales_start && new Date(tt.sales_start) > now) return false;
    if (tt.sales_end && new Date(tt.sales_end) < now) return false;
    return true;
  }

  return (
    <div className="space-y-6">
      <div>
        <div className="flex items-start justify-between">
          <h1 className="text-3xl font-bold">{evt.title}</h1>
          <Badge>{evt.status.label}</Badge>
        </div>
        {evt.vendor && (
          <p className="mt-1 text-sm text-muted-foreground">Organized by {evt.vendor.business_name}</p>
        )}
        <p className="mt-2 text-muted-foreground">{evt.description}</p>
      </div>

      <div className="flex flex-wrap gap-4 text-sm text-muted-foreground">
        <div className="flex items-start gap-2">
          <CalendarDays className="h-4 w-4 mt-0.5 shrink-0" />
          <span>
            <EventDateTime iso={evt.starts_at} timezone={evt.timezone} /> —{" "}
            <EventDateTime iso={evt.ends_at} timezone={evt.timezone} />
          </span>
        </div>
        <div>Capacity: {evt.capacity}</div>
      </div>

      <div className="space-y-3">
        <h2 className="text-xl font-semibold">Select Tickets</h2>
        {ticketTypes.length === 0 ? (
          <p className="text-muted-foreground">No tickets available.</p>
        ) : (
          <div className="space-y-3">
            {ticketTypes.map((tt) => {
              const available = tt.quantity_total - tt.quantity_sold;
              const qty = quantities[tt.id] ?? 0;
              return (
                <Card key={tt.id}>
                  <CardContent className="flex items-center justify-between py-4">
                    <div>
                      <p className="font-medium">{tt.kind.label}</p>
                      <p className="text-sm text-muted-foreground">{formatMoney(tt.price)} · {available} available</p>
                      {tt.sales_start && (
                        <p className="text-xs text-muted-foreground">
                          Sales: {formatEventDateTime(tt.sales_start, evt.timezone).eventLocal} –{" "}
                          {formatEventDateTime(tt.sales_end, evt.timezone).eventLocal}
                        </p>
                      )}
                      {tt.group_size && tt.group_discount && (
                        <p className="text-xs text-green-600 font-medium">
                          Group of {tt.group_size}+: {Math.round(tt.group_discount * 100)}% off
                        </p>
                      )}
                    </div>
                    <div className="flex items-center gap-3">
                      <Button
                        variant="outline" size="icon"
                        disabled={qty <= 0 || !isPurchasable || !isSalesOpen(tt)}
                        onClick={() => setQuantities((q) => ({ ...q, [tt.id]: Math.max(0, (q[tt.id] ?? 0) - 1) }))}
                      >−</Button>
                      <span className="w-6 text-center font-medium">{qty}</span>
                      <Button
                        variant="outline" size="icon"
                        disabled={qty >= available || !isPurchasable || !isSalesOpen(tt)}
                        onClick={() => setQuantities((q) => ({ ...q, [tt.id]: Math.min(available, (q[tt.id] ?? 0) + 1) }))}
                      >+</Button>
                    </div>
                  </CardContent>
                </Card>
              );
            })}
          </div>
        )}
      </div>

      {isPurchasable && totalPoisha > 0 && (
        <Card>
          <CardHeader><CardTitle>Order Summary</CardTitle></CardHeader>
          <CardContent className="space-y-3">
            {Object.entries(quantities)
              .filter(([, qty]) => qty > 0)
              .map(([ttId, qty]) => {
                const tt = ticketTypes.find((t) => t.id === ttId)!;
                return (
                  <div key={ttId} className="flex justify-between text-sm">
                    <span>{tt.kind.label} × {qty}</span>
                    <span>{formatMoney(tt.price * qty)}</span>
                  </div>
                );
              })}
            <div className="flex justify-between border-t pt-3 font-bold">
              <span>Total</span>
              <span>{formatMoney(totalPoisha)}</span>
            </div>
            <Button
              className="w-full"
              onClick={() => checkoutMutation.mutate()}
              disabled={checkoutMutation.isPending}
            >
              {checkoutMutation.isPending ? "Starting checkout…" : "Checkout"}
            </Button>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
