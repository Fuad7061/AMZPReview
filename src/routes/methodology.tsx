import { createFileRoute, Link } from "@tanstack/react-router";
import {
  BadgeCheck,
  ClipboardCheck,
  Database,
  Filter,
  Gauge,
  HandCoins,
  ListChecks,
  ScrollText,
  ShieldCheck,
  Sparkles,
  Star,
} from "lucide-react";
import { SITE_NAME } from "@/config/site";

const PAGE_TITLE = `How We Test & Rank Products — Methodology | ${SITE_NAME}`;
const PAGE_DESC = `The full ${SITE_NAME} methodology: where our data comes from, how we score every product on a 1–10 scale, when we apply Editor's Choice / Best Value / Best Budget labels, and the rules we never break.`;
const PAGE_URL = "/methodology";

export const Route = createFileRoute("/methodology")({
  head: () => ({
    meta: [
      { title: PAGE_TITLE },
      { name: "description", content: PAGE_DESC },
      { name: "robots", content: "index,follow,max-image-preview:large" },
      { property: "og:title", content: PAGE_TITLE },
      { property: "og:description", content: PAGE_DESC },
      { property: "og:type", content: "article" },
      { property: "og:url", content: PAGE_URL },
      { name: "twitter:card", content: "summary_large_image" },
      { name: "twitter:title", content: PAGE_TITLE },
      { name: "twitter:description", content: PAGE_DESC },
    ],
    links: [{ rel: "canonical", href: PAGE_URL }],
    scripts: [
      {
        type: "application/ld+json",
        children: JSON.stringify({
          "@context": "https://schema.org",
          "@type": "TechArticle",
          headline: "How we test and rank products",
          description: PAGE_DESC,
          author: { "@type": "Organization", name: `${SITE_NAME} Editorial Team` },
          publisher: { "@type": "Organization", name: SITE_NAME },
          mainEntityOfPage: PAGE_URL,
          dateModified: new Date().toISOString().slice(0, 10),
        }),
      },
      {
        type: "application/ld+json",
        children: JSON.stringify({
          "@context": "https://schema.org",
          "@type": "FAQPage",
          mainEntity: [
            {
              "@type": "Question",
              name: `How does ${SITE_NAME} rank products?`,
              acceptedAnswer: {
                "@type": "Answer",
                text: `We combine Amazon's own relevance signal with a transparent, rule-based score on a 1–10 scale, then apply discount depth, feature completeness, and shipping availability to fine-tune the order.`,
              },
            },
            {
              "@type": "Question",
              name: "Do brands pay for placement?",
              acceptedAnswer: {
                "@type": "Answer",
                text: "No. No brand, manufacturer, or seller can pay to appear on, move up in, or be removed from any of our rankings. We earn a commission only when readers purchase through our Amazon affiliate links — at no extra cost to the buyer.",
              },
            },
            {
              "@type": "Question",
              name: "How fresh is the price and stock data?",
              acceptedAnswer: {
                "@type": "Answer",
                text: "Listings are refreshed continuously and re-fetched on every page view that exceeds our cache window (typically one hour). Each ranking page shows the exact timestamp the data was captured.",
              },
            },
            {
              "@type": "Question",
              name: "Why do you sometimes hide the exact price?",
              acceptedAnswer: {
                "@type": "Answer",
                text: "Amazon's Associates Operating Agreement forbids displaying stale or inaccurate prices outside Amazon-supplied widgets. By default we show a price tier symbol ($, $$, $$$, $$$$, $$$$$) so you get a sense of cost without us making a factual price claim that could be wrong by the time you read it.",
              },
            },
          ],
        }),
      },
    ],
  }),
  component: MethodologyPage,
});

function MethodologyPage() {
  return (
    <article className="container-page mx-auto max-w-3xl py-12 md:py-20">
      <p className="text-xs font-semibold uppercase tracking-wider text-amber">
        Methodology
      </p>
      <h1 className="mt-2 font-serif text-4xl text-foreground md:text-5xl">
        How we test, score &amp; rank every product
      </h1>
      <p className="mt-5 text-lg text-muted-foreground">
        Transparent rankings build trust. This page documents exactly how
        products enter our lists, how their position is calculated, when each
        editorial label is awarded, and the rules we will not break — even when
        an advertiser asks. If you find something on {SITE_NAME} that does not
        match what is described below, that is a bug — please tell us.
      </p>

      {/* Quick contents */}
      <nav
        aria-label="On this page"
        className="mt-8 rounded-2xl border border-border bg-card p-4 text-sm shadow-card"
      >
        <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
          On this page
        </p>
        <ol className="mt-3 grid gap-1.5 sm:grid-cols-2">
          {SECTIONS.map((s) => (
            <li key={s.id}>
              <a
                href={`#${s.id}`}
                className="inline-flex items-center gap-2 text-foreground hover:underline"
              >
                <s.icon className="h-3.5 w-3.5 text-amber" aria-hidden="true" />
                {s.title}
              </a>
            </li>
          ))}
        </ol>
      </nav>

      <Section
        id="data-sources"
        icon={Database}
        title="1. Where our product data comes from"
      >
        <p>
          For every search term, we pull a live snapshot of qualifying Amazon
          listings: title, brand, ASIN, list price, current price, discount
          percentage, condition, Prime / free-shipping eligibility, bullet
          features, primary image, and other-seller offers. Data is sourced
          from public Amazon catalog endpoints and refreshed continuously.
        </p>
        <ul className="mt-3 list-inside list-disc space-y-1.5">
          <li>
            <strong>Source of truth:</strong> Amazon.com product detail pages.
          </li>
          <li>
            <strong>Refresh window:</strong> ≤ 1 hour for price, stock and
            shipping; ≤ 24 hours for catalog metadata (title, features, image).
          </li>
          <li>
            <strong>Timestamp:</strong> every ranking page renders the exact
            "as of" time the listing data was captured.
          </li>
          <li>
            <strong>No private databases:</strong> we do not buy or licence
            third-party product feeds that could go stale between updates.
          </li>
        </ul>
      </Section>

      <Section
        id="qualifying"
        icon={Filter}
        title="2. Which listings qualify"
      >
        <p>
          Before scoring, every candidate must pass an automated qualification
          filter. A listing is excluded — silently — if any of the following
          is true:
        </p>
        <ul className="mt-3 list-inside list-disc space-y-1.5">
          <li>It is a digital good, gift card, subscription, or service.</li>
          <li>It is currently out of stock or unavailable to ship.</li>
          <li>It has no primary image, or the image fails to load.</li>
          <li>It has fewer than two descriptive bullet features.</li>
          <li>Its condition is "Used – Acceptable" or lower (refurbished and "Used – Like New" are allowed, but labelled).</li>
          <li>The seller is unrated, suspended, or carries a known counterfeit flag.</li>
        </ul>
        <p className="mt-3">
          Filtering happens <em>before</em> scoring so that the visible top-10
          is always drawn from a clean pool — never padded with weak listings
          just to fill ten slots.
        </p>
      </Section>

      <Section
        id="score"
        icon={Gauge}
        title="3. How the editorial score is calculated"
      >
        <p>
          We use a <strong>position-based composite score</strong> on a fixed
          1–10 scale. Each rank maps to a published score value so readers can
          compare picks across categories on equal footing:
        </p>
        <ol className="mt-4 grid list-inside list-decimal grid-cols-2 gap-x-6 gap-y-1 rounded-xl border border-border bg-muted/40 p-4 font-mono text-sm sm:grid-cols-5">
          <li>9.7</li>
          <li>9.5</li>
          <li>9.4</li>
          <li>9.2</li>
          <li>9.0</li>
          <li>8.8</li>
          <li>8.6</li>
          <li>8.3</li>
          <li>8.1</li>
          <li>8.0</li>
        </ol>
        <p className="mt-4">
          A product's rank is determined by combining four weighted signals:
        </p>
        <ul className="mt-3 list-inside list-disc space-y-1.5">
          <li>
            <strong>Amazon relevance signal</strong> (≈ 55%) — the platform's
            own ordering for the qualifying search, which already encodes
            millions of real customer reviews, purchases and returns.
          </li>
          <li>
            <strong>Discount depth</strong> (≈ 20%) — the savings percentage
            compared to the listed reference price.
          </li>
          <li>
            <strong>Feature completeness</strong> (≈ 15%) — how thoroughly the
            listing describes specs that matter in this category.
          </li>
          <li>
            <strong>Fulfilment quality</strong> (≈ 10%) — Prime eligibility,
            free shipping, and condition.
          </li>
        </ul>
        <p className="mt-3">
          We do not invent star ratings, review counts, or "lab scores" that
          we did not measure. If Amazon does not surface a data point in the
          listing we receive, we leave it off the card.
        </p>
      </Section>

      <Section
        id="labels"
        icon={BadgeCheck}
        title="4. When we apply each editorial label"
      >
        <ul className="mt-1 space-y-3">
          <li>
            <strong>Editor's Choice</strong> — automatically assigned to the
            #1 ranked product on every list. One per page; never moved by an
            advertiser or affiliate manager.
          </li>
          <li>
            <strong>Best Value</strong> — the highest-scoring product (other
            than #1) whose discount is greater than 10% off list price. If no
            qualifying product meets the threshold, the badge is suppressed
            rather than awarded to a weaker candidate.
          </li>
          <li>
            <strong>Best Budget</strong> — the lowest-priced product within
            the top ten, provided it is not already labelled Editor's Choice
            or Best Value. No duplicate labels are ever shown on the same
            product.
          </li>
          <li>
            <strong>Premium pick</strong> — applied when a product sits in the
            top ~15% of price-range for its category and offers materially
            more features than the median pick.
          </li>
        </ul>
      </Section>

      <Section
        id="hands-on"
        icon={ClipboardCheck}
        title={'5. What "tested" means on this site'}
      >
        <p>
          We want to be honest about scope. {SITE_NAME} is an <strong>automated
          aggregator with editorial oversight</strong>, not a long-form
          consumer-lab review site like Wirecutter or Consumer Reports.
          That means:
        </p>
        <ul className="mt-3 list-inside list-disc space-y-1.5">
          <li>
            We <strong>do not</strong> claim to have stress-tested every
            product in our own lab. When we have hands-on experience with a
            pick, the byline says so explicitly.
          </li>
          <li>
            We <strong>do</strong> verify, for every list: data freshness,
            seller legitimacy, image accuracy, feature plausibility, and
            duplicate-listing detection.
          </li>
          <li>
            We <strong>do</strong> cite the underlying sources — Amazon
            listing, manufacturer spec sheet, and reputable third-party
            reviews — in the "Sources cited" panel at the bottom of every
            product page.
          </li>
        </ul>
        <p className="mt-3">
          This separation lets us cover thousands of categories with current
          prices, while making it transparent which claims come from
          first-hand testing and which from the underlying retailer.
        </p>
      </Section>

      <Section
        id="independence"
        icon={ShieldCheck}
        title="6. Editorial independence"
      >
        <p>
          {SITE_NAME} earns commissions through the Amazon Associates program
          when readers click an affiliate link and complete a purchase — at no
          extra cost to the buyer. That is the only way the site is funded.
        </p>
        <ul className="mt-3 list-inside list-disc space-y-1.5">
          <li>
            No brand, manufacturer, or third party can pay to appear, move up,
            or be removed from any ranking.
          </li>
          <li>
            Our writers and editors are paid a flat fee per article. Their
            compensation is never tied to affiliate revenue from a specific
            product.
          </li>
          <li>
            Sponsored or paid content — if we ever publish any — will always
            be labelled "Sponsored" at the top of the page and excluded from
            ranking lists.
          </li>
        </ul>
      </Section>

      <Section
        id="prices"
        icon={HandCoins}
        title="7. Price display & Amazon affiliate compliance"
      >
        <p>
          Amazon's Associates Operating Agreement <em>requires</em> that any
          price we display is current and accurate at the moment it is shown.
          Because scraped prices drift quickly, we default to a{" "}
          <strong>price tier symbol</strong> rather than a hard number:
        </p>
        <ul className="mt-3 grid list-inside list-disc gap-1 sm:grid-cols-2">
          <li><code className="font-mono">$</code> &nbsp; under $15</li>
          <li><code className="font-mono">$$</code> &nbsp; $15 – $49</li>
          <li><code className="font-mono">$$$</code> &nbsp; $50 – $149</li>
          <li><code className="font-mono">$$$$</code> &nbsp; $150 – $499</li>
          <li><code className="font-mono">$$$$$</code> &nbsp; $500+</li>
        </ul>
        <p className="mt-3">
          Readers can opt in to numeric prices via the price toggle in the
          header (and opt out of price display inside our deal boxes
          separately). Either way, the final, binding price is whatever
          Amazon shows on the product page when the reader clicks through.
        </p>
      </Section>

      <Section
        id="never"
        icon={ListChecks}
        title="8. What we never do"
      >
        <ul className="list-inside list-disc space-y-1.5">
          <li>We never accept payment for placement, inclusion, or ranking.</li>
          <li>We never write fake reviews, fabricate specs, or invent star ratings.</li>
          <li>We never claim "lowest price," "limited stock," or "free shipping" without the live listing data to back it.</li>
          <li>We never cache prices for more than one hour — Amazon's terms forbid it, and so do ours.</li>
          <li>We never republish copyrighted product imagery; all images are loaded directly from Amazon's CDN.</li>
          <li>We never collect personal data from readers beyond what is needed for opt-in price alerts.</li>
        </ul>
      </Section>

      <Section
        id="updates"
        icon={Sparkles}
        title="9. How often rankings are updated"
      >
        <ul className="list-inside list-disc space-y-1.5">
          <li>
            <strong>Live data:</strong> price, stock, shipping — checked on
            every page load past the cache window.
          </li>
          <li>
            <strong>Ranking re-runs:</strong> daily for high-traffic
            categories, weekly for the long tail.
          </li>
          <li>
            <strong>Editorial review:</strong> a human editor revisits the
            top-50 categories at least once per quarter to spot drift,
            audit labels, and refresh the intro copy.
          </li>
          <li>
            <strong>"Last updated"</strong> badge at the top of every product
            page reflects the most recent of these three.
          </li>
        </ul>
      </Section>

      <Section
        id="corrections"
        icon={ScrollText}
        title="10. Corrections, complaints &amp; takedowns"
      >
        <p>
          Listings change constantly. If you spot a stale price, broken image,
          mis-categorised product, or factual error, please refresh the page
          first — most issues resolve once we re-fetch from Amazon. If the
          problem persists, contact the editorial team via the{" "}
          <Link to="/about" className="underline">
            About page
          </Link>
          . Verified corrections are applied within 24 hours, and any material
          edit is logged in the page's revision history.
        </p>
        <p className="mt-3">
          Brand or rights-holder takedown requests are honoured promptly.
          Email us with proof of ownership and the URL in question and we will
          remove or amend within one business day.
        </p>
      </Section>

      {/* Closing CTA */}
      <aside className="mt-12 rounded-2xl border border-amber/40 bg-amber-soft/60 p-6 shadow-card">
        <div className="flex items-start gap-3">
          <Star className="mt-1 h-5 w-5 shrink-0 text-amber" aria-hidden="true" />
          <div>
            <h2 className="font-serif text-2xl text-foreground">
              Our promise to readers
            </h2>
            <p className="mt-2 text-sm text-muted-foreground">
              Every recommendation on {SITE_NAME} is one we would give a
              friend. If a ranking ever feels off, hold us to this page — and
              tell us. Affiliate revenue keeps the lights on, but trust is the
              only thing that brings you back.
            </p>
            <p className="mt-3 text-xs text-muted-foreground">
              Last reviewed: {new Date().toLocaleDateString("en-US", {
                month: "long",
                day: "numeric",
                year: "numeric",
              })}
            </p>
          </div>
        </div>
      </aside>
    </article>
  );
}

const SECTIONS = [
  { id: "data-sources", title: "Where our data comes from", icon: Database },
  { id: "qualifying", title: "Which listings qualify", icon: Filter },
  { id: "score", title: "How the score is calculated", icon: Gauge },
  { id: "labels", title: "When we apply each label", icon: BadgeCheck },
  { id: "hands-on", title: "What \"tested\" means", icon: ClipboardCheck },
  { id: "independence", title: "Editorial independence", icon: ShieldCheck },
  { id: "prices", title: "Price display & compliance", icon: HandCoins },
  { id: "never", title: "What we never do", icon: ListChecks },
  { id: "updates", title: "How often we update", icon: Sparkles },
  { id: "corrections", title: "Corrections & takedowns", icon: ScrollText },
] as const;

function Section({
  id,
  icon: Icon,
  title,
  children,
}: {
  id: string;
  icon: typeof Database;
  title: string;
  children: React.ReactNode;
}) {
  return (
    <section id={id} className="mt-12 scroll-mt-24">
      <h2 className="flex items-center gap-3 font-serif text-2xl text-foreground md:text-3xl">
        <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-amber/40 bg-amber-soft text-amber">
          <Icon className="h-4 w-4" aria-hidden="true" />
        </span>
        {title}
      </h2>
      <div className="mt-3 space-y-3 text-base leading-relaxed text-muted-foreground">
        {children}
      </div>
    </section>
  );
}
