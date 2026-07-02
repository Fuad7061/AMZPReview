<?php
/**
 * Review freshness signals — optimized for low server load.
 *
 * Strategy (no extra cron pressure):
 *   - Single source of truth: post meta `_pr_last_checked` (unix ts).
 *   - Refresh is LAZY: when a single review/post is viewed and its data is
 *     older than PR_FRESHNESS_TTL, we schedule a one-off background refresh
 *     for THAT post only, via `shutdown` (after response is flushed) and
 *     guarded by a short transient lock so concurrent views don't pile up.
 *   - No per-page PA-API calls in the request path. The response goes out
 *     first, the refresh runs on shutdown using PHP-FPM's fastcgi_finish.
 *   - The heavy "refresh everything" cron is downgraded to WEEKLY and only
 *     touches the oldest 50 ASINs per run (see amazon-refresh.php).
 *
 * UI:
 *   - "Updated <relative>" badge on cards/single when fresh (<=30d).
 *   - "Stale" admin column + filter to surface reviews >180d.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! defined( 'PR_FRESHNESS_TTL' ) )    { define( 'PR_FRESHNESS_TTL', 7 * DAY_IN_SECONDS ); }   // refresh after 7d
if ( ! defined( 'PR_FRESHNESS_FRESH' ) )  { define( 'PR_FRESHNESS_FRESH', 30 * DAY_IN_SECONDS ); } // "Updated" badge window
if ( ! defined( 'PR_FRESHNESS_STALE' ) )  { define( 'PR_FRESHNESS_STALE', 180 * DAY_IN_SECONDS ); }

/** Get last-checked timestamp for a post (falls back to modified date). */
function pr_last_checked( int $post_id ): int {
	$ts = (int) get_post_meta( $post_id, '_pr_last_checked', true );
	if ( $ts > 0 ) { return $ts; }
	return (int) get_post_modified_time( 'U', true, $post_id );
}

/** Mark a post as just checked. */
function pr_mark_checked( int $post_id ): void {
	update_post_meta( $post_id, '_pr_last_checked', time() );
}

/** Age buckets: 'fresh' | 'ok' | 'stale'. */
function pr_freshness_state( int $post_id ): string {
	$age = time() - pr_last_checked( $post_id );
	if ( $age <= PR_FRESHNESS_FRESH ) { return 'fresh'; }
	if ( $age >= PR_FRESHNESS_STALE ) { return 'stale'; }
	return 'ok';
}

/** Render "Updated <relative>" or "Last checked …" line. */
function pr_render_freshness( int $post_id = 0 ): string {
	if ( ! $post_id ) { $post_id = get_the_ID(); }
	if ( ! $post_id ) { return ''; }
	$ts    = pr_last_checked( $post_id );
	$state = pr_freshness_state( $post_id );
	$rel   = human_time_diff( $ts, time() );
	$label = ( $state === 'fresh' )
		? sprintf( esc_html__( 'Updated %s ago', 'product-reviews' ), $rel )
		: sprintf( esc_html__( 'Last checked %s ago', 'product-reviews' ), $rel );
	return sprintf(
		'<span class="pr-freshness pr-freshness--%s" title="%s">%s</span>',
		esc_attr( $state ),
		esc_attr( gmdate( 'Y-m-d H:i', $ts ) . ' UTC' ),
		$label
	);
}

/**
 * LAZY refresh trigger — runs on single review/post views only, AFTER the
 * response is sent. Throttled by transient so high-traffic posts don't
 * spam PA-API.
 */
add_action( 'template_redirect', function () {
	if ( is_admin() || ! is_singular( array( 'review', 'post' ) ) ) { return; }
	$post_id = (int) get_queried_object_id();
	if ( ! $post_id ) { return; }
	$age = time() - pr_last_checked( $post_id );
	if ( $age < PR_FRESHNESS_TTL ) { return; }
	// Lock for 15 min so concurrent visitors don't all queue a refresh.
	$lock = 'pr_fr_lock_' . $post_id;
	if ( get_transient( $lock ) ) { return; }
	set_transient( $lock, 1, 15 * MINUTE_IN_SECONDS );

	add_action( 'shutdown', function () use ( $post_id ) {
		if ( function_exists( 'fastcgi_finish_request' ) ) { @fastcgi_finish_request(); }
		pr_freshness_refresh_post( $post_id );
	}, 99 );
} );

/**
 * Refresh ASINs for a single post via PA-API (batched once, up to 10 ASINs).
 * No-op if PA-API keys aren't configured.
 */
function pr_freshness_refresh_post( int $post_id ): void {
	if ( ! function_exists( 'pr_amazon_get_items' ) ) { return; }
	$products = get_post_meta( $post_id, '_yadfood_products', true );
	if ( ! is_array( $products ) || empty( $products ) ) {
		pr_mark_checked( $post_id );
		return;
	}
	$asins = array();
	foreach ( $products as $p ) {
		if ( ! empty( $p['asin'] ) ) { $asins[] = strtoupper( $p['asin'] ); }
	}
	$asins = array_values( array_unique( $asins ) );
	if ( empty( $asins ) ) { pr_mark_checked( $post_id ); return; }
	$items = pr_amazon_get_items( array_slice( $asins, 0, 10 ) );
	if ( is_wp_error( $items ) || empty( $items ) ) {
		// Still mark as checked to back off; we'll retry after TTL.
		pr_mark_checked( $post_id );
		return;
	}
	foreach ( $products as $i => $p ) {
		$a = strtoupper( $p['asin'] ?? '' );
		if ( ! $a || empty( $items[ $a ] ) ) { continue; }
		$d = $items[ $a ];
		if ( isset( $d['price'] ) )        { $products[ $i ]['price']        = $d['price']; }
		if ( isset( $d['rating'] ) )       { $products[ $i ]['rating']       = $d['rating']; }
		if ( isset( $d['review_count'] ) ) { $products[ $i ]['review_count'] = $d['review_count']; }
		if ( isset( $d['prime'] ) )        { $products[ $i ]['prime']        = (bool) $d['prime']; }
		if ( isset( $d['availability'] ) ) { $products[ $i ]['availability'] = $d['availability']; }
		if ( function_exists( 'pr_price_record' ) && isset( $d['price'] ) ) {
			pr_price_record( $a, $d['price'], $d['currency'] ?? 'USD', $d['availability'] ?? '', 'US' );
		}
	}
	update_post_meta( $post_id, '_yadfood_products', $products );
	pr_mark_checked( $post_id );
}

/** Admin column: Freshness. */
add_filter( 'manage_review_posts_columns', function ( $cols ) {
	$cols['pr_freshness'] = __( 'Freshness', 'product-reviews' );
	return $cols;
} );
add_action( 'manage_review_posts_custom_column', function ( $col, $post_id ) {
	if ( $col !== 'pr_freshness' ) { return; }
	$state = pr_freshness_state( (int) $post_id );
	$ts    = pr_last_checked( (int) $post_id );
	$rel   = human_time_diff( $ts, time() );
	$dot   = array( 'fresh' => '#16a34a', 'ok' => '#ca8a04', 'stale' => '#dc2626' )[ $state ];
	printf(
		'<span style="display:inline-block;width:8px;height:8px;border-radius:50%%;background:%s;margin-right:6px;"></span>%s',
		esc_attr( $dot ),
		esc_html( $rel . ' ago' )
	);
}, 10, 2 );

/** Admin filter: ?pr_stale=1 shows reviews older than PR_FRESHNESS_STALE. */
add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() ) { return; }
	if ( ( $q->get( 'post_type' ) ) !== 'review' ) { return; }
	if ( empty( $_GET['pr_stale'] ) ) { return; }
	$cutoff = time() - PR_FRESHNESS_STALE;
	$q->set( 'meta_query', array(
		'relation' => 'OR',
		array( 'key' => '_pr_last_checked', 'value' => $cutoff, 'compare' => '<=', 'type' => 'NUMERIC' ),
		array( 'key' => '_pr_last_checked', 'compare' => 'NOT EXISTS' ),
	) );
} );

add_filter( 'views_edit-review', function ( $views ) {
	$url = add_query_arg( array( 'post_type' => 'review', 'pr_stale' => 1 ), admin_url( 'edit.php' ) );
	$views['pr_stale'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Stale (>180d)', 'product-reviews' ) . '</a>';
	return $views;
} );
