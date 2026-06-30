"use client";
import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { payoutsApi, ApiError } from "@/lib/api";
import { LoadingSpinner } from "@/components/loading-spinner";
import { ErrorDisplay } from "@/components/error-display";
import { EmptyState } from "@/components/empty-state";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { formatMoney, formatDate } from "@/lib/utils";
import { toast } from "sonner";

const STATUS_VARIANT: Record<string, "default" | "secondary" | "success" | "warning" | "destructive" | "outline"> = {
  pending: "warning",
  processing: "default",
  paid: "success",
  failed: "destructive",
};

export default function VendorPayoutsPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [showPreview, setShowPreview] = useState(false);

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ["vendor-payouts", page],
    queryFn: () => payoutsApi.myPayouts(page),
  });

  const previewQuery = useQuery({
    queryKey: ["vendor-payout-preview"],
    queryFn: () => payoutsApi.preview(),
    enabled: showPreview,
  });

  const requestMutation = useMutation({
    mutationFn: () => payoutsApi.requestPayout(),
    onSuccess: () => {
      toast.success("Payout requested — an admin will process it shortly.");
      setShowPreview(false);
      qc.invalidateQueries({ queryKey: ["vendor-payouts"] });
      qc.invalidateQueries({ queryKey: ["vendor-payout-preview"] });
    },
    onError: (err) => toast.error(err instanceof ApiError ? err.message : "Failed to request payout."),
  });

  if (isLoading) return <LoadingSpinner />;
  if (error) return <ErrorDisplay error={error} retry={refetch} />;

  const payouts = data?.payouts ?? [];
  const pagination = data?.pagination;
  const preview = previewQuery.data?.preview ?? null;

  const totalPaid = payouts.filter(p => p.status.value === "paid").reduce((s, p) => s + p.payable, 0);
  const totalPending = payouts.filter(p => p.status.value === "pending").reduce((s, p) => s + p.payable, 0);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">Payout History</h1>
        <Button onClick={() => setShowPreview(true)}>Request Payout</Button>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <Card>
          <CardHeader><CardTitle className="text-sm text-muted-foreground">Total Paid Out</CardTitle></CardHeader>
          <CardContent><p className="text-2xl font-bold">{formatMoney(totalPaid)}</p></CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-sm text-muted-foreground">Pending</CardTitle></CardHeader>
          <CardContent><p className="text-2xl font-bold">{formatMoney(totalPending)}</p></CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-sm text-muted-foreground">Total Payouts</CardTitle></CardHeader>
          <CardContent><p className="text-2xl font-bold">{pagination?.total ?? payouts.length}</p></CardContent>
        </Card>
      </div>

      {payouts.length === 0 ? (
        <EmptyState message="No payouts yet." />
      ) : (
        <div className="overflow-x-auto rounded-lg border">
          <table className="w-full text-sm">
            <thead className="bg-muted">
              <tr>
                <th className="px-4 py-2 text-left">Batch</th>
                <th className="px-4 py-2 text-right">Gross</th>
                <th className="px-4 py-2 text-right">Commission</th>
                <th className="px-4 py-2 text-right">Net</th>
                <th className="px-4 py-2 text-right">Reserved Refund</th>
                <th className="px-4 py-2 text-left">Status</th>
                <th className="px-4 py-2 text-left">Date</th>
              </tr>
            </thead>
            <tbody>
              {payouts.map((p) => (
                <tr key={p.id} className="border-t">
                  <td className="px-4 py-2 font-mono text-xs">{p.batch_id}</td>
                  <td className="px-4 py-2 text-right">{formatMoney(p.gross)}</td>
                  <td className="px-4 py-2 text-right">{formatMoney(p.commission)}</td>
                  <td className="px-4 py-2 text-right font-medium">{formatMoney(p.net)}</td>
                  <td className="px-4 py-2 text-right text-yellow-600">{formatMoney(p.reserved_refund)}</td>
                  <td className="px-4 py-2">
                    <Badge variant={STATUS_VARIANT[p.status.value] ?? "secondary"}>{p.status.label}</Badge>
                  </td>
                  <td className="px-4 py-2 text-muted-foreground">{formatDate(p.created_at)}</td>
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

      {/* Payout request dialog with preview */}
      <Dialog open={showPreview} onOpenChange={(o) => { if (!requestMutation.isPending) setShowPreview(o); }}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Request Payout</DialogTitle>
          </DialogHeader>

          {previewQuery.isLoading && <LoadingSpinner />}

          {previewQuery.isError && (
            <p className="text-sm text-destructive">Could not load payout preview.</p>
          )}

          {previewQuery.isSuccess && (
            preview === null ? (
              <p className="text-sm text-muted-foreground">
                No eligible settled orders meet the minimum payout threshold yet. Revenue from events
                that have completed will appear here once ready.
              </p>
            ) : (
              <div className="space-y-3 text-sm">
                <p className="text-muted-foreground">
                  Your estimated payout for today&apos;s batch:
                </p>
                <div className="rounded-lg border divide-y">
                  <div className="flex justify-between px-4 py-2">
                    <span className="text-muted-foreground">Gross revenue</span>
                    <span>{formatMoney(preview.gross, preview.currency)}</span>
                  </div>
                  <div className="flex justify-between px-4 py-2">
                    <span className="text-muted-foreground">Platform commission</span>
                    <span className="text-destructive">− {formatMoney(preview.commission, preview.currency)}</span>
                  </div>
                  {preview.reserved_refund > 0 && (
                    <div className="flex justify-between px-4 py-2">
                      <span className="text-muted-foreground">Reserved for refunds</span>
                      <span className="text-yellow-600">− {formatMoney(preview.reserved_refund, preview.currency)}</span>
                    </div>
                  )}
                  <div className="flex justify-between px-4 py-2 font-semibold">
                    <span>You receive</span>
                    <span>{formatMoney(preview.payable, preview.currency)}</span>
                  </div>
                </div>
                {!preview.meets_threshold && (
                  <p className="text-xs text-muted-foreground">
                    Below the minimum threshold of {formatMoney(preview.threshold, preview.currency)}.
                  </p>
                )}
              </div>
            )
          )}

          <DialogFooter>
            <Button variant="outline" onClick={() => setShowPreview(false)} disabled={requestMutation.isPending}>
              Cancel
            </Button>
            <Button
              disabled={!preview?.meets_threshold || requestMutation.isPending || previewQuery.isLoading}
              onClick={() => requestMutation.mutate()}
            >
              {requestMutation.isPending ? "Requesting…" : "Confirm Request"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
