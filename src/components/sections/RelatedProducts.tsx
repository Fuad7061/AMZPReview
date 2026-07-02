import { useEffect, useRef, useState } from "react";
import { ArrowUpRight, Loader2, Truck, Star } from "lucide-react";
import { buildAmazonUrl, fetchProducts } from "@/lib/api";
import { usePriceDisplay } from "@/lib/price-display";
import type { RawProduct } from "@/lib/types";

export function RelatedProducts({ slug }: { slug: string }) {
  const [items, setItems] = useState<RawProduct[]>([]);
  const [page, setPage] = useState(2);
  const [done, setDone] = useState(false);
  const [loading, setLoading] = useState(false);
  const sentinelRef = useRef<HTMLDivElement | null>(null);
  const { format: formatPrice } = usePriceDisplay();

  useEffect(() => {
    if (done) return;
    const el = sentinelRef.current;
    if (!el) return;
    const io = new IntersectionObserver(
      async (entries) => {
        if (!entries[0].isIntersecting || loading || done) return;
        setLoading(true);
        try {
          const res = await fetchProducts(slug, page, true);
          if (!res.products?.length) {
            setDone(true);
          } else {
            setItems((prev) => [...prev, ...res.products]);
            setPage((p) => p + 1);
            if (res.products.length < 10) setDone(true);
          }
        } catch {
          setDone(true);
        } finally {
          setLoading(false);
        }
      },
      { rootMargin: "300px" },
    );
    io.observe(el);
    return () => io.disconnect();
  }, [page, loading, done, slug]);

  return (
    <section aria-labelledby="related-heading" className="mt-12">
      <div className="flex items-end justify-between gap-4">
        <div>
          <h2 id="related-heading" className="font-serif text-3xl text-foreground">
            More options to consider
          </h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Additional listings beyond our top picks — for shoppers who want a wider view.
          </p>
        </div>
        {items.length > 0 && (
          <span className="hidden shrink-0 rounded-full border border-border bg-muted/40 px-3 py-1 text-xs font-medium text-muted-foreground sm:inline-block">
            {items.length} more {items.length === 1 ? "option" : "options"}
          </span>
        )}
      </div>

      {items.length > 0 && (
        <div className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {items.map((p, i) => {
            const savings = typeof p.savings_percentage === "number" ? p.savings_percentage : 0;
            const score = parseFloat(p.score);
            const hasScore = !isNaN(score) && score > 0;
            return (
              <a
                key={`${p.id}-${i}`}
                href={buildAmazonUrl(
                  { ...p, index: i + 11, topProduct: false, bestValue: false, bestBudget: false },
                  i + 11,
                  slug,
                )}
                target="_blank"
                rel="nofollow sponsored noopener"
                className="group relative flex flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-card transition-all duration-200 motion-safe:hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-lift"
              >
                {savings > 0 && (
                  <span className="absolute left-3 top-3 z-10 rounded-full bg-destructive px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-destructive-foreground shadow-sm">
                    Save {savings}%
                  </span>
                )}

                <div className="flex h-44 items-center justify-center overflow-hidden bg-gradient-to-br from-muted/30 to-muted/10 p-4">
                  {p.image_url && (
                    <img
                      src={p.image_url}
                      alt={p.title}
                      loading="lazy"
                      decoding="async"
                      className="max-h-full max-w-full object-contain transition-transform duration-300 motion-safe:group-hover:scale-105"
                    />
                  )}
                </div>

                <div className="flex flex-1 flex-col p-4">
                  {p.brand && (
                    <p className="text-[10px] font-semibold uppercase tracking-wider text-primary">
                      {p.brand}
                    </p>
                  )}
                  <p className="mt-1 line-clamp-2 min-h-[2.5rem] text-sm font-medium leading-snug text-foreground group-hover:text-primary">
                    {p.title}
                  </p>

                  {(hasScore || p.free_shipping) && (
                    <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted-foreground">
                      {hasScore && (
                        <span className="inline-flex items-center gap-1">
                          <Star className="h-3 w-3 fill-amber-400 text-amber-400" />
                          <span className="font-semibold text-foreground">{score.toFixed(1)}</span>
                        </span>
                      )}
                      {p.free_shipping && (
                        <span className="inline-flex items-center gap-1 text-success-foreground">
                          <Truck className="h-3 w-3" /> Free shipping
                        </span>
                      )}
                    </div>
                  )}

                  <div className="mt-3 flex items-end justify-between gap-2 pt-2">
                    <div className="flex flex-col">
                      <span className="text-lg font-bold text-foreground">{formatPrice(p.price)}</span>
                      {p.saving_basis && savings > 0 && (
                        <span className="text-xs text-muted-foreground line-through">
                          {formatPrice(p.saving_basis)}
                        </span>
                      )}
                    </div>
                    <span className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                      View <ArrowUpRight className="h-3 w-3" />
                    </span>
                  </div>
                </div>
              </a>
            );
          })}
        </div>
      )}
      <div ref={sentinelRef} className="mt-6 flex items-center justify-center py-6 text-sm text-muted-foreground">
        {loading && (
          <span className="inline-flex items-center gap-2">
            <Loader2 className="h-4 w-4 animate-spin" /> Loading more options…
          </span>
        )}
        {done && items.length === 0 && <span>No additional options found.</span>}
        {done && items.length > 0 && <span>You've seen all available options.</span>}
      </div>
    </section>
  );
}
