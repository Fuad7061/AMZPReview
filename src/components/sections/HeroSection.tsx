import { Calendar, Clock, Sparkles } from "lucide-react";
import { formatRelativeDate, getCurrentMonth, getCurrentYear, readingTimeMinutes } from "@/lib/utils";

export function HeroSection({
  productName,
  productCount,
  asOf,
  scoreRange,
}: {
  productName: string;
  productCount: number;
  asOf?: string;
  scoreRange?: { high: string; low: string };
}) {
  const year = getCurrentYear();
  const month = getCurrentMonth();
  return (
    <header className="pt-6 pb-8">
      <div className="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-3 py-1 text-xs font-medium text-muted-foreground">
        <Sparkles className="h-3 w-3 text-amber" />
        {month} {year} edition · Expert-reviewed
      </div>
      <h1 className="mt-4 font-serif text-4xl leading-[1.1] tracking-tight text-foreground md:text-6xl">
        The {productCount} Best <span className="text-amber">{productName}</span>
        <br className="hidden md:block" /> of {year}
      </h1>
      <p className="mt-4 max-w-2xl text-base text-muted-foreground md:text-lg">
        We've compared {productCount} top {productName.toLowerCase()} on Amazon
        across price, discount depth, condition, and feature coverage — so you
        can pick the right one in minutes.
      </p>
      <dl className="mt-6 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs text-muted-foreground">
        {asOf && (
          <div className="inline-flex items-center gap-1.5">
            <Calendar className="h-3.5 w-3.5" />
            <span>
              Last updated <strong className="text-foreground">{formatRelativeDate(asOf)}</strong>
            </span>
          </div>
        )}
        <div className="inline-flex items-center gap-1.5">
          <Clock className="h-3.5 w-3.5" />
          <span>{readingTimeMinutes(productCount)} min read</span>
        </div>
      </dl>
    </header>
  );
}
