<?php
/**
 * Amazon trust badges — render Prime, Amazon's Choice, Best Seller,
 * Climate Pledge Friendly, "Lowest in 30 days" chips on product cards.
 *
 * Detection sources (in priority order):
 *  1. Explicit per-product meta fields the autopilot writer / scraper set:
 *     - prime (bool)
 *     - amazons_choice (bool)
 *     - best_seller (bool)
 *     - climate_pledge (bool)
 *     - subscribe_save (bool)
 *  2. Live PA-API refresh (amazon-refresh.php) updates `prime`.
 *  3. Lowest-in-30-days computed from pr_price_history.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Render the badge row for a product.
 * @param array $p Product meta row.
 */
function pr_render_amazon_badges( array $p ): string {
	$badges = array();

	if ( ! empty( $p['prime'] ) ) {
		$badges[] = '<span class="yf-badge yf-badge--prime" title="Eligible for Prime">Prime</span>';
	}
	if ( ! empty( $p['amazons_choice'] ) ) {
		$badges[] = '<span class="yf-badge yf-badge--choice" title="Amazon\'s Choice">Amazon\'s Choice</span>';
	}
	if ( ! empty( $p['best_seller'] ) ) {
		$badges[] = '<span class="yf-badge yf-badge--bestseller" title="Best Seller">#1 Best Seller</span>';
	}
	if ( ! empty( $p['climate_pledge'] ) ) {
		$badges[] = '<span class="yf-badge yf-badge--climate" title="Climate Pledge Friendly">Climate Pledge</span>';
	}
	if ( ! empty( $p['subscribe_save'] ) ) {
		$badges[] = '<span class="yf-badge yf-badge--subscribe" title="Subscribe &amp; Save">Subscribe &amp; Save</span>';
	}

	// Lowest-in-30-days dynamic chip.
	if ( ! empty( $p['asin'] ) && ! empty( $p['price'] ) && function_exists( 'pr_is_lowest_30d' ) ) {
		if ( pr_is_lowest_30d( (string) $p['asin'], $p['price'] ) ) {
			$badges[] = '<span class="yf-badge yf-badge--lowest" title="Lowest price observed in the last 30 days">Lowest in 30d</span>';
		}
	}

	if ( empty( $badges ) ) { return ''; }
	return '<div class="yf-badges">' . implode( '', $badges ) . '</div>';
}

/** Append default style for the badges (kept tiny and tokens-friendly). */
add_action( 'wp_head', function () {
	?>
	<style id="pr-amazon-badges">
	.yf-badges{display:flex;flex-wrap:wrap;gap:.35rem;margin:.4rem 0}
	.yf-badge{font-size:.72rem;line-height:1;padding:.3rem .5rem;border-radius:999px;font-weight:600;letter-spacing:.01em;border:1px solid transparent}
	.yf-badge--prime{background:#0a84ff;color:#fff}
	.yf-badge--choice{background:#232f3e;color:#ff9900}
	.yf-badge--bestseller{background:#ff9900;color:#111}
	.yf-badge--climate{background:#0f7b3e;color:#fff}
	.yf-badge--subscribe{background:#eef2ff;color:#3730a3;border-color:#c7d2fe}
	.yf-badge--lowest{background:#dc2626;color:#fff}
	</style>
	<?php
}, 20 );
