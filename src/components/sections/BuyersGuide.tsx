import { AlertTriangle, BookMarked, Lightbulb } from "lucide-react";
import type { Product } from "@/lib/types";

const COMMON_MISTAKES = [
  "Buying on star rating alone — check the number of verified reviews, not just the average.",
  "Ignoring the return window. Confirm Amazon's return policy before you order.",
  "Falling for a fake list price — compare the price against the 90-day low, not the strikethrough.",
  "Skipping the question-and-answer section, where owners surface real-world quirks the listing hides.",
  "Adding accessories blindly — the bundled extras often cost more than buying them separately.",
];

const GLOSSARY_FALLBACK: Record<string, string> = {
  warranty: "Manufacturer coverage window — read the fine print for what voids it.",
  battery: "Capacity is usually in mAh or Wh; bigger isn't always better if weight matters.",
  resolution: "Pixel count; higher is sharper but needs more storage and compute.",
  bluetooth: "Wireless pairing standard; v5+ improves range and stability.",
  wattage: "Power draw; affects performance ceiling and running cost.",
  warranty_default: "Coverage promise from the brand.",
};

function glossaryFor(feature: string): string {
  const lower = feature.toLowerCase();
  for (const key of Object.keys(GLOSSARY_FALLBACK)) {
    if (lower.includes(key)) return GLOSSARY_FALLBACK[key];
  }
  return "Look for this spec on the manufacturer's official page to confirm wording.";
}

export function BuyersGuide({
  products,
  productName,
}: {
  products: Product[];
  productName: string;
}) {
  const freq = new Map<string, number>();
  for (const p of products) {
    for (const f of p.features || []) {
      const key = f.trim();
      if (!key) continue;
      freq.set(key, (freq.get(key) || 0) + 1);
    }
  }
  const sorted = Array.from(freq.entries()).sort((a, b) => b[1] - a[1]);
  const repeated = sorted.filter(([, c]) => c >= 2);
  const common = (repeated.length >= 3 ? repeated : sorted).slice(0, 6);

  if (!common.length) return null;

  return (
    <section
      id="buyers-guide"
      aria-labelledby="buyers-guide-heading"
      className="scroll-mt-28 rounded-2xl border border-border bg-card p-6 shadow-card md:p-8"
    >
      <div className="flex items-center gap-2">
        <Lightbulb className="h-5 w-5 text-amber" />
        <h2 id="buyers-guide-heading" className="font-serif text-3xl text-foreground">
          What to look for in {productName.toLowerCase()}
        </h2>
      </div>
      <p className="mt-2 text-sm text-muted-foreground">
        These specs appear most often across the top {products.length} {productName.toLowerCase()} we
        compared — a useful shortlist of features to evaluate before you buy.
      </p>

      <ul className="mt-5 grid gap-3 md:grid-cols-2">
        {common.map(([feat, count]) => (
          <li
            key={feat}
            className="flex items-start gap-3 rounded-lg border border-border bg-muted/20 p-3"
          >
            <span className="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber/15 text-xs font-bold text-amber-foreground">
              {count}
            </span>
            <div className="min-w-0">
              <p className="text-sm font-medium text-foreground">{feat}</p>
              <p className="text-xs text-muted-foreground">
                Found in {count} of {products.length} top picks
              </p>
            </div>
          </li>
        ))}
      </ul>

      {/* Glossary */}
      <div className="mt-8">
        <h3 className="inline-flex items-center gap-2 font-serif text-xl text-foreground">
          <BookMarked className="h-4 w-4 text-amber" aria-hidden="true" />
          Quick glossary
        </h3>
        <dl className="mt-3 grid gap-3 md:grid-cols-2">
          {common.slice(0, 4).map(([feat]) => (
            <div key={feat} className="rounded-lg border border-border bg-muted/10 p-3">
              <dt className="text-sm font-semibold text-foreground">{feat}</dt>
              <dd className="mt-1 text-xs text-muted-foreground">{glossaryFor(feat)}</dd>
            </div>
          ))}
        </dl>
      </div>

      {/* Common mistakes */}
      <div className="mt-8">
        <h3 className="inline-flex items-center gap-2 font-serif text-xl text-foreground">
          <AlertTriangle className="h-4 w-4 text-danger" aria-hidden="true" />
          Common mistakes to avoid
        </h3>
        <ul className="mt-3 space-y-2 text-sm text-foreground">
          {COMMON_MISTAKES.map((m) => (
            <li
              key={m}
              className="flex items-start gap-2 rounded-lg border border-danger/20 bg-danger-soft/30 p-3"
            >
              <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0 text-danger" aria-hidden="true" />
              <span>{m}</span>
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}

