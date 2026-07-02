/**
 * Autopilot queue — enqueue keywords, worker publishes each keyword as
 * a live product page at /product/{slug}. The /product/$slug route
 * auto-fetches Amazon listings, auto-categorizes, and renders the full
 * SEO article on demand — no AI model needed.
 */
import { createServerFn } from "@tanstack/react-start";
import { z } from "zod";

function slugify(input: string): string {
  return input
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-|-$/g, "")
    .slice(0, 120);
}

function titleCase(input: string): string {
  return input
    .trim()
    .split(/\s+/)
    .map((w) => (w ? w[0].toUpperCase() + w.slice(1) : w))
    .join(" ");
}


export const enqueueKeywords = createServerFn({ method: "POST" })
  .inputValidator((d: { keywords: string[]; tone?: string }) =>
    z
      .object({
        keywords: z.array(z.string().trim().min(1).max(160)).min(1).max(200),
        tone: z.string().max(200).optional(),
      })
      .parse(d),
  )
  .handler(async ({ data }) => {
    const { isAdmin } = await import("./admin-session.server");
    if (!(await isAdmin())) throw new Error("Unauthorized");
    const { dbConfigured, ensureSchema, getDb } = await import("./db.server");
    if (!dbConfigured()) throw new Error("Database not configured");
    await ensureSchema();
    const db = getDb();
    const tone = data.tone?.trim() || "trustworthy, concise, buyer-focused";
    await db.batch(
      data.keywords.map((kw) => ({
        sql: `INSERT INTO autopilot_queue(keyword, tone, state) VALUES(?, ?, 'queued')`,
        args: [kw, tone],
      })),
      "write",
    );
    return { ok: true as const, enqueued: data.keywords.length };
  });

type QueueItem = {
  id: number;
  keyword: string;
  tone: string | null;
  state: string;
  attempts: number;
  error: string | null;
  draft_id: number | null;
  created_at: string;
  processed_at: string | null;
};

export const listQueue = createServerFn({ method: "GET" }).handler(async () => {
  const { isAdmin } = await import("./admin-session.server");
  if (!(await isAdmin())) throw new Error("Unauthorized");
  const { dbConfigured, ensureSchema, getDb } = await import("./db.server");
  if (!dbConfigured()) return { items: [] as QueueItem[], configured: false as const };
  await ensureSchema();
  const rs = await getDb().execute(
    `SELECT id, keyword, tone, state, attempts, error, draft_id, created_at, processed_at
     FROM autopilot_queue ORDER BY created_at DESC LIMIT 200`,
  );
  return {
    configured: true as const,
    items: rs.rows.map<QueueItem>((r) => ({
      id: Number(r.id),
      keyword: String(r.keyword),
      tone: r.tone == null ? null : String(r.tone),
      state: String(r.state),
      attempts: Number(r.attempts),
      error: r.error == null ? null : String(r.error),
      draft_id: r.draft_id == null ? null : Number(r.draft_id),
      created_at: String(r.created_at),
      processed_at: r.processed_at == null ? null : String(r.processed_at),
    })),
  };
});

export const clearQueue = createServerFn({ method: "POST" })
  .inputValidator((d: { onlyState?: string } | undefined) =>
    z.object({ onlyState: z.enum(["queued", "done", "error", "all"]).default("all") }).parse(d ?? {}),
  )
  .handler(async ({ data }) => {
    const { isAdmin } = await import("./admin-session.server");
    if (!(await isAdmin())) throw new Error("Unauthorized");
    const { dbConfigured, getDb, ensureSchema } = await import("./db.server");
    if (!dbConfigured()) return { ok: false as const };
    await ensureSchema();
    if (data.onlyState === "all") {
      await getDb().execute("DELETE FROM autopilot_queue");
    } else {
      await getDb().execute({
        sql: "DELETE FROM autopilot_queue WHERE state = ?",
        args: [data.onlyState],
      });
    }
    return { ok: true as const };
  });

/**
 * Process up to `batchSize` queued items. Called manually from the admin UI
 * or by an external cron hitting a public API route (below).
 */
export const runQueueOnce = createServerFn({ method: "POST" })
  .inputValidator((d: { batchSize?: number } | undefined) =>
    z.object({ batchSize: z.number().int().min(1).max(20).optional() }).parse(d ?? {}),
  )
  .handler(async ({ data }) => {
    const { isAdmin } = await import("./admin-session.server");
    if (!(await isAdmin())) throw new Error("Unauthorized");
    const { readSettings } = await import("./admin-session.server");
    const settings = await readSettings();
    return runAutopilotBatch(data.batchSize ?? parseInt(settings.autopilotBatchSize || "3", 10));
  });

/** Shared worker — used by both admin trigger and cron route. */
export async function runAutopilotBatch(batchSize: number) {
  const { dbConfigured, ensureSchema, getDb } = await import("./db.server");
  if (!dbConfigured()) return { processed: 0, results: [] as Array<{ id: number; ok: boolean; error?: string; review_id?: number; slug?: string }> };
  await ensureSchema();
  const db = getDb();
  const rs = await db.execute({
    sql: `SELECT id, keyword, tone FROM autopilot_queue
          WHERE state='queued' ORDER BY created_at ASC LIMIT ?`,
    args: [batchSize],
  });
  const results: Array<{ id: number; ok: boolean; error?: string; review_id?: number; slug?: string }> = [];
  for (const row of rs.rows) {
    const id = Number(row.id);
    const kw = String(row.keyword);
    await db.execute({
      sql: `UPDATE autopilot_queue SET state='running', attempts=attempts+1 WHERE id=?`,
      args: [id],
    });
    try {
      const slug = slugify(kw);
      if (!slug) throw new Error("keyword produced empty slug");
      const title = titleCase(kw);
      // Upsert as a published product page. The /product/$slug route
      // auto-generates the article on request — no stored markdown needed.
      const now = new Date().toISOString();
      const existing = await db.execute({
        sql: `SELECT id FROM reviews WHERE slug = ? LIMIT 1`,
        args: [slug],
      });
      let reviewId: number;
      if (existing.rows[0]) {
        reviewId = Number(existing.rows[0].id);
        await db.execute({
          sql: `UPDATE reviews SET keyword=?, title=?, status='published',
                published_at=COALESCE(published_at, ?), updated_at=datetime('now') WHERE id=?`,
          args: [kw, title, now, reviewId],
        });
      } else {
        const ins = await db.execute({
          sql: `INSERT INTO reviews(slug, title, keyword, markdown, status, published_at)
                VALUES(?, ?, ?, '', 'published', ?)`,
          args: [slug, title, kw, now],
        });
        reviewId = Number(ins.lastInsertRowid ?? 0);
      }
      await db.execute({
        sql: `UPDATE autopilot_queue SET state='done', draft_id=?, processed_at=datetime('now'), error=NULL WHERE id=?`,
        args: [reviewId, id],
      });
      results.push({ id, ok: true, review_id: reviewId, slug });
    } catch (e) {
      const msg = e instanceof Error ? e.message : "failed";
      await db.execute({
        sql: `UPDATE autopilot_queue SET state='error', error=?, processed_at=datetime('now') WHERE id=?`,
        args: [msg, id],
      });
      results.push({ id, ok: false, error: msg });
    }
  }
  return { processed: results.length, results };
}

