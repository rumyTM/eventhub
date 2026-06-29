"use client";
import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { payoutsApi } from "@/lib/api";
import { LoadingSpinner } from "@/components/loading-spinner";
import { ErrorDisplay } from "@/components/error-display";
import { EmptyState } from "@/components/empty-state";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { formatMoney, formatDate } from "@/lib/utils";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

const STATUS_VARIANT: Record<string, "default" | "secondary" | "success" | "warning" | "destructive" | "outline"> = {
  pending: "warning",
  processing: "default",
  paid: "success",
  failed: "destructive",
};

export default function VendorPayoutsPage() {
  const [page, setPage] = useState(1);

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ["vendor-payouts", page],
    queryFn: () => payoutsApi.myPayouts(page),
  });

  if (isLoading) return <LoadingSpinner />;
  if (error) return <ErrorDisplay error={error} retry={refetch} />;

  const payouts = data?.payouts ?? [];
  const pagination = data?.pagination;

  const totalNet = payouts.reduce((s, p) => s + p.net, 0);

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Payout History</h1>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <Card>
          <CardHeader><CardTitle className="text-sm text-muted-foreground">Total Paid Out</CardTitle></CardHeader>
          <CardContent>
            <p className="text-2xl font-bold">
              {formatMoney(payouts.filter(p => p.status.value === "paid").reduce((s, p) => s + p.payable, 0))}
            </p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-sm text-muted-foreground">Pending Amount</CardTitle></CardHeader>
          <CardContent>
            <p className="text-2xl font-bold">
              {formatMoney(payouts.filter(p => p.status.value === "pending").reduce((s, p) => s + p.net, 0))}
            </p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-sm text-muted-foreground">Total Net (page)</CardTitle></CardHeader>
          <CardContent><p className="text-2xl font-bold">{formatMoney(totalNet)}</p></CardContent>
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
    </div>
  );
}
