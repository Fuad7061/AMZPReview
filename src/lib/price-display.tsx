import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { formatPrice, parsePrice } from "@/lib/utils";

/**
 * Amazon Associates Operating Agreement forbids displaying stale or
 * inaccurate prices outside the Amazon-supplied widgets. Scraped prices
 * drift quickly, so by default we hide the exact number and show a
 * tier symbol ($, $$, $$$, $$$$, $$$$$) that conveys the rough range
 * without making a factual price claim. Users can opt in to see the
 * last-scraped numeric price via the header toggle.
 */

const STORAGE_KEY = "pickranker-show-prices";
const DEALS_STORAGE_KEY = "pickranker-show-deal-prices";

interface Ctx {
  showPrices: boolean;
  toggle: () => void;
  setShowPrices: (v: boolean) => void;
  /** Format a price respecting the current visibility setting. */
  format: (price: string | number | undefined | null) => string;
  /** Deal-context visibility (DealsBox, ExitIntentDeal). Defaults ON. */
  showDealPrices: boolean;
  toggleDealPrices: () => void;
  setShowDealPrices: (v: boolean) => void;
  /** Format a price for deal contexts. Hides only when explicitly disabled. */
  formatDeal: (price: string | number | undefined | null) => string;
}

const PriceCtx = createContext<Ctx | null>(null);

/** Map a numeric price to a tier symbol — broad enough to be honest. */
export function priceTierSymbol(price: string | number | undefined | null): string {
  if (price === undefined || price === null || price === "") return "—";
  const n = typeof price === "number" ? price : parsePrice(price);
  if (!n || isNaN(n) || n <= 0) return "—";
  if (n < 15) return "$";
  if (n < 50) return "$$";
  if (n < 150) return "$$$";
  if (n < 500) return "$$$$";
  return "$$$$$";
}

export function PriceDisplayProvider({ children }: { children: ReactNode }) {
  // Default hidden (true === prices hidden behind symbols).
  // SSR + first client render must match: start hidden.
  const [showPrices, setShowState] = useState<boolean>(false);
  // Deals default to SHOWING the price (true) — deal-context is opt-out.
  const [showDealPrices, setShowDealState] = useState<boolean>(true);

  useEffect(() => {
    try {
      const v = localStorage.getItem(STORAGE_KEY);
      if (v === "1") setShowState(true);
      const d = localStorage.getItem(DEALS_STORAGE_KEY);
      if (d === "0") setShowDealState(false);
    } catch {
      /* ignore */
    }
  }, []);

  useEffect(() => {
    try {
      localStorage.setItem(STORAGE_KEY, showPrices ? "1" : "0");
    } catch {
      /* ignore */
    }
  }, [showPrices]);

  useEffect(() => {
    try {
      localStorage.setItem(DEALS_STORAGE_KEY, showDealPrices ? "1" : "0");
    } catch {
      /* ignore */
    }
  }, [showDealPrices]);

  const setShowPrices = useCallback((v: boolean) => setShowState(v), []);
  const toggle = useCallback(() => setShowState((v) => !v), []);
  const setShowDealPrices = useCallback((v: boolean) => setShowDealState(v), []);
  const toggleDealPrices = useCallback(() => setShowDealState((v) => !v), []);

  const format = useCallback(
    (price: string | number | undefined | null) => {
      if (showPrices) return formatPrice(price);
      return priceTierSymbol(price);
    },
    [showPrices],
  );

  const formatDeal = useCallback(
    (price: string | number | undefined | null) => {
      if (showDealPrices) return formatPrice(price);
      return priceTierSymbol(price);
    },
    [showDealPrices],
  );

  const value = useMemo<Ctx>(
    () => ({
      showPrices,
      toggle,
      setShowPrices,
      format,
      showDealPrices,
      toggleDealPrices,
      setShowDealPrices,
      formatDeal,
    }),
    [showPrices, toggle, setShowPrices, format, showDealPrices, toggleDealPrices, setShowDealPrices, formatDeal],
  );

  return <PriceCtx.Provider value={value}>{children}</PriceCtx.Provider>;
}

export function usePriceDisplay(): Ctx {
  const ctx = useContext(PriceCtx);
  if (!ctx) {
    // Safe SSR / pre-mount fallback: hidden for general, shown for deals.
    return {
      showPrices: false,
      toggle: () => {},
      setShowPrices: () => {},
      format: (p) => priceTierSymbol(p),
      showDealPrices: true,
      toggleDealPrices: () => {},
      setShowDealPrices: () => {},
      formatDeal: (p) => formatPrice(p),
    };
  }
  return ctx;
}
