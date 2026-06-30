"use client";
import React, { createContext, useCallback, useContext, useEffect, useState } from "react";
import { authApi, setAuthToken, getAuthToken } from "@/lib/api";
import type { User } from "@/lib/api";

interface AuthState {
  user: User | null;
  token: string | null;
  loading: boolean;
}

interface AuthContextValue extends AuthState {
  login: (email: string, password: string) => Promise<void>;
  register: (name: string, email: string, password: string, role: string, businessName?: string, phone?: string) => Promise<void>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [state, setState] = useState<AuthState>({
    user: null,
    token: null,
    loading: true,
  });

  // Restore session from localStorage on mount
  useEffect(() => {
    const token = getAuthToken();
    if (!token) {
      setState((s) => ({ ...s, loading: false }));
      return;
    }
    authApi
      .me()
      .then(({ user }) => {
        setState({ user, token, loading: false });
      })
      .catch(() => {
        setAuthToken(null);
        setState({ user: null, token: null, loading: false });
      });
  }, []);

  const login = useCallback(async (email: string, password: string) => {
    const { token, user } = await authApi.login(email, password);
    setAuthToken(token);
    setState({ user, token, loading: false });
  }, []);

  const register = useCallback(
    async (name: string, email: string, password: string, role: string, businessName?: string, phone?: string) => {
      const { token, user } = await authApi.register(name, email, password, role, businessName, phone);
      setAuthToken(token);
      setState({ user, token, loading: false });
    },
    [],
  );

  const refreshUser = useCallback(async () => {
    const token = getAuthToken();
    if (!token) return;
    const { user } = await authApi.me();
    setState((s) => ({ ...s, user }));
  }, []);

  const logout = useCallback(async () => {
    try {
      await authApi.logout();
    } catch {
      // best-effort
    }
    setAuthToken(null);
    setState({ user: null, token: null, loading: false });
  }, []);

  return (
    <AuthContext.Provider value={{ ...state, login, register, logout, refreshUser }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used inside AuthProvider");
  return ctx;
}
