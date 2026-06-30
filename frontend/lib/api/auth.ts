import { api } from "./client";
import type { AuthTokenResponse, User } from "./types";

export const authApi = {
  login: (email: string, password: string) =>
    api.post<AuthTokenResponse>("/auth/login", { email, password }),

  register: (name: string, email: string, password: string, role: string, businessName?: string, phone?: string) =>
    api.post<AuthTokenResponse>("/auth/register", {
      name,
      email,
      password,
      password_confirmation: password,
      role,
      ...(businessName ? { business_name: businessName } : {}),
      ...(phone ? { phone } : {}),
    }),

  logout: () => api.post<void>("/auth/logout"),

  me: () => api.get<{ user: User }>("/auth/me"),
};
