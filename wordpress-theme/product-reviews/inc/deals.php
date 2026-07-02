<?php
/**
 * Deal intelligence — Milestone 17.
 *
 * Lightweight, cron-free. Pure-PHP detection on top of data we already store:
 *  - per-product `price` vs optional `list_price` / `msrp` (savings %).
 *  - lowest price observed in the last 90 days (pr_price_history).
 * Adds:
 *  - pr_product_deal_info() / pr_render_deal_ribbon() for product cards.
 *  - /deals virtual archive (rewrite + template_include) listing posts that
 *    contain at least one deal product. Results cached for 1h in a transient
 *    so the page costs ~1 query when warm.
 *  - [pr_deals] shortcode that renders the same list anywhere.
 *  - Opt-in deal digest using the existing subscribers list (sent at most
 *    once a week from the weekly Amazon refresh tick — no new cron).
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! defined( 'PR_DEAL_MIN_PCT' ) ) {
	define( 'PR_DEAL_MIN_PCT', 15 ); // 15% off triggers a deal ribbon.
}
if ( ! defined( 'PR_DEAL_CACHE_TTL' ) ) {
	define( 'PR_DEAL_CACHE_TTL', HOUR_IN_SECONDS );
}

/**
 * Compute deal info for a single product row.
 * @return array{is_deal:bool,savings_pct:int,lowest90:bool,list_price:?float,reason:string}
 */
function pr_product_deal_info( array $p ): array {
	$out = array(
		'is_deal'     => false,
		'savings_pct' => 0,
		'lowest90'    => false,
		'list_price'  => null,
		'reason'      => '',
	);
	$price = isset( $p['price'] ) && is_numeric( $p['price'] ) ? (float) $p['price'] : 0.0;
	if ( $price <= 0 ) { return $out; }

	$list = 0.0;
	foreach ( array( 'list_price', 'msrp', 'was_price', 'original_price' ) as $k ) {
		if ( ! empty( $p[ $k ] ) && is_numeric( $p[ $k ] ) && (float) $p[ $k ] > $price ) {
			$list = (float) $p[ $k ];
			break;
		}
	}
	if ( $list > 0 ) {
		$out['list_price']  = $list;
		$out['savings_pct'] = (int) floor( ( ( $list - $price ) / $list ) * 100 );
	}

	if ( ! empty( $p['asin'] ) && function_exists( 'pr_price_lowest' ) ) {
		$lowest = pr_price_lowest( (string) $p['asin'], 90 );
		if ( $lowest !== null && $price <= $lowest + 0.005 ) {
			$out['lowest90'] = true;
		}
	}

	if ( $out['savings_pct'] >= (int) PR_DEAL_MIN_PCT ) {
		$out['is_deal'] = true;
		$out['reason']  = sprintf( 'Save %d%%', $out['savings_pct'] );
	} elseif ( $out['lowest90'] ) {
		$out['is_deal'] = true;
		$out['reason']  = 'Lowest in 90d';
	}
	return $out;
}

/** Render a small deal ribbon (returns empty string when not a deal). */
function pr_render_deal_ribbon( array $p ): string {
	$d = pr_product_deal_info( $p );
	if ( ! $d['is_deal'] ) { return ''; }
	$cls   = $d['savings_pct'] >= 30 ? 'yf-deal yf-deal--hot' : 'yf-deal';
	$title = $d['list_price']
		? sprintf( 'Was %s — now %s', yadfood_format_price( $d['list_price'] ), yadfood_format_price( (float) $p['price'] ) )
		: 'Lowest price in 90 days';
	return sprintf(
		'<div class="%s" title="%s"><span class="yf-deal__dot" aria-hidden="true"></span>%s</div>',
		esc_attr( $cls ),
		esc_attr( $title ),
		esc_html( $d['reason'] )
	);
}

/* -------------------------------------------------------------------------
 * Deal index — posts containing at least one deal product.
 * Cached in a transient; invalidated on any post save and after the weekly
 * Amazon refresh.
 * ------------------------------------------------------------------------*/

/**
 * Build the list of (post_id, top_savings_pct, deal_count) for cards.
 * @return array<int,array{post_id:int,top_pct:int,count:int}>
 */
function pr_deals_index( bool $force = false ): array {
	$cached = $force ? false : get_transient( 'pr_deals_index_v1' );
	if ( is_array( $cached ) ) { return $cached; }
	global $wpdb;
	$rows = $wpdb->get_results(
		"SELECT p.ID, m.meta_value
		 FROM {$wpdb->posts} p
		 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_yadfood_products'
		 WHERE p.post_status='publish' AND p.post_type IN ('review','post')
		 LIMIT 500",
		ARRAY_A
	);
	$out = array();
	foreach ( (array) $rows as $r ) {
		$products = maybe_unserialize( $r['meta_value'] );
		if ( ! is_array( $products ) ) { continue; }
		$top = 0; $count = 0;
		foreach ( $products as $p ) {
			if ( ! is_array( $p ) ) { continue; }
			$info = pr_product_deal_info( $p );
			if ( $info['is_deal'] ) {
				$count++;
				if ( $info['savings_pct'] > $top ) { $top = $info['savings_pct']; }
			}
		}
		if ( $count > 0 ) {
			$out[] = array( 'post_id' => (int) $r['ID'], 'top_pct' => $top, 'count' => $count );
		}
	}
	usort( $out, function ( $a, $b ) { return $b['top_pct'] <=> $a['top_pct']; } );
	set_transient( 'pr_deals_index_v1', $out, PR_DEAL_CACHE_TTL );
	return $out;
}

/** Invalidate the cache on relevant events. */
add_action( 'save_post', function ( $post_id ) {
	if ( in_array( get_post_type( $post_id ), array( 'review', 'post' ), true ) ) {
		delete_transient( 'pr_deals_index_v1' );
	}
} );
add_action( 'pr_amazon_refresh_weekly', function () { delete_transient( 'pr_deals_index_v1' ); }, 20 );
add_action( 'pr_amazon_refresh_daily',  function () { delete_transient( 'pr_deals_index_v1' ); }, 20 );

/* -------------------------------------------------------------------------
 * /deals virtual archive
 * ------------------------------------------------------------------------*/
add_action( 'init', function () {
	add_rewrite_rule( '^deals/?$', 'index.php?pr_deals=1', 'top' );
} );
add_filter( 'query_vars', function ( $v ) { $v[] = 'pr_deals'; return $v; } );
add_action( 'after_switch_theme', function () { flush_rewrite_rules( false ); } );

add_filter( 'template_include', function ( $template ) {
	if ( ! (int) get_query_var( 'pr_deals' ) ) { return $template; }
	$custom = locate_template( array( 'deals.php' ) );
	return $custom ?: $template;
} );

/**
 * Render the deals list (used by template + shortcode).
 */
function pr_render_deals_list( int $limit = 24 ): string {
	$index = array_slice( pr_deals_index(), 0, $limit );
	if ( empty( $index ) ) {
		return '<p class="pr-deals__empty">No active deals right now. Check back soon.</p>';
	}
	$html  = '<ul class="pr-deals">';
	foreach ( $index as $row ) {
		$pid = $row['post_id'];
		$title = get_the_title( $pid );
		$link  = get_permalink( $pid );
		$thumb = get_the_post_thumbnail_url( $pid, 'pr-card' );
		$html .= '<li class="pr-deals__item">';
		$html .= '<a class="pr-deals__link" href="' . esc_url( $link ) . '">';
		if ( $thumb ) {
			$html .= '<img class="pr-deals__img" src="' . esc_url( $thumb ) . '" alt="" loading="lazy" decoding="async">';
		}
		$html .= '<div class="pr-deals__body">';
		$html .= '<h3 class="pr-deals__title">' . esc_html( $title ) . '</h3>';
		$html .= '<div class="pr-deals__meta">';
		$html .= '<span class="pr-deals__pct">−' . (int) $row['top_pct'] . '%</span>';
		$html .= '<span class="pr-deals__count">' . (int) $row['count'] . ' deal' . ( $row['count'] > 1 ? 's' : '' ) . '</span>';
		$html .= '</div></div></a></li>';
	}
	$html .= '</ul>';
	return $html;
}

add_shortcode( 'pr_deals', function ( $atts ) {
	$a = shortcode_atts( array( 'limit' => 24 ), $atts );
	return pr_render_deals_list( (int) $a['limit'] );
} );

/* -------------------------------------------------------------------------
 * Styles (kept tiny; tokens via existing CSS variables where possible).
 * ------------------------------------------------------------------------*/
add_action( 'wp_head', function () {
	?>
	<style id="pr-deals-css">
	.yf-deal{display:inline-flex;align-items:center;gap:.35rem;font-size:.72rem;font-weight:700;letter-spacing:.02em;text-transform:uppercase;padding:.3rem .55rem;border-radius:999px;background:#fde68a;color:#7c2d12;border:1px solid #fbbf24;margin:.35rem 0}
	.yf-deal--hot{background:#fee2e2;color:#7f1d1d;border-color:#fca5a5}
	.yf-deal__dot{width:.45rem;height:.45rem;border-radius:50%;background:currentColor;display:inline-block;animation:prDealPulse 1.6s infinite}
	@keyframes prDealPulse{0%,100%{opacity:1}50%{opacity:.45}}
	.pr-deals{list-style:none;padding:0;margin:1.5rem 0;display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1rem}
	.pr-deals__item{background:var(--pr-surface,#fff);border:1px solid var(--pr-border,#e5e7eb);border-radius:.75rem;overflow:hidden;transition:transform .15s ease, box-shadow .15s ease}
	.pr-deals__item:hover{transform:translateY(-2px);box-shadow:0 8px 24px -12px rgba(0,0,0,.18)}
	.pr-deals__link{display:flex;flex-direction:column;color:inherit;text-decoration:none;height:100%}
	.pr-deals__img{width:100%;aspect-ratio:16/10;object-fit:cover;background:#f3f4f6}
	.pr-deals__body{padding:.85rem 1rem 1rem;display:flex;flex-direction:column;gap:.5rem}
	.pr-deals__title{font-size:1rem;font-weight:600;line-height:1.35;margin:0}
	.pr-deals__meta{display:flex;align-items:center;gap:.6rem;font-size:.8rem;color:var(--pr-muted,#6b7280)}
	.pr-deals__pct{background:#dc2626;color:#fff;padding:.15rem .45rem;border-radius:.35rem;font-weight:700;font-size:.78rem}
	.pr-deals__empty{padding:2rem;text-align:center;color:var(--pr-muted,#6b7280)}
	</style>
	<?php
} );

/* -------------------------------------------------------------------------
 * Opt-in weekly deal digest (piggybacks on the existing weekly refresh).
 * Reuses the subscriber table if pr_subscribers_emails() exists; otherwise
 * silently no-ops. Throttled to once per 6 days via an option timestamp.
 * ------------------------------------------------------------------------*/
add_action( 'pr_amazon_refresh_weekly', 'pr_deals_send_digest', 30 );
function pr_deals_send_digest(): void {
	if ( ! function_exists( 'pr_subscribers_emails' ) ) { return; }
	$last = (int) get_option( 'pr_deals_digest_last', 0 );
	if ( $last && ( time() - $last ) < 6 * DAY_IN_SECONDS ) { return; }
	$index = array_slice( pr_deals_index( true ), 0, 10 );
	if ( empty( $index ) ) { return; }
	$emails = (array) pr_subscribers_emails();
	if ( empty( $emails ) ) { return; }
	$site    = get_bloginfo( 'name' );
	$subject = sprintf( '[%s] This week\'s top deals', $site );
	$body    = "Top deals right now:\n\n";
	foreach ( $index as $row ) {
		$body .= sprintf( "• -%d%%  %s\n  %s\n\n", $row['top_pct'], get_the_title( $row['post_id'] ), get_permalink( $row['post_id'] ) );
	}
	$body .= "Browse all: " . home_url( '/deals/' ) . "\n";
	foreach ( $emails as $to ) {
		wp_mail( $to, $subject, $body );
	}
	update_option( 'pr_deals_digest_last', time(), false );
}
