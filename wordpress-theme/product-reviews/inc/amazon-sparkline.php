<?php
/**
 * Inline SVG sparkline for ASIN price history.
 *
 * Reads from `pr_price_history` (created by amazon-refresh.php) and renders a
 * compact, dependency-free SVG. Cached per ASIN+window in a transient so
 * archive pages don't hammer the DB.
 *
 * Usage: echo pr_render_price_sparkline( $asin, 30 );
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Fetch raw price points for sparkline rendering.
 * Uses the canonical pr_price_series() defined in amazon-refresh.php,
 * normalizing its {d,p} daily rows into {ts,price} points.
 *
 * @return array<int,array{ts:int,price:float}>
 */
function pr_spark_price_points( string $asin, int $days = 30, string $marketplace = 'US' ): array {
	if ( ! function_exists( 'pr_price_series' ) ) { return array(); }
	$rows = pr_price_series( $asin, $days, $marketplace );
	$out  = array();
	foreach ( (array) $rows as $r ) {
		if ( isset( $r['ts'], $r['price'] ) ) {
			$out[] = array( 'ts' => (int) $r['ts'], 'price' => (float) $r['price'] );
		} elseif ( isset( $r['d'], $r['p'] ) ) {
			$out[] = array( 'ts' => (int) strtotime( $r['d'] . ' 00:00:00 UTC' ), 'price' => (float) $r['p'] );
		}
	}
	return $out;
}

/**
 * Render compact SVG sparkline. Returns '' if not enough data.
 */
function pr_render_price_sparkline( string $asin, int $days = 30, array $args = array() ): string {
	$asin = strtoupper( substr( $asin, 0, 16 ) );
	if ( $asin === '' ) { return ''; }

	$cache_key = 'pr_spark_' . md5( $asin . '|' . $days . '|' . wp_json_encode( $args ) );
	$cached    = get_transient( $cache_key );
	if ( is_string( $cached ) ) { return $cached; }

	$series = pr_spark_price_points( $asin, $days );
	if ( count( $series ) < 2 ) {
		set_transient( $cache_key, '', 6 * HOUR_IN_SECONDS );
		return '';
	}

	$w  = (int) ( $args['width']  ?? 140 );
	$h  = (int) ( $args['height'] ?? 36 );
	$pad = 2;

	$prices = array_column( $series, 'price' );
	$min    = min( $prices );
	$max    = max( $prices );
	$range  = max( 0.01, $max - $min );

	$first_ts = $series[0]['ts'];
	$last_ts  = end( $series )['ts'];
	$tspan    = max( 1, $last_ts - $first_ts );

	$pts = array();
	foreach ( $series as $p ) {
		$x = $pad + ( ( $p['ts'] - $first_ts ) / $tspan ) * ( $w - 2 * $pad );
		$y = $h - $pad - ( ( $p['price'] - $min ) / $range ) * ( $h - 2 * $pad );
		$pts[] = round( $x, 1 ) . ',' . round( $y, 1 );
	}
	$poly = implode( ' ', $pts );

	$cur   = end( $prices );
	$first = reset( $prices );
	$delta = $cur - $first;
	$cls   = $delta < 0 ? 'pr-spark--down' : ( $delta > 0 ? 'pr-spark--up' : 'pr-spark--flat' );

	$title = sprintf(
		/* translators: 1: low, 2: high, 3: window in days */
		__( '%1$s – %2$s over last %3$d days', 'product-reviews' ),
		function_exists( 'pr_format_price_localized' ) ? pr_format_price_localized( $min ) : ( '$' . number_format( $min, 2 ) ),
		function_exists( 'pr_format_price_localized' ) ? pr_format_price_localized( $max ) : ( '$' . number_format( $max, 2 ) ),
		$days
	);

	$svg = sprintf(
		'<svg class="pr-spark %1$s" width="%2$d" height="%3$d" viewBox="0 0 %2$d %3$d" role="img" aria-label="%4$s"><title>%4$s</title><polyline points="%5$s" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
		esc_attr( $cls ),
		$w, $h,
		esc_attr( $title ),
		esc_attr( $poly )
	);

	set_transient( $cache_key, $svg, 6 * HOUR_IN_SECONDS );
	return $svg;
}
