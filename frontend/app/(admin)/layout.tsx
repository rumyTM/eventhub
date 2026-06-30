import { RoleGuard } from "@/components/role-guard";
import { Nav } from "@/components/nav";

const ADMIN_LINKS = [
  { href: "/admin", label: "Overview" },
  { href: "/admin/vendors", label: "Vendor Approvals" },
  { href: "/admin/payouts", label: "Payouts" },
  { href: "/admin/refunds", label: "Dispute Queue" },
];

export default function AdminLayout({ children }: { children: React.ReactNode }) {
  return (
    <RoleGuard role="admin">
      <Nav links={ADMIN_LINKS} />
      <main className="mx-auto max-w-7xl px-4 py-8">{children}</main>
    </RoleGuard>
  );
}
