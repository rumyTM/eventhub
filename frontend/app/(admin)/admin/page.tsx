"use client";
import { useQuery } from "@tanstack/react-query";
import { adminApi, payoutsApi, eventsApi } from "@/lib/api";
import { LoadingSpinner } from "@/components/loading-spinner";
import { ErrorDisplay } from "@/components/error-display";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { formatMoney } from "@/lib/utils";
import Link from "next/link";
import { Button } from "@/components/ui/button";

export default function AdminOverviewPage() {
  const vendorsQ = useQuery({
    queryKey: ["admin-pending-vendors"],
    queryFn: () => adminApi.pendingVendors(1),
  });

  const payoutsQ = useQuery({
    queryKey: ["admin-payouts-overview"],
    queryFn: () => payoutsApi.list(1),
  });

  const eventsQ = useQuery({
    queryKey: ["admin-events-overview"],
    queryFn: () => eventsApi.list(1),
  });

  const isLoading = vendorsQ.isLoading || payoutsQ.isLoading || eventsQ.isLoading;
  const error = vendorsQ.error || payoutsQ.error || eventsQ.error;

  if (isLoading) return <LoadingSpinner />;
  if (error) return <ErrorDisplay error={error} />;

  const pendingVendors = vendorsQ.data?.vendors ?? [];
  const payouts = payoutsQ.data?.payouts ?? [];
  const events = eventsQ.data?.events ?? [];

  const totalGMV = payouts.reduce((s, p) => s + p.gross, 0);
  const pendingPayouts = payouts.filter((p) => p.status.value === "pending");

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Admin Overview</h1>

      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <Card>
          <CardHeader><CardTitle className="text-sm text-muted-foreground">Total Events</CardTitle></CardHeader>
          <CardContent><p className="text-3xl font-bold">{eventsQ.data?.pagination?.total ?? events.length}</p></CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-sm text-muted-foreground">Pending Vendors</CardTitle></CardHeader>
          <CardContent><p className="text-3xl font-bold text-yellow-600">{vendorsQ.data?.pagination?.total ?? pendingVendors.length}</p></CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-sm text-muted-foreground">GMV (payouts)</CardTitle></CardHeader>
          <CardContent><p className="text-3xl font-bold">{formatMoney(totalGMV)}</p></CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-sm text-muted-foreground">Pending Payouts</CardTitle></CardHeader>
          <CardContent><p className="text-3xl font-bold">{pendingPayouts.length}</p></CardContent>
        </Card>
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <Card className="col-span-1">
          <CardHeader className="flex-row items-center justify-between">
            <CardTitle className="text-base">Pending Approvals</CardTitle>
            <Button asChild variant="outline" size="sm">
              <Link href="/admin/vendors">View all</Link>
            </Button>
          </CardHeader>
          <CardContent>
            {pendingVendors.length === 0 ? (
              <p className="text-sm text-muted-foreground">None pending.</p>
            ) : (
              <ul className="space-y-2">
                {pendingVendors.slice(0, 5).map((v) => (
                  <li key={v.id} className="text-sm">
                    <span className="font-medium">{v.business_name}</span>
                    <span className="ml-2 text-muted-foreground">{v.kyc_status.label}</span>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>

        <Card className="col-span-1">
          <CardHeader className="flex-row items-center justify-between">
            <CardTitle className="text-base">Recent Payouts</CardTitle>
            <Button asChild variant="outline" size="sm">
              <Link href="/admin/payouts">View all</Link>
            </Button>
          </CardHeader>
          <CardContent>
            {payouts.length === 0 ? (
              <p className="text-sm text-muted-foreground">No payouts.</p>
            ) : (
              <ul className="space-y-2">
                {payouts.slice(0, 5).map((p) => (
                  <li key={p.id} className="flex justify-between text-sm">
                    <span className="font-mono text-xs">{p.batch_id}</span>
                    <span>{formatMoney(p.net)}</span>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>

        <Card className="col-span-1">
          <CardHeader className="flex-row items-center justify-between">
            <CardTitle className="text-base">Quick Actions</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            <Button asChild className="w-full" variant="outline">
              <Link href="/admin/vendors">Review KYC Queue</Link>
            </Button>
            <Button asChild className="w-full" variant="outline">
              <Link href="/admin/payouts">Manage Payouts</Link>
            </Button>
            <Button asChild className="w-full" variant="outline">
              <Link href="/admin/refunds">Refund Queue</Link>
            </Button>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
