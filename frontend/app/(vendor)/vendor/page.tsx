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
