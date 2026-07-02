/**
 * Server-only admin session helpers. Encrypted cookie session via useSession.
 * Settings persist in Turso when configured; env vars remain as defaults.
 */
import { useSession } from "@tanstack/react-start/server";
import { createHash, timingSafeEqual } from "node:crypto";
import { dbConfigured, ensureSchema, getDb } from "./db.server";

export type AdminSession = { authed?: boolean; user?: string };

const sessionConfig = () => ({
  password: process.env.SESSION_SECRET ?? "fallback-dev-secret-please-set-SESSION_SECRET-32chars",
  name: "yf_admin",
  maxAge: 60 * 60 * 24 * 7,
  cookie: { httpOnly: true, secure: true, sameSite: "lax" as const, path: "/" },
});

export async function getAdminSession() {
  return useSession<AdminSession>(sessionConfig());
}

export async function isAdmin(): Promise<boolean> {
  const s = await getAdminSession();
  return Boolean(s.data.authed);
}

/** Timing-safe compare via sha256 digests so length doesn't leak. */
export function safeEqual(a: string, b: string): boolean {
  const x = createHash("sha256").update(a, "utf8").digest();
  const y = createHash("sha256").update(b, "utf8").digest();
  return timingSafeEqual(x, y);
}

export type SiteSettings = {
  headCode: string;
  gaId: string;
  metaTitle: string;
  metaDescription: string;
  /** Canonical site URL (for sitemap, canonical, og:url). Auto-detected if blank. */
  siteUrl: string;
  /** Live product-data endpoint (defaults to bundled Lambda). */
  lambdaUrl: string;
  /** Amazon Associates tag used in outbound links + ascsubtag. */
  amazonTag: string;
  /** Optional PA-API credentials (fallback data source). */
  paapiAccessKey: string;
  paapiSecretKey: string;
  paapiPartnerTag: string;
  /** Custom OpenAI-compatible AI provider. */
  aiBaseUrl: string;
  aiApiKey: string;
  aiModel: string;
  aiSystemPrompt: string;
  /** Autopilot toggles. */
  autopilotEnabled: string; // "1" | "0"
  autopilotBatchSize: string; // integer
  /** Autopilot cron interval hint shown in admin UI (minutes). */
  autopilotIntervalMin: string;
  /** Cron secret for /api/public/autopilot/run (managed here so it's editable). */
  cronSecret: string;
  /** Price cache TTL in minutes. */
  priceCacheTtlMin: string;
};

const SETTING_KEYS: (keyof SiteSettings)[] = [
  "headCode",
  "gaId",
  "metaTitle",
  "metaDescription",
  "siteUrl",
  "lambdaUrl",
  "amazonTag",
  "paapiAccessKey",
  "paapiSecretKey",
  "paapiPartnerTag",
  "aiBaseUrl",
  "aiApiKey",
  "aiModel",
  "aiSystemPrompt",
  "autopilotEnabled",
  "autopilotBatchSize",
  "autopilotIntervalMin",
  "cronSecret",
  "priceCacheTtlMin",
];

function envDefaults(): SiteSettings {
  return {
    headCode: process.env.SITE_HEAD_CODE ?? "",
    gaId: process.env.SITE_GA_ID ?? "",
    metaTitle: process.env.SITE_META_TITLE ?? "YAD Foods — Honest Product Reviews",
    metaDescription:
      process.env.SITE_META_DESCRIPTION ??
      "Independent product reviews and buyer's guides with live pricing.",
    siteUrl: process.env.SITE_URL ?? "",
    lambdaUrl:
      process.env.PRODUCT_API_URL ??
      "https://4pobkr5oa4olwuvhx625uiozay0rrcuu.lambda-url.us-east-1.on.aws/",
    amazonTag: process.env.AMAZON_TAG ?? "YOUR-TAG-20",
    paapiAccessKey: process.env.PAAPI_ACCESS_KEY ?? "",
    paapiSecretKey: process.env.PAAPI_SECRET_KEY ?? "",
    paapiPartnerTag: process.env.PAAPI_PARTNER_TAG ?? "",
    aiBaseUrl: process.env.AI_BASE_URL ?? "https://ai.gateway.lovable.dev/v1",
    aiApiKey: process.env.AI_API_KEY ?? process.env.LOVABLE_API_KEY ?? "",
    aiModel: process.env.AI_MODEL ?? "google/gemini-2.5-flash",
    aiSystemPrompt:
      process.env.AI_SYSTEM_PROMPT ??
      "You are a senior affiliate-review editor writing SEO-optimized, trustworthy product roundups.",
    autopilotEnabled: process.env.AUTOPILOT_ENABLED ?? "0",
    autopilotBatchSize: process.env.AUTOPILOT_BATCH_SIZE ?? "3",
    autopilotIntervalMin: process.env.AUTOPILOT_INTERVAL_MIN ?? "60",
    cronSecret: process.env.CRON_SECRET ?? "",
    priceCacheTtlMin: process.env.PRICE_CACHE_TTL_MIN ?? "60",
  };
}

// In-memory cache as fallback when Turso isn't configured.
let memoryOverlay: Partial<SiteSettings> = {};

export async function readSettings(): Promise<SiteSettings> {
  const defaults = envDefaults();
  if (!dbConfigured()) return { ...defaults, ...memoryOverlay };
  try {
    await ensureSchema();
    const rs = await getDb().execute("SELECT key, value FROM settings");
    const overlay: Partial<SiteSettings> = {};
    for (const row of rs.rows) {
      const k = String(row.key) as keyof SiteSettings;
      if (SETTING_KEYS.includes(k)) overlay[k] = String(row.value);
    }
    return { ...defaults, ...overlay };
  } catch {
    return { ...defaults, ...memoryOverlay };
  }
}

export async function writeSettings(patch: Partial<SiteSettings>): Promise<SiteSettings> {
  const entries = Object.entries(patch).filter(([k]) =>
    SETTING_KEYS.includes(k as keyof SiteSettings),
  ) as [keyof SiteSettings, string][];
  if (dbConfigured()) {
    try {
      await ensureSchema();
      const db = getDb();
      await db.batch(
        entries.map(([k, v]) => ({
          sql: `INSERT INTO settings(key, value, updated_at) VALUES(?, ?, datetime('now'))
                ON CONFLICT(key) DO UPDATE SET value=excluded.value, updated_at=excluded.updated_at`,
          args: [k, v ?? ""],
        })),
        "write",
      );
      return readSettings();
    } catch {
      // fall through to memory
    }
  }
  memoryOverlay = { ...memoryOverlay, ...Object.fromEntries(entries) };
  return readSettings();
}
