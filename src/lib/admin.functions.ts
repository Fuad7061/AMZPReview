import { createServerFn } from "@tanstack/react-start";
import { z } from "zod";

/** Login: verifies credentials against ADMIN_USER / ADMIN_PASS env vars. */
export const adminLogin = createServerFn({ method: "POST" })
  .inputValidator((d: { user: string; pass: string }) =>
    z.object({ user: z.string().min(1).max(200), pass: z.string().min(1).max(400) }).parse(d),
  )
  .handler(async ({ data }) => {
    const { getAdminSession, safeEqual } = await import("./admin-session.server");
    const expectedUser = process.env.ADMIN_USER ?? "admin";
    const expectedPass = process.env.ADMIN_PASS ?? "sk-default-key";
    if (!safeEqual(data.user, expectedUser) || !safeEqual(data.pass, expectedPass)) {
      return { ok: false as const };
    }
    const s = await getAdminSession();
    await s.update({ authed: true, user: data.user });
    return { ok: true as const };
  });

export const adminLogout = createServerFn({ method: "POST" }).handler(async () => {
  const { getAdminSession } = await import("./admin-session.server");
  const s = await getAdminSession();
  await s.clear();
  return { ok: true as const };
});

export const adminMe = createServerFn({ method: "GET" }).handler(async () => {
  const { getAdminSession } = await import("./admin-session.server");
  const s = await getAdminSession();
  return { authed: Boolean(s.data.authed), user: s.data.user ?? null };
});

export const getSiteSettings = createServerFn({ method: "GET" }).handler(async () => {
  const { readSettings } = await import("./admin-session.server");
  return readSettings();
});

/** Public-safe subset of settings — used by public routes for SEO metadata. */
export const getPublicSiteContext = createServerFn({ method: "GET" }).handler(async () => {
  const { readSettings } = await import("./admin-session.server");
  const s = await readSettings();
  return {
    siteUrl: s.siteUrl,
    siteName: s.metaTitle || "YAD Foods",
    metaDescription: s.metaDescription,
  };
});

export const updateSiteSettings = createServerFn({ method: "POST" })
  .inputValidator((d: Record<string, unknown>) =>
    z
      .object({
        headCode: z.string().max(20000).optional(),
        gaId: z.string().max(200).optional(),
        metaTitle: z.string().max(200).optional(),
        metaDescription: z.string().max(500).optional(),
        siteUrl: z.string().max(300).optional(),
        lambdaUrl: z.string().max(500).optional(),
        amazonTag: z.string().max(100).optional(),
        paapiAccessKey: z.string().max(200).optional(),
        paapiSecretKey: z.string().max(200).optional(),
        paapiPartnerTag: z.string().max(100).optional(),
        aiBaseUrl: z.string().max(500).optional(),
        aiApiKey: z.string().max(500).optional(),
        aiModel: z.string().max(200).optional(),
        aiSystemPrompt: z.string().max(4000).optional(),
        autopilotEnabled: z.string().max(2).optional(),
        autopilotBatchSize: z.string().max(4).optional(),
        autopilotIntervalMin: z.string().max(6).optional(),
        cronSecret: z.string().max(200).optional(),
        priceCacheTtlMin: z.string().max(6).optional(),
      })
      .parse(d),
  )
  .handler(async ({ data }) => {
    const { isAdmin, writeSettings } = await import("./admin-session.server");
    if (!(await isAdmin())) throw new Error("Unauthorized");
    return writeSettings(data);
  });

/** DB connection status — for admin dashboard. */
export const getDbStatus = createServerFn({ method: "GET" }).handler(async () => {
  const { isAdmin } = await import("./admin-session.server");
  if (!(await isAdmin())) throw new Error("Unauthorized");
  const { dbStatus } = await import("./db.server");
  return dbStatus();
});

/** Test the configured OpenAI-compatible AI provider. */
export const testAiProvider = createServerFn({ method: "POST" }).handler(async () => {
  const { isAdmin } = await import("./admin-session.server");
  if (!(await isAdmin())) throw new Error("Unauthorized");
  const { testAiConfig } = await import("./ai-provider.server");
  return testAiConfig();
});

/**
 * Bulk keyword → AI-generated review drafts via Lovable AI Gateway.
 * Persists each draft to Turso when configured.
 */
export const generateReviews = createServerFn({ method: "POST" })
  .inputValidator((d: { keywords: string[]; tone?: string }) =>
    z
      .object({
        keywords: z.array(z.string().trim().min(1).max(120)).min(1).max(25),
        tone: z.string().max(200).optional(),
      })
      .parse(d),
  )
  .handler(async ({ data }) => {
    const { isAdmin } = await import("./admin-session.server");
    if (!(await isAdmin())) throw new Error("Unauthorized");
    const { aiChat } = await import("./ai-provider.server");

    const tone = data.tone?.trim() || "trustworthy, concise, buyer-focused";
    const results: Array<{ keyword: string; markdown: string; error?: string; id?: number }> = [];

    let dbReady = false;
    try {
      if (process.env.TURSO_DATABASE_URL) {
        const { ensureSchema } = await import("./db.server");
        await ensureSchema();
        dbReady = true;
      }
    } catch {
      dbReady = false;
    }

    for (const keyword of data.keywords) {
      try {
        const prompt = `Write an SEO-optimized product roundup review for the keyword: "${keyword}".
Style: ${tone}.
Include these sections in markdown:
# Best ${keyword} in 2026
## TL;DR — Our Top 3 Picks
(bullet list: Top Pick, Best Value, Budget — each 1 sentence)
## Who this guide is for
## How we picked
## Top 7 products (numbered, each with: name, 2-sentence why, pros (3), cons (2), who it's for)
## Comparison at a glance (markdown table: Product | Best for | Price range | Rating)
## Buyer's guide (key factors, 4-5 subheadings)
## FAQ (5 questions with concise answers)
## Final verdict
Rules: no fake stats, use price tiers ($/$$/$$$), no invented ASINs, keep US English.`;

        const markdown = await aiChat(prompt);
        let id: number | undefined;
        if (dbReady && markdown) {
          try {
            const { getDb } = await import("./db.server");
            const ins = await getDb().execute({
              sql: "INSERT INTO drafts(keyword, tone, markdown) VALUES(?, ?, ?)",
              args: [keyword, tone, markdown],
            });
            id = Number(ins.lastInsertRowid ?? 0) || undefined;
          } catch {
            /* non-fatal */
          }
        }
        results.push({ keyword, markdown, id });
      } catch (e) {
        results.push({
          keyword,
          markdown: "",
          error: e instanceof Error ? e.message : "generation failed",
        });
      }
    }
    return { results, generatedAt: new Date().toISOString(), persisted: dbReady };
  });

/** List saved drafts (newest first). */
export const listDrafts = createServerFn({ method: "GET" }).handler(async () => {
  const { isAdmin } = await import("./admin-session.server");
  if (!(await isAdmin())) throw new Error("Unauthorized");
  if (!process.env.TURSO_DATABASE_URL) return { drafts: [] as Array<{ id: number; keyword: string; tone: string | null; markdown: string; created_at: string }> };
  const { ensureSchema, getDb } = await import("./db.server");
  await ensureSchema();
  const rs = await getDb().execute(
    "SELECT id, keyword, tone, markdown, created_at FROM drafts ORDER BY created_at DESC LIMIT 200",
  );
  return {
    drafts: rs.rows.map((r) => ({
      id: Number(r.id),
      keyword: String(r.keyword),
      tone: r.tone == null ? null : String(r.tone),
      markdown: String(r.markdown),
      created_at: String(r.created_at),
    })),
  };
});

export const deleteDraft = createServerFn({ method: "POST" })
  .inputValidator((d: { id: number }) => z.object({ id: z.number().int().positive() }).parse(d))
  .handler(async ({ data }) => {
    const { isAdmin } = await import("./admin-session.server");
    if (!(await isAdmin())) throw new Error("Unauthorized");
    if (!process.env.TURSO_DATABASE_URL) return { ok: false as const };
    const { getDb } = await import("./db.server");
    await getDb().execute({ sql: "DELETE FROM drafts WHERE id = ?", args: [data.id] });
    return { ok: true as const };
  });
