import { AMAZON_TAG, API_URL, SITE_DOMAIN } from "@/config/site";
import type {
  ApiResponse,
  PriceHistoryPoint,
  Product,
  RawProduct,
  SortOption,
} from "./types";
import { relevanceScore, significantTokens } from "./utils";

/** Composite "review score" ladder (1 = best). Disclosed on /methodology. */
const SCORE_LADDER = [
  "9.7",
  "9.5",
  "9.4",
  "9.2",
  "9.0",
  "8.8",
  "8.6",
  "8.3",
  "8.1",
  "8.0",
];

const MIN_BEST_VALUE_DISCOUNT = 10; // % required to label a card "Best Value"

function parseMoney(v: unknown): number {
  if (typeof v === "number") return v;
  if (typeof v !== "string") return 0;
  const n = parseFloat(v.replace(/[^0-9.]/g, ""));
  return Number.isFinite(n) ? n : 0;
}

export async function fetchProducts(
  query: string,
  page = 1,
  extendedData = true,
): Promise<ApiResponse> {
  const params = new URLSearchParams({ q: query, tag: AMAZON_TAG });
  if (page > 1) params.set("page", String(page));
  if (extendedData) params.set("extendedData", "true");

  const res = await fetch(`${API_URL}?${params.toString()}`, {
    headers: { Accept: "application/json" },
  });
  if (!res.ok) {
    throw new Error(`Upstream API responded with ${res.status}`);
  }
  return (await res.json()) as ApiResponse;
}

/**
 * Try the slug as-is, then a handful of common variants when upstream returns
 * zero products. Upstream is finicky about plural/singular and "-review"
 * suffixes (e.g. "coffee-bean-review" works, "coffee-grinder-review" doesn't),
 * so we transparently retry instead of showing an empty page.
 */
export async function fetchProductsWithFallback(
  slug: string,
  page = 1,
  extendedData = true,
): Promise<ApiResponse> {
  const tried = new Set<string>();
  const variants: string[] = [];
  const push = (s: string) => {
    const v = s.replace(/-+/g, "-").replace(/^-|-$/g, "");
    if (v && !tried.has(v)) {
      tried.add(v);
      variants.push(v);
    }
  };

  // Prioritize the cleaned, filler-free recombination — upstream returns
  // better results for "golf-outfit-men" than "golf-outfit-for-men", and for
  // "golf-ball" than "what-is-the-best-golf-ball".
  const sig = significantTokens(slug);
  if (sig.length >= 2) {
    push(sig.join("-"));
    push(sig.join("-") + "s");
  }
  // then the raw slug as the user typed it
  push(slug);
  if (slug.endsWith("s")) push(slug.replace(/s$/, ""));
  else push(`${slug}s`);
  // toggle "-review" / "-reviews" suffix on the cleaned form
  const cleaned = sig.length ? sig.join("-") : slug;
  if (/-reviews?$/.test(cleaned)) push(cleaned.replace(/-reviews?$/, ""));
  else {
    push(`${cleaned}-review`);
    push(`${cleaned}-reviews`);
  }
  // progressively drop trailing qualifier (e.g. audience like "men")
  for (let i = sig.length - 1; i >= 2; i--) {
    push(sig.slice(0, i).join("-"));
    push(sig.slice(0, i).join("-") + "s");
  }
  // adjacent token pairs ("golf-outfit", "outfit-men")
  for (let i = 0; i + 1 < sig.length; i++) {
    push(`${sig[i]}-${sig[i + 1]}`);
  }
  // last resort: first noun-ish token only — relevance filter still applies
  if (sig[0]) {
    push(sig[0]);
    push(`${sig[0]}s`);
  }

  /**
   * Relevance filter — products from the upstream API are kept only when they
   * match at least `minHits` of the query's significant tokens. Prevents
   * "golf outfit for men" from surfacing golf balls, tees, or brushes when
   * the fallback degrades to the single token "golf".
   */
  const minHits = sig.length >= 3 ? 2 : sig.length >= 2 ? 2 : 1;
  const filterRelevant = (raw: RawProduct[]): RawProduct[] => {
    if (!sig.length) return raw;
    return raw.filter((p) => {
      const hay = [
        p.title ?? "",
        p.brand ?? "",
        p.category ?? "",
        Array.isArray(p.features) ? p.features.join(" ") : "",
      ].join(" ");
      return relevanceScore(sig, hay) >= minHits;
    });
  };

  let last: ApiResponse | null = null;
  let bestPartial: { res: ApiResponse; filtered: RawProduct[] } | null = null;
  for (const v of variants) {
    const r = await fetchProducts(v, page, extendedData);
    last = r;
    if (!r.products?.length) continue;
    const filtered = filterRelevant(r.products);
    if (filtered.length >= 3) {
      return { ...r, products: filtered };
    }
    if (filtered.length && (!bestPartial || filtered.length > bestPartial.filtered.length)) {
      bestPartial = { res: r, filtered };
    }
  }
  if (bestPartial) {
    return { ...bestPartial.res, products: bestPartial.filtered };
  }
  return last ?? ({ products: [] } as unknown as ApiResponse);
}

/**
 * Apply rule-based scoring + role labels.
 *  - Score = position-based ladder (disclosed on /methodology).
 *  - topProduct: rank #1.
 *  - bestValue: largest savings_percentage AND > 10%, and not already #1.
 *  - bestBudget: cheapest price_sort, only when distinct from above.
 */
export function applyScoring(raw: RawProduct[]): Product[] {
  const products: Product[] = raw.map((p, i) => {
    // Upstream quirk: `savings_amount` sometimes equals the original list price
    // instead of the actual discount. Recompute from saving_basis − price when
    // we can, so the UI never shows nonsense like "$39.98  $44.99 Save $44.99".
    const basisNum = parseMoney(p.saving_basis);
    const priceNum = typeof p.price_sort === "number" ? p.price_sort : parseMoney(p.price);
    let savingsAmount: number | "" = "";
    let savingsPct: number | "" =
      typeof p.savings_percentage === "number" ? p.savings_percentage : "";
    if (basisNum > 0 && priceNum > 0 && basisNum > priceNum) {
      savingsAmount = Math.round((basisNum - priceNum) * 100) / 100;
      savingsPct = Math.round(((basisNum - priceNum) / basisNum) * 100);
    } else {
      // No genuine discount → suppress strikethrough/Save chip entirely.
      savingsAmount = "";
      savingsPct = "";
    }
    return {
      ...p,
      saving_basis: basisNum > priceNum ? p.saving_basis : "",
      savings_amount: savingsAmount,
      savings_percentage: savingsPct,
      index: i + 1,
      topProduct: i === 0,
      bestValue: false,
      bestBudget: false,
      score: SCORE_LADDER[i] ?? "7.9",
    };
  });

  // Best Value = max discount > threshold
  let bestValueIdx = -1;
  let bestDiscount = MIN_BEST_VALUE_DISCOUNT;
  products.forEach((p, i) => {
    const s = typeof p.savings_percentage === "number" ? p.savings_percentage : 0;
    if (s > bestDiscount && i !== 0) {
      bestDiscount = s;
      bestValueIdx = i;
    }
  });
  if (bestValueIdx >= 0) products[bestValueIdx].bestValue = true;

  // Best Budget = cheapest, only if distinct & meaningfully cheaper than #1
  let cheapestIdx = -1;
  let cheapest = Infinity;
  products.forEach((p, i) => {
    if (p.price_sort > 0 && p.price_sort < cheapest) {
      cheapest = p.price_sort;
      cheapestIdx = i;
    }
  });
  if (
    cheapestIdx >= 0 &&
    cheapestIdx !== 0 &&
    cheapestIdx !== bestValueIdx
  ) {
    products[cheapestIdx].bestBudget = true;
  }

  return products;
}

export function sortProducts(products: Product[], sort: SortOption): Product[] {
  const arr = [...products];
  if (sort === "discount") {
    arr.sort((a, b) => {
      const sa = typeof a.savings_percentage === "number" ? a.savings_percentage : 0;
      const sb = typeof b.savings_percentage === "number" ? b.savings_percentage : 0;
      return sb - sa;
    });
  } else if (sort === "price_asc") {
    arr.sort((a, b) => (a.price_sort || Infinity) - (b.price_sort || Infinity));
  } else if (sort === "price_desc") {
    arr.sort((a, b) => (b.price_sort || 0) - (a.price_sort || 0));
  }
  // "relevance" preserves the original API order
  return arr;
}

/**
 * Deterministic synthetic 6-month price history (per product).
 * Marked clearly in the UI as a price snapshot, not real historical data.
 */
export function generatePriceHistory(product: Product): PriceHistoryPoint[] {
  const base = product.price_sort || 19.99;
  // Stable seed from ASIN
  let seed = 0;
  for (let i = 0; i < product.id.length; i++) seed = (seed * 31 + product.id.charCodeAt(i)) | 0;
  const rand = (i: number) => {
    const x = Math.sin(seed + i * 12.9898) * 43758.5453;
    return x - Math.floor(x);
  };

  const points: PriceHistoryPoint[] = [];
  const now = new Date();
  for (let i = 180; i >= 0; i -= 7) {
    const date = new Date(now);
    date.setDate(date.getDate() - i);
    const trend = Math.sin(i * 0.05) * base * 0.08;
    const noise = (rand(i) - 0.5) * base * 0.06;
    const price = Math.max(0.5, base + trend + noise);
    points.push({
      date: date.toISOString().split("T")[0],
      price: Math.round(price * 100) / 100,
    });
  }
  // Force the last point to current price for honesty
  if (points.length) points[points.length - 1].price = base;
  return points;
}

/** Build the canonical Amazon affiliate URL for a given product + position. */
export function buildAmazonUrl(
  product: Product,
  position: number,
  slug: string,
): string {
  const params = new URLSearchParams({
    tag: AMAZON_TAG,
    linkCode: "osi",
    th: "1",
    psc: "1",
    language: "en_US",
    ascsubtag: `${SITE_DOMAIN}-${slug}-${position}`,
  });
  return `https://www.amazon.com/dp/${product.id}?${params.toString()}`;
}

/** Build a search URL on Amazon for the slug (used as fallback / "see all"). */
export function buildAmazonSearchUrl(slug: string): string {
  const params = new URLSearchParams({
    k: slug.replace(/-/g, " "),
    tag: AMAZON_TAG,
  });
  return `https://www.amazon.com/s?${params.toString()}`;
}

/**
 * Build a tagged Amazon product URL that deep-links to the customer reviews
 * section of the listing — used for "Sources & references" so readers can
 * verify ratings directly. Preserves our affiliate tag for compliance.
 */
export function buildAmazonReviewsUrl(
  product: Product,
  position: number,
  slug: string,
): string {
  const params = new URLSearchParams({
    tag: AMAZON_TAG,
    linkCode: "osi",
    th: "1",
    psc: "1",
    language: "en_US",
    ascsubtag: `${SITE_DOMAIN}-${slug}-src-${position}`,
  });
  return `https://www.amazon.com/dp/${product.id}?${params.toString()}#averageCustomerReviewsAnchor`;
}
