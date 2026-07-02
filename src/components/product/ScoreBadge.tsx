import { Star } from "lucide-react";
import { cn } from "@/lib/utils";

export function ScoreBadge({
  score,
  savings,
}: {
  score: string;
  savings?: number | "";
}) {
  return (
    <div className="flex flex-col items-center gap-1 rounded-xl border border-border bg-card px-3 py-2 text-center shadow-card">
      <div className="font-serif text-2xl font-semibold leading-none text-foreground">
        {score}
      </div>
      <div className="flex items-center gap-0.5" aria-label="Editorial score">
        {[0, 1, 2, 3, 4].map((i) => (
          <Star
            key={i}
            aria-hidden="true"
            className={cn(
              "h-3 w-3",
              i < Math.round((parseFloat(score) / 10) * 5)
                ? "fill-amber text-amber"
                : "text-muted",
            )}
          />
        ))}
      </div>
      {typeof savings === "number" && savings > 0 && (
        <div className="rounded-md bg-success-soft px-1.5 py-0.5 text-[10px] font-semibold text-success-foreground">
          Save {savings}%
        </div>
      )}
    </div>
  );
}
