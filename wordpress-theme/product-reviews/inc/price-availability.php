<?php
/**
 * Price & availability snapshot consolidation.
 *
 * Builds a normalized snapshot of per-product price/availability/currency
 * from the review's products meta, persists a lightweight cache, exposes
 * a small design-safe "live pricing" strip, and emits Offer / AggregateOffer
 * JSON-LD. All outputs are no-op when data is missing — design unchanged.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collect normalized offers from products meta.
 * Returns array of arrays: { name,url,price,currency,availability,merchant }.
 */
function pr_pa_collect_offers( $post_id ) {
	$products = get_post_meta( $post_id, '_pr_products', true );
	if ( ! is_array( $products ) || empty( $products ) ) {
		return array();
	}
	$offers = array();
	foreach ( $products as $p ) {
		if ( ! is_array( $p ) ) {
			continue;
		}
		$price = null;
		foreach ( array( 'price', 'current_price', 'amount' ) as $k ) {
			if ( isset( $p[ $k ] ) && is_numeric( $p[ $k ] ) ) {
				$price = (float) $p[ $k ];
				break;
			}
		}
		$url = '';
		foreach ( array( 'affiliate_url', 'url', 'link', 'buy_url' ) as $k ) {
			if ( ! empty( $p[ $k ] ) ) {
				$url = esc_url_raw( $p[ $k ] );
				break;
			}
		}
		if ( $price === null && ! $url ) {
			continue;
		}
		$currency = ! empty( $p['currency'] ) ? strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', $p['currency'] ), 0, 3 ) ) : 'USD';
		$avail    = ! empty( $p['availability'] ) ? sanitize_text_field( $p['availability'] ) : 'InStock';
		$avail    = preg_match( '/^https?:\/\//', $avail ) ? $avail : 'https://schema.org/' . preg_replace( '/[^A-Za-z]/', '', $avail );
		$offers[] = array(
			'name'         => isset( $p['name'] ) ? sanitize_text_field( $p['name'] ) : '',
			'url'          => $url,
			'price'        => $price,
			'currency'     => $currency,
			'availability' => $avail,
			'merchant'     => ! empty( $p['merchant'] ) ? sanitize_text_field( $p['merchant'] ) : '',
		);
	}
	return $offers;
}

/**
 * Snapshot stats: low, high, count, currency (modal).
 */
function pr_pa_snapshot( $post_id ) {
	$offers = pr_pa_collect_offers( $post_id );
	if ( empty( $offers ) ) {
		return null;
	}
	$prices = array();
	$cur    = array();
	foreach ( $offers as $o ) {
		if ( $o['price'] !== null ) {
			$prices[] = $o['price'];
			$cur[]    = $o['currency'];
		}
	}
	if ( empty( $prices ) ) {
		return null;
	}
	$currency = $cur ? array_values( array_count_values( $cur ) )[0] && reset( $cur ) ? array_keys( array_count_values( $cur ) )[0] : 'USD' : 'USD';
	return array(
		'low'      => min( $prices ),
		'high'     => max( $prices ),
		'count'    => count( $prices ),
		'currency' => $currency,
		'offers'   => $offers,
		'updated'  => time(),
	);
}

/**
 * Persist snapshot to post meta on save.
 */
function pr_pa_save_meta( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( get_post_type( $post_id ) !== 'review' ) {
		return;
	}
	$snap = pr_pa_snapshot( $post_id );
	if ( ! $snap ) {
		delete_post_meta( $post_id, '_pr_pa_snapshot' );
		return;
	}
	update_post_meta( $post_id, '_pr_pa_snapshot', $snap );
}
add_action( 'save_post', 'pr_pa_save_meta', 31 );

/**
 * Format a price using site locale + currency symbol best-effort.
 */
function pr_pa_format_price( $amount, $currency ) {
	$symbols = array( 'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥', 'CAD' => 'C$', 'AUD' => 'A$', 'INR' => '₹' );
	$sym     = isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : ( $currency . ' ' );
	return $sym . number_format_i18n( (float) $amount, ( $currency === 'JPY' ? 0 : 2 ) );
}

/**
 * Design-safe "live pricing" strip appended after content. Empty when none.
 */
function pr_pa_render( $content ) {
	if ( ! is_singular( 'review' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$snap = pr_pa_snapshot( get_the_ID() );
	if ( ! $snap ) {
		return $content;
	}
	$low  = pr_pa_format_price( $snap['low'], $snap['currency'] );
	$high = pr_pa_format_price( $snap['high'], $snap['currency'] );
	$html = '<aside class="pr-pa-strip" aria-label="' . esc_attr__( 'Live pricing snapshot', 'product-reviews' ) . '">';
	$html .= '<div class="pr-pa-row">';
	$html .= '<span class="pr-pa-label">' . esc_html__( 'Live prices', 'product-reviews' ) . '</span>';
	if ( $snap['low'] === $snap['high'] ) {
		$html .= '<span class="pr-pa-range">' . esc_html( $low ) . '</span>';
	} else {
		$html .= '<span class="pr-pa-range">' . esc_html( $low ) . ' – ' . esc_html( $high ) . '</span>';
	}
	$html .= '<span class="pr-pa-count">' . sprintf( esc_html( _n( '%d offer', '%d offers', $snap['count'], 'product-reviews' ) ), (int) $snap['count'] ) . '</span>';
	$html .= '<span class="pr-pa-updated">' . esc_html( sprintf( __( 'Updated %s', 'product-reviews' ), human_time_diff( $snap['updated'], time() ) . ' ' . __( 'ago', 'product-reviews' ) ) ) . '</span>';
	$html .= '</div></aside>';
	return $content . $html;
}
add_filter( 'the_content', 'pr_pa_render', 13 );

/**
 * Inline CSS, scoped.
 */
function pr_pa_styles() {
	if ( ! is_singular( 'review' ) ) {
		return;
	}
	$css = '.pr-pa-strip{margin:1rem 0;padding:.75rem 1rem;border:1px dashed rgba(0,0,0,.12);border-radius:.5rem;}'
		. '.pr-pa-row{display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;font-size:.95em;}'
		. '.pr-pa-label{font-weight:600;}'
		. '.pr-pa-range{padding:.2rem .5rem;border-radius:.35rem;background:rgba(0,0,0,.05);}'
		. '.pr-pa-count,.pr-pa-updated{opacity:.7;}';
	echo '<style id="pr-pa-css">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wp_head', 'pr_pa_styles', 91 );

/**
 * AggregateOffer JSON-LD. No-op when no priced offers.
 */
function pr_pa_jsonld() {
	if ( ! is_singular( 'review' ) ) {
		return;
	}
	$snap = pr_pa_snapshot( get_the_ID() );
	if ( ! $snap ) {
		return;
	}
	$offers_ld = array();
	foreach ( $snap['offers'] as $o ) {
		if ( $o['price'] === null ) {
			continue;
		}
		$node = array(
			'@type'         => 'Offer',
			'price'         => (string) $o['price'],
			'priceCurrency' => $o['currency'],
			'availability'  => $o['availability'],
		);
		if ( $o['url'] ) {
			$node['url'] = $o['url'];
		}
		if ( $o['merchant'] ) {
			$node['seller'] = array( '@type' => 'Organization', 'name' => $o['merchant'] );
		}
		$offers_ld[] = $node;
	}
	if ( empty( $offers_ld ) ) {
		return;
	}
	$data = array(
		'@context' => 'https://schema.org',
		'@type'    => 'Product',
		'name'     => get_the_title(),
		'offers'   => array(
			'@type'         => 'AggregateOffer',
			'lowPrice'      => (string) $snap['low'],
			'highPrice'     => (string) $snap['high'],
			'priceCurrency' => $snap['currency'],
			'offerCount'    => $snap['count'],
			'offers'        => $offers_ld,
		),
	);
	echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wp_head', 'pr_pa_jsonld', 82 );
