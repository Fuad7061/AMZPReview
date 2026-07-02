# Deploying the WordPress theme to Hostinger (shared hosting)

This guide walks you through publishing the **Product Reviews** WordPress
theme to a Hostinger shared plan so your domain serves the live site exactly
the way the React preview looks here on Lovable.

> **Reminder:** Hostinger shared plans run **PHP** (not Node), so the React
> build in this repo is used as a *design preview only*. The live site is
> rendered by the bundled WordPress theme in `wordpress-theme/`.

---

## 1 · Point your domain at Hostinger

1. In Hostinger → **Domains** → add your domain (e.g. `yadfoods.com`).
2. Update the nameservers at your registrar to Hostinger's:
   `ns1.dns-parking.com` and `ns2.dns-parking.com`.
3. Wait for DNS to propagate (5–60 minutes).

## 2 · Install WordPress

1. Hostinger → **Hosting → Auto-installer → WordPress**.
2. Install into the **root** of `public_html` (no sub-folder).
3. Choose a strong admin password — you'll harden the login URL later
   with the **WPS Hide Login** plugin (e.g. `/logmein`).

## 3 · Upload the theme

From this repo:

```bash
cd wordpress-theme
./build-zip.sh    # produces wordpress-theme/product-reviews.zip
```

In wp-admin:

1. **Appearance → Themes → Add New → Upload Theme**.
2. Pick `product-reviews.zip`, install and **Activate**.
3. Re-run `./build-zip.sh` for any future change — the script auto-bumps
   the patch version so WP recognises the update.

## 4 · Configure the essentials

| wp-admin → | What to set |
| --- | --- |
| **Settings → General** | Site title, tagline, timezone. |
| **Settings → Permalinks** | Choose **Post name** (`/%postname%/`). |
| **Reviews → Settings** | Amazon Associates tag, API URL, currency. |
| **Reviews → Email Alerts → Transports** | Pick Brevo / SendGrid / SMTP / Mailchimp / wp_mail and paste credentials. |
| **Reviews → Email Alerts → Routing** | Map each purpose (confirmations vs. alerts) to a transport + fallback. |

All credentials are encrypted at rest using the WP salt — never copy them
into git or `wp-config.php`.

## 5 · Verify the live site

1. Open your domain — the homepage should render with the same categories,
   hero, and footer as the React preview.
2. Visit `/best/<category>/` — programmatic hubs should appear.
3. `your-domain.com/sitemap.xml` should list posts, categories, and hubs.
4. Submit the sitemap in **Google Search Console**.

## 6 · Hardening (optional)

- **Custom login URL** — install **WPS Hide Login** and set `/logmein`.
- **Caching** — install **LiteSpeed Cache** (Hostinger ships LiteSpeed).
- **SSL** — Hostinger → **SSL → Install** for the domain.
- **Daily backups** — Hostinger → **Backups → Enable**.

---

### Where the React build fits in

Keep this repo on Lovable as your **design sandbox**:

- Iterate on UI/UX with instant preview.
- When happy, port the same change into the WP theme PHP templates.
- Re-build the zip and upload.

The React app and the WP theme share the same `src/config/site.ts` /
`inc/categories.php` taxonomy — keep them in sync.
