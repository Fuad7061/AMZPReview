import { useNavigate } from "@tanstack/react-router";
import { Search } from "lucide-react";
import { useEffect, useRef, useState, type FormEvent } from "react";
import { titleToSlug } from "@/lib/utils";
import { cn } from "@/lib/utils";

export function SearchBar({
  className,
  size = "md",
  placeholder = "Search any product — e.g. running shoes, vacuum, smart TV",
  autoFocus,
  showShortcut = true,
}: {
  className?: string;
  size?: "sm" | "md" | "lg";
  placeholder?: string;
  autoFocus?: boolean;
  showShortcut?: boolean;
}) {
  const [q, setQ] = useState("");
  const navigate = useNavigate();
  const inputRef = useRef<HTMLInputElement | null>(null);

  // Power-user shortcut: press "/" anywhere to focus the search.
  // Ignored when typing in another input/textarea or with modifier keys.
  useEffect(() => {
    if (!showShortcut) return;
    function onKey(e: KeyboardEvent) {
      if (e.key !== "/" || e.metaKey || e.ctrlKey || e.altKey) return;
      const t = e.target as HTMLElement | null;
      if (t && (t.tagName === "INPUT" || t.tagName === "TEXTAREA" || t.isContentEditable)) return;
      e.preventDefault();
      inputRef.current?.focus();
    }
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [showShortcut]);

  function onSubmit(e: FormEvent) {
    e.preventDefault();
    const slug = titleToSlug(q);
    if (!slug) return;
    navigate({ to: "/product/$slug", params: { slug } });
  }

  return (
    <form
      onSubmit={onSubmit}
      role="search"
      className={cn(
        "relative flex w-full items-center overflow-hidden rounded-full border border-border bg-card shadow-card transition-shadow focus-within:shadow-lift",
        className,
      )}
    >
      <Search
        className={cn(
          "pointer-events-none ml-4 text-muted-foreground",
          size === "lg" ? "h-5 w-5" : "h-4 w-4",
        )}
        aria-hidden="true"
      />
      <input
        ref={inputRef}
        type="search"
        value={q}
        onChange={(e) => setQ(e.target.value)}
        placeholder={placeholder}
        aria-label="Search products"
        autoFocus={autoFocus}
        className={cn(
          "flex-1 bg-transparent px-3 outline-none placeholder:text-muted-foreground",
          size === "lg" ? "py-4 text-base" : size === "sm" ? "py-2 text-sm" : "py-3 text-sm",
        )}
      />
      {showShortcut && size !== "sm" && (
        <kbd
          aria-hidden="true"
          className="mr-2 hidden select-none rounded border border-border bg-muted px-1.5 py-0.5 font-mono text-[10px] font-medium text-muted-foreground md:inline-block"
          title="Press / to search"
        >
          /
        </kbd>
      )}
      <button
        type="submit"
        className={cn(
          "mr-1.5 inline-flex items-center justify-center rounded-full bg-amber font-semibold text-amber-foreground transition-transform hover:scale-[1.02] active:scale-95",
          size === "lg" ? "px-6 py-3 text-sm" : size === "sm" ? "px-3 py-1.5 text-xs" : "px-4 py-2 text-sm",
        )}
      >
        Find picks
      </button>
    </form>
  );
}

