"use client";
import { useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { eventsApi, ApiError, type TicketType } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { LoadingSpinner } from "@/components/loading-spinner";
import { EmptyState } from "@/components/empty-state";
import { formatMoney } from "@/lib/utils";
import { toast } from "sonner";
import { Trash2 } from "lucide-react";

interface Props {
  eventId: string;
  ticketTypes: TicketType[];
  loading: boolean;
  onRefresh: () => void;
}

export function TicketTypesSection({ eventId, ticketTypes, loading, onRefresh }: Props) {
  const qc = useQueryClient();
  const [open, setOpen] = useState(false);
  const [kind, setKind] = useState("general");
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});

  const createMutation = useMutation({
    mutationFn: (data: Parameters<typeof eventsApi.createTicketType>[1]) =>
      eventsApi.createTicketType(eventId, data),
    onSuccess: () => {
      toast.success("Ticket type added");
      qc.invalidateQueries({ queryKey: ["event-ticket-types", eventId] });
      setOpen(false);
      onRefresh();
    },
    onError: (err) => {
      if (err instanceof ApiError && err.errors) {
        const flat: Record<string, string> = {};
        for (const [f, msgs] of Object.entries(err.errors)) flat[f] = msgs[0];
        setFormErrors(flat);
      } else {
        toast.error(err instanceof ApiError ? err.message : "Failed to create ticket type");
      }
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (ttId: string) => eventsApi.deleteTicketType(eventId, ttId),
    onSuccess: () => {
      toast.success("Ticket type deleted");
      onRefresh();
    },
    onError: () => toast.error("Failed to delete"),
  });

  function handleCreate(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setFormErrors({});
    const fd = new FormData(e.currentTarget);
    createMutation.mutate({
      kind,
      price: Math.round(Number(fd.get("price")) * 100), // BDT → poisha
      currency: "BDT",
      quantity_total: Number(fd.get("quantity_total")),
      sales_start: fd.get("sales_start") as string || undefined,
      sales_end: fd.get("sales_end") as string || undefined,
    });
  }

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-semibold">Ticket Types</h2>
        <Button size="sm" onClick={() => { setOpen(true); setFormErrors({}); }}>+ Add Ticket Type</Button>
      </div>

      {loading ? (
        <LoadingSpinner />
      ) : ticketTypes.length === 0 ? (
        <EmptyState message="No ticket types yet." />
      ) : (
        <div className="overflow-x-auto rounded-lg border">
          <table className="w-full text-sm">
            <thead className="bg-muted">
              <tr>
                <th className="px-4 py-2 text-left">Kind</th>
                <th className="px-4 py-2 text-right">Price</th>
                <th className="px-4 py-2 text-right">Total</th>
                <th className="px-4 py-2 text-right">Sold</th>
                <th className="px-4 py-2 text-right">Available</th>
                <th className="px-4 py-2"></th>
              </tr>
            </thead>
            <tbody>
              {ticketTypes.map((tt) => (
                <tr key={tt.id} className="border-t">
                  <td className="px-4 py-2">{tt.kind.label}</td>
                  <td className="px-4 py-2 text-right">{formatMoney(tt.price)}</td>
                  <td className="px-4 py-2 text-right">{tt.quantity_total}</td>
                  <td className="px-4 py-2 text-right">{tt.quantity_sold}</td>
                  <td className="px-4 py-2 text-right">{tt.quantity_total - tt.quantity_sold}</td>
                  <td className="px-4 py-2 text-right">
                    <Button
                      variant="ghost" size="icon" className="text-destructive"
                      onClick={() => { if (confirm("Delete ticket type?")) deleteMutation.mutate(tt.id); }}
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>Add Ticket Type</DialogTitle></DialogHeader>
          <form onSubmit={handleCreate} className="space-y-4">
            <div className="space-y-2">
              <Label>Kind</Label>
              <Select value={kind} onValueChange={setKind}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="general">General</SelectItem>
                  <SelectItem value="vip">VIP</SelectItem>
                  <SelectItem value="early_bird">Early Bird</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="tt-price">Price (BDT)</Label>
                <Input id="tt-price" name="price" type="number" step="0.01" min={0} required />
                {formErrors.price && <p className="text-xs text-destructive">{formErrors.price}</p>}
              </div>
              <div className="space-y-2">
                <Label htmlFor="tt-qty">Total Quantity</Label>
                <Input id="tt-qty" name="quantity_total" type="number" min={1} required />
                {formErrors.quantity_total && <p className="text-xs text-destructive">{formErrors.quantity_total}</p>}
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="tt-start">Sales Start</Label>
                <Input id="tt-start" name="sales_start" type="datetime-local" />
              </div>
              <div className="space-y-2">
                <Label htmlFor="tt-end">Sales End</Label>
                <Input id="tt-end" name="sales_end" type="datetime-local" />
              </div>
            </div>
            <DialogFooter>
              <Button type="submit" disabled={createMutation.isPending}>
                {createMutation.isPending ? "Adding…" : "Add"}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  );
}
