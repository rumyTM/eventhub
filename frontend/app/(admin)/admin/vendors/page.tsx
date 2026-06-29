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
import { formatDate } from "@/lib/utils";
import { toast } from "sonner";
import type { Vendor } from "@/lib/api";

export default function AdminVendorsPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const [rejectVendor, setRejectVendor] = useState<Vendor | null>(null);
  const [rejectReason, setRejectReason] = useState("");

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ["admin-pending-vendors", page],
    queryFn: () => adminApi.pendingVendors(page),
  });

  const verifyMutation = useMutation({
    mutationFn: (id: string) => adminApi.verifyVendor(id),
    onSuccess: () => {
      toast.success("Vendor verified");
      qc.invalidateQueries({ queryKey: ["admin-pending-vendors"] });
    },
    onError: (err) => toast.error(err instanceof ApiError ? err.message : "Failed to verify"),
  });

  const rejectMutation = useMutation({
    mutationFn: ({ id, reason }: { id: string; reason: string }) =>
      adminApi.rejectVendor(id, reason),
    onSuccess: () => {
      toast.success("Vendor rejected");
      setRejectVendor(null);
      setRejectReason("");
      qc.invalidateQueries({ queryKey: ["admin-pending-vendors"] });
    },
    onError: (err) => toast.error(err instanceof ApiError ? err.message : "Failed to reject"),
  });

  if (isLoading) return <LoadingSpinner />;
  if (error) return <ErrorDisplay error={error} retry={refetch} />;

  const vendors = data?.vendors ?? [];
  const pagination = data?.pagination;

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">Vendor KYC Approvals</h1>

      {vendors.length === 0 ? (
        <EmptyState message="No vendors pending review." />
      ) : (
        <div className="overflow-x-auto rounded-lg border">
          <table className="w-full text-sm">
            <thead className="bg-muted">
              <tr>
                <th className="px-4 py-2 text-left">Business</th>
                <th className="px-4 py-2 text-left">Legal Name</th>
                <th className="px-4 py-2 text-left">Status</th>
                <th className="px-4 py-2 text-left">Submitted</th>
                <th className="px-4 py-2"></th>
              </tr>
            </thead>
            <tbody>
              {vendors.map((v) => (
                <tr key={v.id} className="border-t hover:bg-muted/30">
                  <td className="px-4 py-2 font-medium">{v.business_name}</td>
                  <td className="px-4 py-2">{v.legal_name}</td>
                  <td className="px-4 py-2">
                    <Badge variant={v.kyc_status.value === "pending" ? "warning" : v.kyc_status.value === "verified" ? "success" : "destructive"}>
                      {v.kyc_status.label}
                    </Badge>
                  </td>
                  <td className="px-4 py-2 text-muted-foreground">{formatDate(v.submitted_at)}</td>
                  <td className="px-4 py-2">
                    <div className="flex items-center justify-end gap-2">
                      {v.kyc_status.value === "pending" && (
                        <>
                          <Button
                            size="sm"
                            onClick={() => verifyMutation.mutate(v.id)}
                            disabled={verifyMutation.isPending}
                          >
                            Approve
                          </Button>
                          <Button
                            size="sm"
                            variant="destructive"
                            onClick={() => { setRejectVendor(v); setRejectReason(""); }}
                          >
                            Reject
                          </Button>
                        </>
                      )}
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
          <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage(p => p - 1)}>Previous</Button>
          <span className="self-center text-sm">{page} / {pagination.last_page}</span>
          <Button variant="outline" size="sm" disabled={page >= pagination.last_page} onClick={() => setPage(p => p + 1)}>Next</Button>
        </div>
      )}

      <Dialog open={!!rejectVendor} onOpenChange={() => setRejectVendor(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Reject Vendor — {rejectVendor?.business_name}</DialogTitle>
          </DialogHeader>
          <div className="space-y-2">
            <Label>Rejection Reason</Label>
            <Textarea
              value={rejectReason}
              onChange={(e) => setRejectReason(e.target.value)}
              placeholder="Explain why the KYC application is rejected…"
              rows={3}
            />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setRejectVendor(null)}>Cancel</Button>
            <Button
              variant="destructive"
              disabled={!rejectReason.trim() || rejectMutation.isPending}
              onClick={() => {
                if (rejectVendor)
                  rejectMutation.mutate({ id: rejectVendor.id, reason: rejectReason });
              }}
            >
              {rejectMutation.isPending ? "Rejecting…" : "Reject"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
