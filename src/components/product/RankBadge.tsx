import { cn } from "@/lib/utils";

export function RankBadge({ rank }: { rank: number }) {
  return (
    <div
      aria-label={`Rank ${rank}`}
      className={cn(
        "absolute -left-3 top-6 z-10 flex h-10 w-10 items-center justify-center rounded-full border-2 border-background bg-foreground font-serif text-lg font-bold text-background shadow-card",
        rank === 1 && "bg-amber text-amber-foreground",
      )}
    >
      {rank}
    </div>
  );
}
