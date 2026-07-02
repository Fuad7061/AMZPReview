import { useMemo, useState } from "react";
import { TrendingDown, TrendingUp, Info } from "lucide-react";
import { usePriceDisplay } from "@/lib/price-display";
import type { PriceHistoryPoint } from "@/lib/types";

export function PriceHistory({ points }: { points: PriceHistoryPoint[] }) {
  const [hover, setHover] = useState<number | null>(null);
  const { format: formatPrice } = usePriceDisplay();

  const { min, max, current, lowest, highest, avg, path, areaPath, coords, minIdx, maxIdx } =
    useMemo(() => {
      const prices = points.map((p) => p.price);
      const minV = Math.min(...prices);
      const maxV = Math.max(...prices);
      const avgV = prices.reduce((a, b) => a + b, 0) / prices.length;
      const minI = prices.indexOf(minV);
      const maxI = prices.indexOf(maxV);
      const W = 800;
      const H = 200;
      const range = Math.max(0.01, maxV - minV);
      const stepX = points.length > 1 ? W / (points.length - 1) : W;
      const pts = points.map((p, i) => {
        const x = i * stepX;
        // pad vertically so peaks/troughs don't touch edges
        const y = 10 + (H - 20) - ((p.price - minV) / range) * (H - 20);
        return [x, y] as const;
      });
      // smooth catmull-rom-ish path
      const line = pts
        .map(([x, y], i) => `${i === 0 ? "M" : "L"}${x.toFixed(2)},${y.toFixed(2)}`)
        .join(" ");
      const area = `${line} L${W},${H} L0,${H} Z`;
      return {
        min: minV,
        max: maxV,
        current: prices[prices.length - 1],
        lowest: minV,
        highest: maxV,
        avg: avgV,
        path: line,
        areaPath: area,
        coords: pts,
        minIdx: minI,
        maxIdx: maxI,
      };
    }, [points]);

  const firstDate = points[0]?.date;
  const midDate = points[Math.floor(points.length / 2)]?.date;
  const lastDate = points[points.length - 1]?.date;
  const hovered = hover != null ? points[hover] : null;

  return (
    <div className="overflow-hidden rounded-2xl border border-border bg-card shadow-card">
      {/* Header */}
      <div className="px-6 pt-6 pb-4">
        <div className="mb-1 flex items-center gap-2">
          <h4 className="text-base font-bold text-foreground">Price snapshot</h4>
          <Info className="h-4 w-4 text-muted-foreground" aria-hidden="true" />
        </div>
        <p className="max-w-2xl text-xs leading-relaxed text-muted-foreground">
          Illustrative 6-month trend based on the current listed price. Updated daily — verify
          current availability on Amazon.
        </p>
      </div>

      {/* Stats grid */}
      <div className="grid grid-cols-2 gap-3 px-6 pb-6 md:grid-cols-4">
        <StatTile label="Current" value={formatPrice(current)} />
        <StatTile
          label="Lowest"
          value={formatPrice(lowest)}
          tone="amber"
          icon={<TrendingDown className="h-3 w-3" />}
        />
        <StatTile
          label="Highest"
          value={formatPrice(highest)}
          icon={<TrendingUp className="h-3 w-3 text-muted-foreground" />}
        />
        <StatTile label="Average" value={formatPrice(avg)} />
      </div>

      {/* Chart */}
      <div className="relative px-3 pb-3">
        <div
          className="relative h-64 w-full rounded-b-xl bg-gradient-to-b from-transparent to-muted/30"
          onMouseLeave={() => setHover(null)}
        >
          {/* Y-axis labels */}
          <div className="pointer-events-none absolute left-3 top-3 flex h-[calc(100%-3rem)] flex-col justify-between text-[10px] font-medium text-muted-foreground">
            <span>{formatPrice(max)}</span>
            <span>{formatPrice((max + min) / 2)}</span>
            <span>{formatPrice(min)}</span>
          </div>

          {/* SVG */}
          <svg
            className="absolute inset-0 h-full w-full overflow-visible"
            viewBox="0 0 800 200"
            preserveAspectRatio="none"
            aria-label="Price trend chart"
            role="img"
            style={{ padding: "12px 24px 32px 56px", boxSizing: "border-box" }}
          >
            <defs>
              <linearGradient id="ph-fill" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stopColor="var(--color-amber)" stopOpacity="0.28" />
                <stop offset="100%" stopColor="var(--color-amber)" stopOpacity="0" />
              </linearGradient>
            </defs>

            {/* gridlines */}
            {[0, 100, 200].map((y) => (
              <line
                key={y}
                x1="0"
                y1={y}
                x2="800"
                y2={y}
                stroke="var(--color-border)"
                strokeDasharray="4 4"
                vectorEffect="non-scaling-stroke"
              />
            ))}

            <path d={areaPath} fill="url(#ph-fill)" />
            <path
              d={path}
              fill="none"
              stroke="var(--color-amber)"
              strokeWidth="2.5"
              strokeLinecap="round"
              strokeLinejoin="round"
              vectorEffect="non-scaling-stroke"
            />

            {/* highest marker */}
            {coords[maxIdx] && (
              <>
                <circle
                  cx={coords[maxIdx][0]}
                  cy={coords[maxIdx][1]}
                  r="10"
                  fill="var(--color-amber)"
                  fillOpacity="0.15"
                  vectorEffect="non-scaling-stroke"
                />
                <circle
                  cx={coords[maxIdx][0]}
                  cy={coords[maxIdx][1]}
                  r="4"
                  fill="var(--color-amber)"
                  vectorEffect="non-scaling-stroke"
                />
              </>
            )}
            {/* lowest marker */}
            {coords[minIdx] && (
              <>
                <circle
                  cx={coords[minIdx][0]}
                  cy={coords[minIdx][1]}
                  r="10"
                  fill="var(--color-amber)"
                  fillOpacity="0.15"
                  vectorEffect="non-scaling-stroke"
                />
                <circle
                  cx={coords[minIdx][0]}
                  cy={coords[minIdx][1]}
                  r="4"
                  fill="var(--color-amber)"
                  vectorEffect="non-scaling-stroke"
                />
              </>
            )}
            {/* current marker */}
            {coords[coords.length - 1] && (
              <circle
                cx={coords[coords.length - 1][0]}
                cy={coords[coords.length - 1][1]}
                r="5"
                fill="var(--color-amber)"
                stroke="var(--color-background)"
                strokeWidth="2"
                vectorEffect="non-scaling-stroke"
              />
            )}
          </svg>

          {/* hover hit targets */}
          <div
            className="absolute inset-y-3 flex"
            style={{ left: "56px", right: "24px", bottom: "32px", top: "12px" }}
          >
            {points.map((p, i) => (
              <button
                key={p.date}
                type="button"
                onMouseEnter={() => setHover(i)}
                onFocus={() => setHover(i)}
                onBlur={() => setHover(null)}
                aria-label={`${p.date}: ${formatPrice(p.price)}`}
                className="h-full flex-1 cursor-crosshair focus:outline-none focus-visible:bg-amber/10"
              />
            ))}
          </div>

          {/* Floating hover tooltip — reserved space, no CLS */}
          <div
            className={`pointer-events-none absolute right-6 top-3 rounded-md bg-foreground px-2 py-1 text-[11px] font-semibold text-background shadow-lift transition-opacity ${
              hovered ? "opacity-100" : "opacity-0"
            }`}
          >
            {hovered ? `${hovered.date} · ${formatPrice(hovered.price)}` : "—"}
          </div>

          {/* X-axis labels */}
          <div
            className="absolute bottom-2 flex justify-between text-[10px] font-medium text-muted-foreground"
            style={{ left: "56px", right: "24px" }}
          >
            <span className="whitespace-nowrap">{firstDate}</span>
            <span className="whitespace-nowrap hidden sm:inline">{midDate}</span>
            <span className="whitespace-nowrap font-semibold text-foreground">{lastDate}</span>
          </div>
        </div>
      </div>

      {/* Footer */}
      <div className="flex items-center justify-between border-t border-border bg-muted/40 px-6 py-3">
        <span className="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">
          Illustrative · for reference only
        </span>
        <span className="text-[10px] font-medium uppercase tracking-wider text-amber">
          Verify on Amazon →
        </span>
      </div>
    </div>
  );
}

function StatTile({
  label,
  value,
  tone,
  icon,
}: {
  label: string;
  value: string;
  tone?: "amber";
  icon?: React.ReactNode;
}) {
  const isAmber = tone === "amber";
  return (
    <div
      className={`rounded-2xl border p-4 transition-colors ${
        isAmber
          ? "border-amber/30 bg-amber/5 hover:border-amber/50"
          : "border-border bg-muted/30 hover:border-amber/30"
      }`}
    >
      <span
        className={`mb-1 block text-[10px] font-bold uppercase tracking-widest ${
          isAmber ? "text-amber" : "text-muted-foreground"
        }`}
      >
        {label}
      </span>
      <div className="flex items-baseline gap-1">
        <span
          className={`text-2xl font-bold ${isAmber ? "text-amber" : "text-foreground"}`}
        >
          {value}
        </span>
        {icon}
      </div>
    </div>
  );
}
