import { Award, DollarSign, TrendingDown } from "lucide-react";
import { usePriceDisplay } from "@/lib/price-display";
import type { Product } from "@/lib/types";

export function TldrCard({ products }: { products: Product[] }) {
  const { format: formatPrice } = usePriceDisplay();
  const top = products[0];
  const value = products.find((p) => p.bestValue);
  const budget = [...products].sort((a, b) => (a.price_sort || Infinity) - (b.price_sort || Infinity))[0];

  if (!top) return null;

  return (
    <section
      aria-label="Quick picks summary"
      className="mt-6 grid gap-3 rounded-2xl border border-border bg-card p-5 shadow-card md:grid-cols-3"
    >
      <Highlight
        icon={<Award className="h-4 w-4" />}
        label="Editor's Choice"
        title={top.title}
        meta={formatPrice(top.price)}
        href={`#pick-${top.index}`}
      />
      {value ? (
        <Highlight
          icon={<DollarSign className="h-4 w-4" />}
          label="Best Value"
          title={value.title}
          meta={`${value.savings_percentage}% off · ${formatPrice(value.price)}`}
          href={`#pick-${value.index}`}
        />
      ) : (
        budget &&
        budget.index !== 1 && (
          <Highlight
            icon={<TrendingDown className="h-4 w-4" />}
            label="Lowest Price"
            title={budget.title}
            meta={formatPrice(budget.price)}
            href={`#pick-${budget.index}`}
          />
        )
      )}
      {budget && budget.index !== top.index && budget.index !== value?.index && (
        <Highlight
          icon={<TrendingDown className="h-4 w-4" />}
          label="Lowest Price"
          title={budget.title}
          meta={formatPrice(budget.price)}
          href={`#pick-${budget.index}`}
        />
      )}
    </section>
  );
}

function Highlight({
  icon,
  label,
  title,
  meta,
  href,
}: {
  icon: React.ReactNode;
  label: string;
  title: string;
  meta: string;
  href: string;
}) {
  return (
    <a
      href={href}
      className="group min-w-0 rounded-xl border border-border bg-muted/30 p-3 transition-colors hover:bg-muted"
    >
      <div className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-amber">
        {icon} {label}
      </div>
      <p className="mt-1.5 line-clamp-2 text-sm font-medium text-foreground group-hover:underline">
        {title}
      </p>
      <p className="mt-1 text-xs text-muted-foreground">{meta}</p>
    </a>
  );
}
