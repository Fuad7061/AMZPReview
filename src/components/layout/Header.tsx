import { Link, useRouterState } from "@tanstack/react-router";
import { ChevronDown, LayoutGrid, Menu, X } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import { DEPARTMENTS, SITE_NAME } from "@/config/site";
import { relatedCategories } from "@/lib/related-categories";
import { SearchBar } from "../ui/SearchBar";
import { ThemeToggle } from "./ThemeToggle";
import { PriceVisibilityToggle } from "./PriceVisibilityToggle";

export function Header() {
  const [open, setOpen] = useState(false);
  const [deptOpen, setDeptOpen] = useState(false);
  const [activeDept, setActiveDept] = useState(DEPARTMENTS[0]?.slug ?? "");
  const deptRef = useRef<HTMLDivElement | null>(null);

  // Close department mega-menu on outside click / escape.
  useEffect(() => {
    if (!deptOpen) return;
    function onClick(e: MouseEvent) {
      if (deptRef.current && !deptRef.current.contains(e.target as Node)) {
        setDeptOpen(false);
      }
    }
    function onKey(e: KeyboardEvent) {
      if (e.key === "Escape") setDeptOpen(false);
    }
    document.addEventListener("mousedown", onClick);
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("mousedown", onClick);
      document.removeEventListener("keydown", onKey);
    };
  }, [deptOpen]);

  const active = DEPARTMENTS.find((d) => d.slug === activeDept) ?? DEPARTMENTS[0];

  // Context-aware quick links: derive related categories from the current
  // product page slug so users see categories that match their search intent.
  const pathname = useRouterState({ select: (s) => s.location.pathname });
  const currentSlug = useMemo(() => {
    const m = pathname.match(/^\/product\/([^/]+)/);
    return m ? decodeURIComponent(m[1]) : undefined;
  }, [pathname]);
  const featured = useMemo(() => relatedCategories(currentSlug, 5), [currentSlug]);
  const featuredLabel = currentSlug ? "Related categories" : "Popular picks";

  return (
    <header className="sticky top-0 z-40 border-b border-border bg-background/85 backdrop-blur supports-[backdrop-filter]:bg-background/70">
      <div className="container-page grid grid-cols-[auto_1fr_auto] items-center gap-3 py-3 md:py-4">
        <Link
          to="/"
          className="flex shrink-0 items-center gap-2 font-serif text-2xl tracking-tight text-foreground"
          aria-label={`${SITE_NAME} home`}
        >
          <span
            aria-hidden="true"
            className="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-foreground font-sans text-sm font-bold text-background"
          >
            PR
          </span>
          <span className="hidden sm:inline">{SITE_NAME}</span>
        </Link>

        <div className="hidden min-w-0 md:block">
          <SearchBar size="sm" placeholder="Search any product…" />
        </div>

        <div className="flex shrink-0 items-center gap-1">
          <nav className="hidden items-center gap-1 lg:flex">
            <Link
              to="/methodology"
              className="rounded-md px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
              How we rank
            </Link>
            <Link
              to="/about"
              className="rounded-md px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
              About
            </Link>
          </nav>

          <PriceVisibilityToggle />
          <ThemeToggle />

          <button
            type="button"
            onClick={() => setOpen((v) => !v)}
            aria-label="Toggle menu"
            aria-expanded={open}
            className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-border bg-card md:hidden"
          >
            {open ? <X className="h-4 w-4" /> : <Menu className="h-4 w-4" />}
          </button>
        </div>
      </div>

      {/* Department strip — desktop */}
      <div className="hidden border-t border-border md:block" ref={deptRef}>
        <div className="container-page relative flex items-center gap-1 py-2 text-sm">
          <button
            type="button"
            onClick={() => setDeptOpen((v) => !v)}
            aria-expanded={deptOpen}
            aria-haspopup="true"
            aria-controls="dept-menu"
            className="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-foreground px-3.5 py-1.5 text-xs font-semibold text-background hover:bg-foreground/90"
          >
            <LayoutGrid className="h-3.5 w-3.5" aria-hidden="true" />
            Shop by Department
            <ChevronDown
              className={`h-3.5 w-3.5 transition-transform ${deptOpen ? "rotate-180" : ""}`}
              aria-hidden="true"
            />
          </button>

          <div className="mx-1 hidden h-5 w-px shrink-0 bg-border lg:block" />

          <nav
            aria-label={featuredLabel}
            className="flex min-w-0 items-center gap-1 overflow-x-auto"
          >
            <span className="hidden shrink-0 pl-1 pr-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground/80 xl:inline">
              {featuredLabel}:
            </span>
            {featured.map((c) => (
              <Link
                key={c.slug}
                to="/product/$slug"
                params={{ slug: c.slug }}
                className="shrink-0 rounded-full px-2.5 py-1 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
              >
                {c.label}
              </Link>
            ))}
          </nav>

          {/* Mega-menu panel */}
          {deptOpen && (
            <div
              id="dept-menu"
              role="menu"
              className="absolute left-0 right-0 top-full z-50 mt-2 grid grid-cols-[220px_1fr] overflow-hidden rounded-2xl border border-border bg-popover shadow-lift"
            >
              {/* Department list */}
              <ul className="border-r border-border bg-muted/30 py-2">
                {DEPARTMENTS.map((d) => {
                  const isActive = d.slug === active?.slug;
                  return (
                    <li key={d.slug}>
                      <button
                        type="button"
                        onMouseEnter={() => setActiveDept(d.slug)}
                        onFocus={() => setActiveDept(d.slug)}
                        onClick={() => setActiveDept(d.slug)}
                        aria-current={isActive ? "true" : undefined}
                        className={`flex w-full items-center justify-between gap-2 px-4 py-2.5 text-left text-sm transition-colors ${
                          isActive
                            ? "bg-background font-semibold text-foreground"
                            : "text-muted-foreground hover:bg-background/60 hover:text-foreground"
                        }`}
                      >
                        <span className="flex min-w-0 items-center gap-2">
                          <span aria-hidden="true">{d.emoji}</span>
                          <span className="truncate">{d.label}</span>
                        </span>
                        <ChevronDown className="h-4 w-4 -rotate-90 shrink-0 opacity-60" aria-hidden="true" />
                      </button>
                    </li>
                  );
                })}
              </ul>

              {/* Sub-category grid */}
              <div className="p-5">
                <div className="mb-3">
                  <h3 className="font-serif text-lg text-foreground">
                    {active?.label}
                  </h3>
                  <p className="text-xs text-muted-foreground">{active?.blurb}</p>
                </div>
                <ul className="grid grid-cols-2 gap-1.5 lg:grid-cols-3">
                  {active?.children.map((c) => (
                    <li key={c.slug}>
                      <Link
                        to="/product/$slug"
                        params={{ slug: c.slug }}
                        onClick={() => setDeptOpen(false)}
                        className="flex items-center gap-2 rounded-lg border border-transparent px-3 py-2 text-sm text-foreground transition-colors hover:border-border hover:bg-muted"
                      >
                        <span className="text-base" aria-hidden="true">{c.emoji}</span>
                        <span className="truncate">{c.label}</span>
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Mobile menu */}
      {open && (
        <div className="border-t border-border md:hidden">
          <div className="container-page space-y-4 py-4">
            <SearchBar size="sm" autoFocus />
            <div>
              <p className="mb-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                Shop by Department
              </p>
              <div className="space-y-3">
                {DEPARTMENTS.map((d) => (
                  <div key={d.slug}>
                    <p className="text-xs font-semibold text-foreground">
                      <span aria-hidden="true" className="mr-1">{d.emoji}</span>
                      {d.label}
                    </p>
                    <div className="mt-1.5 flex flex-wrap gap-1.5">
                      {d.children.map((c) => (
                        <Link
                          key={c.slug}
                          to="/product/$slug"
                          params={{ slug: c.slug }}
                          onClick={() => setOpen(false)}
                          className="rounded-full border border-border bg-card px-3 py-1 text-xs text-foreground"
                        >
                          {c.label}
                        </Link>
                      ))}
                    </div>
                  </div>
                ))}
              </div>
            </div>
            <div className="flex gap-4 border-t border-border pt-3 text-sm">
              <Link
                to="/methodology"
                onClick={() => setOpen(false)}
                className="text-muted-foreground hover:text-foreground"
              >
                How we rank
              </Link>
              <Link
                to="/about"
                onClick={() => setOpen(false)}
                className="text-muted-foreground hover:text-foreground"
              >
                About
              </Link>
              <Link
                to="/disclosure"
                onClick={() => setOpen(false)}
                className="text-muted-foreground hover:text-foreground"
              >
                Disclosure
              </Link>
            </div>
          </div>
        </div>
      )}
    </header>
  );
}
