/**
 * Autopilot queue — enqueue keywords, worker turns them into review drafts
 * using the Lovable AI Gateway. Drives Bulk Generate at scale.
 */
import { createServerFn } from "@tanstack/react-start";
import { z } from "zod";

async function generateMarkdown(keyword: string, tone: string): Promise<string> {
  const { aiChat } = await import("./ai-provider.server");
  const prompt = `Write an SEO-optimized product roundup review for the keyword: "${keyword}".
Style: ${tone}.
Include markdown sections: TL;DR (top 3), Who this is for, How we picked,
Top 7 products (name, why, pros, cons, who), Comparison table, Buyer's guide, FAQ (5), Final verdict.
Rules: no fake stats, use price tiers ($/$$/$$$), no invented ASINs, US English.`;
  return aiChat(prompt);
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
  if (!dbConfigured()) return { processed: 0, results: [] as Array<{ id: number; ok: boolean; error?: string; draft_id?: number }> };
  await ensureSchema();
  const db = getDb();
  const rs = await db.execute({
    sql: `SELECT id, keyword, tone FROM autopilot_queue
          WHERE state='queued' ORDER BY created_at ASC LIMIT ?`,
    args: [batchSize],
  });
  const results: Array<{ id: number; ok: boolean; error?: string; draft_id?: number }> = [];
  for (const row of rs.rows) {
    const id = Number(row.id);
    const kw = String(row.keyword);
    const tone = row.tone ? String(row.tone) : "trustworthy, concise, buyer-focused";
    await db.execute({
      sql: `UPDATE autopilot_queue SET state='running', attempts=attempts+1 WHERE id=?`,
      args: [id],
    });
    try {
      const markdown = await generateMarkdown(kw, tone);
      const ins = await db.execute({
        sql: `INSERT INTO drafts(keyword, tone, markdown) VALUES(?, ?, ?)`,
        args: [kw, tone, markdown],
      });
      const draftId = Number(ins.lastInsertRowid ?? 0);
      await db.execute({
        sql: `UPDATE autopilot_queue SET state='done', draft_id=?, processed_at=datetime('now'), error=NULL WHERE id=?`,
        args: [draftId, id],
      });
      results.push({ id, ok: true, draft_id: draftId });
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
