"use client";
import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/lib/auth-context";
import { LoadingSpinner } from "@/components/loading-spinner";

export default function RootPage() {
  const { user, loading } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (loading) return;
    if (!user) {
      router.replace("/login");
      return;
    }
    if (user.role.value === "admin") router.replace("/admin");
    else if (user.role.value === "vendor") router.replace("/vendor");
    else router.replace("/events");
  }, [user, loading, router]);

  return <LoadingSpinner />;
}
