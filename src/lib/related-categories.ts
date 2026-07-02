/**
 * Context-aware related-category resolver for the header strip.
 *
 * Goal: when the user is on /product/{slug}, show sibling categories
 * that match their search intent (e.g. on /product/coffee-bean-review
 * surface "Espresso Machines", "Stand Mixers"… from Home & Kitchen).
 *
 * Strategy (in order):
 *   1. Exact slug match -> return up to 6 siblings from same department.
 *   2. Token / keyword overlap (e.g. "coffee", "gaming", "smart") ->
 *      pull sub-categories from any department that share a token,
 *      prefer matches inside the same department first.
 *   3. Fallback to POPULAR_CATEGORIES so the strip is never empty.
 */
import { DEPARTMENTS, POPULAR_CATEGORIES } from "@/config/site";

export type RelatedCategory = { slug: string; label: string; emoji: string };

// Tokens that are too generic to drive a useful intent match.
const STOPWORDS = new Set([
  "the", "and", "for", "with", "best", "review", "reviews",
  "top", "of", "to", "a", "an", "in", "on", "pro", "max", "mini",
]);

function tokenize(slug: string): string[] {
  return slug
    .toLowerCase()
    .split(/[-_\s]+/)
    .filter((t) => t.length > 2 && !STOPWORDS.has(t));
}

export function relatedCategories(currentSlug: string | undefined, limit = 6): RelatedCategory[] {
  const slug = (currentSlug ?? "").toLowerCase();

  // 1. Exact match -> siblings from same department.
  if (slug) {
    for (const dept of DEPARTMENTS) {
      if (dept.children.some((c) => c.slug === slug)) {
        return dept.children.filter((c) => c.slug !== slug).slice(0, limit);
      }
    }
  }

  // 2. Token overlap across all departments.
  const tokens = tokenize(slug);
  if (tokens.length) {
    const scored: { cat: RelatedCategory; score: number }[] = [];
    for (const dept of DEPARTMENTS) {
      const deptTokens = new Set([...tokenize(dept.slug), ...tokenize(dept.label)]);
      const deptHit = tokens.some((t) => deptTokens.has(t));
      for (const child of dept.children) {
        if (child.slug === slug) continue;
        const childTokens = new Set([...tokenize(child.slug), ...tokenize(child.label)]);
        let score = 0;
        for (const t of tokens) if (childTokens.has(t)) score += 3;
        if (deptHit) score += 1;
        if (score > 0) scored.push({ cat: child, score });
      }
    }
    if (scored.length) {
      scored.sort((a, b) => b.score - a.score);
      const seen = new Set<string>();
      const out: RelatedCategory[] = [];
      for (const { cat } of scored) {
        if (seen.has(cat.slug)) continue;
        seen.add(cat.slug);
        out.push(cat);
        if (out.length >= limit) break;
      }
      if (out.length) return out;
    }
  }

  // 3. Fallback — popular categories (excluding current).
  return POPULAR_CATEGORIES.filter((c) => c.slug !== slug).slice(0, limit);
}
