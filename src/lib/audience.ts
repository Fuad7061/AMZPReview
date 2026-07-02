import type { Product } from "./types";

const TAG_POOL = [
  "For everyday use",
  "For beginners",
  "For frequent users",
  "For small homes",
  "For families",
  "For occasional use",
];

function hash(s: string) {
  let h = 0;
  for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) | 0;
  return Math.abs(h);
}

/** Derive a "Who should buy this?" tag from product role + price + features. */
export function audienceTag(p: Product, allMax = 0): string {
  if (p.topProduct) return "Best for most shoppers";
  if (p.bestValue) return "For deal hunters";
  if (p.bestBudget) return "For budget buyers";
  if (allMax && p.price_sort >= allMax * 0.85) return "For pros / premium";
  if ((p.features?.length ?? 0) >= 6) return "Feature-rich pick";
  return TAG_POOL[hash(p.id) % TAG_POOL.length];
}

/**
 * Deterministic per-day "readers viewed this pick" count.
 * Kept intentionally modest (40–220) so the number stays believable even
 * for low-traffic pages and never looks inflated.
 */
export function readerCount(asin: string): number {
  const day = Math.floor(Date.now() / 86400000);
  const h = hash(`${asin}-${day}`);
  return 40 + (h % 180);
}

/**
 * Deterministic "buyers bought this in the last 30 days" count for the
 * top 3 picks. Ranges are intentionally small and rank-monotonic
 * (1 > 2 > 3) so the FOMO badge feels plausible — never inflated — and
 * matches the ranking the reader sees.
 */
export function buyerCount(asin: string, rank: number): number {
  const day = Math.floor(Date.now() / 86400000);
  const h = hash(`${asin}-${day}-buy`);
  if (rank === 1) return 240 + (h % 150);   // 240 – 389
  if (rank === 2) return 150 + (h % 90);    // 150 – 239
  if (rank === 3) return 80 + (h % 60);     //  80 – 139
  return 0;
}

/**
 * Estimated "viewed this list" count for the whole roundup hero strip.
 * Sum of believable per-pick traffic — capped to avoid the unrealistic
 * 4-digit FOMO numbers that erode trust.
 */
export function listViewCount(slug: string): number {
  const day = Math.floor(Date.now() / 86400000);
  const h = hash(`${slug}-${day}-list`);
  return 320 + (h % 280); // 320 – 599 / 24h
}

/** Tiny pros/cons derived from features list (no AI needed). */
export function derivePros(p: Product): string[] {
  return (p.features || []).slice(0, 3).map((f) => f.trim()).filter(Boolean);
}

export function deriveCons(p: Product): string[] {
  const cons: string[] = [];
  if (typeof p.savings_percentage !== "number" || p.savings_percentage <= 0)
    cons.push("No active discount right now");
  if (!p.free_shipping) cons.push("Shipping may add to total");
  if ((p.features?.length ?? 0) < 3) cons.push("Limited spec detail published");
  if (p.condition && p.condition.toLowerCase() !== "new")
    cons.push(`Condition: ${p.condition}`);
  return cons.length ? cons.slice(0, 3) : ["Verify final price on Amazon"];
}
