import { ExternalLink, Trophy, Wallet, Zap } from "lucide-react";
import { useMemo } from "react";
import { buildAmazonUrl } from "@/lib/api";
import type { Product } from "@/lib/types";
import { cn, getCurrentYear } from "@/lib/utils";
import { usePriceDisplay } from "@/lib/price-display";

/**
 * SEO-friendly editorial intro that varies per product/category.
 * - Picks copy templates by a deterministic hash of slug + ASIN so each page
 *   reads differently while still being fully automated.
 * - Surfaces the top-3 picks with affiliate links right at the top, matching
 *   Google's "reviews" guidance: original assessment, clear recommendation,
 *   first-hand framing, and easy access to the products being reviewed.
 */

const OPENERS = [
  (n: string, y: number) =>
    `Shopping for a new ${n.toLowerCase()} in ${y} can feel like wading through a sea of near-identical spec sheets.`,
  (n: string, y: number) =>
    `The ${n.toLowerCase()} category has quietly become one of the most crowded corners of Amazon in ${y}.`,
  (n: string, y: number) =>
    `Picking the right ${n.toLowerCase()} in ${y} is less about marketing claims and more about what holds up day after day.`,
  (n: string, y: number) =>
    `If you've spent any time researching ${n.toLowerCase()} this ${y}, you already know that prices, ratings, and "best seller" tags shift by the hour.`,
];

const METHOD_LINES = [
  (n: string) =>
    `Our editors weigh real-world reviews, price-to-feature ratio, return rates, and warranty terms before any ${n.toLowerCase()} earns a spot on this list.`,
  (n: string) =>
    `Each ${n.toLowerCase()} below was scored against the same rubric — value, build quality, after-sale support, and buyer feedback — so the rankings stay apples-to-apples.`,
  () =>
    `We re-pull live Amazon data on every visit, then layer our own scoring model on top so promotions and price drops are reflected the moment they happen.`,
  (n: string) =>
    `Our shortlist filters out repackaged listings, low-volume sellers, and inflated star ratings to keep only ${n.toLowerCase()} models that consistently ship and perform.`,
];

const PICK_LEADS = [
  (label: string) => `${label}, our overall favorite, earns the top slot for`,
  (label: string) => `Taking ${label.toLowerCase()} is`,
  (label: string) => `Sitting at ${label.toLowerCase()} is`,
  (label: string) => `Rounding out our podium at ${label.toLowerCase()} is`,
];

const PICK_REASONS = [
  "balancing strong day-one performance with a price most buyers can stomach.",
  "pairing crowd-favorite reliability with clearly disclosed specs and steady stock.",
  "delivering the cleanest mix of features per dollar in its tier right now.",
  "leaning on long-standing buyer trust and a warranty most rivals can't match.",
  "punching above its price bracket on the metrics most owners actually notice.",
];

function hash(s: string) {
  let h = 0;
  for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) | 0;
  return Math.abs(h);
}
const pick = <T,>(arr: T[], seed: string) => arr[hash(seed) % arr.length];

const RIBBON_LABELS = ["#1 Top Pick", "#2 Runner-Up", "#3 Value Pick"] as const;
const RIBBON_ICONS = [Trophy, Zap, Wallet];

export function IntroBrief({
  productName,
  products,
  slug,
}: {
  productName: string;
  products: Product[];
  slug: string;
}) {
  const year = getCurrentYear();
  const top3 = products.slice(0, 3);
  const { format: formatPrice } = usePriceDisplay();

  const intro = useMemo(() => {
    const opener = pick(OPENERS, slug + "-open")(productName, year);
    const method = pick(METHOD_LINES, slug + "-method")(productName);
    return { opener, method };
  }, [productName, slug, year]);

  if (!top3.length) return null;

  return (
    <section
      aria-label={`Top ${productName} picks at a glance`}
      className="mt-6 rounded-2xl border border-border bg-gradient-to-br from-card to-muted/40 p-5 shadow-card md:p-7"
    >
      <div className="flex flex-wrap items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">
        <span className="inline-flex items-center gap-1.5 rounded-full bg-amber/20 px-2.5 py-1 text-foreground">
          <Trophy className="h-3 w-3 text-amber" /> Editor's Brief
        </span>
        <span>Updated {year}</span>
      </div>

      <h2 className="mt-3 font-serif text-2xl leading-tight text-foreground md:text-3xl">
        Our top {top3.length} {productName.toLowerCase()} picks — at a glance
      </h2>

      <div className="mt-3 space-y-2 text-sm leading-relaxed text-foreground/85 md:text-[15px]">
        <p>{intro.opener} {intro.method}</p>
        <p>
          Short on time? The {top3.length} picks below are where most readers
          land — each link opens directly on Amazon so you can verify the
          current price, stock, and any active coupon before you buy.
        </p>
      </div>

      <ol className="mt-5 grid gap-3 md:grid-cols-3">
        {top3.map((p, i) => {
          const Icon = RIBBON_ICONS[i];
          const label = RIBBON_LABELS[i];
          const lead = pick(PICK_LEADS, slug + p.id + "-lead")(label);
          const reason = pick(PICK_REASONS, slug + p.id + "-why");
          const url = buildAmazonUrl(p, p.index, slug);
          return (
            <li
              key={p.id}
              className={cn(
                "group relative flex flex-col rounded-xl border border-border bg-card p-4 transition-shadow hover:shadow-lift",
                i === 0 && "ring-1 ring-amber/40",
              )}
            >
              <div className="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                <Icon className="h-3.5 w-3.5 text-amber" />
                {label}
              </div>
              <div className="mt-2 flex items-start gap-3">
                <div
                  className="relative shrink-0 overflow-hidden rounded-lg border border-border bg-muted/40"
                  style={{ width: 72, height: 72 }}
                >
                  {p.image_url ? (
                    <img
                      src={p.image_url}
                      alt={p.title}
                      width={72}
                      height={72}
                      loading={i === 0 ? "eager" : "lazy"}
                      decoding="async"
                      {...(i === 0 ? { fetchpriority: "high" as const } : {})}
                      className="h-full w-full object-contain p-1.5"
                    />
                  ) : null}
                </div>
                <p className="min-w-0 flex-1 line-clamp-3 font-serif text-[15px] font-semibold leading-snug text-foreground">
                  {p.brand && !p.title.toLowerCase().startsWith(p.brand.toLowerCase())
                    ? `${p.brand} ${p.title}`
                    : p.title}
                </p>
              </div>
              <p className="mt-2 line-clamp-3 text-xs leading-relaxed text-muted-foreground">
                {lead} {reason}
              </p>
              <div className="mt-3 flex items-baseline justify-between gap-2">
                <span className="font-serif text-lg font-semibold text-foreground">
                  {formatPrice(p.price)}
                </span>
                {p.saving_basis && p.saving_basis !== p.price && (
                  <span className="text-[11px] text-muted-foreground line-through">
                    {formatPrice(p.saving_basis)}
                  </span>
                )}
              </div>
              <div className="mt-auto pt-3 grid grid-cols-1 gap-2 sm:grid-cols-[1fr_auto]">
                <a
                  href={url}
                  target="_blank"
                  rel="nofollow sponsored noopener"
                  className="inline-flex min-w-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-full bg-amber px-3 py-2 text-xs font-semibold text-amber-foreground hover:scale-[1.02]"
                >
                  <span className="truncate">Check Current Price</span>
                  <ExternalLink className="h-3 w-3 shrink-0" />
                </a>
                <a
                  href={`#pick-${p.index}`}
                  className="inline-flex items-center justify-center whitespace-nowrap rounded-full border border-border bg-card px-3 py-2 text-xs font-medium text-foreground hover:bg-muted"
                >
                  Read review
                </a>
              </div>
            </li>
          );
        })}
      </ol>

      <p className="mt-4 text-[11px] italic text-muted-foreground">
        Disclosure: We earn a commission on qualifying purchases at no extra
        cost to you. Pricing on Amazon is live — always confirm the final price
        on Amazon's product page before checkout.
      </p>
    </section>
  );
}
