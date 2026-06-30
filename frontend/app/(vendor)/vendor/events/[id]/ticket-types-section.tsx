"use client";
import { useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { eventsApi, ApiError, type TicketType } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { DateTimePicker } from "@/components/date-time-picker";
import { LoadingSpinner } from "@/components/loading-spinner";
import { EmptyState } from "@/components/empty-state";
import { formatMoney } from "@/lib/utils";
import { toast } from "sonner";
import { Pencil, Trash2 } from "lucide-react";

interface Props {
  eventId: string;
  eventStartsAt: string;
  eventTimezone: string;
  ticketTypes: TicketType[];
  loading: boolean;
  onRefresh: () => void;
}

type ModalMode = "add" | "edit";

interface TicketTypeFormProps {
  mode: ModalMode;
  initial?: TicketType;
  eventStartsAt: string;
  eventTimezone: string;
  onSubmit: (e: React.FormEvent<HTMLFormElement>) => void;
  isPending: boolean;
  errors: Record<string, string>;
}

function TicketTypeForm({ mode, initial, eventStartsAt, eventTimezone, onSubmit, isPending, errors }: TicketTypeFormProps) {
  const [kind, setKind] = useState(initial?.kind?.value ?? "general");
  const [groupEnabled, setGroupEnabled] = useState(
    !!(initial?.group_size && initial?.group_discount),
  );

  return (
    // key forces re-mount (resets DateTimePicker internal state) when switching between ticket types
    <form key={initial?.id ?? "new"} onSubmit={onSubmit} className="space-y-4">
      <div className="space-y-2">
        <Label>Kind</Label>
        <Select value={kind} onValueChange={setKind}>
          <SelectTrigger>
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="general">General Admission</SelectItem>
            <SelectItem value="vip">VIP</SelectItem>
            <SelectItem value="early_bird">Early Bird</SelectItem>
          </SelectContent>
        </Select>
        {/* hidden input so FormData picks up the Select value */}
        <input type="hidden" name="kind" value={kind} />
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-2">
          <Label htmlFor="tt-price">Price (BDT)</Label>
          <Input
            id="tt-price"
            name="price"
            type="number"
            step="0.01"
            min={0}
            defaultValue={initial ? initial.price / 100 : undefined}
            required
          />
          {errors.price && <p className="text-xs text-destructive">{errors.price}</p>}
        </div>
        <div className="space-y-2">
          <Label htmlFor="tt-qty">Total Quantity</Label>
          <Input
            id="tt-qty"
            name="quantity_total"
            type="number"
            min={1}
            defaultValue={initial?.quantity_total}
            required
          />
          {errors.quantity_total && (
            <p className="text-xs text-destructive">{errors.quantity_total}</p>
          )}
        </div>
      </div>

      <div className="space-y-2">
        <Label>Sales Start</Label>
        <DateTimePicker
          name="sales_start"
          defaultValue={initial?.sales_start ?? new Date().toISOString()}
          timeZone={eventTimezone}
        />
        {errors.sales_start && (
          <p className="text-xs text-destructive">{errors.sales_start}</p>
        )}
      </div>
      <div className="space-y-2">
        <Label>Sales End <span className="text-xs text-muted-foreground">(max: event start)</span></Label>
        <DateTimePicker
          name="sales_end"
          defaultValue={initial?.sales_end ?? eventStartsAt}
          timeZone={eventTimezone}
          maxDate={new Date(eventStartsAt)}
        />
        {errors.sales_end && <p className="text-xs text-destructive">{errors.sales_end}</p>}
      </div>

      <div className="space-y-3 rounded-lg border p-3">
        <div className="flex items-center gap-2">
          <input
            id="group-toggle"
            type="checkbox"
            checked={groupEnabled}
            onChange={(e) => setGroupEnabled(e.target.checked)}
            className="h-4 w-4 rounded border"
          />
          <Label htmlFor="group-toggle" className="cursor-pointer font-normal">
            Enable group discount
          </Label>
        </div>

        {groupEnabled && (
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="tt-group-size">Min Group Size</Label>
              <Input
                id="tt-group-size"
                name="group_size"
                type="number"
                min={2}
                placeholder="e.g. 5"
                defaultValue={initial?.group_size ?? undefined}
                required={groupEnabled}
              />
              {errors.group_size && (
                <p className="text-xs text-destructive">{errors.group_size}</p>
              )}
            </div>
            <div className="space-y-2">
              <Label htmlFor="tt-group-discount">Discount %</Label>
              <Input
                id="tt-group-discount"
                name="group_discount"
                type="number"
                min={1}
                max={100}
                step={0.01}
                placeholder="e.g. 10"
                defaultValue={
                  initial?.group_discount != null
                    ? Math.round(initial.group_discount * 100 * 100) / 100
                    : undefined
                }
                required={groupEnabled}
              />
              {errors.group_discount && (
                <p className="text-xs text-destructive">{errors.group_discount}</p>
              )}
            </div>
          </div>
        )}
        {/* Tell the server to clear group fields if toggle is off during edit */}
        {!groupEnabled && mode === "edit" && (
          <>
            <input type="hidden" name="group_size" value="" />
            <input type="hidden" name="group_discount" value="" />
          </>
        )}
      </div>

      <DialogFooter>
        <Button type="submit" disabled={isPending}>
          {isPending ? (mode === "add" ? "Adding…" : "Saving…") : mode === "add" ? "Add" : "Save"}
        </Button>
      </DialogFooter>
    </form>
  );
}

export function TicketTypesSection({ eventId, eventStartsAt, eventTimezone, ticketTypes, loading, onRefresh }: Props) {
  const qc = useQueryClient();
  const [modalMode, setModalMode] = useState<ModalMode>("add");
  const [open, setOpen] = useState(false);
  const [editTarget, setEditTarget] = useState<TicketType | undefined>();
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});

  function parseForm(fd: FormData, groupEnabled: boolean) {
    const groupSize = fd.get("group_size") as string;
    const groupDiscount = fd.get("group_discount") as string;
    return {
      kind: fd.get("kind") as string,
      price: Math.round(Number(fd.get("price")) * 100),
      currency: "BDT",
      quantity_total: Number(fd.get("quantity_total")),
      sales_start: (fd.get("sales_start") as string) || undefined,
      sales_end: (fd.get("sales_end") as string) || undefined,
      group_size: groupEnabled && groupSize ? Number(groupSize) : undefined,
      group_discount: groupEnabled && groupDiscount ? Number(groupDiscount) / 100 : undefined,
    };
  }

  function isGroupEnabled(fd: FormData) {
    // Group fields are present only when toggle is on; detect by non-empty value
    const gs = fd.get("group_size") as string;
    const gd = fd.get("group_discount") as string;
    return !!(gs && gs !== "" && gd && gd !== "");
  }

  const createMutation = useMutation({
    mutationFn: (data: Parameters<typeof eventsApi.createTicketType>[1]) =>
      eventsApi.createTicketType(eventId, data),
    onSuccess: () => {
      toast.success("Ticket type added");
      qc.invalidateQueries({ queryKey: ["event-ticket-types", eventId] });
      setOpen(false);
      onRefresh();
    },
    onError: handleApiError,
  });

  const updateMutation = useMutation({
    mutationFn: ({
      id,
      data,
    }: {
      id: string;
      data: Parameters<typeof eventsApi.updateTicketType>[2];
    }) => eventsApi.updateTicketType(eventId, id, data),
    onSuccess: () => {
      toast.success("Ticket type updated");
      qc.invalidateQueries({ queryKey: ["event-ticket-types", eventId] });
      setOpen(false);
      onRefresh();
    },
    onError: handleApiError,
  });

  const deleteMutation = useMutation({
    mutationFn: (ttId: string) => eventsApi.deleteTicketType(eventId, ttId),
    onSuccess: () => {
      toast.success("Ticket type deleted");
      onRefresh();
    },
    onError: () => toast.error("Failed to delete"),
  });

  function handleApiError(err: unknown) {
    if (err instanceof ApiError && err.errors) {
      const flat: Record<string, string> = {};
      for (const [f, msgs] of Object.entries(err.errors)) flat[f] = msgs[0];
      setFormErrors(flat);
    } else {
      toast.error(err instanceof ApiError ? err.message : "Something went wrong");
    }
  }

  function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setFormErrors({});
    const fd = new FormData(e.currentTarget);
    const groupOn = isGroupEnabled(fd);
    const payload = parseForm(fd, groupOn);

    if (modalMode === "add") {
      createMutation.mutate(payload);
    } else if (editTarget) {
      updateMutation.mutate({ id: editTarget.id, data: payload });
    }
  }

  function openAdd() {
    setEditTarget(undefined);
    setModalMode("add");
    setFormErrors({});
    setOpen(true);
  }

  function openEdit(tt: TicketType) {
    setEditTarget(tt);
    setModalMode("edit");
    setFormErrors({});
    setOpen(true);
  }

  function handleOpenChange(val: boolean) {
    setOpen(val);
    if (!val) {
      setEditTarget(undefined);
      setFormErrors({});
    }
  }

  const isPending = createMutation.isPending || updateMutation.isPending;

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-semibold">Ticket Types</h2>
        <Button size="sm" onClick={openAdd}>
          + Add Ticket Type
        </Button>
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
                <th className="px-4 py-2 text-right">Group Discount</th>
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
                  <td className="px-4 py-2 text-right text-xs text-muted-foreground">
                    {tt.group_size && tt.group_discount
                      ? `${Math.round(tt.group_discount * 100)}% off (min ${tt.group_size})`
                      : "—"}
                  </td>
                  <td className="px-4 py-2 text-right">
                    <div className="flex items-center justify-end gap-1">
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => openEdit(tt)}
                        title="Edit ticket type"
                      >
                        <Pencil className="h-4 w-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon"
                        className="text-destructive"
                        onClick={() => {
                          if (confirm("Delete ticket type?")) deleteMutation.mutate(tt.id);
                        }}
                        title="Delete ticket type"
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Dialog open={open} onOpenChange={handleOpenChange} modal={false}>
        <DialogContent
          className="max-w-lg"
          onInteractOutside={(e) => e.preventDefault()}
        >
          <DialogHeader>
            <DialogTitle>
              {modalMode === "add" ? "Add Ticket Type" : "Edit Ticket Type"}
            </DialogTitle>
          </DialogHeader>
          <TicketTypeForm
            mode={modalMode}
            initial={editTarget}
            eventStartsAt={eventStartsAt}
            eventTimezone={eventTimezone}
            onSubmit={handleSubmit}
            isPending={isPending}
            errors={formErrors}
          />
        </DialogContent>
      </Dialog>
    </div>
  );
}
