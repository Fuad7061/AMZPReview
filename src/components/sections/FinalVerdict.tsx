import { useMemo } from "react";
import { Award, DollarSign, Wallet, ExternalLink } from "lucide-react";
import type { LucideIcon } from "lucide-react";
import { useServerFn } from "@tanstack/react-start";
import type { Product } from "@/lib/types";
import { buildAmazonUrl } from "@/lib/api";
import { recordAffiliateClick } from "@/lib/click.functions";
import { usePriceDisplay } from "@/lib/price-display";

type Role = {
  key: "overall" | "value" | "budget";
  label: string;
  Icon: LucideIcon;
  blurb: string;
  product: Product | undefined;
};

export function FinalVerdict({
  products,
  slug,
  productName,
}: {
  products: Product[];
  slug: string;
  productName: string;
}) {
  const recordClick = useServerFn(recordAffiliateClick);
  const { format: formatPrice } = usePriceDisplay();

  const roles = useMemo<Role[]>(() => {
    const overall = products.find((p) => p.topProduct) || products[0];
    const value =
      products.find((p) => p.bestValue && p.id !== overall?.id) ||
      products.find((p) => p.id !== overall?.id);
    const budget =
      products.find(
        (p) => p.bestBudget && p.id !== overall?.id && p.id !== value?.id,
      ) ||
      [...products]
        .filter((p) => p.id !== overall?.id && p.id !== value?.id)
        .sort((a, b) => a.price_sort - b.price_sort)[0];

    return [
      {
        key: "overall",
        label: "If you want the best overall",
        Icon: Award,
        blurb: "Top scores across our tests — the safest pick for most shoppers.",
        product: overall,
      },
      {
        key: "value",
        label: "If you want the best value",
        Icon: DollarSign,
        blurb: "Nearly the same experience for noticeably less money.",
        product: value,
      },
      {
        key: "budget",
        label: "If you're on a budget",
        Icon: Wallet,
        blurb: "Lowest price here that still meets our quality bar.",
        product: budget,
      },
    ].filter((r) => r.product) as Role[];
  }, [products]);

  if (!roles.length) return null;

  return (
    <section
      id="verdict"
      aria-labelledby="verdict-heading"
      className="mt-12 rounded-2xl border border-border bg-card p-6 shadow-card md:p-8"
    >
      <div className="mb-5 flex items-center gap-2">
        <span className="inline-flex h-7 items-center rounded-full bg-foreground px-3 text-[11px] font-semibold uppercase tracking-wider text-background">
          Final verdict
        </span>
        <h2
          id="verdict-heading"
          className="font-serif text-2xl text-foreground md:text-3xl"
        >
          Which {productName.toLowerCase()} should you buy?
        </h2>
      </div>

      <ul className="divide-y divide-border">
        {roles.map(({ key, label, Icon, blurb, product }) => {
          const p = product!;
          const url = buildAmazonUrl(p, p.index, slug);
          const onClick = () => {
            void recordClick({
              data: { asin: p.id, position: p.index, slug },
            }).catch(() => {});
          };
          return (
            <li
              key={key}
              className="flex flex-col gap-3 py-4 md:flex-row md:items-center md:gap-5"
            >
              <div className="flex min-w-0 flex-1 items-start gap-3">
                <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-soft text-amber">
                  <Icon className="h-4 w-4" aria-hidden="true" />
                </span>
                <div className="min-w-0">
                  <div className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    {label}
                  </div>
                  <div className="mt-0.5 truncate font-serif text-base text-foreground md:text-lg">
                    {p.brand ? `${p.brand} — ` : ""}
                    <span className="font-normal">{p.title}</span>
                  </div>
                  <p className="mt-1 text-xs text-muted-foreground">{blurb}</p>
                </div>
              </div>
              <div className="flex items-center justify-between gap-3 md:justify-end">
                <div className="text-right">
                  <div className="text-base font-bold text-foreground">
                    {formatPrice(p.price)}
                  </div>
                  {p.savings_percentage ? (
                    <div className="text-[11px] font-semibold text-emerald-600">
                      Save {p.savings_percentage}%
                    </div>
                  ) : null}
                </div>
                <a
                  href={url}
                  target="_blank"
                  rel="nofollow sponsored noopener"
                  onClick={onClick}
                  className="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full bg-foreground px-4 py-2 text-xs font-semibold text-background hover:bg-foreground/90"
                >
                  Check price <ExternalLink className="h-3 w-3" />
                </a>
              </div>
            </li>
          );
        })}
      </ul>

      <p className="mt-5 text-[11px] text-muted-foreground">
        We earn a commission from qualifying purchases at no extra cost to you.
      </p>
    </section>
  );
}
