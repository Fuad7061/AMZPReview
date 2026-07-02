import { createFileRoute, Link } from "@tanstack/react-router";
import { useServerFn } from "@tanstack/react-start";
import { useEffect, useState } from "react";
import { getDbStatus } from "@/lib/admin.functions";

export const Route = createFileRoute("/admin/")({
  component: AdminHome,
});

function AdminHome() {
  const load = useServerFn(getDbStatus);
  const [status, setStatus] = useState<Awaited<ReturnType<typeof getDbStatus>> | null>(null);

  useEffect(() => {
    load().then(setStatus).catch(() => setStatus({ configured: false, connected: false }));
  }, [load]);

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
