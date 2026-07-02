import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

export function formatPrice(price: string | number | undefined | null): string {
  if (price === undefined || price === null || price === "") return "";
  const str = typeof price === "number" ? String(price) : price;
  const cleaned = str.replace(/[^\d.,-]/g, "").replace(",", ".");
  const num = parseFloat(cleaned);
  if (isNaN(num)) return typeof price === "string" ? price : "";
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
    maximumFractionDigits: 2,
  }).format(num);
}

export function parsePrice(price: string | number | undefined): number {
  if (typeof price === "number") return price;
  if (!price) return 0;
  return parseFloat(price.replace(/[^\d.,-]/g, "").replace(",", ".")) || 0;
}

/**
 * Stop-words trimmed from queries / slugs so titles and API lookups stay clean.
 * "Best coffee grinder" → "coffee-grinder" (avoids "Best Best …" in titles);
 * "coffee grinder reviews 2026" → "coffee-grinder".
 */
const STOP_PREFIX = new Set([
  "best", "top", "the", "a", "an", "good", "greatest", "ultimate",
  "cheap", "cheapest", "affordable", "review", "reviews",
  // interrogatives / lead-in phrasing — "what is the best golf ball" → "golf ball"
  "what", "whats", "which", "how", "why", "when", "where", "who",
  "is", "are", "was", "were", "do", "does", "should", "can", "would",
  "im", "i", "me", "looking", "find", "show", "tell",
]);
const STOP_SUFFIX = new Set([
  "review", "reviews", "rated", "ranked", "guide", "buying", "list", "picks",
]);
/** Connective words ignored when scoring product relevance to a query. */
const STOP_FILLER = new Set([
  "for", "with", "and", "or", "of", "to", "in", "on", "at", "by", "from",
  "my", "your", "a", "an", "the", "is", "are", "be", "as", "it", "that",
  "this", "these", "those", "what", "which", "how", "why", "do", "does",
  "should", "can", "would", "i", "im", "me",
]);

export function normalizeSlug(slug: string): string {
  const tokens = slug.toLowerCase().split(/[-_/\s?!.]+/).filter(Boolean);
  let arr = tokens.filter((t) => !/^\d{4}$/.test(t));
  // strip leading question/filler words (multiple passes for stacked stops)
  let changed = true;
  while (changed && arr.length > 1) {
    changed = false;
    if (STOP_PREFIX.has(arr[0]) || STOP_FILLER.has(arr[0])) {
      arr.shift();
      changed = true;
    }
  }
  // strip trailing suffixes & fillers
  changed = true;
  while (changed && arr.length > 1) {
    changed = false;
    const last = arr[arr.length - 1];
    if (STOP_SUFFIX.has(last) || STOP_FILLER.has(last)) {
      arr.pop();
      changed = true;
    }
  }
  return (arr.length ? arr : tokens).join("-");
}

/**
 * Meaningful tokens from a slug/query used to score product relevance.
 * Drops stop-words, fillers, years, and very short tokens. Singular/plural
 * forms collapse to the same stem so "shoe" matches "shoes".
 */
export function significantTokens(slug: string): string[] {
  const base = normalizeSlug(slug)
    .split(/[-_/\s]+/)
    .filter(Boolean)
    .filter((t) => t.length >= 3)
    .filter((t) => !STOP_FILLER.has(t) && !STOP_PREFIX.has(t) && !STOP_SUFFIX.has(t));
  return Array.from(new Set(base.map(stem)));
}

/** Tiny stemmer — enough to match plurals (shoes→shoe, watches→watch). */
function stem(w: string): string {
  if (w.length > 4 && w.endsWith("ies")) return w.slice(0, -3) + "y";
  if (w.length > 4 && w.endsWith("es")) return w.slice(0, -2);
  if (w.length > 3 && w.endsWith("s") && !w.endsWith("ss")) return w.slice(0, -1);
  return w;
}

/**
 * Count how many significant query tokens appear in the haystack text.
 * Matches whole words against the stemmed form, so "men" ↔ "men's", and
 * "shoe" ↔ "shoes".
 */
export function relevanceScore(tokens: string[], haystack: string): number {
  if (!tokens.length) return 0;
  const hay = haystack.toLowerCase();
  let hits = 0;
  for (const t of tokens) {
    const re = new RegExp(`\\b${t.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}[a-z]{0,3}\\b`, "i");
    if (re.test(hay)) hits++;
  }
  return hits;
}

export function slugToTitle(slug: string): string {
  return normalizeSlug(slug)
    .split("-")
    .filter(Boolean)
    .map((w) => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase())
    .join(" ")
    .replace(/\bAnd\b/g, "and")
    .replace(/\bFor\b/g, "for")
    .replace(/\bWith\b/g, "with")
    .replace(/\bOf\b/g, "of")
    .replace(/\bThe\b/g, "the");
}

export function titleToSlug(title: string): string {
  const raw = title
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9\s-]/g, "")
    .replace(/\s+/g, "-")
    .replace(/-+/g, "-");
  return normalizeSlug(raw);
}

/** Deterministic "picked by N shoppers" estimate — stable per slug per day. */
export function estimatedPickCount(seed: string): number {
  let h = 0;
  for (let i = 0; i < seed.length; i++) h = (h * 31 + seed.charCodeAt(i)) | 0;
  const day = Math.floor(Date.now() / 86_400_000);
  const x = Math.abs(h ^ day);
  return 180 + (x % 320);
}

export function getCurrentYear(): number {
  return new Date().getFullYear();
}

export function getCurrentMonth(): string {
  return new Date().toLocaleString("en-US", { month: "long" });
}

export function formatRelativeDate(iso?: string | undefined): string {
  // Display the actual data timestamp (e.g. scrape time) so shoppers see
  // when prices/availability were last verified. Falls back to today only
  // when no timestamp is provided.
  const d = iso ? new Date(iso) : new Date();
  const date = isNaN(d.getTime()) ? new Date() : d;
  return date.toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
    timeZone: "UTC",
  });
}


export function readingTimeMinutes(productCount: number): number {
  // ~30s scan per card + ~1min comparison/intro/FAQ.
  return Math.max(2, Math.round((productCount * 30 + 60) / 60));
}
