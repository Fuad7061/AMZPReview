import { DollarSign } from "lucide-react";
import { usePriceDisplay } from "@/lib/price-display";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Switch } from "@/components/ui/switch";

export function PriceVisibilityToggle() {
  const {
    showPrices,
    toggle,
    showDealPrices,
    toggleDealPrices,
  } = usePriceDisplay();

  return (
    <Popover>
      <PopoverTrigger asChild>
        <button
          type="button"
          aria-label="Price display settings"
          title="Price display settings"
          className="inline-flex h-9 items-center gap-1.5 rounded-full border border-border bg-card px-3 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
        >
          <DollarSign className="h-3.5 w-3.5" />
          <span className="hidden sm:inline">
            {showPrices ? "Prices: on" : "Prices: $$"}
          </span>
        </button>
      </PopoverTrigger>
      <PopoverContent align="end" className="w-80 p-4">
        <div className="space-y-1">
          <p className="text-sm font-semibold text-foreground">Price display</p>
          <p className="text-xs text-muted-foreground">
            Amazon's affiliate policy discourages displaying stale prices. We
            hide exact prices by default and show range symbols ($, $$, $$$)
            so you still get a sense of cost.
          </p>
        </div>

        <div className="mt-4 space-y-4">
          <label className="flex items-start justify-between gap-3">
            <span className="min-w-0">
              <span className="block text-sm font-medium text-foreground">
                Show exact product prices
              </span>
              <span className="block text-[11px] text-muted-foreground">
                Reveal last-scraped numeric prices in review cards & tables.
              </span>
            </span>
            <Switch checked={showPrices} onCheckedChange={toggle} aria-label="Show exact prices" />
          </label>

          <label className="flex items-start justify-between gap-3">
            <span className="min-w-0">
              <span className="block text-sm font-medium text-foreground">
                Show prices in deal boxes
              </span>
              <span className="block text-[11px] text-muted-foreground">
                Deal-of-the-day &amp; exit-intent boxes. On by default — deals lose meaning without a price.
              </span>
            </span>
            <Switch
              checked={showDealPrices}
              onCheckedChange={toggleDealPrices}
              aria-label="Show deal prices"
            />
          </label>
        </div>
      </PopoverContent>
    </Popover>
  );
}
