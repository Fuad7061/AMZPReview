import { Award, DollarSign, Wallet } from "lucide-react";
import type { LucideIcon } from "lucide-react";

type Kind = "editor" | "value" | "budget";

const CONFIG: Record<Kind, { label: string; Icon: LucideIcon }> = {
  editor: { label: "Editor's Choice", Icon: Award },
  value: { label: "Best Value", Icon: DollarSign },
  budget: { label: "Best Budget", Icon: Wallet },
};

export function Ribbon({ kind }: { kind: Kind }) {
  const { label, Icon } = CONFIG[kind];
  return (
    <div className="ribbon-fold absolute -top-2 left-6 z-10 inline-flex items-center gap-1.5 rounded-r-md bg-ribbon px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-ribbon-foreground shadow-ribbon">
      <Icon className="h-3.5 w-3.5" aria-hidden="true" />
      <span>{label}</span>
    </div>
  );
}
