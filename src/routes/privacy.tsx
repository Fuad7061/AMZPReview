import { createFileRoute } from "@tanstack/react-router";
import { SITE_NAME } from "@/config/site";

export const Route = createFileRoute("/privacy")({
  head: () => ({
    meta: [
      { title: `Privacy — ${SITE_NAME}` },
      { name: "description", content: `Privacy notice for ${SITE_NAME}.` },
      { property: "og:title", content: `Privacy — ${SITE_NAME}` },
      { property: "og:description", content: `Privacy notice for ${SITE_NAME}.` },
      { property: "og:url", content: "/privacy" },
    ],
    links: [{ rel: "canonical", href: "/privacy" }],
  }),
  component: PrivacyPage,
});

function PrivacyPage() {
  return (
    <article className="container-page mx-auto max-w-3xl py-12 md:py-20">
      <p className="text-xs font-semibold uppercase tracking-wider text-amber">Legal</p>
      <h1 className="mt-2 font-serif text-5xl text-foreground">Privacy</h1>
      <p className="mt-5 text-muted-foreground">
        This page is maintained by the {SITE_NAME} team to answer common
        questions about how this site handles your information.
      </p>

      <section className="mt-8 space-y-4 text-muted-foreground">
        <h2 className="font-serif text-2xl text-foreground">What we collect</h2>
        <p>
          When you submit the price-alert form, we collect the email address
          you provide so we can notify you about price drops on the product you
          requested. We do not collect addresses, payment info, or contact
          lists.
        </p>

        <h2 className="font-serif text-2xl text-foreground">Cookies</h2>
        <p>
          We use minimal localStorage to remember your theme preference (light
          or dark). We do not run third-party advertising trackers on this
          site. Amazon may set its own cookies when you click an affiliate
          link and visit Amazon.com.
        </p>

        <h2 className="font-serif text-2xl text-foreground">Sharing</h2>
        <p>
          We do not sell your data. Affiliate link clicks are tracked in
          aggregate to understand which picks are useful; this data does not
          include your email or identity.
        </p>

        <h2 className="font-serif text-2xl text-foreground">Your rights</h2>
        <p>
          You can unsubscribe from price alerts at any time using the link in
          any alert email. To request deletion of your email from our list,
          contact us through the footer.
        </p>
      </section>
    </article>
  );
}
