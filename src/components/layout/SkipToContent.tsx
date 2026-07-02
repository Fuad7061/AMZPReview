/**
 * Visually hidden link, revealed on keyboard focus, that jumps screen
 * reader / keyboard users past the header straight to the main content.
 */
export function SkipToContent() {
  return (
    <a
      href="#main"
      className="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-[100] focus:rounded-full focus:bg-foreground focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-background focus:shadow-lift focus-visible:ring-2 focus-visible:ring-ring"
    >
      Skip to content
    </a>
  );
}
