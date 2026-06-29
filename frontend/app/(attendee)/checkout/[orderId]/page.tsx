"use client";
import { useEffect, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { ordersApi } from "@/lib/api";
import { LoadingSpinner } from "@/components/loading-spinner";
import { ErrorDisplay } from "@/components/error-display";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { formatMoney, formatDate, secondsUntil, fmtCountdown } from "@/lib/utils";
import { Clock, CheckCircle2, XCircle } from "lucide-react";
import Link from "next/link";

export default function CheckoutPage({ params }: { params: { orderId: string } }) {
  const router = useRouter();
  const [secondsLeft, setSecondsLeft] = useState<number | null>(null);
  const [expired, setExpired] = useState(false);

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ["order", params.orderId],
    queryFn: () => ordersApi.show(params.orderId),
    refetchInterval: (query) => {
      // Poll every 3s while pending
      const status = query.state.data?.order?.status?.value;
      if (!status || status === "pending") return 3000;
      return false;
    },
  });

  const order = data?.order;
  const status = order?.status?.value;

  // Countdown from hold_expires_at
  useEffect(() => {
    if (!order?.hold_expires_at) return;

    const tick = () => {
      const secs = secondsUntil(order.hold_expires_at!);
      setSecondsLeft(secs);
      if (secs === 0) setExpired(true);
    };
    tick();
    const interval = setInterval(tick, 1000);
    return () => clearInterval(interval);
  }, [order?.hold_expires_at]);

  if (isLoading) return <LoadingSpinner />;
  if (error) return <ErrorDisplay error={error} retry={refetch} />;
  if (!order) return null;

  if (status === "paid") {
    return (
      <div className="flex flex-col items-center gap-4 py-16">
        <CheckCircle2 className="h-16 w-16 text-green-500" />
        <h1 className="text-2xl font-bold">Payment Successful!</h1>
        <p className="text-muted-foreground">
          Total paid: <strong>{formatMoney(order.total, order.currency)}</strong>
        </p>
        <p className="text-sm text-muted-foreground">Order ID: {order.id}</p>
        <Button asChild><Link href="/orders">View My Orders</Link></Button>
      </div>
    );
  }

  if (status === "failed" || status === "expired") {
    return (
      <div className="flex flex-col items-center gap-4 py-16">
        <XCircle className="h-16 w-16 text-destructive" />
        <h1 className="text-2xl font-bold">
          {status === "expired" ? "Order Expired" : "Payment Failed"}
        </h1>
        <p className="text-muted-foreground">
          {status === "expired"
            ? "Your hold expired. Please try checking out again."
            : "Payment could not be processed. Please try again."}
        </p>
        <Button variant="outline" onClick={() => router.push("/events")}>Back to Events</Button>
      </div>
    );
  }

  if (expired) {
    return (
      <div className="flex flex-col items-center gap-4 py-16">
        <Clock className="h-16 w-16 text-yellow-500" />
        <h1 className="text-2xl font-bold">Hold Expired</h1>
        <p className="text-muted-foreground">Your 15-minute ticket hold has expired.</p>
        <Button variant="outline" onClick={() => router.push("/events")}>Back to Events</Button>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-md space-y-6">
      <h1 className="text-2xl font-bold">Completing Payment</h1>

      {order.hold_expires_at && secondsLeft !== null && (
        <Card className="border-yellow-300 bg-yellow-50">
          <CardContent className="flex items-center gap-3 py-4">
            <Clock className="h-6 w-6 text-yellow-600" />
            <div>
              <p className="font-medium text-yellow-800">Hold expires in</p>
              <p className="text-2xl font-bold tabular-nums text-yellow-700">
                {fmtCountdown(secondsLeft)}
              </p>
            </div>
          </CardContent>
        </Card>
      )}

      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <CardTitle>Order #{order.id.slice(-8)}</CardTitle>
            <Badge>{order.status.label}</Badge>
          </div>
        </CardHeader>
        <CardContent className="space-y-3">
          {order.items?.map((item) => (
            <div key={item.id} className="flex justify-between text-sm">
              <span>Ticket × {item.quantity}</span>
              <span>{formatMoney(item.unit_price * item.quantity)}</span>
            </div>
          ))}
          <div className="flex justify-between border-t pt-3 font-bold">
            <span>Total</span>
            <span>{formatMoney(order.total, order.currency)}</span>
          </div>
          <p className="text-center text-sm text-muted-foreground">
            Processing payment automatically…
          </p>
          <div className="flex justify-center">
            <LoadingSpinner className="py-2" />
          </div>
          <p className="text-center text-xs text-muted-foreground">
            This page updates automatically. Created {formatDate(order.created_at)}.
          </p>
        </CardContent>
      </Card>
    </div>
  );
}
