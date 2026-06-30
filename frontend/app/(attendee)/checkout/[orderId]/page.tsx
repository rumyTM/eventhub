"use client";
import { useEffect, useState } from "react";
import { useQuery, useMutation } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { ordersApi, ApiError } from "@/lib/api";
import { LoadingSpinner } from "@/components/loading-spinner";
import { ErrorDisplay } from "@/components/error-display";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { formatMoney, formatDate, secondsUntil, fmtCountdown } from "@/lib/utils";
import { Clock, CheckCircle2, XCircle, CreditCard, Lock } from "lucide-react";
import Link from "next/link";
import { toast } from "sonner";

export default function CheckoutPage({ params }: { params: { orderId: string } }) {
  const router = useRouter();
  const [secondsLeft, setSecondsLeft] = useState<number | null>(null);
  const [expired, setExpired] = useState(false);
  const [paymentStarted, setPaymentStarted] = useState(false);

  const payMutation = useMutation({
    mutationFn: () => ordersApi.pay(params.orderId),
    onSuccess: () => setPaymentStarted(true),
    onError: (err) => {
      if (err instanceof ApiError) toast.error(err.message);
      else toast.error("Payment could not be initiated. Please try again.");
    },
  });

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ["order", params.orderId],
    queryFn: () => ordersApi.show(params.orderId),
    refetchInterval: (query) => {
      const status = query.state.data?.order?.status?.value;
      // Keep polling while pending (covers both in-flight and payment_failed/retry states)
      if (!status || status === "pending") return 3000;
      return false;
    },
  });

  const order = data?.order;
  const status = order?.status?.value;

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
        <Button asChild>
          <Link href="/orders">View My Orders</Link>
        </Button>
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
        <Button variant="outline" onClick={() => router.push("/events")}>
          Back to Events
        </Button>
      </div>
    );
  }

  if (expired) {
    return (
      <div className="flex flex-col items-center gap-4 py-16">
        <Clock className="h-16 w-16 text-yellow-500" />
        <h1 className="text-2xl font-bold">Hold Expired</h1>
        <p className="text-muted-foreground">Your 15-minute ticket hold has expired.</p>
        <Button variant="outline" onClick={() => router.push("/events")}>
          Back to Events
        </Button>
      </div>
    );
  }

  const holdBanner = order.hold_expires_at && secondsLeft !== null && (
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
  );

  const orderSummary = (
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
      </CardContent>
    </Card>
  );

  // After clicking Pay — wait for the webhook to settle the order
  if (paymentStarted) {
    // Payment webhook reported failure — let the user retry
    if (order.payment_failed) {
      return (
        <div className="mx-auto max-w-md space-y-6">
          <h1 className="text-2xl font-bold">Payment Failed</h1>
          {holdBanner}
          {orderSummary}
          <Card className="border-destructive">
            <CardContent className="space-y-4 pt-6">
              <div className="flex flex-col items-center gap-3">
                <XCircle className="h-10 w-10 text-destructive" />
                <p className="text-center text-sm text-muted-foreground">
                  Your payment could not be processed. You can try again while your hold is still active.
                </p>
              </div>
              <Button
                className="w-full"
                size="lg"
                onClick={() => setPaymentStarted(false)}
              >
                Try Again
              </Button>
            </CardContent>
          </Card>
        </div>
      );
    }

    return (
      <div className="mx-auto max-w-md space-y-6">
        <h1 className="text-2xl font-bold">Completing Payment</h1>
        {holdBanner}
        {orderSummary}
        <Card>
          <CardContent className="space-y-3 pt-6">
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

  // Payment form — cosmetic demo card inputs; actual charge is handled by payment-service
  return (
    <div className="mx-auto max-w-md space-y-6">
      <h1 className="text-2xl font-bold">Complete Payment</h1>
      {holdBanner}
      {orderSummary}

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <CreditCard className="h-4 w-4" />
            Payment Details
          </CardTitle>
          <p className="text-xs text-muted-foreground">
            Demo environment — no real card is charged
          </p>
        </CardHeader>
        <CardContent>
          <form
            onSubmit={(e) => {
              e.preventDefault();
              payMutation.mutate();
            }}
            className="space-y-4"
          >
            <div className="space-y-2">
              <Label htmlFor="card-number">Card Number</Label>
              <Input
                id="card-number"
                defaultValue="4242 4242 4242 4242"
                placeholder="1234 5678 9012 3456"
                maxLength={19}
                className="font-mono tracking-widest"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="card-name">Cardholder Name</Label>
              <Input id="card-name" defaultValue="Demo User" placeholder="Name on card" />
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="card-expiry">Expiry</Label>
                <Input
                  id="card-expiry"
                  defaultValue="12/28"
                  placeholder="MM/YY"
                  maxLength={5}
                  className="font-mono"
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="card-cvv">CVV</Label>
                <Input
                  id="card-cvv"
                  defaultValue="123"
                  placeholder="•••"
                  maxLength={4}
                  className="font-mono"
                />
              </div>
            </div>

            <Button type="submit" className="w-full" size="lg" disabled={payMutation.isPending}>
              <Lock className="mr-2 h-4 w-4" />
              {payMutation.isPending ? "Submitting…" : `Pay ${formatMoney(order.total, order.currency)}`}
            </Button>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
