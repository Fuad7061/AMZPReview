import { useEffect, useRef, useState, type ReactNode } from "react";

/**
 * Renders a lightweight placeholder until it scrolls within `rootMargin`,
 * then mounts `children`. Used to defer below-the-fold sections (FAQ,
 * RelatedProducts) so they don't compete with the LCP.
 */
export function LazyMount({
  children,
  rootMargin = "400px",
  minHeight = 200,
}: {
  children: ReactNode;
  rootMargin?: string;
  minHeight?: number;
}) {
  const ref = useRef<HTMLDivElement | null>(null);
  const [shown, setShown] = useState(false);

  useEffect(() => {
    if (shown) return;
    const el = ref.current;
    if (!el || typeof IntersectionObserver === "undefined") {
      setShown(true);
      return;
    }
    const io = new IntersectionObserver(
      (entries) => {
        if (entries.some((e) => e.isIntersecting)) {
          setShown(true);
          io.disconnect();
        }
      },
      { rootMargin },
    );
    io.observe(el);
    return () => io.disconnect();
  }, [shown, rootMargin]);

  return (
    <div ref={ref} style={shown ? undefined : { minHeight }}>
      {shown ? children : null}
    </div>
  );
}
