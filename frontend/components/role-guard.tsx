"use client";
import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/lib/auth-context";
import { LoadingSpinner } from "./loading-spinner";

interface Props {
  role: "admin" | "vendor" | "attendee";
  children: React.ReactNode;
}

export function RoleGuard({ role, children }: Props) {
  const { user, loading } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (loading) return;
    if (!user) {
      router.replace("/login");
      return;
    }
    if (user.role.value !== role) {
      router.replace("/");
    }
  }, [user, loading, role, router]);

  if (loading || !user || user.role.value !== role) return <LoadingSpinner />;
  return <>{children}</>;
}
