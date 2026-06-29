"use client";
import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { payoutsApi, ApiError } from "@/lib/api";
import { LoadingSpinner } from "@/components/loading-spinner";
import { ErrorDisplay } from "@/components/error-display";
import { EmptyState } from "@/components/empty-state";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { formatMoney, formatDate } from "@/lib/utils";
import { toast } from "sonner";

const STATUS_VARIANT: Record<string, "default" | "secondary" | "success" | "warning" | "destructive" | "outline"> = {
  pending: "warning",
  processing: "default",
  paid: "success",
  failed: "destructive",
};

export default function AdminPayoutsPage() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ["admin-payouts", page],
    queryFn: () => payoutsApi.list(page),
  });

  const buildMutation = useMutation({
    mutationFn: () => payoutsApi.build({}),
    onSuccess: (res) => {
      toast.success(`Payout batch built: ${res.payouts_created} payout(s) created`);
      qc.invalidateQueries({ queryKey: ["admin-payouts"] });
    },
    onError: (err) => toast.error(err instanceof ApiError ? err.message : "Failed to build batch"),
  });

  const executeMutation = useMutation({
    mutationFn: (id: string) => payoutsApi.execute(id),
    onSuccess: () => {
      toast.success("Payout executed");
      qc.invalidateQueries({ queryKey: ["admin-payouts"] });
    },
    onError: (err) => toast.error(err instanceof ApiError ? err.message : "Execution failed"),
  });

  if (isLoading) return <LoadingSpinner />;
  if (error) return <ErrorDisplay error={error} retry={refetch} />;

  const payouts = data?.payouts ?? [];
  const pagination = data?.pagination;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">Payout Management</h1>
        <Button onClick={() => buildMutation.mutate()} disabled={buildMutation.isPending}>
          {buildMutation.isPending ? "Building…" : "Build Payout Batch"}
        </Button>
      </div>

      {payouts.length === 0 ? (
        <EmptyState message="No payouts." />
      ) : (
        <div className="overflow-x-auto rounded-lg border">
          <table className="w-full text-sm">
            <thead className="bg-muted">
              <tr>
                <th className="px-4 py-2 text-left">Batch</th>
                <th className="px-4 py-2 text-left">Vendor</th>
                <th className="px-4 py-2 text-right">Gross</th>
                <th className="px-4 py-2 text-right">Net</th>
                <th className="px-4 py-2 text-right">Payable</th>
                <th className="px-4 py-2 text-left">Status</th>
                <th className="px-4 py-2 text-left">Date</th>
                <th className="px-4 py-2"></th>
              </tr>
            </thead>
            <tbody>
              {payouts.map((p) => (
                <tr key={p.id} className="border-t hover:bg-muted/30">
                  <td className="px-4 py-2 font-mono text-xs">{p.batch_id}</td>
                  <td className="px-4 py-2 text-xs font-mono">{p.vendor_id.slice(-8)}</td>
                  <td className="px-4 py-2 text-right">{formatMoney(p.gross)}</td>
                  <td className="px-4 py-2 text-right">{formatMoney(p.net)}</td>
                  <td className="px-4 py-2 text-right font-medium">{formatMoney(p.payable)}</td>
                  <td className="px-4 py-2">
                    <Badge variant={STATUS_VARIANT[p.status.value] ?? "secondary"}>{p.status.label}</Badge>
                  </td>
                  <td className="px-4 py-2 text-muted-foreground">{formatDate(p.created_at)}</td>
                  <td className="px-4 py-2 text-right">
                    {p.status.value === "pending" && (
                      <Button
                        size="sm"
                        disabled={executeMutation.isPending}
                        onClick={() => executeMutation.mutate(p.id)}
                      >
                        Execute
                      </Button>
                    )}
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
    </div>
  );
}
