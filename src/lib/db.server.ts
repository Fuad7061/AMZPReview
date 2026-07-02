/**
 * Turso (libSQL) client + auto-migrating schema.
 * Server-only. Import lazily inside server-fn handlers.
 */
// Use the web client — HTTP-based, works in every serverless runtime
// (Netlify Functions, Cloudflare Workers, Node, edge). Avoids native
// binding issues that the default `@libsql/client` entry can hit.
import { createClient, type Client } from "@libsql/client/web";

let _client: Client | null = null;
let _initPromise: Promise<void> | null = null;

function cleanEnv(v: string | undefined): string | undefined {
  if (!v) return undefined;
  // Strip ALL whitespace/newlines and surrounding quotes — common copy-paste
  // artifacts (Netlify UI can inject trailing \n, quotes, or CR) that make
  // Turso return HTTP 400 / 401. Tokens are base64url so no legit whitespace.
  let s = v.replace(/[\r\n\t]/g, "").trim();
  // Peel matching wrapping quotes (single or double), possibly repeated.
  while (
    (s.startsWith('"') && s.endsWith('"')) ||
    (s.startsWith("'") && s.endsWith("'"))
  ) {
    s = s.slice(1, -1).trim();
  }
  return s;
}

function normalizeUrl(raw: string): string {
  // The web client speaks HTTPS. `libsql://` and `wss://` both map to https.
  let u = raw;
  if (u.startsWith("libsql://")) u = "https://" + u.slice("libsql://".length);
  else if (u.startsWith("wss://")) u = "https://" + u.slice("wss://".length);
  else if (u.startsWith("ws://")) u = "http://" + u.slice("ws://".length);
  return u.replace(/\/+$/, "");
}

export function getDb(): Client {
  if (_client) return _client;
  const url = cleanEnv(process.env.TURSO_DATABASE_URL);
  const authToken = cleanEnv(process.env.TURSO_AUTH_TOKEN);
  if (!url) throw new Error("TURSO_DATABASE_URL not configured");
  _client = createClient({ url: normalizeUrl(url), authToken });
  return _client;
}

export function dbConfigured(): boolean {
  return Boolean(cleanEnv(process.env.TURSO_DATABASE_URL));
}

export async function ensureSchema(): Promise<void> {
  if (_initPromise) return _initPromise;
  _initPromise = (async () => {
    const db = getDb();
    await db.batch(
      [
        `CREATE TABLE IF NOT EXISTS settings (
          key TEXT PRIMARY KEY,
          value TEXT NOT NULL,
          updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        )`,
        `CREATE TABLE IF NOT EXISTS drafts (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          keyword TEXT NOT NULL,
          tone TEXT,
          markdown TEXT NOT NULL,
          created_at TEXT NOT NULL DEFAULT (datetime('now'))
        )`,
        `CREATE INDEX IF NOT EXISTS idx_drafts_created ON drafts(created_at DESC)`,

        /** Click analytics — one row per outbound Amazon click. */
        `CREATE TABLE IF NOT EXISTS clicks (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          asin TEXT NOT NULL,
          slug TEXT NOT NULL,
          position INTEGER NOT NULL,
          referrer TEXT,
          user_agent TEXT,
          ip_hash TEXT,
          created_at TEXT NOT NULL DEFAULT (datetime('now'))
        )`,
        `CREATE INDEX IF NOT EXISTS idx_clicks_created ON clicks(created_at DESC)`,
        `CREATE INDEX IF NOT EXISTS idx_clicks_asin ON clicks(asin)`,
        `CREATE INDEX IF NOT EXISTS idx_clicks_slug ON clicks(slug)`,

        /** Autopilot queue — keywords waiting to be turned into drafts. */
        `CREATE TABLE IF NOT EXISTS autopilot_queue (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          keyword TEXT NOT NULL,
          tone TEXT,
          state TEXT NOT NULL DEFAULT 'queued',
          attempts INTEGER NOT NULL DEFAULT 0,
          error TEXT,
          draft_id INTEGER,
          created_at TEXT NOT NULL DEFAULT (datetime('now')),
          processed_at TEXT
        )`,
        `CREATE INDEX IF NOT EXISTS idx_queue_state ON autopilot_queue(state, created_at)`,

        /** Reviews CRUD — published/scheduled/draft reviews. */
        `CREATE TABLE IF NOT EXISTS reviews (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          slug TEXT NOT NULL UNIQUE,
          title TEXT NOT NULL,
          keyword TEXT NOT NULL,
          markdown TEXT NOT NULL,
          meta_title TEXT,
          meta_description TEXT,
          status TEXT NOT NULL DEFAULT 'draft',
          published_at TEXT,
          created_at TEXT NOT NULL DEFAULT (datetime('now')),
          updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        )`,
        `CREATE INDEX IF NOT EXISTS idx_reviews_status ON reviews(status, published_at DESC)`,

        /** Short-lived price cache — populated on demand, never stores images. */
        `CREATE TABLE IF NOT EXISTS price_cache (
          asin TEXT PRIMARY KEY,
          price REAL,
          currency TEXT,
          rating REAL,
          reviews_count INTEGER,
          availability TEXT,
          fetched_at TEXT NOT NULL DEFAULT (datetime('now'))
        )`,
      ],
      "write",
    );
  })().catch((e) => {
    _initPromise = null;
    throw e;
  });
  return _initPromise;
}

export async function dbStatus(): Promise<{
  configured: boolean;
  connected: boolean;
  error?: string;
  host?: string;
  counts?: { drafts: number; reviews: number; clicks: number; queue: number };
}> {
  const url = cleanEnv(process.env.TURSO_DATABASE_URL);
  const hasToken = Boolean(cleanEnv(process.env.TURSO_AUTH_TOKEN));
  if (!url) return { configured: false, connected: false };
  try {
    await ensureSchema();
    const db = getDb();
    const [d, r, c, q] = await Promise.all([
      db.execute("SELECT COUNT(*) AS n FROM drafts"),
      db.execute("SELECT COUNT(*) AS n FROM reviews"),
      db.execute("SELECT COUNT(*) AS n FROM clicks"),
      db.execute("SELECT COUNT(*) AS n FROM autopilot_queue WHERE state='queued'"),
    ]);
    return {
      configured: true,
      connected: true,
      host: new URL(normalizeUrl(url)).host,
      counts: {
        drafts: Number(d.rows[0]?.n ?? 0),
        reviews: Number(r.rows[0]?.n ?? 0),
        clicks: Number(c.rows[0]?.n ?? 0),
        queue: Number(q.rows[0]?.n ?? 0),
      },
    };
  } catch (e) {
    const raw = e instanceof Error ? e.message : "connection failed";
    // Give admins an actionable hint alongside the raw SDK error.
    let hint = "";
    if (/401|unauthor|token/i.test(raw)) hint = " — check TURSO_AUTH_TOKEN (expired, wrong DB, or has extra whitespace).";
    else if (/400/.test(raw)) hint = " — check TURSO_DATABASE_URL host and that TURSO_AUTH_TOKEN belongs to this DB (regenerate if unsure).";
    else if (/ENOTFOUND|fetch failed|network/i.test(raw)) hint = " — DNS/network error reaching the Turso host.";
    return {
      configured: true,
      connected: false,
      error: raw + hint + (hasToken ? "" : " — TURSO_AUTH_TOKEN is empty."),
    };
  }
}
