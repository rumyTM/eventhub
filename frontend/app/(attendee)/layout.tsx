import { RoleGuard } from "@/components/role-guard";
import { Nav } from "@/components/nav";

const ATTENDEE_LINKS = [
  { href: "/events", label: "Browse Events" },
  { href: "/orders", label: "My Orders" },
];

export default function AttendeeLayout({ children }: { children: React.ReactNode }) {
  return (
    <RoleGuard role="attendee">
      <Nav links={ATTENDEE_LINKS} />
      <main className="mx-auto max-w-7xl px-4 py-8">{children}</main>
    </RoleGuard>
  );
}
