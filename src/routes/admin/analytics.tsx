import { createFileRoute } from "@tanstack/react-router";
import { useServerFn } from "@tanstack/react-start";
import { useEffect, useState } from "react";
import { getClickAnalytics } from "@/lib/analytics.functions";

export const Route = createFileRoute("/admin/analytics")({
  component: AnalyticsPage,
});

type Data = Awaited<ReturnType<typeof getClickAnalytics>>;

function AnalyticsPage() {
  const load = useServerFn(getClickAnalytics);
  const [days, setDays] = useState(30);
  const [data, setData] = useState<Data | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    setBusy(true);
    load({ data: { days } })
      .then(setData)
      .finally(() => setBusy(false));
  }, [load, days]);

  if (!data) return <p className="text-sm text-muted-foreground">Loading…</p>;

  if (!data.configured) {
    return (
      <div className="rounded-lg border border-amber/30 bg-amber/5 p-4 text-sm">
        Database not configured — connect Turso to record and view click analytics.
      </div>
    );
  }

  const maxDay = Math.max(1, ...data.byDay.map((d) => d.n));

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="font-serif text-xl">Click analytics</h2>
          <p className="text-xs text-muted-foreground">
            Outbound Amazon clicks over the last {days} days.
          </p>
        </div>
        <div className="flex items-center gap-2">
          {[7, 30, 90, 365].map((d) => (
            <button
              key={d}
              onClick={() => setDays(d)}
              className={`rounded-full border px-3 py-1 text-xs font-medium ${
                days === d
                  ? "border-amber bg-amber/10 text-amber-800"
                  : "border-border bg-card hover:bg-muted"
              }`}
            >
              {d}d
            </button>
          ))}
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <Stat label="Total clicks" value={data.total.toLocaleString()} loading={busy} />
        <Stat label="Unique products" value={data.topAsins.length.toLocaleString()} />
        <Stat label="Unique pages" value={data.topSlugs.length.toLocaleString()} />
      </div>

      <section className="rounded-lg border border-border bg-card p-4">
        <h3 className="font-semibold text-sm">Clicks per day</h3>
        {data.byDay.length === 0 ? (
          <p className="mt-2 text-xs text-muted-foreground">No clicks yet.</p>
        ) : (
          <div className="mt-4 flex h-32 items-end gap-1">
            {[...data.byDay].reverse().map((d) => (
              <div key={d.day} className="flex flex-1 flex-col items-center gap-1">
                <div
                  className="w-full rounded-t bg-amber/70"
                  style={{ height: `${(d.n / maxDay) * 100}%`, minHeight: 2 }}
                  title={`${d.day}: ${d.n}`}
                />
              </div>
            ))}
          </div>
        )}
      </section>

      <div className="grid gap-4 lg:grid-cols-2">
        <TopList title="Top ASINs" rows={data.topAsins.map((r) => ({ label: r.asin, n: r.n }))} />
        <TopList title="Top pages" rows={data.topSlugs.map((r) => ({ label: r.slug, n: r.n }))} />
      </div>

      <section className="rounded-lg border border-border bg-card p-4">
        <h3 className="font-semibold text-sm">Recent clicks</h3>
        <div className="mt-3 overflow-x-auto">
          <table className="w-full text-xs">
            <thead className="text-left text-muted-foreground">
              <tr>
                <th className="py-1 pr-3">When</th>
                <th className="py-1 pr-3">Page</th>
                <th className="py-1 pr-3">ASIN</th>
                <th className="py-1 pr-3">Pos</th>
              </tr>
            </thead>
            <tbody>
              {data.recent.map((r, i) => (
                <tr key={i} className="border-t border-border">
                  <td className="py-1 pr-3">{new Date(r.created_at + "Z").toLocaleString()}</td>
                  <td className="py-1 pr-3 font-mono">{r.slug}</td>
                  <td className="py-1 pr-3 font-mono">{r.asin}</td>
                  <td className="py-1 pr-3">{r.position}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
}

function Stat({ label, value, loading }: { label: string; value: string; loading?: boolean }) {
  return (
    <div className="rounded-lg border border-border bg-card p-4">
      <div className="text-xs text-muted-foreground">{label}</div>
      <div className={`mt-1 font-serif text-2xl ${loading ? "opacity-60" : ""}`}>{value}</div>
    </div>
  );
}

function TopList({ title, rows }: { title: string; rows: { label: string; n: number }[] }) {
  const max = Math.max(1, ...rows.map((r) => r.n));
  return (
    <section className="rounded-lg border border-border bg-card p-4">
      <h3 className="font-semibold text-sm">{title}</h3>
      {rows.length === 0 ? (
        <p className="mt-2 text-xs text-muted-foreground">No data.</p>
      ) : (
        <ul className="mt-3 space-y-1.5">
          {rows.map((r) => (
            <li key={r.label} className="text-xs">
              <div className="flex justify-between gap-2">
                <span className="truncate font-mono">{r.label}</span>
                <span className="font-medium">{r.n}</span>
              </div>
              <div className="mt-0.5 h-1.5 rounded bg-muted">
                <div className="h-1.5 rounded bg-amber/70" style={{ width: `${(r.n / max) * 100}%` }} />
              </div>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
