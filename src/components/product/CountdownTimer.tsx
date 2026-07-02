import { useEffect, useState } from "react";

function getTimeUntilMidnight() {
  const now = new Date();
  const tomorrow = new Date(now);
  tomorrow.setHours(24, 0, 0, 0);
  const diff = tomorrow.getTime() - now.getTime();
  return {
    h: Math.floor(diff / 3_600_000),
    m: Math.floor((diff % 3_600_000) / 60_000),
    s: Math.floor((diff % 60_000) / 1000),
  };
}

export function CountdownTimer() {
  const [t, setT] = useState<ReturnType<typeof getTimeUntilMidnight> | null>(null);
  useEffect(() => {
    setT(getTimeUntilMidnight());
    const id = setInterval(() => setT(getTimeUntilMidnight()), 1000);
    return () => clearInterval(id);
  }, []);

  return (
    <div
      className="flex items-center gap-2 rounded-xl border border-amber/30 bg-amber-soft/60 px-3 py-2 text-foreground"
      role="status"
      aria-live="polite"
    >
      <span className="text-xs font-medium text-foreground/80">Refreshes in</span>
      <div className="flex items-center gap-1 font-mono text-sm">
        <TimeBox value={t?.h} label="hrs" />
        <span className="opacity-60">:</span>
        <TimeBox value={t?.m} label="min" />
        <span className="opacity-60">:</span>
        <TimeBox value={t?.s} label="sec" />
      </div>
    </div>
  );
}

function TimeBox({ value, label }: { value: number | undefined; label: string }) {
  const display = value == null ? "--" : String(value).padStart(2, "0");
  return (
    <span className="flex flex-col items-center">
      <span
        className="rounded bg-background/80 px-1.5 py-0.5 font-semibold text-foreground"
        suppressHydrationWarning
      >
        {display}
      </span>
      <span className="mt-0.5 text-[9px] uppercase tracking-wider text-foreground/60">{label}</span>
    </span>
  );
}
