import { InboxIcon } from "lucide-react";

export function EmptyState({ message = "No items found." }: { message?: string }) {
  return (
    <div className="flex flex-col items-center gap-3 py-10 text-muted-foreground">
      <InboxIcon className="h-10 w-10" />
      <p className="text-sm">{message}</p>
    </div>
  );
}
