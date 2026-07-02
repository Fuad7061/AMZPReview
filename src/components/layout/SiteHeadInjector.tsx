import { useEffect } from "react";
import { useServerFn } from "@tanstack/react-start";
import { getSiteSettings } from "@/lib/admin.functions";

/**
 * Client-side injector: pulls admin settings once, appends custom <head>
 * HTML and GA4 gtag.js. Runs after hydration — sufficient for GA and most
 * pixel tags. For SEO verification prefer DNS or HTML-file methods.
 */
export function SiteHeadInjector() {
  const load = useServerFn(getSiteSettings);
  useEffect(() => {
    let cancelled = false;
    load()
      .then((s) => {
        if (cancelled || typeof document === "undefined") return;
        if (s.headCode?.trim()) {
          const holder = document.createElement("div");
          holder.setAttribute("data-admin-head", "true");
          holder.innerHTML = s.headCode;
          Array.from(holder.childNodes).forEach((n) => document.head.appendChild(n));
        }
        if (s.gaId?.trim() && /^G-[A-Z0-9]+$/i.test(s.gaId.trim())) {
          const id = s.gaId.trim();
          const s1 = document.createElement("script");
          s1.async = true;
          s1.src = `https://www.googletagmanager.com/gtag/js?id=${id}`;
          document.head.appendChild(s1);
          const s2 = document.createElement("script");
          s2.text = `window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','${id}');`;
          document.head.appendChild(s2);
        }
      })
      .catch(() => {});
    return () => {
      cancelled = true;
    };
  }, [load]);
  return null;
}
