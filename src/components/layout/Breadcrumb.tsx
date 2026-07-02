import { Link } from "@tanstack/react-router";
import { ChevronRight, Home } from "lucide-react";

export interface Crumb {
  label: string;
  to?: string;
  params?: Record<string, string>;
}

export function Breadcrumb({ items }: { items: Crumb[] }) {
  return (
    <nav aria-label="Breadcrumb" className="text-xs text-muted-foreground">
      <ol className="flex flex-wrap items-center gap-1">
        <li>
          <Link to="/" className="inline-flex items-center gap-1 hover:text-foreground">
            <Home className="h-3 w-3" />
            <span className="sr-only">Home</span>
          </Link>
        </li>
        {items.map((it, i) => (
          <li key={i} className="inline-flex items-center gap-1">
            <ChevronRight className="h-3 w-3" />
            {it.to && i < items.length - 1 ? (
              <Link
                to={it.to as never}
                params={it.params as never}
                className="hover:text-foreground"
              >
                {it.label}
              </Link>
            ) : (
              <span aria-current={i === items.length - 1 ? "page" : undefined} className="text-foreground">
                {it.label}
              </span>
            )}
          </li>
        ))}
      </ol>
    </nav>
  );
}
