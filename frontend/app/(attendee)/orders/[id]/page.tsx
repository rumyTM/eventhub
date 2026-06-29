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
import { formatMoney, formatDate } from "@/lib/utils";
import { toast } from "sonner";
import Link from "next/link";

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
    onSuccess: ({ refund }) => {
      toast.success(`Refund processed: ${refund.status.label} — ${refund.policy_applied}`);
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
  const canRefund = order.status.value === "paid" || order.status.value === "partially_refunded";

  return (
    <div className="max-w-2xl space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">Order Details</h1>
        <Link href="/orders" className="text-sm text-primary underline">← Back</Link>
      </div>

      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <CardTitle className="text-sm font-mono text-muted-foreground">{order.id}</CardTitle>
            <Badge>{order.status.label}</Badge>
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            {order.items?.map((item) => (
              <div key={item.id} className="flex justify-between text-sm">
                <span>Ticket × {item.quantity}</span>
                <span>{formatMoney(item.unit_price * item.quantity, order.currency)}</span>
              </div>
            ))}
            <div className="flex justify-between border-t pt-2 font-bold">
              <span>Total</span>
              <span>{formatMoney(order.total, order.currency)}</span>
            </div>
          </div>

          {order.holds && order.holds.length > 0 && (
            <div>
              <p className="mb-1 text-xs font-medium text-muted-foreground uppercase">Holds</p>
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
