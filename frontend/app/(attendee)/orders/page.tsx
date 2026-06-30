"use client";
import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ordersApi } from "@/lib/api";
import { LoadingSpinner } from "@/components/loading-spinner";
import { ErrorDisplay } from "@/components/error-display";
import { EmptyState } from "@/components/empty-state";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { formatMoney, formatDate, formatEventNames } from "@/lib/utils";
import Link from "next/link";

const STATUS_VARIANT: Record<string, "default" | "secondary" | "success" | "warning" | "destructive" | "outline"> = {
  pending: "warning",
  paid: "success",
  failed: "destructive",
  expired: "secondary",
  refunded: "outline",
  partially_refunded: "outline",
};

export default function OrderHistoryPage() {
  const [page, setPage] = useState(1);

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ["my-orders", page],
    queryFn: () => ordersApi.list(page),
  });

  if (isLoading) return <LoadingSpinner />;
  if (error) return <ErrorDisplay error={error} retry={refetch} />;

  const orders = data?.orders ?? [];
  const pagination = data?.pagination;

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">My Orders</h1>

      {orders.length === 0 ? (
        <EmptyState message="No orders yet." />
      ) : (
        <div className="overflow-x-auto rounded-lg border">
          <table className="w-full text-sm">
            <thead className="bg-muted">
              <tr>
                <th className="px-4 py-2 text-left">Event</th>
                <th className="px-4 py-2 text-left">Status</th>
                <th className="px-4 py-2 text-right">Total</th>
                <th className="px-4 py-2 text-left">Date</th>
                <th className="px-4 py-2"></th>
              </tr>
            </thead>
            <tbody>
              {orders.map((order) => (
                <tr key={order.id} className="border-t hover:bg-muted/30">
                  <td className="px-4 py-2">
                    <div>{formatEventNames(order.events)}</div>
                    <div className="font-mono text-xs text-muted-foreground">#{order.id.slice(-8)}</div>
                  </td>
                  <td className="px-4 py-2">
                    <div className="flex flex-wrap items-center gap-1">
                      <Badge variant={STATUS_VARIANT[order.status.value] ?? "secondary"}>
                        {order.status.label}
                      </Badge>
                      {order.has_pending_refund && !order.latest_dispute && (
                        <Badge variant="secondary">Refund Requested</Badge>
                      )}
                      {order.latest_dispute?.status.value === "open" && (
                        <Badge variant="warning">Dispute Pending</Badge>
                      )}
                      {order.latest_dispute?.status.value === "resolved" && (
                        <Badge variant="success">Dispute Approved</Badge>
                      )}
                      {order.latest_dispute?.status.value === "rejected" && (
                        <Badge variant="destructive">Dispute Rejected</Badge>
                      )}
                    </div>
                  </td>
                  <td className="px-4 py-2 text-right">{formatMoney(order.total, order.currency)}</td>
                  <td className="px-4 py-2 text-muted-foreground">{formatDate(order.created_at)}</td>
                  <td className="px-4 py-2 text-right">
                    <div className="flex items-center justify-end gap-2">
                      {order.status.value === "pending" &&
                        order.hold_expires_at &&
                        new Date(order.hold_expires_at) > new Date() && (
                        <Link href={`/checkout/${order.id}`}>
                          <Button size="sm">Pay Now</Button>
                        </Link>
                      )}
                      <Link href={`/orders/${order.id}`}>
                        <Button variant="outline" size="sm">Details</Button>
                      </Link>
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
          <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>Previous</Button>
          <span className="self-center text-sm">{page} / {pagination.last_page}</span>
          <Button variant="outline" size="sm" disabled={page >= pagination.last_page} onClick={() => setPage((p) => p + 1)}>Next</Button>
        </div>
      )}
    </div>
  );
}
