import { ExternalLink } from "lucide-react";
import { useEffect, useState } from "react";
import { useServerFn } from "@tanstack/react-start";
import { buildAmazonUrl } from "@/lib/api";
import { recordAffiliateClick } from "@/lib/click.functions";
import type { Product } from "@/lib/types";
import { usePriceDisplay } from "@/lib/price-display";

/**
 * Sticky "View top deal" bar pinned to the viewport bottom on mobile.
 * Appears after the user scrolls past the hero to avoid blocking the H1.
 */
export function MobileStickyCta({
  product,
  slug,
}: {
  product: Product | undefined;
  slug: string;
}) {
  const [show, setShow] = useState(false);
  const recordClick = useServerFn(recordAffiliateClick);
  const { format: formatPrice } = usePriceDisplay();

  useEffect(() => {
    if (typeof window === "undefined") return;
    const onScroll = () => setShow(window.scrollY > 600);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  if (!product || !show) return null;
  const url = buildAmazonUrl(product, product.index, slug);

  return (
    <div
      className="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-card/95 px-3 pt-3 shadow-lift backdrop-blur md:hidden"
      style={{ paddingBottom: "calc(0.75rem + env(safe-area-inset-bottom))" }}
    >
      <div className="flex items-center gap-3">
        <div className="min-w-0 flex-1">
          <p className="text-[11px] uppercase tracking-wider text-muted-foreground">
            #1 Top pick
          </p>
          <p className="line-clamp-1 text-sm font-semibold text-foreground">
            {formatPrice(product.price)} · {product.title}
          </p>
        </div>
        <a
          href={url}
          target="_blank"
          rel="nofollow sponsored noopener"
          onClick={() =>
            void recordClick({
              data: { asin: product.id, position: product.index, slug },
            }).catch(() => {})
          }
          className="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-amber px-4 py-2.5 text-sm font-semibold text-amber-foreground"
        >
          View deal <ExternalLink className="h-3.5 w-3.5" aria-hidden="true" />
        </a>
      </div>
    </div>
  );
}
