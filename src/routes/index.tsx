import { createFileRoute, Link } from "@tanstack/react-router";
import { Award, ShieldCheck, Tag, TrendingDown, Sparkles } from "lucide-react";
import { POPULAR_CATEGORIES, SITE_DESCRIPTION, SITE_NAME, SITE_TAGLINE } from "@/config/site";
import { SearchBar } from "@/components/ui/SearchBar";
import { AffiliateDisclosure } from "@/components/sections/AffiliateDisclosure";

export const Route = createFileRoute("/")({
  head: () => ({
    meta: [
      { title: `${SITE_NAME} — ${SITE_TAGLINE}` },
      { name: "description", content: SITE_DESCRIPTION },
      { property: "og:title", content: `${SITE_NAME} — ${SITE_TAGLINE}` },
      { property: "og:description", content: SITE_DESCRIPTION },
      { property: "og:url", content: "/" },
      { property: "og:type", content: "website" },
    ],
    links: [{ rel: "canonical", href: "/" }],
  }),
  component: Home,
});

function Home() {
  return (
    <>
      <AffiliateDisclosure />

      {/* Hero */}
      <section className="container-page pt-10 pb-12 md:pt-16 md:pb-20">
        <div className="mx-auto max-w-3xl text-center">
          <div className="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-3 py-1 text-xs font-medium text-muted-foreground">
            <Sparkles className="h-3 w-3 text-amber" />
            Updated every hour with live Amazon data
          </div>
          <h1 className="mt-5 font-serif text-5xl leading-[1.05] tracking-tight text-foreground md:text-7xl">
            Find the <span className="text-amber">best</span> product.
            <br /> Skip the research.
          </h1>
          <p className="mx-auto mt-5 max-w-xl text-base text-muted-foreground md:text-lg">
            Search any product. We instantly rank the top 10 on Amazon, compare
            them side-by-side, and show you the best value — fact-checked
            against live listings.
          </p>

          <div className="mt-8">
            <SearchBar size="lg" />
          </div>

          {/* Trending */}
          <div className="mt-6 flex flex-wrap items-center justify-center gap-2 text-xs">
            <span className="text-muted-foreground">Trending:</span>
            {["running shoes", "robot vacuum", "espresso machine", "4k tv", "ergonomic chair"].map(
              (q) => (
                <Link
                  key={q}
                  to="/product/$slug"
                  params={{ slug: q.replace(/\s+/g, "-") }}
                  className="rounded-full border border-border bg-card px-3 py-1 text-foreground/80 hover:bg-muted hover:text-foreground"
                >
                  {q}
                </Link>
              ),
            )}
          </div>
        </div>
      </section>

      {/* Categories */}
      <section className="container-page py-8">
        <h2 className="font-serif text-3xl text-foreground md:text-4xl">Popular categories</h2>
        <p className="mt-1 text-sm text-muted-foreground">
          Start with one of these, or search anything above.
        </p>
        <div className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
          {POPULAR_CATEGORIES.map((c) => (
            <Link
              key={c.slug}
              to="/product/$slug"
              params={{ slug: c.slug }}
              className="group flex flex-col items-start gap-2 rounded-2xl border border-border bg-card p-5 shadow-card transition-all hover:-translate-y-0.5 hover:shadow-lift"
            >
              <span className="text-3xl" aria-hidden="true">
                {c.emoji}
              </span>
              <span className="font-medium text-foreground group-hover:text-amber">
                {c.label}
              </span>
              <span className="text-xs text-muted-foreground">Top 10 picks →</span>
            </Link>
          ))}
        </div>
      </section>

      {/* Trust */}
      <section className="container-page py-16">
        <div className="grid gap-5 md:grid-cols-3">
          <TrustCard
            icon={<Award className="h-5 w-5" />}
            title="Expert-curated rankings"
            body="Rule-based scoring that's the same for every product. No paid placements, no surprise sponsors."
          />
          <TrustCard
            icon={<TrendingDown className="h-5 w-5" />}
            title="Live price tracking"
            body="Prices and availability refresh hourly. We show timestamps so you always know how fresh the data is."
          />
          <TrustCard
            icon={<ShieldCheck className="h-5 w-5" />}
            title="Fact-grounded reviews"
            body="Every spec on this site comes from the Amazon listing itself — we never invent claims we can't back up."
          />
        </div>
      </section>

      {/* Footer disclosure */}
      <section className="container-page pb-16">
        <div className="rounded-2xl border border-border bg-card p-6 text-sm text-muted-foreground shadow-card md:p-8">
          <div className="flex items-start gap-3">
            <Tag className="mt-0.5 h-5 w-5 shrink-0 text-amber" />
            <p>
              <strong className="text-foreground">{SITE_NAME}</strong> is a
              participant in the Amazon Services LLC Associates Program. As an
              Amazon Associate, we earn from qualifying purchases. Rankings are
              independent of any commercial relationship.{" "}
              <Link to="/disclosure" className="underline underline-offset-2">
                Full disclosure
              </Link>{" "}
              ·{" "}
              <Link to="/methodology" className="underline underline-offset-2">
                How we rank
              </Link>
            </p>
          </div>
        </div>
      </section>
    </>
  );
}

function TrustCard({
  icon,
  title,
  body,
}: {
  icon: React.ReactNode;
  title: string;
  body: string;
}) {
  return (
    <div className="rounded-2xl border border-border bg-card p-6 shadow-card">
      <div className="inline-flex h-9 w-9 items-center justify-center rounded-full bg-amber-soft text-amber-foreground">
        {icon}
      </div>
      <h3 className="mt-4 font-serif text-xl text-foreground">{title}</h3>
      <p className="mt-1 text-sm text-muted-foreground">{body}</p>
    </div>
  );
}
