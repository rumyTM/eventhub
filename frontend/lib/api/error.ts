export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly errors: Record<string, string[]> | null = null,
    public readonly retryAfter?: number,
  ) {
    super(message);
    this.name = "ApiError";
  }

  /** True when this is a 401 — caller should redirect to /login */
  get isUnauthorized() {
    return this.status === 401;
  }

  get isForbidden() {
    return this.status === 403;
  }

  get isRateLimited() {
    return this.status === 429;
  }
}
