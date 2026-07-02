import { useEffect, useState } from "react";
import { Flame, X } from "lucide-react";
import { buildAmazonUrl } from "@/lib/api";
import type { Product } from "@/lib/types";
import { usePriceDisplay } from "@/lib/price-display";
import { CountdownTimer } from "@/components/product/CountdownTimer";

/**
 * Exit-intent + scroll-depth CTA for the top deal.
 * - Fires when the user moves cursor toward the top edge (desktop), OR
 *   scrolls past 60% of the page (mobile/tablet).
 * - Dismissed state persists for the session.
 */
export function ExitIntentDeal({
  product,
  slug,
}: {
  product: Product | undefined;
  slug: string;
}) {
  const [open, setOpen] = useState(false);
  const [dismissed, setDismissed] = useState(false);
  const { formatDeal } = usePriceDisplay();

  useEffect(() => {
    if (typeof window === "undefined") return;
    if (sessionStorage.getItem("exit-deal-dismissed") === "1") {
      setDismissed(true);
      return;
    }
    let armed = false;
    const arm = () => {
      armed = true;
    };
    const t = window.setTimeout(arm, 8000); // don't fire too early

    const onMouseOut = (e: MouseEvent) => {
      if (!armed) return;
      if (e.clientY <= 0 && !e.relatedTarget) setOpen(true);
    };
    const onScroll = () => {
      if (!armed) return;
      const max =
        document.documentElement.scrollHeight - window.innerHeight;
      if (max > 0 && window.scrollY / max > 0.6) setOpen(true);
    };
    document.addEventListener("mouseout", onMouseOut);
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => {
      window.clearTimeout(t);
      document.removeEventListener("mouseout", onMouseOut);
      window.removeEventListener("scroll", onScroll);
    };
  }, []);

  if (!product || dismissed || !open) return null;

  const url = buildAmazonUrl(product, product.index, slug);
  const dismiss = () => {
    setDismissed(true);
    sessionStorage.setItem("exit-deal-dismissed", "1");
  };

  return (
    <div
      role="dialog"
      aria-label="Top deal"
      className="fixed inset-x-3 bottom-3 z-50 mx-auto max-w-md rounded-2xl border border-amber/40 bg-card p-4 shadow-lift motion-safe:animate-in motion-safe:slide-in-from-bottom-4 md:bottom-6"
    >
      <button
        type="button"
        onClick={dismiss}
        aria-label="Dismiss"
        className="absolute right-2 top-2 inline-flex h-7 w-7 items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
      >
        <X className="h-4 w-4" aria-hidden="true" />
      </button>
      <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-amber">
        <Flame className="h-3.5 w-3.5" aria-hidden="true" /> Don't miss this deal
      </div>
      <div className="mt-2 flex gap-3">
        {product.image_url && (
          <div className="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border bg-muted/40">
            <img
              src={product.image_url}
              alt=""
              loading="lazy"
              decoding="async"
              className="max-h-full max-w-full object-contain p-1.5"
            />
          </div>
        )}
        <div className="min-w-0 flex-1">
          <p className="line-clamp-2 text-sm font-medium text-foreground">
            {product.title}
          </p>
          <div className="mt-1.5 flex items-end justify-between gap-2">
            <div>
              <div className="font-serif text-lg font-semibold leading-none text-foreground">
                {formatDeal(product.price)}
              </div>
              <div className="mt-0.5 text-[11px] text-muted-foreground">
                Live Amazon price
              </div>
            </div>
            <CountdownTimer />
          </div>
        </div>
      </div>
      <a
        href={url}
        target="_blank"
        rel="nofollow sponsored noopener"
        onClick={dismiss}
        className="mt-3 inline-flex w-full items-center justify-center rounded-full bg-amber px-4 py-2.5 text-sm font-semibold text-amber-foreground hover:bg-amber/90"
      >
        Grab this deal on Amazon
      </a>
    </div>
  );
}
