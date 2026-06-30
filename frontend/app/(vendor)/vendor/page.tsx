"use client";
import { useQuery } from "@tanstack/react-query";
import { eventsApi } from "@/lib/api";
import { useAuth } from "@/lib/auth-context";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { LoadingSpinner } from "@/components/loading-spinner";
import { ErrorDisplay } from "@/components/error-display";
import { formatMoney } from "@/lib/utils";
import Link from "next/link";
import { Button } from "@/components/ui/button";

export default function VendorDashboard() {
  const { user } = useAuth();
  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ["vendor-events"],
    queryFn: () => eventsApi.list(1),
  });

  if (isLoading) return <LoadingSpinner />;
  if (error) return <ErrorDisplay error={error} retry={refetch} />;

  const events = data?.events ?? [];
  const totalSales = events.reduce((sum, e) => {
    const sold = e.ticket_types?.reduce((s, tt) => s + tt.quantity_sold, 0) ?? 0;
    const revenue = e.ticket_types?.reduce((s, tt) => s + tt.quantity_sold * tt.price, 0) ?? 0;
    return { sold: sum.sold + sold, revenue: sum.revenue + revenue };
  }, { sold: 0, revenue: 0 });

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">Welcome, {user?.name}</h1>
        <Button asChild>
          <Link href="/vendor/events/new">+ New Event</Link>
        </Button>
      </div>

      {user?.vendor?.kyc_status?.value === "pending" && !user.vendor.submitted_at && (
        <Card className="border-yellow-300 bg-yellow-50">
          <CardContent className="flex items-start justify-between py-4">
            <div>
              <p className="font-medium text-yellow-900">KYC verification required</p>
              <p className="mt-1 text-sm text-yellow-800">
                Submit your business documents to unlock event publishing and payouts.
              </p>
            </div>
            <Button asChild size="sm" className="ml-4 shrink-0 bg-yellow-700 hover:bg-yellow-800">
              <Link href="/vendor/kyc">Submit KYC</Link>
            </Button>
          </CardContent>
        </Card>
      )}

      {user?.vendor?.kyc_status?.value === "pending" && user.vendor.submitted_at && (
        <Card className="border-blue-300 bg-blue-50">
          <CardContent className="py-4">
            <p className="font-medium text-blue-900">Documents submitted — awaiting admin review</p>
            <p className="mt-1 text-sm text-blue-800">
              Your KYC application is under review. You can draft events now, but publishing and
              payouts will be unlocked once an admin approves your account.
            </p>
          </CardContent>
        </Card>
      )}

      {user?.vendor?.kyc_status?.value === "rejected" && (
        <Card className="border-red-300 bg-red-50">
          <CardContent className="py-4">
            <p className="font-medium text-red-900">KYC verification rejected</p>
            <p className="mt-1 text-sm text-red-800">
              Your KYC application was rejected. You cannot publish events or receive payouts.
              Please contact support.
            </p>
          </CardContent>
        </Card>
      )}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <Card>
          <CardHeader><CardTitle className="text-sm text-muted-foreground">Total Events</CardTitle></CardHeader>
          <CardContent><p className="text-3xl font-bold">{events.length}</p></CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-sm text-muted-foreground">Tickets Sold</CardTitle></CardHeader>
          <CardContent><p className="text-3xl font-bold">{totalSales.sold}</p></CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-sm text-muted-foreground">Gross Revenue</CardTitle></CardHeader>
          <CardContent><p className="text-3xl font-bold">{formatMoney(totalSales.revenue)}</p></CardContent>
        </Card>
      </div>

      <div>
        <h2 className="mb-3 text-lg font-semibold">Recent Events</h2>
        {events.length === 0 ? (
          <p className="text-muted-foreground">No events yet. <Link href="/vendor/events/new" className="text-primary underline">Create your first event.</Link></p>
        ) : (
          <div className="overflow-x-auto rounded-lg border">
            <table className="w-full text-sm">
              <thead className="bg-muted">
                <tr>
                  <th className="px-4 py-2 text-left">Title</th>
                  <th className="px-4 py-2 text-left">Status</th>
                  <th className="px-4 py-2 text-right">Sold</th>
                  <th className="px-4 py-2 text-right">Revenue</th>
                  <th className="px-4 py-2"></th>
                </tr>
              </thead>
              <tbody>
                {events.slice(0, 5).map((evt) => {
                  const sold = evt.ticket_types?.reduce((s, tt) => s + tt.quantity_sold, 0) ?? 0;
                  const rev = evt.ticket_types?.reduce((s, tt) => s + tt.quantity_sold * tt.price, 0) ?? 0;
                  return (
                    <tr key={evt.id} className="border-t">
                      <td className="px-4 py-2">{evt.title}</td>
                      <td className="px-4 py-2">{evt.status.label}</td>
                      <td className="px-4 py-2 text-right">{sold}</td>
                      <td className="px-4 py-2 text-right">{formatMoney(rev)}</td>
                      <td className="px-4 py-2 text-right">
                        <Link href={`/vendor/events/${evt.id}`} className="text-primary text-xs underline">View</Link>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
