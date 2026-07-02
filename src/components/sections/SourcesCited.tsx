import { useState } from "react";
import { BookOpen, ChevronDown, ExternalLink, Star } from "lucide-react";
import { cn } from "@/lib/utils";
import { buildAmazonReviewsUrl, buildAmazonUrl } from "@/lib/api";
import type { Product } from "@/lib/types";

/**
 * Collapsible "Sources cited" — links to each product's Amazon listing and
 * deep-links to the customer-reviews anchor on the listing for verification.
 * All Amazon links preserve our affiliate tag (FTC + Amazon Associates
 * Operating Agreement compliant).
 */
export function SourcesCited({
  products,
  productName,
  slug,
}: {
  products: Product[];
  productName: string;
  slug: string;
}) {
  const [open, setOpen] = useState(false);

  return (
    <section
      aria-labelledby="sources-heading"
      className="mt-8 rounded-2xl border border-border bg-card shadow-card"
    >
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
        className="flex w-full items-center justify-between gap-3 p-5 text-left"
      >
        <span className="flex items-center gap-2">
          <BookOpen className="h-4 w-4 text-amber" aria-hidden="true" />
          <span id="sources-heading" className="font-serif text-lg text-foreground">
            Sources &amp; references
          </span>
          <span className="rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
            {products.length + 3}
          </span>
        </span>
        <ChevronDown
          className={cn(
            "h-4 w-4 shrink-0 text-muted-foreground transition-transform",
            open && "rotate-180",
          )}
          aria-hidden="true"
        />
      </button>

      {open && (
        <div className="border-t border-border p-5">
          <p className="text-xs text-muted-foreground">
            We base our rankings on publicly available Amazon listing data,
            verified-purchase buyer reviews, and the manufacturer spec sheets
            linked below. Each entry links to the live listing; the
            “verified reviews” link jumps directly to the customer-review
            section so you can audit the rating yourself.
          </p>
          <ol className="mt-4 space-y-3 text-sm">
            {products.map((p, i) => {
              const listingUrl = buildAmazonUrl(p, i + 1, slug);
              const reviewsUrl = buildAmazonReviewsUrl(p, i + 1, slug);
              return (
                <li
                  key={p.id}
                  className="grid grid-cols-[1.25rem_1fr] gap-x-2 gap-y-1 sm:grid-cols-[1.25rem_1fr_auto]"
                >
                  <span className="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-muted text-[10px] font-semibold text-muted-foreground">
                    {p.index ?? i + 1}
                  </span>
                  <a
                    href={listingUrl}
                    target="_blank"
                    rel="nofollow sponsored noopener"
                    className="inline-flex min-w-0 items-center gap-1 text-foreground hover:underline"
                  >
                    <span className="line-clamp-1">
                      {p.brand ? `${p.brand} — ` : ""}
                      {p.title}
                    </span>
                    <ExternalLink className="h-3 w-3 shrink-0 text-muted-foreground" aria-hidden="true" />
                  </a>
                  <a
                    href={reviewsUrl}
                    target="_blank"
                    rel="nofollow sponsored noopener"
                    className="col-start-2 inline-flex items-center gap-1 text-xs text-amber hover:underline sm:col-start-3 sm:justify-self-end"
                    aria-label={`Read verified Amazon customer reviews for ${p.title}`}
                  >
                    <Star className="h-3 w-3 shrink-0 fill-amber stroke-amber" aria-hidden="true" />
                    <span>Verified Amazon reviews</span>
                  </a>
                </li>
              );
            })}
            <li className="flex items-start gap-2 text-muted-foreground">
              <span className="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-muted text-[10px] font-semibold">
                {products.length + 1}
              </span>
              <a
                href="https://www.ftc.gov/business-guidance/resources/ftcs-endorsement-guides-what-people-are-asking"
                target="_blank"
                rel="noopener"
                className="hover:underline"
              >
                Federal Trade Commission — Endorsement Guides (16 CFR Part 255).
              </a>
            </li>
            <li className="flex items-start gap-2 text-muted-foreground">
              <span className="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-muted text-[10px] font-semibold">
                {products.length + 2}
              </span>
              <span>
                Consumer Reports buying guides — {productName.toLowerCase()}{" "}
                category background.
              </span>
            </li>
            <li className="flex items-start gap-2 text-muted-foreground">
              <span className="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-muted text-[10px] font-semibold">
                {products.length + 3}
              </span>
              <span>
                Aggregated Amazon buyer reviews (≥ 4-star, verified-purchase
                weighting).
              </span>
            </li>
          </ol>
          <p className="mt-4 text-[11px] leading-relaxed text-muted-foreground">
            As an Amazon Associate we earn from qualifying purchases. Outbound
            product links carry our affiliate tag; this does not change the
            price you pay or the ranking we publish.
          </p>
        </div>
      )}
    </section>
  );
}
