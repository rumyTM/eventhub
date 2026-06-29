"use client";
import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { adminApi, ApiError } from "@/lib/api";
import { LoadingSpinner } from "@/components/loading-spinner";
import { ErrorDisplay } from "@/components/error-display";
import { EmptyState } from "@/components/empty-state";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { formatMoney, formatDate } from "@/lib/utils";
import { toast } from "sonner";
import type { Order } from "@/lib/api";

export default function AdminRefundsPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [selectedOrder, setSelectedOrder] = useState<Order | null>(null);
  const [reason, setReason] = useState("");

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ["admin-disputed-orders", page],
    queryFn: () => adminApi.disputedOrders(page),
  });

  const refundMutation = useMutation({
    mutationFn: ({ orderId, reason }: { orderId: string; reason: string }) =>
      adminApi.initiateRefund(orderId, reason),
    onSuccess: () => {
      toast.success("Refund initiated");
      setSelectedOrder(null);
      setReason("");
      qc.invalidateQueries({ queryKey: ["admin-disputed-orders"] });
    },
    onError: (err) => toast.error(err instanceof ApiError ? err.message : "Failed to initiate refund"),
  });

  if (isLoading) return <LoadingSpinner />;
  if (error) return <ErrorDisplay error={error} retry={refetch} />;

  const orders = data?.orders ?? [];
  const pagination = data?.pagination;

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">Dispute / Refund Queue</h1>
      <p className="text-sm text-muted-foreground">
        Orders in dispute status are listed here for admin review and manual refund initiation.
      </p>

      {orders.length === 0 ? (
        <EmptyState message="No disputed orders in the queue." />
      ) : (
        <div className="overflow-x-auto rounded-lg border">
          <table className="w-full text-sm">
            <thead className="bg-muted">
              <tr>
                <th className="px-4 py-2 text-left">Order ID</th>
                <th className="px-4 py-2 text-left">Status</th>
                <th className="px-4 py-2 text-right">Total</th>
                <th className="px-4 py-2 text-left">Created</th>
                <th className="px-4 py-2"></th>
              </tr>
            </thead>
            <tbody>
              {orders.map((o) => (
                <tr key={o.id} className="border-t hover:bg-muted/30">
                  <td className="px-4 py-2 font-mono text-xs">{o.id.slice(-12)}</td>
                  <td className="px-4 py-2">
                    <Badge variant="warning">{o.status.label}</Badge>
                  </td>
                  <td className="px-4 py-2 text-right">{formatMoney(o.total, o.currency)}</td>
                  <td className="px-4 py-2 text-muted-foreground">{formatDate(o.created_at)}</td>
                  <td className="px-4 py-2 text-right">
                    <Button
                      size="sm"
                      variant="destructive"
                      onClick={() => { setSelectedOrder(o); setReason(""); }}
                    >
                      Initiate Refund
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {pagination && pagination.last_page > 1 && (
        <div className="flex justify-end gap-2">
          <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage(p => p - 1)}>Previous</Button>
          <span className="self-center text-sm">{page} / {pagination.last_page}</span>
          <Button variant="outline" size="sm" disabled={page >= pagination.last_page} onClick={() => setPage(p => p + 1)}>Next</Button>
        </div>
      )}

      <Dialog open={!!selectedOrder} onOpenChange={() => setSelectedOrder(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Initiate Admin Refund — Order {selectedOrder?.id.slice(-8)}</DialogTitle>
          </DialogHeader>
          <div className="space-y-2">
            <p className="text-sm text-muted-foreground">
              Total: <strong>{selectedOrder ? formatMoney(selectedOrder.total, selectedOrder.currency) : "—"}</strong>
            </p>
            <Label>Reason</Label>
            <Textarea
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              placeholder="Reason for admin-initiated refund…"
              rows={3}
            />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setSelectedOrder(null)}>Cancel</Button>
            <Button
              variant="destructive"
              disabled={!reason.trim() || refundMutation.isPending}
              onClick={() => {
                if (selectedOrder)
                  refundMutation.mutate({ orderId: selectedOrder.id, reason });
              }}
            >
              {refundMutation.isPending ? "Processing…" : "Confirm Refund"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
