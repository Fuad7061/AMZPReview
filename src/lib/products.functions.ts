/**
 * Live product-data server functions. Always hits the configured Lambda
 * endpoint via cURL/fetch — no image storage, no static data. Short-lived
 * price cache (TTL configurable in admin settings) so autopilot / analytics
 * pages don't hammer upstream.
 */
import { createServerFn } from "@tanstack/react-start";
import { z } from "zod";

type LiveProduct = {
  asin: string;
  title: string;
  price: number | null;
  currency: string | null;
  rating: number | null;
  reviews: number | null;
  image_url: string | null;
  url: string;
  availability: string | null;
  source: "lambda" | "paapi" | "cache";
};

async function callLambda(
  base: string,
  keyword: string,
  count: number,
  tag: string,
): Promise<LiveProduct[]> {
  const u = new URL(base);
  u.searchParams.set("q", keyword);
  u.searchParams.set("tag", tag);
  u.searchParams.set("extendedData", "true");
  const res = await fetch(u.toString(), { headers: { Accept: "application/json" } });
  if (!res.ok) throw new Error(`Lambda ${res.status}: ${(await res.text()).slice(0, 200)}`);
  const json = (await res.json()) as {
    products?: Array<Record<string, unknown>>;
  };
  return (json.products ?? []).slice(0, count).map((p) => ({
    asin: String(p.id ?? ""),
    title: String(p.title ?? ""),
    price: typeof p.price_sort === "number" ? p.price_sort : null,
    currency: "USD",
    rating: typeof p.rating === "number" ? p.rating : null,
    reviews: typeof p.reviews_count === "number" ? p.reviews_count : null,
    image_url: typeof p.image_url === "string" ? p.image_url : null,
    url: typeof p.url === "string" ? p.url : "",
    availability: typeof p.availability === "string" ? p.availability : null,
    source: "lambda" as const,
  }));
}

/**
 * Search live products from the configured backend. Public — used by the
 * regular product pages too, so no admin check.
 */
export const liveSearch = createServerFn({ method: "GET" })
  .inputValidator((d: { keyword: string; count?: number }) =>
    z
      .object({
        keyword: z.string().trim().min(1).max(160),
        count: z.number().int().min(1).max(20).default(7),
      })
      .parse(d),
  )
  .handler(async ({ data }) => {
    const { readSettings } = await import("./admin-session.server");
    const settings = await readSettings();
    const base = settings.lambdaUrl?.trim() ||
      "https://4pobkr5oa4olwuvhx625uiozay0rrcuu.lambda-url.us-east-1.on.aws/";
    const tag = settings.amazonTag?.trim() || "YOUR-TAG-20";
    try {
      const products = await callLambda(base, data.keyword, data.count, tag);
      return { ok: true as const, products, source: "lambda" as const };
    } catch (e) {
      return {
        ok: false as const,
        products: [] as LiveProduct[],
        error: e instanceof Error ? e.message : "lambda failed",
      };
    }
  });

/**
 * Refresh price / rating / availability for a single ASIN. Uses cache when
 * fresh; on miss calls upstream by searching the ASIN. Result stored in
 * price_cache so subsequent reads are cheap.
 */
export const refreshPrice = createServerFn({ method: "POST" })
  .inputValidator((d: { asin: string; force?: boolean }) =>
    z
      .object({ asin: z.string().trim().min(1).max(40), force: z.boolean().default(false) })
      .parse(d),
  )
  .handler(async ({ data }) => {
    const { isAdmin } = await import("./admin-session.server");
    if (!(await isAdmin())) throw new Error("Unauthorized");
    const { readSettings } = await import("./admin-session.server");
    const { dbConfigured, ensureSchema, getDb } = await import("./db.server");
    const settings = await readSettings();
    const ttlMin = Math.max(1, parseInt(settings.priceCacheTtlMin || "60", 10));

    if (dbConfigured() && !data.force) {
      await ensureSchema();
      const rs = await getDb().execute({
        sql: `SELECT asin, price, currency, rating, reviews_count, availability, fetched_at
              FROM price_cache
              WHERE asin = ? AND fetched_at >= datetime('now', ?)`,
        args: [data.asin, `-${ttlMin} minutes`],
      });
      if (rs.rows[0]) {
        const r = rs.rows[0];
        return {
          asin: String(r.asin),
          price: r.price == null ? null : Number(r.price),
          currency: r.currency == null ? null : String(r.currency),
          rating: r.rating == null ? null : Number(r.rating),
          reviews: r.reviews_count == null ? null : Number(r.reviews_count),
          availability: r.availability == null ? null : String(r.availability),
          fetched_at: String(r.fetched_at),
          source: "cache" as const,
        };
      }
    }

    const base = settings.lambdaUrl?.trim() ||
      "https://4pobkr5oa4olwuvhx625uiozay0rrcuu.lambda-url.us-east-1.on.aws/";
    const tag = settings.amazonTag?.trim() || "YOUR-TAG-20";
    const products = await callLambda(base, data.asin, 5, tag);
    const found = products.find((p) => p.asin.toUpperCase() === data.asin.toUpperCase()) ?? products[0];
    if (!found) throw new Error("ASIN not found upstream");

    if (dbConfigured()) {
      await ensureSchema();
      await getDb().execute({
        sql: `INSERT INTO price_cache(asin, price, currency, rating, reviews_count, availability, fetched_at)
              VALUES(?, ?, ?, ?, ?, ?, datetime('now'))
              ON CONFLICT(asin) DO UPDATE SET
                price=excluded.price, currency=excluded.currency, rating=excluded.rating,
                reviews_count=excluded.reviews_count, availability=excluded.availability,
                fetched_at=excluded.fetched_at`,
        args: [
          found.asin,
          found.price,
          found.currency,
          found.rating,
          found.reviews,
          found.availability,
        ],
      });
    }
    return {
      asin: found.asin,
      price: found.price,
      currency: found.currency,
      rating: found.rating,
      reviews: found.reviews,
      availability: found.availability,
      fetched_at: new Date().toISOString(),
      source: "lambda" as const,
    };
  });

/** Admin: quick health check against the configured Lambda. */
export const testLambda = createServerFn({ method: "POST" })
  .inputValidator((d: { keyword?: string } | undefined) =>
    z.object({ keyword: z.string().trim().min(1).max(160).default("wireless earbuds") }).parse(d ?? {}),
  )
  .handler(async ({ data }) => {
    const { isAdmin, readSettings } = await import("./admin-session.server");
    if (!(await isAdmin())) throw new Error("Unauthorized");
    const settings = await readSettings();
    const base = settings.lambdaUrl?.trim() ||
      "https://4pobkr5oa4olwuvhx625uiozay0rrcuu.lambda-url.us-east-1.on.aws/";
    const started = Date.now();
    try {
      const products = await callLambda(base, data.keyword, 3, settings.amazonTag || "YOUR-TAG-20");
      return {
        ok: true as const,
        latency_ms: Date.now() - started,
        count: products.length,
        sample: products.slice(0, 3).map((p) => ({ asin: p.asin, title: p.title.slice(0, 80) })),
      };
    } catch (e) {
      return {
        ok: false as const,
        latency_ms: Date.now() - started,
        error: e instanceof Error ? e.message : "failed",
      };
    }
  });
