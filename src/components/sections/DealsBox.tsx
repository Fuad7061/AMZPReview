import { useEffect, useState } from "react";
import { Flame, Tag } from "lucide-react";
import { useServerFn } from "@tanstack/react-start";
import type { Product } from "@/lib/types";
import { recordAffiliateClick } from "@/lib/click.functions";
import { buildAmazonUrl } from "@/lib/api";
import { usePriceDisplay } from "@/lib/price-display";

function getEndOfDay() {
  const now = new Date();
  const end = new Date(now);
  end.setHours(24, 0, 0, 0);
  return end.getTime();
}

function tick(target: number) {
  const diff = Math.max(0, target - Date.now());
  return {
    h: Math.floor(diff / 3_600_000),
    m: Math.floor((diff % 3_600_000) / 60_000),
    s: Math.floor((diff % 60_000) / 1000),
  };
}

export function DealsBox({ products, slug }: { products: Product[]; slug: string }) {
  const deal =
    [...products]
      .filter((p) => typeof p.savings_percentage === "number" && p.savings_percentage)
      .sort((a, b) => (Number(b.savings_percentage) || 0) - (Number(a.savings_percentage) || 0))[0] ||
    products[0];

  const [target] = useState(getEndOfDay);
  const [t, setT] = useState<{ h: number; m: number; s: number } | null>(null);
  const recordClick = useServerFn(recordAffiliateClick);
  const { formatDeal } = usePriceDisplay();

  useEffect(() => {
    setT(tick(target));
    const id = setInterval(() => setT(tick(target)), 1000);
    return () => clearInterval(id);
  }, [target]);

  if (!deal) return null;

  const dealUrl = buildAmazonUrl(deal, deal.index ?? 1, slug);

  const onClick = () => {
    void recordClick({
      data: { asin: deal.id, position: deal.index ?? 1, slug },
    }).catch(() => {});
  };

  return (
    <section
      id="deals"
      aria-labelledby="deals-heading"
      className="mt-12 overflow-hidden rounded-2xl border border-amber/40 bg-gradient-to-br from-amber-soft/70 to-card shadow-card"
    >
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-amber/30 bg-amber-soft/60 px-5 py-3">
        <h2
          id="deals-heading"
          className="inline-flex items-center gap-2 font-serif text-xl text-foreground"
        >
          <Flame className="h-5 w-5 text-amber" aria-hidden="true" />
          Deals are Live
        </h2>
        <span className="text-xs font-semibold uppercase tracking-wider text-foreground/70">
          Special Amazon deal ends in:
        </span>
      </div>

      <div className="grid gap-5 p-5 md:grid-cols-[120px_1fr_auto] md:items-center">
        <a
          href={dealUrl}
          target="_blank"
          rel="sponsored nofollow noopener"
          onClick={onClick}
          className="block overflow-hidden rounded-xl border border-border bg-white"
        >
          <img
            src={deal.image_url}
            alt={deal.title}
            loading="lazy"
            className="mx-auto h-28 w-28 object-contain"
          />
        </a>

        <div className="min-w-0">
          <a
            href={dealUrl}
            target="_blank"
            rel="sponsored nofollow noopener"
            onClick={onClick}
            className="block text-sm font-semibold text-foreground hover:underline md:text-base"
          >
            {deal.title}
          </a>
          <div className="mt-2 flex flex-wrap items-center gap-2 text-sm">
            <span className="inline-flex items-center gap-1 rounded-full bg-amber px-2.5 py-1 text-xs font-bold text-background">
              <Tag className="h-3 w-3" aria-hidden="true" />
              {typeof deal.savings_percentage === "number" && deal.savings_percentage > 0
                ? `${deal.savings_percentage}% OFF`
                : "Live deal"}
            </span>
            <span className="font-bold text-foreground">{formatDeal(deal.price)}</span>
            {deal.saving_basis && (
              <span className="text-xs text-muted-foreground line-through">
                {formatDeal(deal.saving_basis)}
              </span>
            )}
          </div>
        </div>

        <div className="flex flex-col items-center gap-3 md:items-end">
          <div className="flex items-center gap-2" aria-live="polite" role="timer">
            <TimeBlock value={t?.h} label="Hours" />
            <TimeBlock value={t?.m} label="Minutes" />
            <TimeBlock value={t?.s} label="Seconds" />
          </div>
          <a
            href={dealUrl}
            target="_blank"
            rel="sponsored nofollow noopener"
            onClick={onClick}
            className="inline-flex items-center justify-center rounded-full bg-foreground px-5 py-2.5 text-sm font-semibold text-background hover:bg-foreground/90"
          >
            View All Deals
          </a>
        </div>
      </div>
    </section>
  );
}

function TimeBlock({ value, label }: { value: number | undefined; label: string }) {
  const display = value == null ? "--" : String(value).padStart(2, "0");
  return (
    <div className="flex flex-col items-center">
      <span
        suppressHydrationWarning
        className="min-w-[44px] rounded-lg bg-foreground px-2 py-1.5 text-center font-mono text-lg font-bold text-background tabular-nums"
      >
        {display}
      </span>
      <span className="mt-1 text-[10px] uppercase tracking-wider text-foreground/60">{label}</span>
    </div>
  );
}
