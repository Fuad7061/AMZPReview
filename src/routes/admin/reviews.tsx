import { createFileRoute, Link } from "@tanstack/react-router";
import { useServerFn } from "@tanstack/react-start";
import { useCallback, useEffect, useState } from "react";
import { deleteReview, listReviews } from "@/lib/reviews.functions";

export const Route = createFileRoute("/admin/reviews")({
  component: ReviewsPage,
});

type List = Awaited<ReturnType<typeof listReviews>>;

function ReviewsPage() {
  const list = useServerFn(listReviews);
  const remove = useServerFn(deleteReview);
  const [status, setStatus] = useState<"all" | "draft" | "published">("all");
  const [data, setData] = useState<List | null>(null);

  const refresh = useCallback(() => {
    list({ data: { status } }).then(setData).catch(() => setData(null));
  }, [list, status]);
  useEffect(() => refresh(), [refresh]);

  async function onDelete(id: number) {
    if (!confirm("Delete this review permanently?")) return;
    await remove({ data: { id } });
    refresh();
  }

  if (data && !data.configured) {
    return (
      <div className="rounded-lg border border-amber/30 bg-amber/5 p-4 text-sm">
        Database not configured — Reviews CRUD requires Turso.
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="font-serif text-xl">Reviews</h2>
          <p className="text-xs text-muted-foreground">
            Persistent editorial layer. Published reviews are live at{" "}
            <code className="rounded bg-muted px-1">/r/&lt;slug&gt;</code>.
          </p>
        </div>
        <div className="flex items-center gap-2">
          {(["all", "draft", "published"] as const).map((s) => (
            <button
              key={s}
              onClick={() => setStatus(s)}
              className={`rounded-full border px-3 py-1 text-xs font-medium ${
                status === s
                  ? "border-amber bg-amber/10 text-amber-800"
                  : "border-border bg-card hover:bg-muted"
              }`}
            >
              {s}
            </button>
          ))}
          <Link
            to="/admin/reviews/new"
            className="rounded-full bg-amber px-4 py-1.5 text-xs font-semibold text-white shadow hover:bg-amber/90"
          >
            + New review
          </Link>
        </div>
      </div>

      <div className="overflow-x-auto rounded-lg border border-border">
        <table className="w-full text-xs">
          <thead className="bg-muted/50 text-left">
            <tr>
              <th className="p-2">Title</th>
              <th className="p-2">Keyword</th>
              <th className="p-2">Slug</th>
              <th className="p-2">Status</th>
              <th className="p-2">Updated</th>
              <th className="p-2 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            {(data?.items ?? []).map((r) => (
              <tr key={r.id} className="border-t border-border">
                <td className="p-2 font-medium">{r.title}</td>
                <td className="p-2 text-muted-foreground">{r.keyword}</td>
                <td className="p-2 font-mono">{r.slug}</td>
                <td className="p-2">
                  <span
                    className={`rounded-full px-2 py-0.5 text-[10px] font-medium ${
                      r.status === "published"
                        ? "bg-green-100 text-green-800"
                        : "bg-muted text-muted-foreground"
                    }`}
                  >
                    {r.status}
                  </span>
                </td>
                <td className="p-2">{new Date(r.updated_at + "Z").toLocaleString()}</td>
                <td className="p-2 text-right">
                  <div className="inline-flex gap-1">
                    {r.status === "published" && (
                      <a
                        href={`/r/${r.slug}`}
                        target="_blank"
                        rel="noreferrer"
                        className="rounded-full border border-border bg-background px-2 py-0.5 hover:bg-muted"
                      >
                        View
                      </a>
                    )}
                    <Link
                      to="/admin/reviews/$id"
                      params={{ id: String(r.id) }}
                      className="rounded-full border border-border bg-background px-2 py-0.5 hover:bg-muted"
                    >
                      Edit
                    </Link>
                    <button
                      onClick={() => onDelete(r.id)}
                      className="rounded-full border border-destructive/30 bg-background px-2 py-0.5 text-destructive hover:bg-destructive/10"
                    >
                      Delete
                    </button>
                  </div>
                </td>
              </tr>
            ))}
            {data && data.items.length === 0 && (
              <tr>
                <td colSpan={6} className="p-4 text-center text-muted-foreground">
                  No reviews yet. Create one, or promote a draft from Bulk Generate.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
