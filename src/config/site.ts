/**
 * Site-wide configuration — single source of truth.
 *
 * Change SITE_NAME, SITE_DOMAIN, or AMAZON_TAG below and the whole site
 * (header, footer, disclosures, page titles, OG tags, affiliate links,
 * tracking subtags) updates automatically.
 */

export const SITE_NAME = "PickRanker";
export const SITE_TAGLINE = "Find & compare the best products on Amazon";
export const SITE_DESCRIPTION =
  "Expert-curated rankings, side-by-side comparisons, and live Amazon pricing — so you can pick the right product in minutes, not hours.";

// Public domain (used inside ascsubtag tracking and disclosures).
export const SITE_DOMAIN = "pickranker.com";

// Canonical URL root. Override via VITE_SITE_URL at build time so the same
// codebase can ship to multiple hosts (staging, preview, production) without
// hard-coding the domain into sitemaps, JSON-LD or OG tags.
export const SITE_URL: string =
  (typeof import.meta !== "undefined" && (import.meta as ImportMeta).env?.VITE_SITE_URL) ||
  `https://${SITE_DOMAIN}`;

// Your Amazon Associates tracking tag. Replace with your real tag.
export const AMAZON_TAG = "YOUR-TAG-20";

// Upstream product data API (the provided Lambda).
export const API_URL =
  "https://4pobkr5oa4olwuvhx625uiozay0rrcuu.lambda-url.us-east-1.on.aws";

/**
 * Feature flags — flip a conversion / SEO surface off without code-diving.
 * Keep defaults conservative: only enable what's been verified live.
 * The WordPress theme has the same toggles under Reviews → Settings.
 */
export const FEATURES = {
  /** Sticky "View top deal" CTA bar on mobile product pages. */
  mobileStickyCta: true,
  /** Exit-intent popover offering the best current discount. */
  exitIntentDeal: true,
  /** "Deals are live" countdown box near the top of product pages. */
  dealsBox: true,
  /** Price-drop email-alert section (preview-only on the React build). */
  emailAlerts: true,
  /** Amazon-style mega-menu in the header. */
  megaMenu: true,
  /** Inject FAQPage / Breadcrumb / Product JSON-LD into every roundup. */
  richResults: true,
} as const;


// Popular categories shown on the homepage (flat list — kept for back-compat).
// Curated from high-search-volume Amazon queries (headphones, air fryers,
// robot vacuums, smartwatches, etc.) to maximise organic discovery.
export const POPULAR_CATEGORIES: { slug: string; label: string; emoji: string }[] = [
  { slug: "wireless-earbuds", label: "Wireless Earbuds", emoji: "🎧" },
  { slug: "smartwatches", label: "Smartwatches", emoji: "⌚" },
  { slug: "air-fryers", label: "Air Fryers", emoji: "🍟" },
  { slug: "robot-vacuums", label: "Robot Vacuums", emoji: "🤖" },
  { slug: "smart-tvs", label: "Smart TVs", emoji: "📺" },
  { slug: "laptops", label: "Laptops", emoji: "💻" },
  { slug: "coffee-bean-review", label: "Coffee Beans", emoji: "☕" },
  { slug: "mattresses", label: "Mattresses", emoji: "🛏️" },
  { slug: "running-shoes", label: "Running Shoes", emoji: "👟" },
];

// Amazon-style hierarchical "Shop by Department" structure.
// Departments group sub-categories the way shoppers actually browse.
//
// EDITABILITY: this constant is the single source of truth for the mega-menu.
// The companion WordPress theme exposes the same taxonomy through
// wp-admin → Reviews → Categories (see wordpress-theme/product-reviews/inc/
// categories.php), so editors using the WP build can add / rename / reorder
// departments without touching code. On the React build, edit this array.
export type Department = {
  slug: string;
  label: string;
  emoji: string;
  blurb: string;
  children: { slug: string; label: string; emoji: string }[];
};

export const DEPARTMENTS: Department[] = [
  {
    slug: "electronics",
    label: "Electronics",
    emoji: "📱",
    blurb: "Phones, laptops, audio & TVs",
    children: [
      { slug: "wireless-earbuds", label: "Wireless Earbuds", emoji: "🎧" },
      { slug: "noise-cancelling-headphones", label: "Noise-Cancelling Headphones", emoji: "🎧" },
      { slug: "bluetooth-speakers", label: "Bluetooth Speakers", emoji: "🔊" },
      { slug: "smartwatches", label: "Smartwatches", emoji: "⌚" },
      { slug: "laptops", label: "Laptops", emoji: "💻" },
      { slug: "tablets", label: "Tablets", emoji: "📱" },
      { slug: "smart-tvs", label: "Smart TVs", emoji: "📺" },
      { slug: "printers", label: "Printers", emoji: "🖨️" },
      { slug: "monitors", label: "Monitors", emoji: "🖥️" },
      { slug: "e-readers", label: "E-Readers", emoji: "📖" },
    ],
  },
  {
    slug: "home-kitchen",
    label: "Home & Kitchen",
    emoji: "🏠",
    blurb: "Appliances, cookware & bedding",
    children: [
      { slug: "air-fryers", label: "Air Fryers", emoji: "🍟" },
      { slug: "espresso-machines", label: "Espresso Machines", emoji: "☕" },
      { slug: "coffee-bean-review", label: "Coffee Beans", emoji: "🫘" },
      { slug: "stand-mixers", label: "Stand Mixers", emoji: "🥣" },
      { slug: "blenders", label: "Blenders", emoji: "🥤" },
      { slug: "instant-pots", label: "Instant Pots", emoji: "🍲" },
      { slug: "robot-vacuums", label: "Robot Vacuums", emoji: "🤖" },
      { slug: "vacuum-cleaners", label: "Vacuum Cleaners", emoji: "🧹" },
      { slug: "air-purifiers", label: "Air Purifiers", emoji: "💨" },
      { slug: "mattresses", label: "Mattresses", emoji: "🛏️" },
      { slug: "weighted-blankets", label: "Weighted Blankets", emoji: "🧣" },
    ],
  },
  {
    slug: "smart-home",
    label: "Smart Home",
    emoji: "💡",
    blurb: "Security, lighting & assistants",
    children: [
      { slug: "smart-doorbells", label: "Video Doorbells", emoji: "🔔" },
      { slug: "security-cameras", label: "Security Cameras", emoji: "📷" },
      { slug: "smart-thermostats", label: "Smart Thermostats", emoji: "🌡️" },
      { slug: "smart-bulbs", label: "Smart Bulbs", emoji: "💡" },
      { slug: "smart-plugs", label: "Smart Plugs", emoji: "🔌" },
      { slug: "voice-assistants", label: "Voice Assistants", emoji: "🗣️" },
    ],
  },
  {
    slug: "beauty-personal-care",
    label: "Beauty & Personal Care",
    emoji: "💄",
    blurb: "Grooming, skincare & haircare",
    children: [
      { slug: "beard-trimmers", label: "Beard Trimmers", emoji: "✂️" },
      { slug: "electric-shavers", label: "Electric Shavers", emoji: "🪒" },
      { slug: "electric-toothbrushes", label: "Electric Toothbrushes", emoji: "🪥" },
      { slug: "hair-dryers", label: "Hair Dryers", emoji: "💨" },
      { slug: "skincare-serums", label: "Skincare Serums", emoji: "🧴" },
      { slug: "sunscreens", label: "Sunscreens", emoji: "🧴" },
    ],
  },
  {
    slug: "health-fitness",
    label: "Health & Fitness",
    emoji: "🏋️",
    blurb: "Training, recovery & supplements",
    children: [
      { slug: "adjustable-dumbbells", label: "Adjustable Dumbbells", emoji: "🏋️" },
      { slug: "treadmills", label: "Treadmills", emoji: "🏃" },
      { slug: "yoga-mats", label: "Yoga Mats", emoji: "🧘" },
      { slug: "massage-guns", label: "Massage Guns", emoji: "💆" },
      { slug: "protein-powders", label: "Protein Powders", emoji: "🥛" },
      { slug: "fitness-trackers", label: "Fitness Trackers", emoji: "⌚" },
    ],
  },
  {
    slug: "sports-outdoors",
    label: "Sports & Outdoors",
    emoji: "🛴",
    blurb: "Ride, run, camp & explore",
    children: [
      { slug: "running-shoes", label: "Running Shoes", emoji: "👟" },
      { slug: "electric-scooters", label: "Electric Scooters", emoji: "🛴" },
      { slug: "electric-bikes", label: "Electric Bikes", emoji: "🚲" },
      { slug: "hiking-backpacks", label: "Hiking Backpacks", emoji: "🎒" },
      { slug: "tents", label: "Tents", emoji: "⛺" },
      { slug: "coolers", label: "Coolers", emoji: "🧊" },
    ],
  },
  {
    slug: "gaming",
    label: "Gaming",
    emoji: "🎮",
    blurb: "Consoles, PCs & accessories",
    children: [
      { slug: "gaming-headsets", label: "Gaming Headsets", emoji: "🎧" },
      { slug: "gaming-mice", label: "Gaming Mice", emoji: "🖱️" },
      { slug: "mechanical-keyboards", label: "Mechanical Keyboards", emoji: "⌨️" },
      { slug: "gaming-chairs", label: "Gaming Chairs", emoji: "🪑" },
      { slug: "vr-headsets", label: "VR Headsets", emoji: "🥽" },
    ],
  },
  {
    slug: "baby-pets",
    label: "Baby & Pets",
    emoji: "🍼",
    blurb: "Care for the whole family",
    children: [
      { slug: "baby-monitors", label: "Baby Monitors", emoji: "👶" },
      { slug: "strollers", label: "Strollers", emoji: "🚼" },
      { slug: "car-seats", label: "Car Seats", emoji: "🚗" },
      { slug: "pet-cameras", label: "Pet Cameras", emoji: "🐶" },
      { slug: "automatic-pet-feeders", label: "Automatic Pet Feeders", emoji: "🥣" },
    ],
  },
  {
    slug: "office-productivity",
    label: "Office & Productivity",
    emoji: "🪑",
    blurb: "Desks, chairs & work-from-home",
    children: [
      { slug: "office-chairs", label: "Office Chairs", emoji: "🪑" },
      { slug: "standing-desks", label: "Standing Desks", emoji: "🖥️" },
      { slug: "webcams", label: "Webcams", emoji: "📹" },
      { slug: "portable-monitors", label: "Portable Monitors", emoji: "🖥️" },
    ],
  },
];

