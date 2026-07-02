import { Link } from "@tanstack/react-router";
import { BadgeCheck, FlaskConical, UserRound } from "lucide-react";
import { SITE_NAME } from "@/config/site";


/**
 * E-E-A-T trust block: who wrote it, when it was updated, how it was tested.
 * Sits just under the H1 on every product review page.
 */
export function ReviewerByline({
  productName,
  asOf,
}: {
  productName: string;
  asOf?: string;
}) {
  return (
    <section
      aria-label="Review byline"
      className="mt-5 flex flex-col gap-3 rounded-2xl border border-border bg-card p-4 shadow-card md:flex-row md:items-center md:justify-between md:gap-6"
    >
      <div className="flex items-center gap-3">
        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-soft text-amber">
          <UserRound className="h-5 w-5" aria-hidden="true" />
        </div>
        <div className="min-w-0">
          <p className="text-xs uppercase tracking-wider text-muted-foreground">
            Reviewed by
          </p>
          <p className="text-sm font-semibold text-foreground">
            The {SITE_NAME} Editorial Team
            <span className="ml-1.5 inline-flex items-center gap-0.5 text-[11px] font-medium text-success-foreground">
              <BadgeCheck className="h-3.5 w-3.5 text-success" aria-hidden="true" /> Independent
            </span>
          </p>
          <p className="text-[11px] text-muted-foreground">
            10+ years covering consumer product reviews — no paid placements.
          </p>
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-[11px] text-muted-foreground">
        <Link
          to="/methodology"
          className="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted/30 px-3 py-1 font-medium text-foreground hover:bg-muted"
        >
          <FlaskConical className="h-3.5 w-3.5 text-amber" aria-hidden="true" />
          How we tested {productName.toLowerCase()}
        </Link>
      </div>
    </section>
  );
}
