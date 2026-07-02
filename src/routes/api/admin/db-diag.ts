/**
 * Admin diagnostic — probes Turso via raw HTTP and returns the actual
 * response body/status. Protected by ADMIN_PASSWORD (?key=...).
 * Never returns secret material.
 */
import { createFileRoute } from "@tanstack/react-router";

function clean(v: string | undefined): string {
  if (!v) return "";
  let s = v.replace(/[\r\n\t]/g, "").trim();
  while (
    (s.startsWith('"') && s.endsWith('"')) ||
    (s.startsWith("'") && s.endsWith("'"))
  ) s = s.slice(1, -1).trim();
  return s;
}

function normalizeUrl(raw: string): string {
  let u = raw;
  if (u.startsWith("libsql://")) u = "https://" + u.slice(9);
  else if (u.startsWith("wss://")) u = "https://" + u.slice(6);
  return u.replace(/\/+$/, "");
}

export const Route = createFileRoute("/api/admin/db-diag")({
  server: {
    handlers: {
      GET: async ({ request }) => {
        const url = new URL(request.url);
        const key = url.searchParams.get("key") ?? "";
        const adminPass = clean(process.env.ADMIN_PASSWORD);
        if (!adminPass || key !== adminPass) {
          return new Response("Unauthorized", { status: 401 });
        }

        const rawUrl = process.env.TURSO_DATABASE_URL;
        const rawToken = process.env.TURSO_AUTH_TOKEN;
        const cleanedUrl = clean(rawUrl);
        const cleanedToken = clean(rawToken);
        const httpUrl = cleanedUrl ? normalizeUrl(cleanedUrl) : "";

        const info: Record<string, unknown> = {
          urlPresent: Boolean(rawUrl),
          tokenPresent: Boolean(rawToken),
          urlLenRaw: rawUrl?.length ?? 0,
          urlLenClean: cleanedUrl.length,
          tokenLenRaw: rawToken?.length ?? 0,
          tokenLenClean: cleanedToken.length,
          urlHadWhitespace: rawUrl ? rawUrl !== cleanedUrl : false,
          tokenHadWhitespace: rawToken ? rawToken !== cleanedToken : false,
          httpEndpoint: httpUrl ? new URL(httpUrl).host : null,
          tokenPreview: cleanedToken
            ? `${cleanedToken.slice(0, 6)}…${cleanedToken.slice(-4)} (len ${cleanedToken.length})`
            : null,
        };

        if (!httpUrl || !cleanedToken) {
          return Response.json({ ok: false, stage: "env", info });
        }

        try {
          const resp = await fetch(`${httpUrl}/v2/pipeline`, {
            method: "POST",
            headers: {
              "content-type": "application/json",
              authorization: `Bearer ${cleanedToken}`,
            },
            body: JSON.stringify({
              requests: [
                { type: "execute", stmt: { sql: "SELECT 1 AS n" } },
                { type: "close" },
              ],
            }),
          });
          const text = await resp.text();
          return Response.json({
            ok: resp.ok,
            stage: "http",
            status: resp.status,
            body: text.slice(0, 800),
            info,
          });
        } catch (e) {
          return Response.json({
            ok: false,
            stage: "fetch",
            error: e instanceof Error ? e.message : String(e),
            info,
          });
        }
      },
    },
  },
});
