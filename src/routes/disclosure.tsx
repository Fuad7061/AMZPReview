import { createFileRoute } from "@tanstack/react-router";
import { FullDisclosure } from "@/components/sections/AffiliateDisclosure";
import { SITE_NAME } from "@/config/site";

export const Route = createFileRoute("/disclosure")({
  head: () => ({
    meta: [
      { title: `Affiliate disclosure — ${SITE_NAME}` },
      { name: "description", content: `Full Amazon Associates affiliate disclosure for ${SITE_NAME}.` },
      { property: "og:title", content: `Affiliate disclosure — ${SITE_NAME}` },
      { property: "og:description", content: `Full Amazon Associates affiliate disclosure.` },
      { property: "og:url", content: "/disclosure" },
    ],
    links: [{ rel: "canonical", href: "/disclosure" }],
  }),
  component: DisclosurePage,
});

function DisclosurePage() {
  return (
    <div className="container-page py-12 md:py-20">
      <div className="mx-auto max-w-3xl">
        <p className="text-xs font-semibold uppercase tracking-wider text-amber">Legal</p>
        <h1 className="mt-2 font-serif text-5xl text-foreground">Affiliate disclosure</h1>
        <FullDisclosure />
      </div>
    </div>
  );
}
