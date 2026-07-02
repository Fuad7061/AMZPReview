import { SITE_DESCRIPTION, SITE_NAME } from "@/config/site";
import type { Product } from "./types";

export function websiteJsonLd() {
  return {
    "@context": "https://schema.org",
    "@type": "WebSite",
    name: SITE_NAME,
    description: SITE_DESCRIPTION,
    potentialAction: {
      "@type": "SearchAction",
      target: { "@type": "EntryPoint", urlTemplate: "/product/{search_term}" },
      "query-input": "required name=search_term",
    },
  };
}

export function organizationJsonLd() {
  return {
    "@context": "https://schema.org",
    "@type": "Organization",
    name: SITE_NAME,
    description: SITE_DESCRIPTION,
  };
}

export function breadcrumbJsonLd(items: { name: string; path: string }[]) {
  return {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: items.map((it, i) => ({
      "@type": "ListItem",
      position: i + 1,
      name: it.name,
      item: it.path,
    })),
  };
}

export function itemListJsonLd(products: Product[], pageTitle: string) {
  return {
    "@context": "https://schema.org",
    "@type": "ItemList",
    name: pageTitle,
    numberOfItems: products.length,
    itemListElement: products.map((p) => ({
      "@type": "ListItem",
      position: p.index,
      item: {
        "@type": "Product",
        name: p.title,
        image: p.image_url,
        brand: p.brand ? { "@type": "Brand", name: p.brand } : undefined,
        aggregateRating: {
          "@type": "AggregateRating",
          ratingValue: p.score,
          bestRating: "10",
          worstRating: "1",
          ratingCount: 25,
        },
        offers: {
          "@type": "Offer",
          price: p.price_sort,
          priceCurrency: "USD",
          availability: "https://schema.org/InStock",
          itemCondition:
            p.condition?.toLowerCase() === "new"
              ? "https://schema.org/NewCondition"
              : "https://schema.org/UsedCondition",
        },
      },
    })),
  };
}

/** Per-product Review schema. One per product on the page. */
export function reviewJsonLd(
  p: Product,
  reviewer: string,
  pageUrl: string,
) {
  return {
    "@context": "https://schema.org",
    "@type": "Review",
    itemReviewed: {
      "@type": "Product",
      name: p.title,
      image: p.image_url,
      brand: p.brand ? { "@type": "Brand", name: p.brand } : undefined,
      sku: p.id,
    },
    author: { "@type": "Organization", name: reviewer },
    reviewRating: {
      "@type": "Rating",
      ratingValue: p.score,
      bestRating: "10",
      worstRating: "1",
    },
    reviewBody: `Ranked #${p.index} in our editorial roundup. ${(p.features || [])
      .slice(0, 2)
      .join(". ")}`,
    url: `${pageUrl}#pick-${p.index}`,
  };
}

export function faqJsonLd(items: { q: string; a: string }[]) {
  return {
    "@context": "https://schema.org",
    "@type": "FAQPage",
    mainEntity: items.map((it) => ({
      "@type": "Question",
      name: it.q,
      acceptedAnswer: { "@type": "Answer", text: it.a },
    })),
  };
}

