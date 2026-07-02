/**
 * Admin analytics — read-only aggregates over the clicks table.
 */
import { createServerFn } from "@tanstack/react-start";
import { z } from "zod";

export const getClickAnalytics = createServerFn({ method: "GET" })
  .inputValidator((d: { days?: number } | undefined) =>
    z.object({ days: z.number().int().min(1).max(365).default(30) }).parse(d ?? {}),
  )
  .handler(async ({ data }) => {
    const { isAdmin } = await import("./admin-session.server");
    if (!(await isAdmin())) throw new Error("Unauthorized");
    const { dbConfigured, ensureSchema, getDb } = await import("./db.server");
    if (!dbConfigured()) {
      return {
        configured: false as const,
        total: 0,
        byDay: [] as Array<{ day: string; n: number }>,
        topAsins: [] as Array<{ asin: string; n: number }>,
        topSlugs: [] as Array<{ slug: string; n: number }>,
        recent: [] as Array<{ asin: string; slug: string; position: number; created_at: string }>,
      };
    }
    await ensureSchema();
    const db = getDb();
    const since = `datetime('now', '-${data.days} days')`;
    const [total, byDay, topAsins, topSlugs, recent] = await Promise.all([
      db.execute(`SELECT COUNT(*) AS n FROM clicks WHERE created_at >= ${since}`),
      db.execute(
        `SELECT date(created_at) AS day, COUNT(*) AS n FROM clicks
         WHERE created_at >= ${since} GROUP BY day ORDER BY day DESC LIMIT 60`,
      ),
      db.execute(
        `SELECT asin, COUNT(*) AS n FROM clicks WHERE created_at >= ${since}
         GROUP BY asin ORDER BY n DESC LIMIT 20`,
      ),
      db.execute(
        `SELECT slug, COUNT(*) AS n FROM clicks WHERE created_at >= ${since}
         GROUP BY slug ORDER BY n DESC LIMIT 20`,
      ),
      db.execute(
        `SELECT asin, slug, position, created_at FROM clicks
         ORDER BY created_at DESC LIMIT 50`,
      ),
    ]);
    return {
      configured: true as const,
      total: Number(total.rows[0]?.n ?? 0),
      byDay: byDay.rows.map((r) => ({ day: String(r.day), n: Number(r.n) })),
      topAsins: topAsins.rows.map((r) => ({ asin: String(r.asin), n: Number(r.n) })),
      topSlugs: topSlugs.rows.map((r) => ({ slug: String(r.slug), n: Number(r.n) })),
      recent: recent.rows.map((r) => ({
        asin: String(r.asin),
        slug: String(r.slug),
        position: Number(r.position),
        created_at: String(r.created_at),
      })),
    };
  });
