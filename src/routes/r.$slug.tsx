/**
 * Public route: render a published review with full SEO metadata
 * (canonical, og:*, twitter card, Article + BreadcrumbList JSON-LD).
 * All product data (prices, ratings, images) still comes live from
 * the configured Lambda endpoint — nothing is stored server-side
 * except the editorial markdown itself.
 */
import { createFileRoute, notFound } from "@tanstack/react-router";
import { getPublishedReview } from "@/lib/reviews.functions";
import { getPublicSiteContext } from "@/lib/admin.functions";

export const Route = createFileRoute("/r/$slug")({
  loader: async ({ params }) => {
    const [r, ctx] = await Promise.all([
      getPublishedReview({ data: { slug: params.slug } }),
      getPublicSiteContext(),
    ]);
    if (!r) throw notFound();
    return { review: r, ctx };
  },
  head: ({ loaderData, params }) => {
    if (!loaderData) return { meta: [{ title: "Review" }] };
    const { review: r, ctx } = loaderData;
    const base = (ctx.siteUrl || "").replace(/\/$/, "");
    const url = `${base}/r/${params.slug}`;
    const title = r.metaTitle || r.title;
    const description = r.metaDescription || r.title;
    const published = r.publishedAt || new Date().toISOString();

    const articleJsonLd = {
      "@context": "https://schema.org",
      "@type": "Article",
      headline: r.title,
      description,
      datePublished: published,
      dateModified: published,
      mainEntityOfPage: { "@type": "WebPage", "@id": url },
      author: {
        "@type": "Organization",
        name: ctx.siteName || "YAD Foods Editorial",
      },
      publisher: {
        "@type": "Organization",
        name: ctx.siteName || "YAD Foods",
        url: base || undefined,
      },
      keywords: r.keyword,
    };

    const breadcrumbJsonLd = {
      "@context": "https://schema.org",
      "@type": "BreadcrumbList",
      itemListElement: [
        { "@type": "ListItem", position: 1, name: "Home", item: base || "/" },
        { "@type": "ListItem", position: 2, name: "Reviews", item: `${base}/r` },
        { "@type": "ListItem", position: 3, name: r.title, item: url },
      ],
    };

    return {
      meta: [
        { title },
        { name: "description", content: description },
        { name: "keywords", content: r.keyword },
        { property: "og:title", content: title },
        { property: "og:description", content: description },
        { property: "og:type", content: "article" },
        { property: "og:url", content: url },
        { property: "article:published_time", content: published },
        { property: "article:modified_time", content: published },
        { name: "twitter:card", content: "summary_large_image" },
        { name: "twitter:title", content: title },
        { name: "twitter:description", content: description },
      ],
      links: base ? [{ rel: "canonical", href: url }] : [],
      scripts: [
        {
          type: "application/ld+json",
          children: JSON.stringify(articleJsonLd),
        },
        {
          type: "application/ld+json",
          children: JSON.stringify(breadcrumbJsonLd),
        },
      ],
    };
  },
  component: PublishedReview,
  notFoundComponent: () => (
    <div className="mx-auto max-w-2xl px-4 py-16 text-center">
      <h1 className="font-serif text-3xl">Review not found</h1>
      <p className="mt-2 text-sm text-muted-foreground">
        This review may have been unpublished or moved.
      </p>
    </div>
  ),
  errorComponent: ({ error }) => (
    <div className="mx-auto max-w-2xl px-4 py-16 text-center">
      <h1 className="font-serif text-3xl">Something went wrong</h1>
      <p className="mt-2 text-sm text-destructive">{error.message}</p>
    </div>
  ),
});

function PublishedReview() {
  const { review: r } = Route.useLoaderData();
  return (
    <article className="mx-auto max-w-3xl px-4 py-10">
      <header className="mb-8 border-b border-border pb-6">
        <h1 className="font-serif text-4xl leading-tight">{r.title}</h1>
        {r.publishedAt && (
          <p className="mt-2 text-xs text-muted-foreground">
            Published {new Date(r.publishedAt).toLocaleDateString()} · keyword:{" "}
            <span className="font-mono">{r.keyword}</span>
          </p>
        )}
      </header>
      <div className="prose prose-sm max-w-none whitespace-pre-wrap font-mono text-sm">
        {r.markdown}
      </div>
    </article>
  );
}
