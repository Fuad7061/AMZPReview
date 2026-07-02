import { Info } from "lucide-react";
import { Link } from "@tanstack/react-router";
import { SITE_NAME } from "@/config/site";

export function AffiliateDisclosure() {
  return (
    <div className="border-b border-disclosure-border bg-disclosure-bg">
      <div className="container-page flex items-start gap-2 py-2 text-xs text-disclosure-fg">
        <Info className="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" />
        <p>
          <strong>Advertising disclosure:</strong> As an Amazon Associate,{" "}
          {SITE_NAME} earns from qualifying purchases. This does not affect our
          rankings or prices.{" "}
          <Link to="/disclosure" className="underline underline-offset-2 hover:no-underline">
            Learn more
          </Link>
          .
        </p>
      </div>
    </div>
  );
}

export function FullDisclosure() {
  return (
    <section
      id="disclosure"
      aria-labelledby="disclosure-heading"
      className="mx-auto mt-12 max-w-3xl rounded-2xl border border-border bg-card p-6 text-sm text-muted-foreground"
    >
      <h2 id="disclosure-heading" className="font-serif text-2xl text-foreground">
        Disclosure
      </h2>
      <p className="mt-3">
        {SITE_NAME} is a participant in the Amazon Services LLC Associates
        Program, an affiliate advertising program designed to provide a means
        for sites to earn advertising fees by advertising and linking to
        Amazon.com.
      </p>
      <p className="mt-3">
        <strong>As an Amazon Associate, {SITE_NAME} earns from qualifying purchases.</strong>{" "}
        This relationship does not influence the rankings or scores you see on this
        site — those are derived from the product data itself (see{" "}
        <Link to="/methodology" className="underline underline-offset-2">
          our methodology
        </Link>
        ). Prices and availability are accurate as of the date and time shown on
        each listing and are subject to change. Always check the price on
        Amazon.com for the most current information.
      </p>
      <p className="mt-3">
        Product names, logos, and brands are property of their respective
        owners. Amazon and the Amazon logo are trademarks of Amazon.com, Inc.
        or its affiliates.
      </p>
    </section>
  );
}
