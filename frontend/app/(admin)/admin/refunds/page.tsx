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
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { formatMoney, formatDate } from "@/lib/utils";
import { toast } from "sonner";
import type { DisputeItem } from "@/lib/api";

type Action = "resolve" | "reject";

export default function AdminDisputesPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [selected, setSelected] = useState<DisputeItem | null>(null);
  const [action, setAction] = useState<Action>("resolve");
  const [resolution, setResolution] = useState("");

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ["admin-disputes", page],
    queryFn: () => adminApi.listDisputes(page),
  });

  const resolveMutation = useMutation({
    mutationFn: ({ id, resolution }: { id: string; resolution: string }) =>
      adminApi.resolveDispute(id, resolution || undefined),
    onSuccess: () => {
      toast.success("Dispute approved — refund queued.");
      closeDialog();
      qc.invalidateQueries({ queryKey: ["admin-disputes"] });
    },
    onError: (err) => toast.error(err instanceof ApiError ? err.message : "Failed to resolve dispute"),
  });

  const rejectMutation = useMutation({
    mutationFn: ({ id, resolution }: { id: string; resolution: string }) =>
      adminApi.rejectDispute(id, resolution),
    onSuccess: () => {
      toast.success("Dispute rejected.");
      closeDialog();
      qc.invalidateQueries({ queryKey: ["admin-disputes"] });
    },
    onError: (err) => toast.error(err instanceof ApiError ? err.message : "Failed to reject dispute"),
  });

  const closeDialog = () => { setSelected(null); setResolution(""); };
  const openDialog = (d: DisputeItem, a: Action) => { setSelected(d); setAction(a); setResolution(""); };

  const isPending = resolveMutation.isPending || rejectMutation.isPending;

  const submit = () => {
    if (!selected) return;
    if (action === "resolve") resolveMutation.mutate({ id: selected.id, resolution });
    else rejectMutation.mutate({ id: selected.id, resolution });
  };

  if (isLoading) return <LoadingSpinner />;
  if (error) return <ErrorDisplay error={error} retry={refetch} />;

  const disputes = data?.disputes ?? [];
  const pagination = data?.pagination;

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">Dispute Queue</h1>
      <p className="text-sm text-muted-foreground">
        Out-of-policy refund requests submitted by attendees inside the {"<"}24 h window. Approve to
        issue a full refund override, or reject to deny with a reason.
      </p>

      {disputes.length === 0 ? (
        <EmptyState message="No open disputes." />
      ) : (
        <div className="overflow-x-auto rounded-lg border">
          <table className="w-full text-sm">
            <thead className="bg-muted">
              <tr>
                <th className="px-4 py-2 text-left">Dispute ID</th>
                <th className="px-4 py-2 text-left">Order</th>
                <th className="px-4 py-2 text-right">Order Total</th>
                <th className="px-4 py-2 text-left">Reason</th>
                <th className="px-4 py-2 text-left">Opened</th>
                <th className="px-4 py-2"></th>
              </tr>
            </thead>
            <tbody>
              {disputes.map((d) => (
                <tr key={d.id} className="border-t hover:bg-muted/30">
                  <td className="px-4 py-2 font-mono text-xs">{d.id.slice(-8)}</td>
                  <td className="px-4 py-2 font-mono text-xs">{d.order_id.slice(-8)}</td>
                  <td className="px-4 py-2 text-right">
                    {d.order ? formatMoney(d.order.total, d.order.currency) : "—"}
                  </td>
                  <td className="px-4 py-2 capitalize">{d.reason.replace(/_/g, " ")}</td>
                  <td className="px-4 py-2 text-muted-foreground">{formatDate(d.created_at)}</td>
                  <td className="px-4 py-2">
                    <div className="flex justify-end gap-2">
                      <Button size="sm" variant="default" onClick={() => openDialog(d, "resolve")}>
                        Approve
                      </Button>
                      <Button size="sm" variant="destructive" onClick={() => openDialog(d, "reject")}>
                        Reject
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

      <Dialog open={!!selected} onOpenChange={() => !isPending && closeDialog()}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {action === "resolve" ? "Approve Refund" : "Reject Dispute"} — Order{" "}
              {selected?.order_id.slice(-8)}
            </DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            {action === "resolve" ? (
              <p className="text-sm text-muted-foreground">
                A full refund of{" "}
                <strong>
                  {selected?.order ? formatMoney(selected.order.total, selected.order.currency) : "—"}
                </strong>{" "}
                will be issued as an admin override. The attendee will be notified.
              </p>
            ) : (
              <p className="text-sm text-muted-foreground">
                No refund will be issued. Provide a reason for the attendee.
              </p>
            )}
            <div className="space-y-2">
              <Label>{action === "resolve" ? "Resolution note (optional)" : "Reason (required)"}</Label>
              <Textarea
                value={resolution}
                onChange={(e) => setResolution(e.target.value)}
                placeholder={
                  action === "resolve"
                    ? "Optional note for internal records…"
                    : "Explain why the refund request was denied…"
                }
                rows={3}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={closeDialog} disabled={isPending}>
              Cancel
            </Button>
            <Button
              variant={action === "resolve" ? "default" : "destructive"}
              disabled={isPending || (action === "reject" && !resolution.trim())}
              onClick={submit}
            >
              {isPending
                ? "Processing…"
                : action === "resolve"
                ? "Confirm Approve"
                : "Confirm Reject"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
