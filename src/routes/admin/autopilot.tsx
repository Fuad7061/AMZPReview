import { createFileRoute } from "@tanstack/react-router";
import { useServerFn } from "@tanstack/react-start";
import { useCallback, useEffect, useState } from "react";
import {
  clearQueue,
  enqueueKeywords,
  listQueue,
  runQueueOnce,
} from "@/lib/autopilot.functions";

export const Route = createFileRoute("/admin/autopilot")({
  component: AutopilotPage,
});

type Queue = Awaited<ReturnType<typeof listQueue>>;

function AutopilotPage() {
  const enqueue = useServerFn(enqueueKeywords);
  const list = useServerFn(listQueue);
  const run = useServerFn(runQueueOnce);
  const clear = useServerFn(clearQueue);

  const [raw, setRaw] = useState("");
  const [tone, setTone] = useState("trustworthy, concise, buyer-focused");
  const [queue, setQueue] = useState<Queue | null>(null);
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState<string | null>(null);

  const refresh = useCallback(() => {
    list().then(setQueue).catch(() => setQueue(null));
  }, [list]);
  useEffect(() => refresh(), [refresh]);

  async function onEnqueue(e: React.FormEvent) {
    e.preventDefault();
    setMsg(null);
    const keywords = raw.split(/\r?\n/).map((k) => k.trim()).filter(Boolean).slice(0, 200);
    if (!keywords.length) return;
    setBusy(true);
    try {
      const r = await enqueue({ data: { keywords, tone } });
      setMsg(`Enqueued ${r.enqueued} keyword${r.enqueued === 1 ? "" : "s"}.`);
      setRaw("");
      refresh();
    } catch (e) {
      setMsg(e instanceof Error ? e.message : "Failed");
    } finally {
      setBusy(false);
    }
  }

  async function onRun() {
    setBusy(true);
    setMsg(null);
    try {
      const r = await run({});
      setMsg(`Processed ${r.processed} item${r.processed === 1 ? "" : "s"}.`);
      refresh();
    } catch (e) {
      setMsg(e instanceof Error ? e.message : "Failed");
    } finally {
      setBusy(false);
    }
  }

  async function onClear(state: "queued" | "done" | "error" | "all") {
    if (!confirm(`Clear ${state === "all" ? "the entire queue" : `all "${state}" items`}?`)) return;
    await clear({ data: { onlyState: state } });
    refresh();
  }

  if (queue && !queue.configured) {
    return (
      <div className="rounded-lg border border-amber/30 bg-amber/5 p-4 text-sm">
        Database not configured — Autopilot requires Turso for queue persistence.
      </div>
    );
  }

  const counts = queue?.items.reduce(
    (acc, i) => {
      acc[i.state] = (acc[i.state] || 0) + 1;
      return acc;
    },
    {} as Record<string, number>,
  ) ?? {};

  return (
    <div className="space-y-8">
      <form onSubmit={onEnqueue} className="space-y-4">
        <div>
          <h2 className="font-serif text-xl">Autopilot queue</h2>
          <p className="mt-1 text-xs text-muted-foreground">
            Enqueue up to 200 keywords. Each is turned into a review draft by the AI worker.
            Trigger runs manually below, or configure a cron to POST{" "}
            <code className="rounded bg-muted px-1">/api/public/autopilot/run</code> with the
            <code className="rounded bg-muted px-1">X-Cron-Secret</code> header.
          </p>
        </div>
        <label className="block">
          <span className="text-sm font-medium">Keywords (one per line)</span>
          <textarea
            className="mt-1 h-32 w-full rounded-md border border-input bg-background p-3 font-mono text-xs shadow-sm"
            value={raw}
            onChange={(e) => setRaw(e.target.value)}
            placeholder="best wireless earbuds&#10;best air fryer&#10;best robot vacuum"
          />
        </label>
        <label className="block">
          <span className="text-sm font-medium">Tone / voice</span>
          <input
            className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm"
            value={tone}
            onChange={(e) => setTone(e.target.value)}
          />
        </label>
        <div className="flex flex-wrap gap-2">
          <button
            type="submit"
            disabled={busy}
            className="rounded-full bg-amber px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-amber/90 disabled:opacity-60"
          >
            {busy ? "Working…" : "Enqueue"}
          </button>
          <button
            type="button"
            onClick={onRun}
            disabled={busy}
            className="rounded-full border border-border bg-card px-5 py-2.5 text-sm font-semibold hover:bg-muted disabled:opacity-60"
          >
            Run batch now
          </button>
        </div>
        {msg && <p className="text-sm text-muted-foreground">{msg}</p>}
      </form>

      <section>
        <div className="flex flex-wrap items-center justify-between gap-3">
          <h3 className="font-serif text-lg">Queue ({queue?.items.length ?? 0})</h3>
          <div className="flex flex-wrap gap-2 text-xs">
            <Pill label="Queued" n={counts.queued ?? 0} tone="amber" />
            <Pill label="Done" n={counts.done ?? 0} tone="green" />
            <Pill label="Error" n={counts.error ?? 0} tone="red" />
            <button
              onClick={refresh}
              className="rounded-full border border-border bg-background px-3 py-1 font-medium hover:bg-muted"
            >
              Refresh
            </button>
            <button
              onClick={() => onClear("done")}
              className="rounded-full border border-border bg-background px-3 py-1 font-medium hover:bg-muted"
            >
              Clear done
            </button>
            <button
              onClick={() => onClear("error")}
              className="rounded-full border border-border bg-background px-3 py-1 font-medium hover:bg-muted"
            >
              Clear errors
            </button>
          </div>
        </div>
        <div className="mt-3 overflow-x-auto rounded-lg border border-border">
          <table className="w-full text-xs">
            <thead className="bg-muted/50 text-left">
              <tr>
                <th className="p-2">Keyword</th>
                <th className="p-2">State</th>
                <th className="p-2">Attempts</th>
                <th className="p-2">Draft</th>
                <th className="p-2">Created</th>
                <th className="p-2">Error</th>
              </tr>
            </thead>
            <tbody>
              {(queue?.items ?? []).map((i) => (
                <tr key={i.id} className="border-t border-border">
                  <td className="p-2 font-medium">{i.keyword}</td>
                  <td className="p-2">
                    <StateBadge state={i.state} />
                  </td>
                  <td className="p-2">{i.attempts}</td>
                  <td className="p-2 font-mono">{i.draft_id ?? "—"}</td>
                  <td className="p-2">{new Date(i.created_at + "Z").toLocaleString()}</td>
                  <td className="p-2 text-destructive">{i.error ?? ""}</td>
                </tr>
              ))}
              {queue && queue.items.length === 0 && (
                <tr>
                  <td colSpan={6} className="p-4 text-center text-muted-foreground">
                    Queue is empty.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
}

function Pill({ label, n, tone }: { label: string; n: number; tone: "amber" | "green" | "red" }) {
  const cls =
    tone === "amber"
      ? "bg-amber/10 text-amber-800"
      : tone === "green"
        ? "bg-green-100 text-green-800"
        : "bg-red-100 text-red-800";
  return <span className={`rounded-full px-2 py-0.5 ${cls}`}>{label}: {n}</span>;
}

function StateBadge({ state }: { state: string }) {
  const map: Record<string, string> = {
    queued: "bg-amber/10 text-amber-800",
    running: "bg-blue-100 text-blue-800",
    done: "bg-green-100 text-green-800",
    error: "bg-red-100 text-red-800",
  };
  return (
    <span className={`rounded-full px-2 py-0.5 text-[10px] font-medium ${map[state] ?? "bg-muted"}`}>
      {state}
    </span>
  );
}
