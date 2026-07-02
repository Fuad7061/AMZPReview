import { createFileRoute, notFound } from "@tanstack/react-router";
import { ArrowUpDown, Eye, EyeOff, Sparkles } from "lucide-react";
import { useMemo, useState } from "react";
import { applyScoring, fetchProductsWithFallback, sortProducts } from "@/lib/api";
import { breadcrumbJsonLd, faqJsonLd, itemListJsonLd, reviewJsonLd } from "@/lib/jsonld";
import { estimatedPickCount, formatRelativeDate, getCurrentYear, slugToTitle } from "@/lib/utils";
import { SITE_NAME } from "@/config/site";
import type { Product, SortOption } from "@/lib/types";
import { AffiliateDisclosure } from "@/components/sections/AffiliateDisclosure";
import { DealsBox } from "@/components/sections/DealsBox";
import { FinalVerdict } from "@/components/sections/FinalVerdict";
import { Breadcrumb } from "@/components/layout/Breadcrumb";
import { HeroSection } from "@/components/sections/HeroSection";
import { IntroBrief } from "@/components/sections/IntroBrief";
import { ReviewerByline } from "@/components/sections/ReviewerByline";
import { SourcesCited } from "@/components/sections/SourcesCited";
import { ExitIntentDeal } from "@/components/sections/ExitIntentDeal";
import { MobileStickyCta } from "@/components/product/MobileStickyCta";
import { LazyMount } from "@/components/util/LazyMount";

import { TableOfContents } from "@/components/sections/TableOfContents";
import { ComparisonTable } from "@/components/sections/ComparisonTable";
import { BuyersGuide } from "@/components/sections/BuyersGuide";
import { buildDefaultFaq, FaqSection } from "@/components/sections/FaqSection";
import { RelatedProducts } from "@/components/sections/RelatedProducts";
import { EmailAlert } from "@/components/sections/EmailAlert";
import { ProductCard } from "@/components/product/ProductCard";
import { CountdownTimer } from "@/components/product/CountdownTimer";
import { Flame, Sparkles as Spark } from "lucide-react";

type PriceTier = "all" | "under50" | "50to150" | "150to500" | "over500";

export const Route = createFileRoute("/product/$slug")({
  loader: async ({ params }) => {
    const slug = params.slug;
    const res = await fetchProductsWithFallback(slug, 1, true);
    if (!res.products?.length) throw notFound();
    const products = applyScoring(res.products).slice(0, 10);
    return { products, asOf: res.created_timestamp, slug };
  },
  head: ({ params, loaderData }) => {
    const name = slugToTitle(params.slug);
    const year = getCurrentYear();
    const title = `${loaderData?.products.length ?? 10} Best ${name} of ${year} — Expert Reviews | ${SITE_NAME}`;
    const desc = `Compare the ${loaderData?.products.length ?? 10} best ${name.toLowerCase()} of ${year}. Live Amazon prices, side-by-side specs, and our top picks — updated ${formatRelativeDate(loaderData?.asOf) || "today"}.`;
    const ogImage = loaderData?.products?.[0]?.image_url;
    const pageUrl = `/product/${params.slug}`;

    const meta: Array<
      | { title: string }
      | { name?: string; property?: string; content: string }
    > = [
      { title },
      { name: "description", content: desc },
      { property: "og:title", content: title },
      { property: "og:description", content: desc },
      { property: "og:type", content: "article" },
      { property: "og:url", content: pageUrl },
      { name: "twitter:title", content: title },
      { name: "twitter:description", content: desc },
    ];
    if (ogImage) {
      meta.push(
        { property: "og:image", content: ogImage },
        { name: "twitter:image", content: ogImage },
        { name: "twitter:card", content: "summary_large_image" },
      );
    }

    const links: Array<Record<string, string>> = [
      { rel: "canonical", href: pageUrl },
    ];
    // LCP preload — top pick image is the largest visible element above the fold.
    if (ogImage) {
      links.push({
        rel: "preload",
        as: "image",
        href: ogImage,
        fetchpriority: "high",
      });
    }

    const scripts: { type: string; children: string }[] = [];
    if (loaderData?.products?.length) {
      scripts.push(
        {
          type: "application/ld+json",
          children: JSON.stringify(
            breadcrumbJsonLd([
              { name: "Home", path: "/" },
              { name: name, path: pageUrl },
            ]),
          ),
        },
        {
          type: "application/ld+json",
          children: JSON.stringify(itemListJsonLd(loaderData.products, title)),
        },
        {
          type: "application/ld+json",
          children: JSON.stringify(
            faqJsonLd(
              buildDefaultFaq(name, formatRelativeDate(loaderData.asOf))
                .map((f) => ({
                  q: f.q,
                  a: typeof f.a === "string" ? f.a : strip(f.a),
                })),
            ),
          ),
        },
      );
      // Per-product Review schema — one entry per ranked pick.
      for (const p of loaderData.products) {
        scripts.push({
          type: "application/ld+json",
          children: JSON.stringify(reviewJsonLd(p, `${SITE_NAME} Editorial Team`, pageUrl)),
        });
      }
    }

    return { meta, links, scripts };
  },
  errorComponent: ProductError,
  notFoundComponent: ProductNotFound,
  component: ProductPage,
});

function strip(node: unknown): string {
  if (node == null || typeof node === "boolean") return "";
  if (typeof node === "string" || typeof node === "number") return String(node);
  if (Array.isArray(node)) return node.map(strip).join("");
  if (typeof node === "object" && node && "props" in node) {
    // @ts-expect-error - react element shape
    return strip(node.props.children);
  }
  return "";
}

function ProductError({ error, reset }: { error: Error; reset: () => void }) {
  return (
    <div className="container-page py-20 text-center">
      <h1 className="font-serif text-4xl text-foreground">Couldn't load these picks</h1>
      <p className="mt-2 text-sm text-muted-foreground">{error.message}</p>
      <button
        onClick={reset}
        className="mt-6 inline-flex items-center justify-center rounded-full bg-foreground px-5 py-2.5 text-sm font-semibold text-background"
      >
        Try again
      </button>
    </div>
  );
}

function ProductNotFound() {
  return (
    <div className="container-page py-20 text-center">
      <h1 className="font-serif text-4xl text-foreground">No products found</h1>
      <p className="mt-2 text-sm text-muted-foreground">
        We couldn't find Amazon listings for that search. Try a broader or
        different term.
      </p>
    </div>
  );
}

const TIERS: { value: PriceTier; label: string; min: number; max: number }[] = [
  { value: "all", label: "All prices", min: 0, max: Infinity },
  { value: "under50", label: "Under $50", min: 0, max: 50 },
  { value: "50to150", label: "$50–$150", min: 50, max: 150 },
  { value: "150to500", label: "$150–$500", min: 150, max: 500 },
  { value: "over500", label: "$500+", min: 500, max: Infinity },
];

function ProductPage() {
  const { products: initial, asOf, slug } = Route.useLoaderData();
  const [sort, setSort] = useState<SortOption>("relevance");
  const [tier, setTier] = useState<PriceTier>("all");
  const [showCompare, setShowCompare] = useState(false);

  const productName = slugToTitle(slug);
  const products = useMemo<Product[]>(() => {
    const t = TIERS.find((x) => x.value === tier)!;
    const filtered = initial.filter(
      (p: Product) => p.price_sort > 0 && p.price_sort >= t.min && p.price_sort < t.max,
    );

    const base = tier === "all" ? initial : filtered.length ? filtered : initial;
    return sortProducts(base, sort);
  }, [initial, sort, tier]);
  const faqItems = useMemo(
    () => buildDefaultFaq(productName, formatRelativeDate(asOf)),
    [productName, asOf],
  );

  const scoreRange = {
    high: initial[0]?.score ?? "9.7",
    low: initial[initial.length - 1]?.score ?? "8.0",
  };
  const pickCount = estimatedPickCount(slug);
  const topPick = initial[0];

  return (
    <>
      <AffiliateDisclosure />
      <div className="container-page py-6">
        <Breadcrumb
          items={[{ label: productName, to: "/product/$slug", params: { slug } }]}
        />
        <HeroSection
          productName={productName}
          productCount={initial.length}
          asOf={asOf}
          scoreRange={scoreRange}
        />

        {/* Trust block: reviewer byline + methodology link */}
        <ReviewerByline productName={productName} asOf={asOf} />

        <IntroBrief productName={productName} products={initial} slug={slug} />

        {/* Toolbar — compare + sort + price-tier filter */}
        <div
          id="toolbar"
          className="mt-8 grid gap-3 rounded-2xl border border-border bg-card p-3 shadow-card md:p-4 lg:grid-cols-[auto_auto_1fr] lg:items-center"
        >
          {/* Primary action — neutral so the amber "Check Price" CTA always wins */}
          <button
            type="button"
            onClick={() => setShowCompare((v) => !v)}
            aria-expanded={showCompare}
            className="inline-flex w-full items-center justify-center gap-2 rounded-full border border-foreground/15 bg-foreground/[0.04] px-5 py-2.5 text-sm font-semibold text-foreground hover:bg-foreground/[0.08] sm:w-auto"
          >
            {showCompare ? (
              <>
                <EyeOff className="h-4 w-4 shrink-0" aria-hidden="true" />
                <span className="truncate">Hide comparison table</span>
              </>
            ) : (
              <>
                <Eye className="h-4 w-4 shrink-0" aria-hidden="true" />
                <span className="truncate">Compare all side-by-side</span>
              </>
            )}
          </button>
          <a
            href="#pick-1"
            className="inline-flex w-full items-center justify-center gap-2 rounded-full border border-border bg-card px-4 py-2 text-xs font-semibold text-foreground hover:bg-muted sm:w-auto"
          >
            <Sparkles className="h-3.5 w-3.5 shrink-0 text-amber" aria-hidden="true" />
            <span className="truncate">Jump to #1 pick</span>
          </a>

          <div className="flex flex-wrap items-center gap-3 lg:ml-auto lg:justify-end">
            <div className="inline-flex min-w-0 items-center gap-2">
              <label htmlFor="tier" className="shrink-0 text-xs text-muted-foreground">
                Price
              </label>
              <select
                id="tier"
                value={tier}
                onChange={(e) => setTier(e.target.value as PriceTier)}
                className="min-w-0 max-w-full rounded-full border border-border bg-card px-3 py-1.5 text-xs font-medium text-foreground outline-none focus:ring-2 focus:ring-ring"
              >
                {TIERS.map((t) => (
                  <option key={t.value} value={t.value}>{t.label}</option>
                ))}
              </select>
            </div>
            <div className="inline-flex min-w-0 items-center gap-2">
              <ArrowUpDown className="h-3.5 w-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
              <label htmlFor="sort" className="shrink-0 text-xs text-muted-foreground">
                Sort
              </label>
              <select
                id="sort"
                value={sort}
                onChange={(e) => setSort(e.target.value as SortOption)}
                className="min-w-0 max-w-full rounded-full border border-border bg-card px-3 py-1.5 text-xs font-medium text-foreground outline-none focus:ring-2 focus:ring-ring"
              >
                <option value="relevance">Our ranking</option>
                <option value="discount">Biggest discount</option>
                <option value="price_asc">Lowest price</option>
                <option value="price_desc">Highest price</option>
              </select>
            </div>
          </div>
        </div>

        {showCompare && (
          <div className="mt-6">
            <ComparisonTable products={initial} slug={slug} />
          </div>
        )}

        {/* Body: TOC + cards */}
        <div className="mt-10 grid gap-8 lg:grid-cols-[220px_1fr]">
          <aside className="hidden lg:block">
            <TableOfContents products={initial} />
          </aside>

          <div className="space-y-6">
            {products.map((p, i) => (
              <div key={p.id}>
                <ProductCard product={p} slug={slug} asOf={asOf} />
                {i === 0 && (
                  <div className="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber/40 bg-amber-soft/50 p-3 text-xs">
                    <span className="inline-flex items-center gap-2 font-semibold text-foreground">
                      <Flame className="h-4 w-4 text-amber" aria-hidden="true" />
                      <span>
                        <strong>{pickCount} shoppers</strong> have viewed this guide in the last 24 hours{asOf ? ` · prices verified ${formatRelativeDate(asOf)}` : ""}.
                      </span>
                    </span>
                    <CountdownTimer />
                  </div>
                )}
              </div>
            ))}
          </div>
        </div>

        {/* Final verdict — decision helper */}
        <FinalVerdict products={initial} slug={slug} productName={productName} />

        {/* Buyer's guide (defer — below the fold) */}
        <LazyMount>
          <div className="mt-12">
            <BuyersGuide products={initial} productName={productName} />
          </div>
        </LazyMount>

        {/* FAQ (defer) */}
        <LazyMount>
          <div className="mt-8">
            <FaqSection items={faqItems} />
          </div>
        </LazyMount>

        {/* Sources cited (collapsible) */}
        <SourcesCited products={initial} productName={productName} slug={slug} />

        {/* Email */}
        <EmailAlert productName={productName} slug={slug} />

        {/* Related (defer) */}
        <LazyMount>
          <RelatedProducts slug={slug} />
        </LazyMount>

        {/* Live deals box */}
        <DealsBox products={initial} slug={slug} />

        <p className="mx-auto mt-8 max-w-3xl text-center text-xs text-muted-foreground">
          <Spark className="mr-1 inline h-3 w-3 text-amber" />
          Listings sourced from Amazon.com on {formatRelativeDate(asOf)}. Prices and
          availability subject to change.
        </p>
      </div>

      {/* Mobile sticky CTA + exit-intent deal */}
      <MobileStickyCta product={topPick} slug={slug} />
      <ExitIntentDeal product={topPick} slug={slug} />
    </>
  );
}
