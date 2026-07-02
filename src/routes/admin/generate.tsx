import { createFileRoute } from "@tanstack/react-router";
import { useServerFn } from "@tanstack/react-start";
import { useCallback, useEffect, useState } from "react";
import { deleteDraft, generateReviews, listDrafts } from "@/lib/admin.functions";

export const Route = createFileRoute("/admin/generate")({
  component: GeneratePage,
});

type Draft = {
  id: number;
  keyword: string;
  tone: string | null;
  markdown: string;
  created_at: string;
};

function GeneratePage() {
  const gen = useServerFn(generateReviews);
  const load = useServerFn(listDrafts);
  const remove = useServerFn(deleteDraft);
  const [raw, setRaw] = useState("best coffee makers\nbest wireless earbuds\nbest robot vacuums");
  const [tone, setTone] = useState("trustworthy, concise, buyer-focused");
  const [busy, setBusy] = useState(false);
  const [results, setResults] = useState<Array<{ keyword: string; markdown: string; error?: string; id?: number }>>([]);
  const [error, setError] = useState<string | null>(null);
  const [persisted, setPersisted] = useState<boolean | null>(null);
  const [drafts, setDrafts] = useState<Draft[]>([]);

  const refresh = useCallback(() => {
    load().then((r) => setDrafts(r.drafts)).catch(() => setDrafts([]));
  }, [load]);

  useEffect(() => {
    refresh();
  }, [refresh]);

  async function onGenerate(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    setResults([]);
    const keywords = raw
      .split(/\r?\n/)
      .map((k) => k.trim())
      .filter(Boolean)
      .slice(0, 25);
    if (!keywords.length) {
      setError("Add at least one keyword");
      setBusy(false);
      return;
    }
    try {
      const r = await gen({ data: { keywords, tone } });
      setResults(r.results);
      setPersisted(r.persisted);
      refresh();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Generation failed");
    } finally {
      setBusy(false);
    }
  }

  async function onDelete(id: number) {
    if (!confirm("Delete this draft?")) return;
    await remove({ data: { id } });
    refresh();
  }

  return (
    <div className="space-y-8">
      <form onSubmit={onGenerate} className="space-y-4">
        <div>
          <h2 className="font-serif text-xl">Bulk keyword → review drafts</h2>
          <p className="mt-1 text-xs text-muted-foreground">
            One keyword per line, up to 25. Drafts auto-save to your Turso database.
          </p>
        </div>
        <label className="block">
          <span className="text-sm font-medium">Keywords (one per line)</span>
          <textarea
            className="mt-1 h-40 w-full rounded-md border border-input bg-background p-3 font-mono text-xs shadow-sm"
            value={raw}
            onChange={(e) => setRaw(e.target.value)}
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
        <button
          type="submit"
          disabled={busy}
          className="rounded-full bg-amber px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-amber/90 disabled:opacity-60"
        >
          {busy ? "Generating…" : "Generate drafts"}
        </button>
        {error && <p className="text-sm text-destructive">{error}</p>}
        {persisted === false && !error && (
          <p className="text-xs text-amber-700">
            Generated but not saved — database not configured.
          </p>
        )}
      </form>

      {results.length > 0 && (
        <div className="space-y-4">
          <h3 className="font-serif text-lg">Just generated ({results.length})</h3>
          {results.map((r, i) => (
            <details key={i} className="rounded-lg border border-border bg-card p-4" open={i === 0}>
              <summary className="cursor-pointer text-sm font-semibold">
                {r.keyword} {r.error ? <span className="text-destructive">(error)</span> : null}
              </summary>
              {r.error ? (
                <p className="mt-2 text-xs text-destructive">{r.error}</p>
              ) : (
                <>
                  <div className="mt-3 flex justify-end">
                    <button
                      type="button"
                      onClick={() => navigator.clipboard.writeText(r.markdown)}
                      className="rounded-full border border-border bg-background px-3 py-1 text-xs font-medium hover:bg-muted"
                    >
                      Copy markdown
                    </button>
                  </div>
                  <pre className="mt-2 max-h-96 overflow-auto whitespace-pre-wrap rounded bg-muted/40 p-3 text-xs">
                    {r.markdown}
                  </pre>
                </>
              )}
            </details>
          ))}
        </div>
      )}

      <section>
        <div className="flex items-center justify-between">
          <h3 className="font-serif text-lg">Saved drafts ({drafts.length})</h3>
          <button
            onClick={refresh}
            className="rounded-full border border-border bg-background px-3 py-1 text-xs font-medium hover:bg-muted"
          >
            Refresh
          </button>
        </div>
        {drafts.length === 0 ? (
          <p className="mt-2 text-sm text-muted-foreground">
            No saved drafts yet. Generated drafts save automatically when the database is connected.
          </p>
        ) : (
          <div className="mt-3 space-y-2">
            {drafts.map((d) => (
              <details key={d.id} className="rounded-lg border border-border bg-card p-4">
                <summary className="flex cursor-pointer items-center justify-between gap-3">
                  <span className="text-sm font-semibold">{d.keyword}</span>
                  <span className="text-xs text-muted-foreground">
                    {new Date(d.created_at + "Z").toLocaleString()}
                  </span>
                </summary>
                <div className="mt-3 flex justify-end gap-2">
                  <button
                    type="button"
                    onClick={() => navigator.clipboard.writeText(d.markdown)}
                    className="rounded-full border border-border bg-background px-3 py-1 text-xs font-medium hover:bg-muted"
                  >
                    Copy
                  </button>
                  <button
                    type="button"
                    onClick={() => onDelete(d.id)}
                    className="rounded-full border border-destructive/30 bg-background px-3 py-1 text-xs font-medium text-destructive hover:bg-destructive/10"
                  >
                    Delete
                  </button>
                </div>
                <pre className="mt-2 max-h-96 overflow-auto whitespace-pre-wrap rounded bg-muted/40 p-3 text-xs">
                  {d.markdown}
                </pre>
              </details>
            ))}
          </div>
        )}
      </section>
    </div>
  );
}
