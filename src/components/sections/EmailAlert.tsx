import { useState, type FormEvent } from "react";
import { Bell, Mail } from "lucide-react";
import { useServerFn } from "@tanstack/react-start";
import { subscribeToPriceAlerts } from "@/lib/subscribe.functions";

export function EmailAlert({
  productName,
  slug,
}: {
  productName: string;
  slug: string;
}) {
  const subscribe = useServerFn(subscribeToPriceAlerts);
  const [email, setEmail] = useState("");
  const [state, setState] = useState<"idle" | "loading" | "done" | "error">("idle");

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    if (!/.+@.+\..+/.test(email)) {
      setState("error");
      return;
    }
    setState("loading");
    try {
      await subscribe({ data: { email, productName, slug } });
      setState("done");
    } catch {
      setState("error");
    }
  }

  if (state === "done") {
    return (
      <section className="mt-10 overflow-hidden rounded-2xl border border-amber/30 bg-amber-soft/50 p-6 text-center shadow-card md:p-10">
        <div className="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-full bg-amber text-2xl text-amber-foreground">
          📬
        </div>
        <h2 className="mt-4 font-serif text-3xl text-foreground">You're in</h2>
        <p className="mt-2 text-sm text-muted-foreground">
          We'll let you know when prices drop on {productName.toLowerCase()}.
        </p>
      </section>
    );
  }

  return (
    <section className="mt-10 overflow-hidden rounded-2xl border border-amber/30 bg-amber-soft/40 p-6 shadow-card md:p-10">
      <div className="mx-auto max-w-2xl text-center">
        <div className="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-full bg-amber/20 text-amber-foreground">
          <Bell className="h-6 w-6" />
        </div>
        <h2 className="mt-4 font-serif text-3xl text-foreground">
          Get price-drop alerts for {productName.toLowerCase()}
        </h2>
        <p className="mt-2 text-sm text-muted-foreground">
          Never overpay. We'll email you when prices fall on the top picks above.
        </p>
        <form onSubmit={onSubmit} className="mt-5 flex flex-col gap-2 sm:flex-row">
          <label htmlFor="alert-email" className="sr-only">
            Email address
          </label>
          <div className="relative flex-1">
            <Mail className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <input
              id="alert-email"
              type="email"
              value={email}
              onChange={(e) => {
                setEmail(e.target.value);
                if (state === "error") setState("idle");
              }}
              placeholder="you@example.com"
              required
              className="w-full rounded-full border border-border bg-card py-3 pl-10 pr-4 text-sm outline-none focus:ring-2 focus:ring-ring"
            />
          </div>
          <button
            type="submit"
            disabled={state === "loading"}
            className="rounded-full bg-foreground px-6 py-3 text-sm font-semibold text-background transition-transform hover:scale-[1.02] active:scale-95 disabled:opacity-60"
          >
            {state === "loading" ? "Setting…" : "Set alert"}
          </button>
        </form>
        {state === "error" && (
          <p className="mt-2 text-xs text-danger">Please enter a valid email address.</p>
        )}
        <p className="mt-3 text-[11px] text-muted-foreground">
          No spam. Unsubscribe anytime. We never share your email.
        </p>
        <p className="mt-1 text-[10px] uppercase tracking-wide text-muted-foreground/70">
          Preview only — live alerts run on the bundled WordPress install (Reviews → Email Alerts).
        </p>
      </div>
    </section>
  );
}
