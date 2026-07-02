/**
 * Public cron endpoint. Configure your VPS / Coolify / Netlify scheduler
 * to POST here every N minutes when autopilot is enabled.
 *
 * Auth: send header  X-Cron-Secret: $CRON_SECRET  (set in env).
 */
import { createFileRoute } from "@tanstack/react-router";

export const Route = createFileRoute("/api/public/autopilot/run")({
  server: {
    handlers: {
      POST: async ({ request }) => {
        const { readSettings } = await import("@/lib/admin-session.server");
        const settings = await readSettings();
        const secret = settings.cronSecret || process.env.CRON_SECRET || "";
        if (!secret) return new Response("cron disabled — set cronSecret in admin", { status: 503 });
        if (request.headers.get("x-cron-secret") !== secret) {
          return new Response("unauthorized", { status: 401 });
        }
        if (settings.autopilotEnabled !== "1") {
          return Response.json({ skipped: true, reason: "autopilot disabled" });
        }
        const { runAutopilotBatch } = await import("@/lib/autopilot.functions");
        const batchSize = Math.max(1, Math.min(20, parseInt(settings.autopilotBatchSize || "3", 10)));
        try {
          const out = await runAutopilotBatch(batchSize);
          return Response.json({ ok: true, ...out });
        } catch (e) {
          return Response.json(
            { ok: false, error: e instanceof Error ? e.message : "failed" },
            { status: 500 },
          );
        }
      },
    },
  },
});
