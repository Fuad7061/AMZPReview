import { useState } from "react";
import { ChevronDown } from "lucide-react";
import { Link } from "@tanstack/react-router";
import { SITE_NAME } from "@/config/site";
import { cn } from "@/lib/utils";

export interface FaqItem {
  q: string;
  a: React.ReactNode;
}

export function buildDefaultFaq(productName: string, asOf?: string): FaqItem[] {
  return [
    {
      q: `How did ${SITE_NAME} pick these ${productName.toLowerCase()}?`,
      a: (
        <>
          We rank live Amazon listings using a composite of position, discount
          depth, and feature coverage. We do not accept payment for placement.
          See our full{" "}
          <Link to="/methodology" className="underline underline-offset-2">
            methodology
          </Link>
          .
        </>
      ),
    },
    {
      q: "Are the prices on this page current?",
      a: (
        <>
          Prices reflect the Amazon listing at the time shown
          {asOf ? ` (${asOf})` : ""} and are subject to change. Always confirm
          the live price on Amazon.com before purchasing.
        </>
      ),
    },
    {
      q: `How does ${SITE_NAME} make money?`,
      a: (
        <>
          As an Amazon Associate, {SITE_NAME} earns a small commission when you
          buy through our links — at no extra cost to you. This never affects
          our rankings.{" "}
          <Link to="/disclosure" className="underline underline-offset-2">
            Full disclosure
          </Link>
          .
        </>
      ),
    },
    {
      q: "What if a product is out of stock?",
      a: (
        <>
          Stock changes constantly. If a listing is unavailable when you click
          through, try one of the alternatives in our comparison table, or
          refresh this page in a few hours for the latest snapshot.
        </>
      ),
    },
  ];
}

export function FaqSection({ items }: { items: FaqItem[] }) {
  return (
    <section
      id="faq"
      aria-labelledby="faq-heading"
      className="scroll-mt-28 rounded-2xl border border-border bg-card p-6 shadow-card md:p-8"
    >
      <h2 id="faq-heading" className="font-serif text-3xl text-foreground">
        Frequently asked questions
      </h2>
      <div className="mt-5 divide-y divide-border">
        {items.map((item, i) => (
          <FaqRow key={i} item={item} defaultOpen={i === 0} />
        ))}
      </div>
    </section>
  );
}

function FaqRow({ item, defaultOpen }: { item: FaqItem; defaultOpen?: boolean }) {
  const [open, setOpen] = useState(!!defaultOpen);
  return (
    <div className="py-3">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
        className="flex w-full items-center justify-between gap-3 text-left"
      >
        <span className="font-medium text-foreground">{item.q}</span>
        <ChevronDown
          className={cn(
            "h-4 w-4 shrink-0 text-muted-foreground transition-transform",
            open && "rotate-180",
          )}
        />
      </button>
      {open && (
        <div className="mt-2 text-sm leading-relaxed text-muted-foreground">{item.a}</div>
      )}
    </div>
  );
}
