/**
 * Reviews CRUD — persistent editorial layer stored in Turso. Publishing
 * flips `status` to 'published' and stamps `published_at`. Public route
 * lives at /r/$slug.
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

const CreateSchema = z.object({
  title: z.string().trim().min(3).max(200),
  keyword: z.string().trim().min(1).max(160),
  slug: z.string().trim().min(1).max(120).optional(),
  markdown: z.string().min(1).max(60000),
  metaTitle: z.string().max(200).optional(),
  metaDescription: z.string().max(500).optional(),
  status: z.enum(["draft", "published"]).default("draft"),
});

const UpdateSchema = CreateSchema.partial().extend({ id: z.number().int().positive() });

type ReviewListItem = {
  id: number;
  slug: string;
  title: string;
  keyword: string;
  status: string;
  published_at: string | null;
  updated_at: string;
};

export const listReviews = createServerFn({ method: "GET" })
  .inputValidator((d: { status?: "all" | "draft" | "published" } | undefined) =>
    z.object({ status: z.enum(["all", "draft", "published"]).default("all") }).parse(d ?? {}),
  )
  .handler(async ({ data }) => {
    const { isAdmin } = await import("./admin-session.server");
    if (!(await isAdmin())) throw new Error("Unauthorized");
    const { dbConfigured, ensureSchema, getDb } = await import("./db.server");
    if (!dbConfigured()) return { items: [] as ReviewListItem[], configured: false as const };
    await ensureSchema();
    const rs = await getDb().execute(
      data.status === "all"
        ? "SELECT id, slug, title, keyword, status, published_at, updated_at FROM reviews ORDER BY updated_at DESC LIMIT 500"
        : {
            sql: "SELECT id, slug, title, keyword, status, published_at, updated_at FROM reviews WHERE status = ? ORDER BY updated_at DESC LIMIT 500",
            args: [data.status],
          },
    );
    return {
      configured: true as const,
      items: rs.rows.map<ReviewListItem>((r) => ({
        id: Number(r.id),
        slug: String(r.slug),
        title: String(r.title),
        keyword: String(r.keyword),
        status: String(r.status),
        published_at: r.published_at == null ? null : String(r.published_at),
        updated_at: String(r.updated_at),
      })),
    };
  });

export const getReview = createServerFn({ method: "GET" })
  .inputValidator((d: { id: number }) => z.object({ id: z.number().int().positive() }).parse(d))
  .handler(async ({ data }) => {
    const { isAdmin } = await import("./admin-session.server");
    if (!(await isAdmin())) throw new Error("Unauthorized");
    const { dbConfigured, ensureSchema, getDb } = await import("./db.server");
    if (!dbConfigured()) throw new Error("Database not configured");
    await ensureSchema();
    const rs = await getDb().execute({ sql: "SELECT * FROM reviews WHERE id = ?", args: [data.id] });
    const r = rs.rows[0];
    if (!r) throw new Error("Not found");
    return {
      id: Number(r.id),
      slug: String(r.slug),
      title: String(r.title),
      keyword: String(r.keyword),
      markdown: String(r.markdown),
      metaTitle: r.meta_title == null ? "" : String(r.meta_title),
      metaDescription: r.meta_description == null ? "" : String(r.meta_description),
      status: String(r.status),
      published_at: r.published_at == null ? null : String(r.published_at),
      created_at: String(r.created_at),
      updated_at: String(r.updated_at),
    };
  });

export const createReview = createServerFn({ method: "POST" })
  .inputValidator((d: unknown) => CreateSchema.parse(d))
  .handler(async ({ data }) => {
    const { isAdmin } = await import("./admin-session.server");
    if (!(await isAdmin())) throw new Error("Unauthorized");
    const { dbConfigured, ensureSchema, getDb } = await import("./db.server");
    if (!dbConfigured()) throw new Error("Database not configured");
    await ensureSchema();
    const slug = slugify(data.slug || data.title);
    const publishedAt = data.status === "published" ? new Date().toISOString() : null;
    const ins = await getDb().execute({
      sql: `INSERT INTO reviews(slug, title, keyword, markdown, meta_title, meta_description, status, published_at)
            VALUES(?, ?, ?, ?, ?, ?, ?, ?)`,
      args: [
        slug,
        data.title,
        data.keyword,
        data.markdown,
        data.metaTitle ?? null,
        data.metaDescription ?? null,
        data.status,
        publishedAt,
      ],
    });
    return { id: Number(ins.lastInsertRowid ?? 0), slug };
  });

export const updateReview = createServerFn({ method: "POST" })
  .inputValidator((d: unknown) => UpdateSchema.parse(d))
  .handler(async ({ data }) => {
    const { isAdmin } = await import("./admin-session.server");
    if (!(await isAdmin())) throw new Error("Unauthorized");
    const { dbConfigured, ensureSchema, getDb } = await import("./db.server");
    if (!dbConfigured()) throw new Error("Database not configured");
    await ensureSchema();
    const cur = await getDb().execute({ sql: "SELECT * FROM reviews WHERE id = ?", args: [data.id] });
    const row = cur.rows[0];
    if (!row) throw new Error("Not found");
    const next = {
      slug: data.slug ? slugify(data.slug) : String(row.slug),
      title: data.title ?? String(row.title),
      keyword: data.keyword ?? String(row.keyword),
      markdown: data.markdown ?? String(row.markdown),
      metaTitle: data.metaTitle ?? (row.meta_title == null ? null : String(row.meta_title)),
      metaDescription:
        data.metaDescription ?? (row.meta_description == null ? null : String(row.meta_description)),
      status: data.status ?? String(row.status),
    };
    const wasPublished = String(row.status) === "published";
    const publishedAt =
      next.status === "published"
        ? wasPublished
          ? (row.published_at as string | null)
          : new Date().toISOString()
        : null;
    await getDb().execute({
      sql: `UPDATE reviews SET slug=?, title=?, keyword=?, markdown=?, meta_title=?, meta_description=?,
            status=?, published_at=?, updated_at=datetime('now') WHERE id=?`,
      args: [
        next.slug,
        next.title,
        next.keyword,
        next.markdown,
        next.metaTitle,
        next.metaDescription,
        next.status,
        publishedAt,
        data.id,
      ],
    });
    return { ok: true as const, slug: next.slug };
  });

export const deleteReview = createServerFn({ method: "POST" })
  .inputValidator((d: { id: number }) => z.object({ id: z.number().int().positive() }).parse(d))
  .handler(async ({ data }) => {
    const { isAdmin } = await import("./admin-session.server");
    if (!(await isAdmin())) throw new Error("Unauthorized");
    const { dbConfigured, getDb, ensureSchema } = await import("./db.server");
    if (!dbConfigured()) return { ok: false as const };
    await ensureSchema();
    await getDb().execute({ sql: "DELETE FROM reviews WHERE id = ?", args: [data.id] });
    return { ok: true as const };
  });

/** Public read: fetch a published review by slug. Used by /r/$slug route. */
export const getPublishedReview = createServerFn({ method: "GET" })
  .inputValidator((d: { slug: string }) => z.object({ slug: z.string().trim().min(1).max(160) }).parse(d))
  .handler(async ({ data }) => {
    const { dbConfigured, ensureSchema, getDb } = await import("./db.server");
    if (!dbConfigured()) return null;
    await ensureSchema();
    const rs = await getDb().execute({
      sql: `SELECT slug, title, keyword, markdown, meta_title, meta_description, published_at
            FROM reviews WHERE slug = ? AND status = 'published' LIMIT 1`,
      args: [data.slug],
    });
    const r = rs.rows[0];
    if (!r) return null;
    return {
      slug: String(r.slug),
      title: String(r.title),
      keyword: String(r.keyword),
      markdown: String(r.markdown),
      metaTitle: r.meta_title == null ? "" : String(r.meta_title),
      metaDescription: r.meta_description == null ? "" : String(r.meta_description),
      publishedAt: r.published_at == null ? null : String(r.published_at),
    };
  });
