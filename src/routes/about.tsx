import { createFileRoute, Link } from "@tanstack/react-router";
import { SITE_NAME } from "@/config/site";

export const Route = createFileRoute("/about")({
  head: () => ({
    meta: [
      { title: `About — ${SITE_NAME}` },
      { name: "description", content: `What ${SITE_NAME} is, how it works, and why we built it.` },
      { property: "og:title", content: `About — ${SITE_NAME}` },
      { property: "og:description", content: `What ${SITE_NAME} is, how it works, and why we built it.` },
      { property: "og:url", content: "/about" },
    ],
    links: [{ rel: "canonical", href: "/about" }],
  }),
  component: AboutPage,
});

function AboutPage() {
  return (
    <article className="container-page mx-auto max-w-3xl py-12 md:py-20">
      <p className="text-xs font-semibold uppercase tracking-wider text-amber">About</p>
      <h1 className="mt-2 font-serif text-5xl text-foreground">Why {SITE_NAME} exists</h1>
      <p className="mt-5 text-lg text-muted-foreground">
        Shopping on Amazon is overwhelming. Every product has thousands of
        listings, dozens of variants, and pricing that changes hourly.{" "}
        {SITE_NAME} cuts through the noise: search any product and instantly
        see the top 10, ranked and compared with live data.
      </p>

      <div className="prose-section">
        <Section title="What we do">
          <p>
            We pull live product data from Amazon, score and rank the top 10,
            and present them on a single page with the comparison, pricing, and
            features you'd otherwise have to gather across dozens of tabs.
          </p>
        </Section>

        <Section title="What we don't do">
          <ul className="list-inside list-disc space-y-1">
            <li>We don't physically test products — we surface and structure third-party data.</li>
            <li>We don't take payment for placement. Ranking is rule-based and the same for every product.</li>
            <li>We don't invent specs, ratings, or reviews. If it isn't in the listing, we don't claim it.</li>
          </ul>
        </Section>

        <Section title="How we make money">
          <p>
            As an Amazon Associate, we earn a small commission when you buy
            through our links — at no extra cost to you. This relationship has
            zero influence on ranking. See our full{" "}
            <Link to="/disclosure" className="underline">affiliate disclosure</Link> and{" "}
            <Link to="/methodology" className="underline">methodology</Link>.
          </p>
        </Section>
      </div>
    </article>
  );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="mt-10">
      <h2 className="font-serif text-3xl text-foreground">{title}</h2>
      <div className="mt-3 text-base leading-relaxed text-muted-foreground">{children}</div>
    </section>
  );
}
