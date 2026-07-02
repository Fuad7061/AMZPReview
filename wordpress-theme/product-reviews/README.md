# YadFood Reviews — WordPress Theme

Editorial-style Amazon affiliate review theme with **live Amazon product data**, **AI-generated articles**, smart search, and Gutenberg blocks. Built for `yadfood.com`.

## What it does

- Fully automated review article generation (on-demand + scheduled).
- Live product data via Amazon Product Advertising API (PA-API 5.0).
- BYOK AI: OpenAI, Google Gemini, or Anthropic Claude.
- Custom post type `Review` with categories, tags, and meta.
- Smart search query normalization (strips "best", "review", "for men", etc.).
- JSON-LD schema: Article, ItemList, Product, FAQPage, BreadcrumbList.
- Gutenberg blocks: Top Products, Comparison Table, Pros/Cons.
- Editorial design system: warm paper + amber CTA + emerald success.

## Installation

1. **Download / zip the theme**
   - Zip the `yadfood-reviews/` folder so the zip's root is `yadfood-reviews/` (not `wordpress-theme/yadfood-reviews/`).
   - Or, on your server, copy the folder to `wp-content/themes/yadfood-reviews/` directly.

2. **Install in WordPress**
   - WP admin → **Appearance → Themes → Add New → Upload Theme**.
   - Pick the zip → **Install Now → Activate**.

3. **Configure settings** — go to **Appearance → Customize**:

   - **Affiliate Settings**
     - Amazon Associates Tag: `YOUR-TAG-20` (replace with your real tag).
     - FTC Disclosure: pre-filled, edit to taste.
   - **AI Article Generation**
     - Provider: OpenAI / Gemini / Anthropic.
     - Model: e.g. `gpt-4o-mini`, `gemini-2.5-flash`, `claude-3-5-sonnet-latest`.
     - API Key: paste your provider key.
     - Auto-generate scheduled articles: check to enable daily cron.
   - **Amazon Product API** (required for live data)
     - PA-API Access Key + Secret Key from [Amazon Associates Central → Tools → PA-API](https://affiliate-program.amazon.com/assoc_credentials/home).
     - Region: `us-east-1` (default).
     - Marketplace: `www.amazon.com`.

4. **Set the permalink structure**
   - WP admin → **Settings → Permalinks → Post name** → Save (flushes rewrite rules for `/review/...`).

## Generating articles

### On-demand
1. WP admin → **Reviews → Auto Generator**.
2. Type a keyword (e.g. *best coffee grinder*).
3. Choose product count and status (draft recommended).
4. Click **Generate Article**.
5. Edit the draft, publish when happy.

### Scheduled (daily)
1. **Reviews → Auto Queue** — paste one keyword per line.
2. Enable cron in **Customize → AI Article Generation → Auto-generate scheduled articles**.
3. WP-Cron processes one keyword per day and saves a draft.

## Editing a review

Each review post has these structured fields (auto-filled by the AI, editable by hand):
- **TL;DR** — short summary shown in the amber box.
- **Intro** — first editorial paragraph.
- **Products** (JSON array) — rank, ASIN, title, image, price, rating, why, pros, cons, badge.
- **Buyer's Guide** — JSON array of bullet points.
- **FAQs** — JSON array of `{q, a}`.

## Gutenberg blocks

Insert any of these in any post or page:
- **YadFood — Top Products** (`yadfood/top-products`) — set `reviewId` and `count`.
- **YadFood — Comparison Table** (`yadfood/comparison`) — set `reviewId`.
- **YadFood — Pros / Cons** (`yadfood/pros-cons`) — pure inline lists.

## REST API

| Method | Endpoint | Notes |
|---|---|---|
| POST | `/wp-json/yadfood/v1/generate` | Body: `{ keyword, count?, status? }`. Requires `edit_posts`. |
| POST | `/wp-json/yadfood/v1/click`    | Affiliate click logger stub. Public. |

## Requirements

- WordPress 6.4+
- PHP 8.0+
- HTTPS (PA-API requires it)
- Shared hosting is fine — no Composer, no Node build step, no SDK.

## Notes & caveats

- **PA-API requires sales:** Amazon revokes PA-API access if the account hasn't had qualifying sales in 30 days. Generate a few articles, drive traffic, get a sale, you're set.
- **AI costs:** OpenAI `gpt-4o-mini` ≈ $0.001 per article. Gemini Flash is cheaper. Claude Sonnet is pricier but writes the best copy.
- **WP-Cron:** triggers on page visits. For high reliability, set a real cron job hitting `wp-cron.php` once an hour.
- **Categories:** the AI assigns one per article; you can rename/merge them under **Reviews → Categories**.

## File map

```
yadfood-reviews/
├── style.css                  Theme header
├── functions.php              Theme bootstrap
├── header.php / footer.php    Layout chrome
├── index.php                  Home (latest reviews)
├── single-review.php          Main review page
├── archive-review.php         Category / archive pages
├── search.php                 Smart-search results
├── page.php / 404.php
├── inc/
│   ├── helpers.php            Shared helpers
│   ├── enqueue.php            Scripts/styles loader
│   ├── post-types.php         review CPT
│   ├── taxonomies.php         review_category / review_tag
│   ├── meta-fields.php        Native meta box + REST meta registration
│   ├── customizer.php         All admin settings
│   ├── smart-search.php       Query normalizer (PHP port)
│   ├── schema.php             JSON-LD output
│   ├── amazon-api.php         PA-API SearchItems (AWS SigV4)
│   ├── ai-generator.php       AI providers + article assembly
│   ├── admin-page.php         Auto Generator + Auto Queue pages
│   ├── cron.php               Daily scheduled generation
│   ├── rest-api.php           REST endpoints
│   └── blocks.php             Gutenberg blocks
├── template-parts/
│   ├── product-card.php
│   ├── comparison-table.php
│   ├── related-products.php
│   └── affiliate-disclosure.php
├── assets/
│   ├── css/main.css           Design system
│   ├── css/admin.css
│   ├── js/main.js             Click tracking + TOC
│   └── js/admin.js
└── languages/                  (translation .pot lives here)
```

## License

GPL v2 or later.
