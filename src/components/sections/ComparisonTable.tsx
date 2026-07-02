import { Check, ExternalLink, Info, Minus, ThumbsDown, ThumbsUp } from "lucide-react";
import { buildAmazonUrl } from "@/lib/api";
import type { Product } from "@/lib/types";
import { usePriceDisplay } from "@/lib/price-display";
import { audienceTag, derivePros, deriveCons } from "@/lib/audience";
import { HoverCard, HoverCardContent, HoverCardTrigger } from "@/components/ui/hover-card";


export function ComparisonTable({
  products,
  slug,
}: {
  products: Product[];
  slug: string;
}) {
  const { format: formatPrice } = usePriceDisplay();
  // Aggregate features across all products
  const allFeatures = Array.from(
    new Set(products.flatMap((p) => (p.features || []).map((f) => f.trim()).filter(Boolean))),
  ).slice(0, 8); // cap to keep table readable

  return (
    <section id="compare" className="scroll-mt-28">
      <div className="overflow-x-auto rounded-2xl border border-border bg-card shadow-card">
        <table className="w-full min-w-[720px] border-collapse text-sm">
          <thead>
            <tr className="border-b border-border bg-muted/30">
              <th className="sticky left-0 z-10 w-40 bg-muted/30 p-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                Compare
              </th>
              {products.map((p) => (
                <th key={p.id} className="min-w-[170px] p-3 text-center align-top">
                  <HoverCard openDelay={120} closeDelay={80}>
                    <HoverCardTrigger asChild>
                      <a href={`#pick-${p.index}`} className="block">
                        <div className="mx-auto h-16 w-16 overflow-hidden rounded-lg bg-muted/40">
                          {p.image_url && (
                            <img
                              src={p.image_url}
                              alt={p.title}
                              loading="lazy"
                              className="h-full w-full object-contain p-1"
                            />
                          )}
                        </div>
                        <div className="mt-2 inline-flex items-center gap-1 text-xs font-bold text-foreground">
                          #{p.index} · {p.score}
                          <Info className="h-3 w-3 text-muted-foreground" aria-hidden="true" />
                        </div>
                        <p className="mt-1 line-clamp-2 text-xs font-medium text-foreground">
                          {p.title}
                        </p>
                      </a>
                    </HoverCardTrigger>
                    <HoverCardContent className="w-72 text-left">
                      <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        Quick pros &amp; cons
                      </p>
                      <ul className="mt-2 space-y-1 text-xs text-foreground">
                        {derivePros(p).map((pro) => (
                          <li key={pro} className="flex items-start gap-1.5">
                            <ThumbsUp className="mt-0.5 h-3 w-3 shrink-0 text-success" aria-hidden="true" />
                            <span className="line-clamp-2">{pro}</span>
                          </li>
                        ))}
                        {deriveCons(p).map((con) => (
                          <li key={con} className="flex items-start gap-1.5">
                            <ThumbsDown className="mt-0.5 h-3 w-3 shrink-0 text-danger" aria-hidden="true" />
                            <span className="line-clamp-2">{con}</span>
                          </li>
                        ))}
                      </ul>
                    </HoverCardContent>
                  </HoverCard>
                </th>
              ))}

            </tr>
          </thead>
          <tbody>
            <Row label="Price" sticky>
              {products.map((p) => (
                <Cell key={p.id}>
                  <span className="font-semibold text-foreground">{formatPrice(p.price)}</span>
                  {typeof p.savings_percentage === "number" && p.savings_percentage > 0 && (
                    <div className="text-[11px] text-success-foreground">{p.savings_percentage}% off</div>
                  )}
                </Cell>
              ))}
            </Row>
            <Row label="Score" sticky>
              {products.map((p) => (
                <Cell key={p.id}>{p.score} / 10</Cell>
              ))}
            </Row>
            <Row label="Best for" sticky>
              {products.map((p) => (
                <Cell key={p.id}>
                  <span className="inline-flex rounded-full bg-amber-soft px-2 py-0.5 text-[11px] font-semibold text-amber-foreground">
                    {audienceTag(p)}
                  </span>
                </Cell>
              ))}
            </Row>

            <Row label="Brand" sticky>
              {products.map((p) => (
                <Cell key={p.id}>{p.brand || "—"}</Cell>
              ))}
            </Row>
            <Row label="Condition" sticky>
              {products.map((p) => (
                <Cell key={p.id}>{p.condition || "—"}</Cell>
              ))}
            </Row>
            <Row label="Free shipping" sticky>
              {products.map((p) => (
                <Cell key={p.id}>
                  {p.free_shipping ? <Check className="mx-auto h-4 w-4 text-success" /> : <Minus className="mx-auto h-4 w-4 text-muted-foreground" />}
                </Cell>
              ))}
            </Row>

            {allFeatures.map((feat) => (
              <Row key={feat} label={feat} sticky>
                {products.map((p) => (
                  <Cell key={p.id}>
                    {(p.features || []).some((f) => f.trim() === feat) ? (
                      <Check className="mx-auto h-4 w-4 text-success" />
                    ) : (
                      <Minus className="mx-auto h-4 w-4 text-muted-foreground" />
                    )}
                  </Cell>
                ))}
              </Row>
            ))}

            <tr className="border-t-2 border-border bg-muted/20">
              <td className="sticky left-0 z-10 bg-muted/20 p-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                Buy
              </td>
              {products.map((p) => (
                <td key={p.id} className="p-3 text-center">
                  <a
                    href={buildAmazonUrl(p, p.index, slug)}
                    target="_blank"
                    rel="nofollow sponsored noopener"
                    className="inline-flex items-center justify-center gap-1 rounded-full bg-foreground px-3 py-1.5 text-xs font-semibold text-background hover:bg-foreground/90"
                  >
                    View <ExternalLink className="h-3 w-3" />
                  </a>
                </td>
              ))}
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  );
}

function Row({
  label,
  sticky,
  children,
}: {
  label: string;
  sticky?: boolean;
  children: React.ReactNode;
}) {
  return (
    <tr className="border-b border-border last:border-0">
      <th
        scope="row"
        className={
          (sticky ? "sticky left-0 z-10 bg-card " : "") +
          "p-3 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground"
        }
      >
        {label}
      </th>
      {children}
    </tr>
  );
}

function Cell({ children }: { children: React.ReactNode }) {
  return <td className="p-3 text-center text-sm text-foreground">{children}</td>;
}
