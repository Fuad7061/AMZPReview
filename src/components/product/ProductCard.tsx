import { Check, ChevronDown, ChevronUp, ExternalLink, LineChart, Package, ShoppingBag, Truck, Users } from "lucide-react";
import { useMemo, useState } from "react";
import { buildAmazonUrl, generatePriceHistory } from "@/lib/api";
import { recordAffiliateClick } from "@/lib/click.functions";
import { useServerFn } from "@tanstack/react-start";
import type { Product } from "@/lib/types";
import { cn, formatRelativeDate } from "@/lib/utils";
import { usePriceDisplay } from "@/lib/price-display";
import { audienceTag, buyerCount, readerCount } from "@/lib/audience";
import { ShoppingCart } from "lucide-react";
import { RankBadge } from "./RankBadge";
import { Ribbon } from "./Ribbon";
import { ScoreBadge } from "./ScoreBadge";
import { PriceHistory } from "./PriceHistory";
import { MoreDealsList } from "./MoreDealsList";


export function ProductCard({
  product,
  slug,
  asOf,
}: {
  product: Product;
  slug: string;
  asOf?: string;
}) {
  const [showFeatures, setShowFeatures] = useState(false);
  const [showHistory, setShowHistory] = useState(false);
  const [showDeals, setShowDeals] = useState(false);
  const recordClick = useServerFn(recordAffiliateClick);
  const { format: formatPrice, showPrices } = usePriceDisplay();

  const url = useMemo(
    () => buildAmazonUrl(product, product.index, slug),
    [product, slug],
  );
  const history = useMemo(() => generatePriceHistory(product), [product]);

  const ribbon = product.topProduct
    ? "editor"
    : product.bestValue
      ? "value"
      : product.bestBudget
        ? "budget"
        : null;

  function onClickAffiliate() {
    void recordClick({
      data: { asin: product.id, position: product.index, slug },
    }).catch(() => {});
  }

  return (
    <article
      id={`pick-${product.index}`}
      className={cn(
        "relative grid gap-5 rounded-2xl border border-border bg-card p-5 shadow-card transition-shadow hover:shadow-lift lg:grid-cols-[190px_minmax(0,1fr)_180px] lg:gap-5 lg:p-6",
        product.topProduct && "ring-2 ring-amber/40",
      )}
    >
      <RankBadge rank={product.index} />
      {ribbon && <Ribbon kind={ribbon} />}

      {/* Image */}
      <div className="relative flex h-44 items-center justify-center overflow-hidden rounded-xl bg-muted/40 lg:h-60">
        {product.image_url ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={product.image_url}
            alt={`${product.title} — rank ${product.index}`}
            loading="lazy"
            decoding="async"
            className="max-h-full max-w-full object-contain p-3"
          />
        ) : (
          <Package className="h-12 w-12 text-muted-foreground" aria-hidden="true" />
        )}
        <div className="absolute right-2 top-2 lg:hidden">
          <ScoreBadge
            score={product.score}
            savings={typeof product.savings_percentage === "number" ? product.savings_percentage : ""}
          />
        </div>
      </div>

      {/* Body */}
      <div className="min-w-0">
        {product.brand && (
          <p className="text-xs uppercase tracking-wider text-muted-foreground">
            {product.brand}
          </p>
        )}
        <h3 className="mt-1 font-serif text-xl leading-snug text-foreground hyphens-auto break-words md:text-[1.45rem]">
          <a
            href={url}
            target="_blank"
            rel="nofollow sponsored noopener"
            onClick={onClickAffiliate}
            className="hover:underline"
          >
            {product.title}
          </a>
        </h3>

        {/* Pills */}
        <div className="mt-2 flex flex-wrap gap-1.5">
          <Pill tone="amber">
            <Users className="mr-1 inline h-3 w-3" /> {audienceTag(product)}
          </Pill>
          {typeof product.savings_percentage === "number" && product.savings_percentage > 0 && (
            <Pill tone="success">{product.savings_percentage}% off</Pill>
          )}
          {product.free_shipping && (
            <Pill tone="info">
              <Truck className="mr-1 inline h-3 w-3" /> Free shipping
            </Pill>
          )}
          {product.condition && product.condition.toLowerCase() !== "new" && (
            <Pill tone="muted">{product.condition}</Pill>
          )}
          {product.category_v2 && (
            <Pill tone="muted">{product.category_v2}</Pill>
          )}
        </div>

        {product.index <= 3 ? (
          <p className="mt-3 inline-flex items-center gap-2 rounded-full border border-success/40 bg-success-soft/70 px-3 py-1.5 text-sm font-semibold text-success-foreground shadow-sm">
            <ShoppingCart className="h-4 w-4 text-success" aria-hidden="true" />
            <span>
              <strong className="font-bold">
                {buyerCount(product.id, product.index).toLocaleString()}+
              </strong>{" "}
              shoppers bought this in the last 30 days
            </span>
          </p>
        ) : (
          <p className="mt-2 text-[11px] text-muted-foreground">
            <Users className="mr-1 inline h-3 w-3" aria-hidden="true" />
            <strong className="text-foreground">{readerCount(product.id).toLocaleString()}</strong>{" "}
            readers viewed this pick in the last 7 days
          </p>
        )}


        {/* Features */}
        {product.features?.length > 0 && (
          <div className="mt-4">
            <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
              Key features
            </p>
            <div
              className={cn(
                "relative mt-2 overflow-hidden transition-[max-height] duration-200",
                showFeatures ? "max-h-[600px]" : "max-h-24",
              )}
            >
              <ul className="space-y-1.5 text-sm text-foreground">
                {product.features.map((f, i) => (
                  <li key={i} className="flex items-start gap-2">
                    <Check className="mt-0.5 h-4 w-4 shrink-0 text-success" aria-hidden="true" />
                    <span>{f}</span>
                  </li>
                ))}
              </ul>
              {!showFeatures && product.features.length > 3 && (
                <div className="pointer-events-none absolute inset-x-0 bottom-0 h-12 bg-gradient-to-t from-card to-transparent" />
              )}
            </div>
            {product.features.length > 3 && (
              <button
                type="button"
                onClick={() => setShowFeatures((v) => !v)}
                aria-expanded={showFeatures}
                className="mt-1 inline-flex items-center gap-1 text-xs font-medium text-foreground hover:underline"
              >
                {showFeatures ? (
                  <>
                    Show less <ChevronUp className="h-3 w-3" />
                  </>
                ) : (
                  <>
                    Show {product.features.length - 3} more <ChevronDown className="h-3 w-3" />
                  </>
                )}
              </button>
            )}
          </div>
        )}

        {/* Expandables — promoted so they're easy to discover */}
        <div className="mt-4 flex flex-wrap gap-2">
          <button
            type="button"
            onClick={() => setShowHistory((v) => !v)}
            aria-expanded={showHistory}
            className="inline-flex items-center gap-1.5 rounded-full border border-amber/50 bg-amber/10 px-3.5 py-2 text-xs font-semibold text-foreground hover:bg-amber/20"
          >
            <LineChart className="h-3.5 w-3.5 text-amber" />
            {showHistory ? "Hide price snapshot" : "View price snapshot"}
          </button>
          {product.moreDeals && product.moreDeals.length > 0 && (
            <button
              type="button"
              onClick={() => setShowDeals((v) => !v)}
              aria-expanded={showDeals}
              className="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-3.5 py-2 text-xs font-semibold text-foreground hover:bg-muted"
            >
              <ShoppingBag className="h-3.5 w-3.5" />
              {showDeals ? "Hide other sellers" : `Compare ${product.moreDeals.length} other sellers`}
            </button>
          )}
        </div>

        {asOf && (
          <p className="mt-4 text-[11px] italic text-muted-foreground">
            Source: Amazon.com listing as of {formatRelativeDate(asOf)}. Price subject to change.
          </p>
        )}
      </div>


      {/* Aside: score + price + CTA */}
      <aside className="flex flex-col items-stretch gap-3">
        <div className="hidden lg:block">
          <ScoreBadge
            score={product.score}
            savings={typeof product.savings_percentage === "number" ? product.savings_percentage : ""}
          />
        </div>
        <div className="rounded-xl border border-border bg-muted/40 p-2.5 text-center">
          <div className="font-serif text-2xl font-semibold leading-none text-foreground">
            {formatPrice(product.price)}
          </div>
          {product.saving_basis && product.saving_basis !== product.price && (
            <div className="mt-1.5 text-[11px] text-muted-foreground">
              <span className="line-through">{formatPrice(product.saving_basis)}</span>
              {typeof product.savings_amount === "number" && product.savings_amount > 0 && (
                <div className="mt-0.5 font-medium text-success">
                  Save{" "}
                  {showPrices
                    ? formatPrice(product.savings_amount)
                    : typeof product.savings_percentage === "number"
                      ? `${product.savings_percentage}%`
                      : ""}
                </div>
              )}
            </div>
          )}
          <p className="mt-1.5 text-[10px] uppercase tracking-wider text-muted-foreground">
            Live Amazon price
          </p>
        </div>
        <a
          href={url}
          target="_blank"
          rel="nofollow sponsored noopener"
          onClick={onClickAffiliate}
          aria-label={`Check price for ${product.title} on Amazon`}
          className="group inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-amber px-3 py-3 text-sm font-bold leading-tight text-amber-foreground shadow-card transition-transform motion-safe:hover:scale-[1.02] motion-safe:active:scale-95"
        >
          <span>Check Price</span>
          <ExternalLink className="h-4 w-4 shrink-0 transition-transform motion-safe:group-hover:translate-x-0.5" aria-hidden="true" />
        </a>

        <p className="text-center text-[10px] leading-tight text-muted-foreground">
          Opens Amazon in a new tab · verify final price &amp; offers there
        </p>
      </aside>

      {/* Full-width expandable panels */}
      {showHistory && (
        <div className="lg:col-span-3">
          <PriceHistory points={history} />
        </div>
      )}
      {showDeals && product.moreDeals && (
        <div className="lg:col-span-3">
          <MoreDealsList deals={product.moreDeals} />
        </div>
      )}
    </article>
  );
}

function Pill({
  tone,
  children,
}: {
  tone: "success" | "info" | "muted" | "amber";
  children: React.ReactNode;
}) {
  const cls =
    tone === "success"
      ? "bg-success-soft text-success-foreground border-success/30"
      : tone === "info"
        ? "bg-info-soft text-info border-info/30"
        : tone === "amber"
          ? "bg-amber-soft text-amber-foreground border-amber/30"
          : "bg-muted text-muted-foreground border-border";
  return (
    <span className={cn("inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium", cls)}>
      {children}
    </span>
  );
}

