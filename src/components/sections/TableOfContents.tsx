import { useEffect, useState } from "react";
import { ListOrdered } from "lucide-react";
import { cn } from "@/lib/utils";
import type { Product } from "@/lib/types";

export function TableOfContents({ products }: { products: Product[] }) {
  const [active, setActive] = useState<string>("");

  useEffect(() => {
    const ids = ["compare", ...products.map((p) => `pick-${p.index}`), "buyers-guide", "faq"];
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((e) => {
          if (e.isIntersecting) setActive(e.target.id);
        });
      },
      { rootMargin: "-40% 0% -50% 0%" },
    );
    ids.forEach((id) => {
      const el = document.getElementById(id);
      if (el) observer.observe(el);
    });
    return () => observer.disconnect();
  }, [products]);

  return (
    <nav
      aria-label="On this page"
      className="hidden lg:sticky lg:top-28 lg:block lg:max-h-[calc(100vh-8rem)] lg:overflow-y-auto"
    >
      <p className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
        <ListOrdered className="h-3.5 w-3.5" /> On this page
      </p>
      <ol className="mt-3 space-y-1 text-sm">
        <TocItem id="compare" label="Compare all" active={active} />
        {products.map((p) => (
          <TocItem key={p.id} id={`pick-${p.index}`} label={`${p.index}. ${p.brand || p.title.split(" ").slice(0, 3).join(" ")}`} active={active} />
        ))}
        <TocItem id="buyers-guide" label="Buyer's guide" active={active} />
        <TocItem id="faq" label="FAQ" active={active} />
      </ol>
    </nav>
  );
}

function TocItem({ id, label, active }: { id: string; label: string; active: string }) {
  return (
    <li>
      <a
        href={`#${id}`}
        className={cn(
          "block truncate rounded-md border-l-2 py-1 pl-3 pr-2 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground",
          active === id ? "border-amber bg-amber-soft/40 text-foreground" : "border-transparent",
        )}
      >
        {label}
      </a>
    </li>
  );
}
