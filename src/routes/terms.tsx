import { createFileRoute } from "@tanstack/react-router";
import { SITE_NAME } from "@/config/site";

export const Route = createFileRoute("/terms")({
  head: () => ({
    meta: [
      { title: `Terms of use — ${SITE_NAME}` },
      { name: "description", content: `Terms of use for ${SITE_NAME}.` },
      { property: "og:title", content: `Terms of use — ${SITE_NAME}` },
      { property: "og:description", content: `Terms of use for ${SITE_NAME}.` },
      { property: "og:url", content: "/terms" },
    ],
    links: [{ rel: "canonical", href: "/terms" }],
  }),
  component: TermsPage,
});

function TermsPage() {
  return (
    <article className="container-page mx-auto max-w-3xl py-12 md:py-20">
      <p className="text-xs font-semibold uppercase tracking-wider text-amber">Legal</p>
      <h1 className="mt-2 font-serif text-5xl text-foreground">Terms of use</h1>

      <section className="mt-8 space-y-4 text-muted-foreground">
        <p>
          By using {SITE_NAME}, you agree to these terms. The information on
          this site is provided "as is" without warranty of any kind. Product
          details, pricing, and availability are sourced from Amazon.com and
          may not always be current — always confirm the live price on Amazon
          before purchasing.
        </p>
        <p>
          {SITE_NAME} is not affiliated with Amazon.com beyond participation in
          the Amazon Services LLC Associates Program. Product trademarks and
          brand names are the property of their respective owners.
        </p>
        <p>
          Rankings, scores, and editorial picks are our opinion based on
          publicly available product data, not professional product reviews or
          purchasing advice. Make your own informed decision before buying.
        </p>
      </section>
    </article>
  );
}
