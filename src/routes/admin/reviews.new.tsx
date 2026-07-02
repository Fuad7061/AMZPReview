import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { useServerFn } from "@tanstack/react-start";
import { useState } from "react";
import { createReview } from "@/lib/reviews.functions";

export const Route = createFileRoute("/admin/reviews/new")({
  component: NewReviewPage,
});

function NewReviewPage() {
  const create = useServerFn(createReview);
  const navigate = useNavigate();
  const [form, setForm] = useState({
    title: "",
    keyword: "",
    slug: "",
    markdown: "# New review\n\nWrite here…",
    metaTitle: "",
    metaDescription: "",
    status: "draft" as "draft" | "published",
  });
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setErr(null);
    try {
      const r = await create({ data: form });
      navigate({ to: "/admin/reviews/$id", params: { id: String(r.id) } });
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Failed");
    } finally {
      setBusy(false);
    }
  }

  return (
    <ReviewForm
      form={form}
      setForm={setForm}
      onSubmit={onSubmit}
      busy={busy}
      err={err}
      title="New review"
    />
  );
}

export function ReviewForm({
  form,
  setForm,
  onSubmit,
  busy,
  err,
  title,
  extra,
}: {
  form: {
    title: string;
    keyword: string;
    slug: string;
    markdown: string;
    metaTitle: string;
    metaDescription: string;
    status: "draft" | "published";
  };
  setForm: (
    updater: (f: {
      title: string;
      keyword: string;
      slug: string;
      markdown: string;
      metaTitle: string;
      metaDescription: string;
      status: "draft" | "published";
    }) => typeof form,
  ) => void;
  onSubmit: (e: React.FormEvent) => void;
  busy: boolean;
  err: string | null;
  title: string;
  extra?: React.ReactNode;
}) {
  return (
    <form onSubmit={onSubmit} className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="font-serif text-xl">{title}</h2>
        {extra}
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Title">
          <input
            required
            className="input"
            value={form.title}
            onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))}
          />
        </Field>
        <Field label="Keyword">
          <input
            required
            className="input"
            value={form.keyword}
            onChange={(e) => setForm((f) => ({ ...f, keyword: e.target.value }))}
          />
        </Field>
        <Field label="Slug (optional — auto from title)">
          <input
            className="input"
            value={form.slug}
            onChange={(e) => setForm((f) => ({ ...f, slug: e.target.value }))}
          />
        </Field>
        <Field label="Status">
          <select
            className="input"
            value={form.status}
            onChange={(e) => setForm((f) => ({ ...f, status: e.target.value as "draft" | "published" }))}
          >
            <option value="draft">Draft</option>
            <option value="published">Published</option>
          </select>
        </Field>
      </div>

      <Field label="Meta title">
        <input
          className="input"
          value={form.metaTitle}
          onChange={(e) => setForm((f) => ({ ...f, metaTitle: e.target.value }))}
        />
      </Field>
      <Field label="Meta description">
        <textarea
          className="input h-20"
          value={form.metaDescription}
          onChange={(e) => setForm((f) => ({ ...f, metaDescription: e.target.value }))}
        />
      </Field>

      <Field label="Markdown">
        <textarea
          required
          className="input h-96 font-mono text-xs"
          value={form.markdown}
          onChange={(e) => setForm((f) => ({ ...f, markdown: e.target.value }))}
        />
      </Field>

      <div className="flex items-center gap-3">
        <button
          type="submit"
          disabled={busy}
          className="rounded-full bg-amber px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-amber/90 disabled:opacity-60"
        >
          {busy ? "Saving…" : "Save"}
        </button>
        {err && <span className="text-sm text-destructive">{err}</span>}
      </div>

      <style>{`.input{width:100%;border-radius:0.375rem;border:1px solid hsl(var(--input));background:hsl(var(--background));padding:0.5rem 0.75rem;font-size:0.875rem}`}</style>
    </form>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <label className="block">
      <span className="text-sm font-medium">{label}</span>
      <div className="mt-1">{children}</div>
    </label>
  );
}
