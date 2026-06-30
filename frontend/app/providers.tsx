"use client";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { AuthProvider } from "@/lib/auth-context";
import { Toaster } from "sonner";
import { useState } from "react";

export function Providers({ children }: { children: React.ReactNode }) {
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            staleTime: 30_000,
            // Always hit the network on mount (e.g. clicking a nav link), even if cached data is
            // within staleTime — the cache still renders instantly while the refetch resolves, so
            // this trades one extra request for never showing data that's gone stale from another
            // page's mutation (e.g. approving a vendor, then navigating back to the pending queue).
            refetchOnMount: "always",
            retry: (failureCount, error) => {
              // Don't retry on 401/403/404
              if (
                error &&
                typeof error === "object" &&
                "status" in error &&
                typeof (error as { status: number }).status === "number"
              ) {
                const status = (error as { status: number }).status;
                if ([401, 403, 404].includes(status)) return false;
              }
              return failureCount < 2;
            },
          },
        },
      }),
  );

  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        {children}
        <Toaster position="top-right" richColors />
      </AuthProvider>
    </QueryClientProvider>
  );
}
