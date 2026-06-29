"use client";
import { ApiError } from "@/lib/api";
import { AlertCircle, RefreshCw } from "lucide-react";
import { Button } from "./ui/button";

interface Props {
  error: unknown;
  retry?: () => void;
}

export function ErrorDisplay({ error, retry }: Props) {
  if (error instanceof ApiError) {
    if (error.isRateLimited) {
      return (
        <div className="flex flex-col items-center gap-3 py-10 text-yellow-600">
          <AlertCircle className="h-8 w-8" />
          <p className="font-medium">Too many requests</p>
          {error.retryAfter && (
            <p className="text-sm text-muted-foreground">
              Please wait {error.retryAfter}s before trying again.
            </p>
          )}
          {retry && (
            <Button variant="outline" size="sm" onClick={retry}>
              <RefreshCw className="mr-2 h-4 w-4" /> Retry
            </Button>
          )}
        </div>
      );
    }

    if (error.isForbidden) {
      return (
        <div className="flex flex-col items-center gap-2 py-10 text-destructive">
          <AlertCircle className="h-8 w-8" />
          <p className="font-medium">Access denied</p>
          <p className="text-sm text-muted-foreground">You don&apos;t have permission to view this.</p>
        </div>
      );
    }

    return (
      <div className="flex flex-col items-center gap-3 py-10 text-destructive">
        <AlertCircle className="h-8 w-8" />
        <p className="font-medium">{error.message}</p>
        {retry && (
          <Button variant="outline" size="sm" onClick={retry}>
            <RefreshCw className="mr-2 h-4 w-4" /> Retry
          </Button>
        )}
      </div>
    );
  }

  return (
    <div className="flex flex-col items-center gap-3 py-10 text-destructive">
      <AlertCircle className="h-8 w-8" />
      <p className="font-medium">An unexpected error occurred</p>
      {retry && (
        <Button variant="outline" size="sm" onClick={retry}>
          <RefreshCw className="mr-2 h-4 w-4" /> Retry
        </Button>
      )}
    </div>
  );
}
