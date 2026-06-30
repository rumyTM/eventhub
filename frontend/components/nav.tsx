"use client";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useAuth } from "@/lib/auth-context";
import { Button } from "./ui/button";
import { toast } from "sonner";

interface NavLink {
  href: string;
  label: string;
}

export function Nav({ links }: { links: NavLink[] }) {
  const { user, logout } = useAuth();
  const router = useRouter();

  async function handleLogout() {
    await logout();
    toast.success("Signed out");
    router.push("/login");
  }

  return (
    <header className="border-b bg-background">
      <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
        <div className="flex items-center gap-6">
          <Link href="/" className="font-bold text-primary">
            EventHub
          </Link>
          <nav className="hidden gap-4 md:flex">
            {links.map((l) => (
              <Link
                key={l.href}
                href={l.href}
                className="text-sm text-muted-foreground hover:text-foreground"
              >
                {l.label}
              </Link>
            ))}
          </nav>
        </div>
        <div className="flex items-center gap-3">
          {user && (
            <span className="hidden text-sm text-muted-foreground md:block">
              {user.name} ({user.role.label})
            </span>
          )}
          <Button variant="outline" size="sm" onClick={handleLogout}>
            Sign out
          </Button>
        </div>
      </div>
    </header>
  );
}
