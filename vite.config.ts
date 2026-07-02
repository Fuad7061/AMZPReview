// @lovable.dev/vite-tanstack-config already includes tanstackStart, viteReact, tailwindcss,
// tsConfigPaths, nitro, componentTagger, etc. Do NOT re-add them.
import { defineConfig } from "@lovable.dev/vite-tanstack-config";

// Inside Lovable's own build the preset is forced to cloudflare; outside (Netlify, VPS, etc.)
// this preset applies. Netlify preset writes the SSR handler to .netlify/functions-internal
// and static assets to dist/, which Netlify auto-wires.
export default defineConfig({
  tanstackStart: {
    server: { entry: "server" },
  },
  nitro: {
    preset: process.env.NITRO_PRESET || "netlify",
  },
});
