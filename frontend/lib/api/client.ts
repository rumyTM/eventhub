import { ApiError } from "./error";
import type { ApiEnvelope } from "./types";

const BASE_URL =
  process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:8000/api/v1";

let _token: string | null = null;

export function setAuthToken(token: string | null) {
  _token = token;
  if (typeof window !== "undefined") {
    if (token) {
      localStorage.setItem("eventhub_token", token);
    } else {
      localStorage.removeItem("eventhub_token");
    }
  }
}

export function getAuthToken(): string | null {
  if (_token) return _token;
  if (typeof window !== "undefined") {
    _token = localStorage.getItem("eventhub_token");
  }
  return _token;
}

type RequestOptions = {
  method?: string;
  body?: unknown;
  headers?: Record<string, string>;
  idempotencyKey?: string;
};

export async function apiFetch<T>(
  path: string,
  options: RequestOptions = {},
): Promise<T> {
  const { method = "GET", body, headers: extraHeaders = {}, idempotencyKey } = options;

  const headers: Record<string, string> = {
    "Content-Type": "application/json",
    Accept: "application/json",
    ...extraHeaders,
  };

  const token = getAuthToken();
  if (token) {
    headers["Authorization"] = `Bearer ${token}`;
  }

  if (idempotencyKey) {
    headers["Idempotency-Key"] = idempotencyKey;
  }

  const res = await fetch(`${BASE_URL}${path}`, {
    method,
    headers,
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });

  // Parse JSON regardless of status (error envelope is also JSON)
  let json: ApiEnvelope<T>;
  try {
    json = await res.json();
  } catch {
    throw new ApiError(`HTTP ${res.status}: non-JSON response`, res.status);
  }

  if (!res.ok || !json.success) {
    const retryAfter =
      res.status === 429
        ? (json.data as Record<string, number> | null)?.retry_after
        : undefined;
    throw new ApiError(json.message, res.status, json.errors, retryAfter);
  }

  return json.data as T;
}

// ── Convenience helpers ───────────────────────────────────────────────────────

export const api = {
  get: <T>(path: string, headers?: Record<string, string>) =>
    apiFetch<T>(path, { headers }),

  post: <T>(path: string, body?: unknown, opts?: Omit<RequestOptions, "method" | "body">) =>
    apiFetch<T>(path, { method: "POST", body, ...opts }),

  put: <T>(path: string, body?: unknown) =>
    apiFetch<T>(path, { method: "PUT", body }),

  delete: <T>(path: string) =>
    apiFetch<T>(path, { method: "DELETE" }),
};
