import { Link } from "@tanstack/react-router";
import { POPULAR_CATEGORIES, SITE_NAME } from "@/config/site";

export function Footer() {
  return (
    <footer className="mt-16 border-t border-border bg-paper">
      <div className="container-page grid gap-10 py-12 md:grid-cols-4">
        <div className="md:col-span-2">
          <div className="flex items-center gap-2">
            <span
              aria-hidden="true"
              className="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-foreground font-sans text-sm font-bold text-background"
            >
              PR
            </span>
            <span className="font-serif text-2xl">{SITE_NAME}</span>
          </div>
          <p className="mt-3 max-w-md text-sm text-muted-foreground">
            Expert-curated product rankings powered by live Amazon listings. We
            don't accept paid placements — rankings reflect price, value, and
            listing freshness only.
          </p>
          <p className="mt-4 max-w-md text-xs italic text-muted-foreground">
            As an Amazon Associate, {SITE_NAME} earns from qualifying purchases.
            Prices and availability are accurate as of the date shown and are
            subject to change.
          </p>
        </div>

        <div>
          <h3 className="font-sans text-xs font-semibold uppercase tracking-wider text-muted-foreground">
            Browse
          </h3>
          <ul className="mt-3 space-y-2 text-sm">
            {POPULAR_CATEGORIES.slice(0, 6).map((c) => (
              <li key={c.slug}>
                <Link
                  to="/product/$slug"
                  params={{ slug: c.slug }}
                  className="text-foreground/80 hover:text-foreground"
                >
                  {c.label}
                </Link>
              </li>
            ))}
          </ul>
        </div>

        <div>
          <h3 className="font-sans text-xs font-semibold uppercase tracking-wider text-muted-foreground">
            About
          </h3>
          <ul className="mt-3 space-y-2 text-sm">
            <li>
              <Link to="/about" className="text-foreground/80 hover:text-foreground">
                About {SITE_NAME}
              </Link>
            </li>
            <li>
              <Link to="/methodology" className="text-foreground/80 hover:text-foreground">
                How we rank
              </Link>
            </li>
            <li>
              <Link to="/disclosure" className="text-foreground/80 hover:text-foreground">
                Affiliate disclosure
              </Link>
            </li>
            <li>
              <Link to="/privacy" className="text-foreground/80 hover:text-foreground">
                Privacy
              </Link>
            </li>
            <li>
              <Link to="/terms" className="text-foreground/80 hover:text-foreground">
                Terms
              </Link>
            </li>
          </ul>
        </div>
      </div>
      <div className="border-t border-border">
        <div className="container-page flex flex-col items-start justify-between gap-2 py-5 text-xs text-muted-foreground md:flex-row md:items-center">
          <span>© {new Date().getFullYear()} {SITE_NAME}. All rights reserved.</span>
          <span>
            Amazon and the Amazon logo are trademarks of Amazon.com, Inc. or its affiliates.
          </span>
        </div>
      </div>
    </footer>
  );
}
