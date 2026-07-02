<?php
/**
 * Amazon price refresh + price-history tracking.
 *
 * - Custom table `pr_price_history` stores (asin, marketplace, price, ts).
 * - Daily cron `pr_amazon_refresh_daily` walks all ASINs in published reviews
 *   and refreshes price/availability/rating via PA-API GetItems (batched by 10).
 * - Helpers expose lowest-30-day price and a "Lowest in 30d" badge.
 * - Updates the per-product meta in `_yadfood_products` so cards re-render
 *   with the fresh price without needing a manual save.
 *
 * Images are NEVER sideloaded — only numeric/string fields are stored.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Create / upgrade table on theme activation or admin_init. */
function pr_price_history_install(): void {
	global $wpdb;
	$table   = $wpdb->prefix . 'pr_price_history';
	$charset = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		asin VARCHAR(16) NOT NULL,
		marketplace VARCHAR(8) NOT NULL DEFAULT 'US',
		price DECIMAL(12,2) NOT NULL,
		currency VARCHAR(8) NOT NULL DEFAULT 'USD',
		availability VARCHAR(32) NOT NULL DEFAULT '',
		captured_at DATETIME NOT NULL,
		PRIMARY KEY (id),
		KEY asin_ts (asin, captured_at),
		KEY mk_ts (marketplace, captured_at)
	) {$charset};";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
	update_option( 'pr_price_history_v', 1, false );
}
add_action( 'admin_init', function () {
	if ( (int) get_option( 'pr_price_history_v', 0 ) < 1 ) { pr_price_history_install(); }
} );

/** Insert one snapshot row. */
function pr_price_record( string $asin, $price, string $currency = 'USD', string $availability = '', string $marketplace = 'US' ): void {
	if ( ! is_numeric( $price ) || $price <= 0 ) { return; }
	global $wpdb;
	$wpdb->insert( $wpdb->prefix . 'pr_price_history', array(
		'asin'         => strtoupper( substr( $asin, 0, 16 ) ),
		'marketplace'  => strtoupper( substr( $marketplace, 0, 8 ) ),
		'price'        => (float) $price,
		'currency'     => substr( $currency, 0, 8 ),
		'availability' => substr( $availability, 0, 32 ),
		'captured_at'  => current_time( 'mysql', true ),
	), array( '%s','%s','%f','%s','%s','%s' ) );
}

/** Lowest price observed over last N days for an ASIN. Returns null if none. */
function pr_price_lowest( string $asin, int $days = 30, string $marketplace = 'US' ): ?float {
	global $wpdb;
	$t = $wpdb->prefix . 'pr_price_history';
	$row = $wpdb->get_var( $wpdb->prepare(
		"SELECT MIN(price) FROM {$t} WHERE asin=%s AND marketplace=%s AND captured_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)",
		strtoupper( $asin ), strtoupper( $marketplace ), $days
	) );
	return $row === null ? null : (float) $row;
}

/** Convenience: "Lowest in 30 days" boolean for a given current price. */
function pr_is_lowest_30d( string $asin, $current_price, string $marketplace = 'US' ): bool {
	if ( ! is_numeric( $current_price ) ) { return false; }
	$lowest = pr_price_lowest( $asin, 30, $marketplace );
	if ( $lowest === null ) { return false; }
	return ( (float) $current_price <= $lowest + 0.005 );
}

/** Get full daily series for charts. */
function pr_price_series( string $asin, int $days = 90, string $marketplace = 'US' ): array {
	global $wpdb;
	$t = $wpdb->prefix . 'pr_price_history';
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT DATE(captured_at) d, MIN(price) p FROM {$t}
		 WHERE asin=%s AND marketplace=%s AND captured_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
		 GROUP BY DATE(captured_at) ORDER BY d ASC",
		strtoupper( $asin ), strtoupper( $marketplace ), $days
	), ARRAY_A );
	return is_array( $rows ) ? $rows : array();
}

/**
 * Collect ASINs that are due for refresh.
 *
 * Optimization: instead of walking every published post every day, we only
 * process posts whose `_pr_last_checked` is older than PR_FRESHNESS_TTL
 * (default 7d) and cap the batch so a single cron tick stays cheap.
 *
 * @return array<int,array{asin:string,post_id:int,index:int}>
 */
function pr_collect_all_asins( int $limit_posts = 50 ): array {
	global $wpdb;
	$ttl    = defined( 'PR_FRESHNESS_TTL' ) ? PR_FRESHNESS_TTL : 7 * DAY_IN_SECONDS;
	$cutoff = time() - $ttl;
	// Prefer oldest-checked posts first. NULL last_checked == never checked.
	$post_ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT p.ID FROM {$wpdb->posts} p
		 LEFT JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key='_pr_last_checked'
		 WHERE p.post_status='publish' AND p.post_type IN ('review','post')
		   AND ( m.meta_value IS NULL OR CAST(m.meta_value AS UNSIGNED) <= %d )
		 ORDER BY CAST(COALESCE(m.meta_value,'0') AS UNSIGNED) ASC
		 LIMIT %d",
		$cutoff, $limit_posts
	) );
	$out = array();
	foreach ( (array) $post_ids as $pid ) {
		$products = get_post_meta( (int) $pid, '_yadfood_products', true );
		if ( ! is_array( $products ) ) { continue; }
		foreach ( $products as $i => $p ) {
			if ( ! empty( $p['asin'] ) ) {
				$out[] = array( 'asin' => strtoupper( $p['asin'] ), 'post_id' => (int) $pid, 'index' => (int) $i );
			}
		}
	}
	return $out;
}

/**
 * Weekly refresh sweep — capped per run to stay light on the server.
 * Lazy per-post refresh in inc/freshness.php handles the high-traffic posts
 * on-demand; this sweep only picks up the long tail (low/no traffic posts).
 */
function pr_amazon_refresh_run(): void {
	// Single-flight lock so overlapping cron ticks can't double-run.
	if ( get_transient( 'pr_amazon_refresh_lock' ) ) { return; }
	set_transient( 'pr_amazon_refresh_lock', 1, 10 * MINUTE_IN_SECONDS );

	$asins = pr_collect_all_asins( 50 );
	if ( empty( $asins ) ) { delete_transient( 'pr_amazon_refresh_lock' ); return; }
	$unique = array_values( array_unique( wp_list_pluck( $asins, 'asin' ) ) );
	$chunks = array_chunk( $unique, 10 );
	$touched = array();
	foreach ( $chunks as $batch ) {
		$items = pr_amazon_get_items( $batch );
		if ( is_wp_error( $items ) || empty( $items ) ) { continue; }
		foreach ( $items as $asin => $data ) {
			pr_price_record(
				$asin,
				$data['price'] ?? 0,
				$data['currency'] ?? 'USD',
				$data['availability'] ?? '',
				'US'
			);
			foreach ( $asins as $row ) {
				if ( $row['asin'] !== $asin ) { continue; }
				$products = get_post_meta( $row['post_id'], '_yadfood_products', true );
				if ( ! is_array( $products ) || ! isset( $products[ $row['index'] ] ) ) { continue; }
				if ( isset( $data['price'] ) )        { $products[ $row['index'] ]['price']        = $data['price']; }
				if ( isset( $data['rating'] ) )       { $products[ $row['index'] ]['rating']       = $data['rating']; }
				if ( isset( $data['review_count'] ) ) { $products[ $row['index'] ]['review_count'] = $data['review_count']; }
				if ( isset( $data['prime'] ) )        { $products[ $row['index'] ]['prime']        = (bool) $data['prime']; }
				if ( isset( $data['availability'] ) ) { $products[ $row['index'] ]['availability'] = $data['availability']; }
				update_post_meta( $row['post_id'], '_yadfood_products', $products );
				$touched[ $row['post_id'] ] = true;
			}
		}
		usleep( 1100000 );
	}
	foreach ( array_keys( $touched ) as $pid ) {
		if ( function_exists( 'pr_mark_checked' ) ) { pr_mark_checked( (int) $pid ); }
	}
	update_option( 'pr_amazon_last_refresh', time(), false );
	delete_transient( 'pr_amazon_refresh_lock' );
}

/** Schedule + unschedule cron on theme switch. Weekly to keep load low. */
add_action( 'after_switch_theme', function () {
	// Migrate any pre-existing daily schedule to weekly.
	$old = wp_next_scheduled( 'pr_amazon_refresh_daily' );
	if ( $old ) { wp_unschedule_event( $old, 'pr_amazon_refresh_daily' ); }
	if ( ! wp_next_scheduled( 'pr_amazon_refresh_weekly' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', 'pr_amazon_refresh_weekly' );
	}
} );
add_action( 'switch_theme', function () {
	wp_clear_scheduled_hook( 'pr_amazon_refresh_daily' );
	wp_clear_scheduled_hook( 'pr_amazon_refresh_weekly' );
} );
// Ensure 'weekly' recurrence exists (WP ships hourly/twicedaily/daily only).
add_filter( 'cron_schedules', function ( $s ) {
	if ( empty( $s['weekly'] ) ) {
		$s['weekly'] = array( 'interval' => 7 * DAY_IN_SECONDS, 'display' => __( 'Once weekly', 'product-reviews' ) );
	}
	return $s;
} );
// Self-heal: schedule weekly job on init if missing (covers existing installs).
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'pr_amazon_refresh_weekly' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', 'pr_amazon_refresh_weekly' );
	}
	$old = wp_next_scheduled( 'pr_amazon_refresh_daily' );
	if ( $old ) { wp_unschedule_event( $old, 'pr_amazon_refresh_daily' ); }
} );
add_action( 'pr_amazon_refresh_weekly', 'pr_amazon_refresh_run' );
// Keep daily hook bound for back-compat with any existing scheduled events.
add_action( 'pr_amazon_refresh_daily', 'pr_amazon_refresh_run' );


/**
 * PA-API GetItems wrapper. Returns map ASIN => fields.
 * Mirrors the SigV4 plumbing in amazon-api.php but for the GetItems operation
 * (small enough to keep self-contained).
 *
 * @param string[] $asins  Up to 10 ASINs.
 * @return array<string,array<string,mixed>>|WP_Error
 */
function pr_amazon_get_items( array $asins ) {
	$asins = array_slice( array_values( array_filter( array_map( 'strtoupper', $asins ) ) ), 0, 10 );
	if ( empty( $asins ) ) { return array(); }
	$access = trim( (string) get_option( 'yadfood_paapi_access_key', '' ) );
	$secret = trim( (string) get_option( 'yadfood_paapi_secret_key', '' ) );
	$tag    = pr_affiliate_tag();
	if ( $access === '' || $secret === '' ) {
		return new WP_Error( 'paapi_missing_keys', 'PA-API keys not configured' );
	}
	$region = (string) get_option( 'yadfood_paapi_region', 'us-east-1' );
	$host   = 'webservices.amazon.com';
	$path   = '/paapi5/getitems';
	$payload = wp_json_encode( array(
		'ItemIds'     => $asins,
		'Resources'   => array(
			'ItemInfo.Title',
			'Images.Primary.Large',
			'Offers.Listings.Price',
			'Offers.Listings.Availability.Message',
			'Offers.Listings.DeliveryInfo.IsPrimeEligible',
			'Offers.Listings.ProgramEligibility.IsPrimeExclusive',
			'CustomerReviews.StarRating',
			'CustomerReviews.Count',
			'BrowseNodeInfo.WebsiteSalesRank',
		),
		'PartnerTag'  => $tag,
		'PartnerType' => 'Associates',
		'Marketplace' => (string) get_option( 'yadfood_paapi_marketplace', 'www.amazon.com' ),
	) );

	$amz_date   = gmdate( 'Ymd\THis\Z' );
	$date_stamp = gmdate( 'Ymd' );
	$hash       = hash( 'sha256', $payload );
	$canon_h    = "content-encoding:amz-1.0\ncontent-type:application/json; charset=UTF-8\nhost:{$host}\nx-amz-date:{$amz_date}\nx-amz-target:com.amazon.paapi5.v1.ProductAdvertisingAPIv1.GetItems\n";
	$signed_h   = 'content-encoding;content-type;host;x-amz-date;x-amz-target';
	$canon      = "POST\n{$path}\n\n{$canon_h}\n{$signed_h}\n{$hash}";
	$scope      = "{$date_stamp}/{$region}/ProductAdvertisingAPI/aws4_request";
	$sts        = "AWS4-HMAC-SHA256\n{$amz_date}\n{$scope}\n" . hash( 'sha256', $canon );
	$k = hash_hmac( 'sha256', $date_stamp, 'AWS4' . $secret, true );
	$k = hash_hmac( 'sha256', $region, $k, true );
	$k = hash_hmac( 'sha256', 'ProductAdvertisingAPI', $k, true );
	$k = hash_hmac( 'sha256', 'aws4_request', $k, true );
	$sig = hash_hmac( 'sha256', $sts, $k );
	$auth = "AWS4-HMAC-SHA256 Credential={$access}/{$scope}, SignedHeaders={$signed_h}, Signature={$sig}";

	$res = wp_remote_post( "https://{$host}{$path}", array(
		'headers' => array(
			'Content-Encoding' => 'amz-1.0',
			'Content-Type'     => 'application/json; charset=UTF-8',
			'Host'             => $host,
			'X-Amz-Date'       => $amz_date,
			'X-Amz-Target'     => 'com.amazon.paapi5.v1.ProductAdvertisingAPIv1.GetItems',
			'Authorization'    => $auth,
		),
		'body'    => $payload,
		'timeout' => 30,
	) );
	if ( is_wp_error( $res ) ) { return $res; }
	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( empty( $body['ItemsResult']['Items'] ) ) {
		return new WP_Error( 'paapi_no_items', 'GetItems returned no items', $body );
	}
	$out = array();
	foreach ( $body['ItemsResult']['Items'] as $it ) {
		$asin = $it['ASIN'] ?? '';
		if ( ! $asin ) { continue; }
		$listing = $it['Offers']['Listings'][0] ?? array();
		$out[ strtoupper( $asin ) ] = array(
			'price'        => isset( $listing['Price']['Amount'] ) ? (float) $listing['Price']['Amount'] : null,
			'currency'     => $listing['Price']['Currency'] ?? 'USD',
			'availability' => $listing['Availability']['Message'] ?? '',
			'prime'        => ! empty( $listing['DeliveryInfo']['IsPrimeEligible'] ),
			'rating'       => isset( $it['CustomerReviews']['StarRating']['Value'] ) ? (float) $it['CustomerReviews']['StarRating']['Value'] : null,
			'review_count' => isset( $it['CustomerReviews']['Count'] ) ? (int) $it['CustomerReviews']['Count'] : null,
		);
	}
	return $out;
}

/** Admin action to force a refresh on demand. */
add_action( 'admin_post_pr_amazon_refresh_now', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'forbidden' ); }
	check_admin_referer( 'pr_amazon_refresh' );
	pr_amazon_refresh_run();
	wp_safe_redirect( add_query_arg( 'pr_refreshed', '1', wp_get_referer() ?: admin_url() ) );
	exit;
} );
