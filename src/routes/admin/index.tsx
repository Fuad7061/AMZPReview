import { createFileRoute, Link } from "@tanstack/react-router";
import { useServerFn } from "@tanstack/react-start";
import { useEffect, useState } from "react";
import { applyDbConnection, getDbStatus, testDbConnection } from "@/lib/admin.functions";

export const Route = createFileRoute("/admin/")({
  component: AdminHome,
});

function AdminHome() {
  const load = useServerFn(getDbStatus);
  const testDb = useServerFn(testDbConnection);
  const applyDb = useServerFn(applyDbConnection);
  const [status, setStatus] = useState<Awaited<ReturnType<typeof getDbStatus>> | null>(null);
  const [dbForm, setDbForm] = useState({ databaseUrl: "", authToken: "" });
  const [dbBusy, setDbBusy] = useState<"test" | "apply" | null>(null);
  const [dbResult, setDbResult] = useState<{
    ok: boolean;
    host?: string;
    latency_ms?: number;
    error?: string;
    status?: Awaited<ReturnType<typeof getDbStatus>> | null;
  } | null>(null);

  useEffect(() => {
    load().then(setStatus).catch(() => setStatus({ configured: false, connected: false }));
  }, [load]);

  async function onTestDb(e: React.FormEvent) {
    e.preventDefault();
    setDbBusy("test");
    setDbResult(null);
    try {
      setDbResult(await testDb({ data: dbForm }));
    } finally {
      setDbBusy(null);
    }
  }

  async function onApplyDb() {
    setDbBusy("apply");
    setDbResult(null);
    try {
      const result = await applyDb({ data: dbForm });
      setDbResult(result);
      if (result.status) setStatus(result.status);
    } finally {
      setDbBusy(null);
    }
  }

  const envSnippet = [
    `TURSO_DATABASE_URL=${dbForm.databaseUrl.trim() || "libsql://your-database.turso.io"}`,
    `TURSO_AUTH_TOKEN=${dbForm.authToken.trim() || "your-turso-auth-token"}`,
  ].join("\n");

  const cards = [
    {
      to: "/admin/settings" as const,
      title: "Site Settings",
      desc: "Product-data endpoint, Amazon tag, PA-API creds, autopilot toggles, SEO & analytics.",
    },
    {
      to: "/admin/generate" as const,
      title: "Bulk Generate",
      desc: "One-shot: paste keywords, get review drafts back immediately (up to 25 at a time).",
    },
    {
      to: "/admin/autopilot" as const,
      title: "Autopilot Queue",
      desc: "Enqueue up to 200 keywords; a cron worker converts them to drafts in the background.",
    },
    {
      to: "/admin/reviews" as const,
      title: "Reviews CRUD",
      desc: "Create, edit, publish, and delete reviews. Published pages go live at /r/<slug>.",
    },
    {
      to: "/admin/analytics" as const,
      title: "Click Analytics",
      desc: "Outbound Amazon clicks: totals, per-day chart, top ASINs, top pages, recent activity.",
    },
  ];
  return (
    <div>
      <h2 className="font-serif text-xl">Welcome</h2>
      <p className="mt-1 text-sm text-muted-foreground">Pick a section below to manage your site.</p>

      <div className="mt-6 rounded-lg border border-border bg-card p-4 text-sm">
        <div className="flex flex-wrap items-center gap-3">
          <span className="font-semibold">Database:</span>
          {status === null ? (
            <span className="text-muted-foreground">Checking…</span>
          ) : status.connected ? (
            <span className="inline-flex items-center gap-2 rounded-full bg-green-100 px-3 py-0.5 text-xs font-medium text-green-800">
              <span className="h-2 w-2 rounded-full bg-green-500" />
              Connected (Turso {status.host})
            </span>
          ) : status.configured ? (
            <span className="inline-flex items-center gap-2 rounded-full bg-red-100 px-3 py-0.5 text-xs font-medium text-red-800">
              <span className="h-2 w-2 rounded-full bg-red-500" />
              Configured but not reachable
            </span>
          ) : (
            <span className="inline-flex items-center gap-2 rounded-full bg-amber-100 px-3 py-0.5 text-xs font-medium text-amber-800">
              <span className="h-2 w-2 rounded-full bg-amber-500" />
              Not configured — settings persist in memory only
            </span>
          )}
        </div>
        {status?.error && (
          <p className="mt-2 text-xs text-destructive break-all">{status.error}</p>
        )}
        {status?.source === "temporary" && (
          <p className="mt-2 text-xs text-amber">
            Using dashboard-pasted credentials for this running server. Add the same values to Netlify
            environment variables for permanent deploys.
          </p>
        )}
        {!status?.configured && (
          <p className="mt-2 text-xs text-muted-foreground">
            Add <code>TURSO_DATABASE_URL</code> and <code>TURSO_AUTH_TOKEN</code> in Project Settings
            → Secrets to enable persistent storage.
          </p>
        )}
        {status?.counts && (
          <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
            {[
              { label: "Reviews", n: status.counts.reviews },
              { label: "Drafts", n: status.counts.drafts },
              { label: "Queued", n: status.counts.queue },
              { label: "Clicks", n: status.counts.clicks },
            ].map((c) => (
              <div key={c.label} className="rounded border border-border bg-background p-2 text-center">
                <div className="text-xs text-muted-foreground">{c.label}</div>
                <div className="font-serif text-lg">{c.n.toLocaleString()}</div>
              </div>
            ))}
          </div>
        )}

        <form onSubmit={onTestDb} className="mt-5 rounded-md border border-border bg-background p-4">
          <div className="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <h3 className="font-semibold">Paste database credentials</h3>
              <p className="mt-1 text-xs text-muted-foreground">
                Test the Turso URL and token here. Use “Apply now” to make the current admin server use
                these values, then copy the same two lines into Netlify Environment variables.
              </p>
            </div>
            {dbResult && (
              <span
                className={`mt-2 inline-flex w-fit items-center gap-2 rounded-full px-3 py-1 text-xs font-medium sm:mt-0 ${
                  dbResult.ok ? "bg-green-100 text-green-800" : "bg-red-100 text-red-800"
                }`}
              >
                <span className={`h-2 w-2 rounded-full ${dbResult.ok ? "bg-green-500" : "bg-red-500"}`} />
                {dbResult.ok
                  ? `Connected${dbResult.host ? ` to ${dbResult.host}` : ""}${dbResult.latency_ms ? ` · ${dbResult.latency_ms}ms` : ""}`
                  : "Connection failed"}
              </span>
            )}
          </div>

          <div className="mt-4 grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            <label className="block">
              <span className="text-xs font-medium">TURSO_DATABASE_URL</span>
              <input
                className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs shadow-sm"
                value={dbForm.databaseUrl}
                onChange={(e) => setDbForm((s) => ({ ...s, databaseUrl: e.target.value }))}
                placeholder="libsql://amazpreview-username.aws-us-east-2.turso.io"
                autoComplete="off"
                spellCheck={false}
              />
            </label>
            <label className="block">
              <span className="text-xs font-medium">TURSO_AUTH_TOKEN</span>
              <input
                className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs shadow-sm"
                type="password"
                value={dbForm.authToken}
                onChange={(e) => setDbForm((s) => ({ ...s, authToken: e.target.value }))}
                placeholder="Paste the Turso token"
                autoComplete="off"
                spellCheck={false}
              />
            </label>
          </div>

          {dbResult?.error && <p className="mt-3 break-all text-xs text-destructive">{dbResult.error}</p>}

          <div className="mt-4 grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
            <label className="block">
              <span className="text-xs font-medium">Netlify variables to copy</span>
              <textarea
                className="mt-1 h-20 w-full rounded-md border border-input bg-muted/40 px-3 py-2 font-mono text-[11px] shadow-sm"
                value={envSnippet}
                readOnly
              />
            </label>
            <div className="flex flex-wrap gap-2">
              <button
                type="submit"
                disabled={dbBusy !== null}
                className="rounded-full border border-border bg-card px-4 py-2 text-xs font-semibold hover:bg-muted disabled:opacity-60"
              >
                {dbBusy === "test" ? "Testing…" : "Test connection"}
              </button>
              <button
                type="button"
                onClick={onApplyDb}
                disabled={dbBusy !== null || !dbForm.databaseUrl.trim() || !dbForm.authToken.trim()}
                className="rounded-full bg-amber px-4 py-2 text-xs font-semibold text-white shadow hover:bg-amber/90 disabled:opacity-60"
              >
                {dbBusy === "apply" ? "Applying…" : "Apply now"}
              </button>
            </div>
          </div>
        </form>
      </div>

      <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {cards.map((c) => (
          <Link
            key={c.to}
            to={c.to}
            className="group block rounded-xl border border-border bg-card p-5 shadow-sm transition-all hover:-translate-y-0.5 hover:border-amber hover:shadow-md"
          >
            <h3 className="font-semibold text-foreground group-hover:text-amber">{c.title}</h3>
            <p className="mt-2 text-sm text-muted-foreground">{c.desc}</p>
          </Link>
        ))}
      </div>
    </div>
  );
}
