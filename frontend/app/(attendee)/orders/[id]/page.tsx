"use client";
import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { ordersApi, ApiError } from "@/lib/api";
import { LoadingSpinner } from "@/components/loading-spinner";
import { ErrorDisplay } from "@/components/error-display";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { formatMoney, formatDate, formatEventNames } from "@/lib/utils";
import { toast } from "sonner";
import Link from "next/link";
import { CreditCard } from "lucide-react";

export default function OrderDetailPage({ params }: { params: { id: string } }) {
  const qc = useQueryClient();
  const [refundOpen, setRefundOpen] = useState(false);
  const [reason, setReason] = useState("");

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ["order-detail", params.id],
    queryFn: () => ordersApi.show(params.id),
  });

  const refundMutation = useMutation({
    mutationFn: () => ordersApi.refund(params.id, reason),
    onSuccess: (result) => {
      if ("dispute" in result) {
        toast.info("Your request is outside the automatic refund window. A dispute has been opened — an admin will review it.");
      } else {
        const pct = result.refund.policy_applied;
        toast.success(`Refund requested — ${pct}% policy applies. You'll receive a confirmation shortly.`);
      }
      setRefundOpen(false);
      qc.invalidateQueries({ queryKey: ["order-detail", params.id] });
      qc.invalidateQueries({ queryKey: ["my-orders"] });
    },
    onError: (err) => {
      if (err instanceof ApiError) {
        if (err.status === 422) toast.error(err.message);
        else if (err.isRateLimited) toast.error(`Rate limited. Try again in ${err.retryAfter}s.`);
        else toast.error(err.message);
      } else {
        toast.error("Failed to request refund.");
      }
    },
  });

  if (isLoading) return <LoadingSpinner />;
  if (error) return <ErrorDisplay error={error} retry={refetch} />;
  if (!data) return null;

  const order = data.order;
  const canRefund =
    !order.has_pending_refund &&
    !order.latest_dispute &&
    (order.status.value === "paid" || order.status.value === "partially_refunded");
  const canPay =
    order.status.value === "pending" &&
    !!order.hold_expires_at &&
    new Date(order.hold_expires_at) > new Date();

  return (
    <div className="max-w-2xl space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">Order Details</h1>
        <Link href="/orders" className="text-sm text-primary underline">← Back</Link>
      </div>

      {/* Pay CTA — prominent, above the detail card */}
      {canPay && (
        <Card className="border-blue-200 bg-blue-50">
          <CardContent className="flex items-center justify-between py-4">
            <div>
              <p className="font-medium text-blue-900">Payment pending</p>
              <p className="text-sm text-blue-700">Complete your payment to confirm the tickets.</p>
            </div>
            <Link href={`/checkout/${order.id}`}>
              <Button className="gap-2">
                <CreditCard className="h-4 w-4" />
                Complete Payment
              </Button>
            </Link>
          </CardContent>
        </Card>
      )}

      {/* Order summary card */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle>{formatEventNames(order.events)}</CardTitle>
              <p className="font-mono text-xs text-muted-foreground">#{order.id.slice(-8)}</p>
            </div>
            <Badge>{order.status.label}</Badge>
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          {/* Line items with discount breakdown */}
          <div className="space-y-3">
            {order.items?.map((item) => {
              const hasDiscount = item.original_price !== null && item.original_price !== item.unit_price;
              const discountPct = hasDiscount
                ? Math.round((1 - item.unit_price / item.original_price!) * 100)
                : 0;
              const savedPerUnit = hasDiscount ? item.original_price! - item.unit_price : 0;
              const ticketLabel = item.ticket_type?.kind.label ?? "Ticket";

              return (
                <div key={item.id} className="space-y-1">
                  <div className="flex items-start justify-between text-sm">
                    <div>
                      <span>{ticketLabel} × {item.quantity}</span>
                      {hasDiscount && (
                        <span className="ml-2 rounded bg-green-100 px-1.5 py-0.5 text-xs font-medium text-green-700">
                          {discountPct}% group discount
                        </span>
                      )}
                    </div>
                    <div className="text-right">
                      <div>{formatMoney(item.unit_price * item.quantity, order.currency)}</div>
                      {hasDiscount && (
                        <div className="text-xs text-muted-foreground line-through">
                          {formatMoney(item.original_price! * item.quantity, order.currency)}
                        </div>
                      )}
                    </div>
                  </div>
                  {hasDiscount && (
                    <p className="text-xs text-green-600">
                      You saved {formatMoney(savedPerUnit * item.quantity, order.currency)}
                    </p>
                  )}
                </div>
              );
            })}
            <div className="flex justify-between border-t pt-2 font-bold">
              <span>Total</span>
              <span>{formatMoney(order.total, order.currency)}</span>
            </div>
          </div>

          {/* Active holds */}
          {order.holds && order.holds.length > 0 && (
            <div>
              <p className="mb-1 text-xs font-medium uppercase text-muted-foreground">Holds</p>
              {order.holds.map((h) => (
                <div key={h.id} className="flex justify-between text-xs text-muted-foreground">
                  <span>{h.status.label}</span>
                  <span>Expires: {formatDate(h.expires_at)}</span>
                </div>
              ))}
            </div>
          )}

          <p className="text-xs text-muted-foreground">Created: {formatDate(order.created_at)}</p>

          {canRefund && (
            <Button variant="destructive" onClick={() => setRefundOpen(true)}>
              Request Refund
            </Button>
          )}
        </CardContent>
      </Card>

      {/* Tickets — shown after payment succeeds (tickets are issued only then) */}
      {order.tickets && order.tickets.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Tickets</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            {order.tickets.map((ticket, idx) => (
              <div key={ticket.id} className="rounded-md border p-3 text-sm space-y-2">
                <div className="flex items-center justify-between">
                  <span className="font-medium">
                    {ticket.ticket_type?.kind.label ?? "Ticket"} #{idx + 1}
                  </span>
                  <Badge
                    variant={
                      ticket.status.value === "checked_in"
                        ? "success"
                        : ticket.status.value === "refunded"
                        ? "secondary"
                        : ticket.status.value === "transferred"
                        ? "outline"
                        : "default"
                    }
                  >
                    {ticket.status.label}
                  </Badge>
                </div>
                <div className="flex items-center gap-2">
                  <span className="text-xs text-muted-foreground">QR Code</span>
                  <code className="rounded bg-muted px-2 py-0.5 font-mono text-xs">{ticket.qr_code}</code>
                </div>
                {ticket.checked_in_at && (
                  <p className="text-xs text-muted-foreground">
                    Checked in: {formatDate(ticket.checked_in_at)}
                  </p>
                )}
              </div>
            ))}
          </CardContent>
        </Card>
      )}

      {/* Refund summary — shown whenever a refund exists (any status) */}
      {order.latest_refund && (
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle className="text-base">Refund</CardTitle>
              <Badge
                variant={
                  order.latest_refund.status.value === "completed"
                    ? "default"
                    : order.latest_refund.status.value === "failed"
                    ? "destructive"
                    : "secondary"
                }
              >
                {order.latest_refund.status.label}
              </Badge>
            </div>
          </CardHeader>
          <CardContent className="space-y-2 text-sm">
            <div className="flex justify-between">
              <span className="text-muted-foreground">Amount refunded</span>
              <span className="font-semibold">
                {formatMoney(order.latest_refund.amount, order.currency)}
              </span>
            </div>
            <div className="flex justify-between">
              <span className="text-muted-foreground">Policy applied</span>
              <span>
                {order.latest_refund.policy_applied === "admin_override"
                  ? "Admin override (100%)"
                  : `${order.latest_refund.policy_applied}% of order total`}
              </span>
            </div>
            <div className="flex justify-between">
              <span className="text-muted-foreground">Requested</span>
              <span>{formatDate(order.latest_refund.created_at)}</span>
            </div>
            {(order.latest_refund.status.value === "requested" ||
              order.latest_refund.status.value === "pending") && (
              <p className="pt-1 text-xs text-muted-foreground">
                Your refund is being processed. You will receive a confirmation once it is complete.
              </p>
            )}
          </CardContent>
        </Card>
      )}

      {/* Dispute section — shown when a dispute exists (open / resolved / rejected) */}
      {order.latest_dispute && (
        <Card className={
          order.latest_dispute.status.value === "rejected"
            ? "border-red-200 bg-red-50"
            : order.latest_dispute.status.value === "resolved"
            ? "border-green-200 bg-green-50"
            : "border-yellow-200 bg-yellow-50"
        }>
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle className="text-base">Refund Dispute</CardTitle>
              <Badge
                variant={
                  order.latest_dispute.status.value === "rejected"
                    ? "destructive"
                    : order.latest_dispute.status.value === "resolved"
                    ? "default"
                    : "secondary"
                }
              >
                {order.latest_dispute.status.label}
              </Badge>
            </div>
          </CardHeader>
          <CardContent className="space-y-2 text-sm">
            <div className="flex justify-between">
              <span className="text-muted-foreground">Reason submitted</span>
              <span className="capitalize">{order.latest_dispute.reason.replace(/_/g, " ")}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-muted-foreground">Opened</span>
              <span>{formatDate(order.latest_dispute.created_at)}</span>
            </div>
            {order.latest_dispute.resolution && (
              <div className="space-y-1 border-t pt-2">
                <span className="text-muted-foreground">Admin response</span>
                <p className="rounded bg-white/60 px-3 py-2 text-sm">{order.latest_dispute.resolution}</p>
              </div>
            )}
            {order.latest_dispute.status.value === "open" && (
              <p className="pt-1 text-xs text-yellow-800">
                Your dispute is under review. An admin will respond shortly.
              </p>
            )}
            {order.latest_dispute.status.value === "rejected" && (
              <p className="pt-1 text-xs text-red-700">
                Your refund dispute was denied. No refund will be issued for this order.
              </p>
            )}
            {order.latest_dispute.status.value === "resolved" && (
              <p className="pt-1 text-xs text-green-700">
                Your dispute was approved. A full refund has been queued — check the refund section above.
              </p>
            )}
          </CardContent>
        </Card>
      )}

      <Dialog open={refundOpen} onOpenChange={setRefundOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>Request Refund</DialogTitle></DialogHeader>
          <div className="space-y-3">
            <p className="text-sm text-muted-foreground">
              The refund amount is calculated automatically based on the event&apos;s policy
              (100% if &gt;48h before, 50% if 24–48h, 0% if less).
            </p>
            <div className="space-y-2">
              <Label>Reason</Label>
              <Textarea
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder="Please provide a reason…"
                rows={3}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setRefundOpen(false)}>Cancel</Button>
            <Button
              variant="destructive"
              disabled={!reason.trim() || refundMutation.isPending}
              onClick={() => refundMutation.mutate()}
            >
              {refundMutation.isPending ? "Requesting…" : "Confirm Refund"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
