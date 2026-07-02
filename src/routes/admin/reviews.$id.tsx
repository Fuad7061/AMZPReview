import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { useServerFn } from "@tanstack/react-start";
import { useEffect, useState } from "react";
import { deleteReview, getReview, updateReview } from "@/lib/reviews.functions";
import { ReviewForm } from "./reviews.new";

export const Route = createFileRoute("/admin/reviews/$id")({
  component: EditReviewPage,
});

function EditReviewPage() {
  const { id } = Route.useParams();
  const load = useServerFn(getReview);
  const save = useServerFn(updateReview);
  const remove = useServerFn(deleteReview);
  const navigate = useNavigate();
  const [form, setForm] = useState<{
    title: string;
    keyword: string;
    slug: string;
    markdown: string;
    metaTitle: string;
    metaDescription: string;
    status: "draft" | "published";
  } | null>(null);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(null);
  const [saved, setSaved] = useState<string | null>(null);

  useEffect(() => {
    load({ data: { id: Number(id) } })
      .then((r) =>
        setForm({
          title: r.title,
          keyword: r.keyword,
          slug: r.slug,
          markdown: r.markdown,
          metaTitle: r.metaTitle,
          metaDescription: r.metaDescription,
          status: r.status === "published" ? "published" : "draft",
        }),
      )
      .catch((e) => setErr(e instanceof Error ? e.message : "Failed"));
  }, [id, load]);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!form) return;
    setBusy(true);
    setErr(null);
    setSaved(null);
    try {
      await save({ data: { id: Number(id), ...form } });
      setSaved("Saved.");
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Failed");
    } finally {
      setBusy(false);
    }
  }

  async function onDelete() {
    if (!confirm("Delete this review permanently?")) return;
    await remove({ data: { id: Number(id) } });
    navigate({ to: "/admin/reviews" });
  }

  if (err && !form) return <p className="text-sm text-destructive">{err}</p>;
  if (!form) return <p className="text-sm text-muted-foreground">Loading…</p>;

  return (
    <ReviewForm
      form={form}
      setForm={(u) => setForm((prev) => (prev ? u(prev) : prev))}
      onSubmit={onSubmit}
      busy={busy}
      err={err}
      title={`Edit: ${form.title || "(untitled)"}`}
      extra={
        <div className="flex items-center gap-2">
          {saved && <span className="text-sm text-green-700">{saved}</span>}
          <button
            type="button"
            onClick={onDelete}
            className="rounded-full border border-destructive/30 bg-background px-3 py-1.5 text-xs font-medium text-destructive hover:bg-destructive/10"
          >
            Delete
          </button>
        </div>
      }
    />
  );
}
