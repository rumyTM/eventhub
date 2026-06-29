import { RoleGuard } from "@/components/role-guard";
import { Nav } from "@/components/nav";

const VENDOR_LINKS = [
  { href: "/vendor", label: "Dashboard" },
  { href: "/vendor/events", label: "Events" },
  { href: "/vendor/payouts", label: "Payouts" },
];

export default function VendorLayout({ children }: { children: React.ReactNode }) {
  return (
    <RoleGuard role="vendor">
      <Nav links={VENDOR_LINKS} />
      <main className="mx-auto max-w-7xl px-4 py-8">{children}</main>
    </RoleGuard>
  );
}
