import { createServerFn } from "@tanstack/react-start";
import { getRequestHeader } from "@tanstack/react-start/server";
import { z } from "zod";
import { createHash } from "node:crypto";

const Schema = z.object({
  asin: z.string().trim().min(1).max(40),
  position: z.number().int().min(1).max(50),
  slug: z.string().trim().min(1).max(200),
});

/**
 * Log an outbound Amazon click. Persists to Turso when configured so the
 * admin analytics dashboard can rank top-CTR products. IP is hashed, never
 * stored raw.
 */
export const recordAffiliateClick = createServerFn({ method: "POST" })
  .inputValidator((data: unknown) => Schema.parse(data))
  .handler(async ({ data }) => {
    const referrer = getRequestHeader("referer") ?? null;
    const ua = getRequestHeader("user-agent") ?? null;
    const fwd = getRequestHeader("x-forwarded-for") ?? getRequestHeader("x-real-ip") ?? "";
    const ipHash = fwd
      ? createHash("sha256").update(fwd + (process.env.SESSION_SECRET ?? "salt")).digest("hex").slice(0, 32)
      : null;

    try {
      const { dbConfigured, ensureSchema, getDb } = await import("./db.server");
      if (dbConfigured()) {
        await ensureSchema();
        await getDb().execute({
          sql: `INSERT INTO clicks(asin, slug, position, referrer, user_agent, ip_hash)
                VALUES(?, ?, ?, ?, ?, ?)`,
          args: [data.asin, data.slug, data.position, referrer, ua, ipHash],
        });
      } else {
        console.log(`[click] asin=${data.asin} pos=${data.position} slug=${data.slug}`);
      }
    } catch (e) {
      // Never break the user's outbound click if the DB hiccups.
      console.error("[click] persist failed", e);
    }
    return { success: true } as const;
  });
