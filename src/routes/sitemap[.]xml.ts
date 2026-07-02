/**
 * Dynamic sitemap.xml — includes static routes AND every published review
 * from Turso automatically. Base URL comes from admin settings (siteUrl)
 * or is auto-derived from the request Host header.
 */
import { createFileRoute } from "@tanstack/react-router";
import type {} from "@tanstack/react-start";

interface SitemapEntry {
  path: string;
  lastmod?: string;
  changefreq?: "always" | "hourly" | "daily" | "weekly" | "monthly" | "yearly" | "never";
  priority?: string;
}

export const Route = createFileRoute("/sitemap.xml")({
  server: {
    handlers: {
      GET: async ({ request }) => {
        const { readSettings } = await import("@/lib/admin-session.server");
        const settings = await readSettings();

        const url = new URL(request.url);
        const proto = request.headers.get("x-forwarded-proto") ?? url.protocol.replace(":", "");
        const host = request.headers.get("host") ?? url.host;
        const baseUrl = (settings.siteUrl || `${proto}://${host}`).replace(/\/$/, "");

        const entries: SitemapEntry[] = [
          { path: "/", changefreq: "daily", priority: "1.0" },
          { path: "/about", changefreq: "monthly", priority: "0.5" },
          { path: "/methodology", changefreq: "monthly", priority: "0.7" },
          { path: "/disclosure", changefreq: "yearly", priority: "0.3" },
          { path: "/privacy", changefreq: "yearly", priority: "0.3" },
          { path: "/terms", changefreq: "yearly", priority: "0.3" },
        ];

        // Append every published product page from the database.
        try {
          const { dbConfigured, ensureSchema, getDb } = await import("@/lib/db.server");
          if (dbConfigured()) {
            await ensureSchema();
            const rs = await getDb().execute(
              `SELECT slug, updated_at FROM reviews WHERE status='published' ORDER BY updated_at DESC LIMIT 5000`,
            );
            const seen = new Set<string>();
            for (const r of rs.rows) {
              const slug = String(r.slug);
              if (seen.has(slug)) continue;
              seen.add(slug);
              entries.push({
                path: `/product/${slug}`,
                lastmod: String(r.updated_at).replace(" ", "T") + "Z",
                changefreq: "weekly",
                priority: "0.8",
              });
            }
          }
        } catch {
          /* sitemap must never 500 — fall back to static entries */
        }

        const urls = entries.map((e) =>
          [
            `  <url>`,
            `    <loc>${baseUrl}${e.path}</loc>`,
            e.lastmod ? `    <lastmod>${e.lastmod}</lastmod>` : null,
            e.changefreq ? `    <changefreq>${e.changefreq}</changefreq>` : null,
            e.priority ? `    <priority>${e.priority}</priority>` : null,
            `  </url>`,
          ]
            .filter(Boolean)
            .join("\n"),
        );

        const xml = [
          `<?xml version="1.0" encoding="UTF-8"?>`,
          `<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">`,
          ...urls,
          `</urlset>`,
        ].join("\n");

        return new Response(xml, {
          headers: {
            "Content-Type": "application/xml; charset=utf-8",
            "Cache-Control": "public, max-age=600",
          },
        });
      },
    },
  },
});
