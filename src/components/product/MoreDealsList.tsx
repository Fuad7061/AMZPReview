import { ExternalLink } from "lucide-react";
import { usePriceDisplay } from "@/lib/price-display";
import type { MoreDeal } from "@/lib/types";

export function MoreDealsList({ deals }: { deals: MoreDeal[] }) {
  const { format: formatPrice } = usePriceDisplay();
  if (!deals?.length) return null;
  return (
    <div className="rounded-lg border border-border bg-muted/20 p-3">
      <p className="mb-2 text-xs font-semibold text-foreground">Other sellers</p>
      <ul className="space-y-1.5 text-xs">
        {deals.slice(0, 5).map((d, i) => (
          <li key={i} className="flex items-center justify-between gap-2">
            <span className="truncate text-muted-foreground">
              {d.merchant || "Marketplace"}
              {d.condition && d.condition.toLowerCase() !== "new" && (
                <span className="ml-1 text-muted-foreground">· {d.condition}</span>
              )}
              {d.free_shipping && <span className="ml-1 text-success-foreground">· Free shipping</span>}
            </span>
            <a
              href={d.url}
              target="_blank"
              rel="nofollow sponsored noopener"
              className="inline-flex shrink-0 items-center gap-1 font-semibold text-foreground hover:underline"
            >
              {formatPrice(d.price)} <ExternalLink className="h-3 w-3" />
            </a>
          </li>
        ))}
      </ul>
    </div>
  );
}
