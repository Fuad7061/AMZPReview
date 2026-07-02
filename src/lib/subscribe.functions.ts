import { createServerFn } from "@tanstack/react-start";
import { z } from "zod";

const Schema = z.object({
  email: z.string().trim().toLowerCase().email().max(254),
  productName: z.string().trim().min(1).max(200),
  slug: z.string().trim().min(1).max(200),
});

/**
 * This is the Lovable preview surface. Live price-drop alerts run inside the
 * bundled WordPress theme (wordpress-theme/product-reviews) — see Reviews →
 * Email Alerts in wp-admin for transports (Brevo / SendGrid / Gmail / Mailchimp
 * / custom SMTP), routing, and subscribers. The preview returns success so the
 * UI can be designed and demoed, but it does not store or send anything.
 */
export const subscribeToPriceAlerts = createServerFn({ method: "POST" })
  .inputValidator((data: unknown) => Schema.parse(data))
  .handler(async ({ data }) => {
    console.log(
      `[preview-only] price-alert email=${data.email} product=${data.productName} slug=${data.slug}`,
    );
    return { success: true, previewOnly: true } as const;
  });
