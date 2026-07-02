import { createServerFn } from "@tanstack/react-start";
import { z } from "zod";

const Schema = z.object({
  asin: z.string().trim().min(1).max(40),
  position: z.number().int().min(1).max(50),
  slug: z.string().trim().min(1).max(200),
});

export const recordAffiliateClick = createServerFn({ method: "POST" })
  .inputValidator((data: unknown) => Schema.parse(data))
  .handler(async ({ data }) => {
    console.log(
      `[click] asin=${data.asin} pos=${data.position} slug=${data.slug}`,
    );
    return { success: true } as const;
  });
