<?php
/**
 * Helpers — small reusable functions used across templates.
 *
 * @package YadFoodReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the configured Amazon Associates tag.
 * pr_affiliate_tag() is the new name; yadfood_amazon_tag() is kept as an alias.
 */
function pr_affiliate_tag() {
	$tag = get_option( 'pr_affiliate_tag', '' );
	if ( ! $tag ) {
		// Legacy fallback so existing installs keep their tag.
		$tag = get_option( 'yadfood_amazon_tag', 'YOUR-TAG-20' );
	}
	return sanitize_text_field( $tag );
}
function yadfood_amazon_tag() { return pr_affiliate_tag(); }

/**
 * Lightweight symmetric encrypt/decrypt for sensitive options (cookie jar etc.)
 * Uses a key derived from AUTH_KEY so even a DB dump cannot read the value
 * without the wp-config secrets.
 */
if ( ! function_exists( 'pr_secret_key' ) ) {
function pr_secret_key(): string {
	$base = defined( 'AUTH_KEY' ) ? AUTH_KEY : ( defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'product-reviews-fallback' );
	return hash( 'sha256', $base . '|pr-source', true );
}
}
if ( ! function_exists( 'pr_encrypt' ) ) {
function pr_encrypt( string $plain ): string {
	if ( $plain === '' || ! function_exists( 'openssl_encrypt' ) ) { return $plain; }
	$iv  = random_bytes( 16 );
	$enc = openssl_encrypt( $plain, 'aes-256-cbc', pr_secret_key(), OPENSSL_RAW_DATA, $iv );
	return 'enc:' . base64_encode( $iv . $enc );
}
}
if ( ! function_exists( 'pr_decrypt' ) ) {
function pr_decrypt( string $value ): string {
	if ( strncmp( $value, 'enc:', 4 ) !== 0 || ! function_exists( 'openssl_decrypt' ) ) { return $value; }
	$raw = base64_decode( substr( $value, 4 ) );
	if ( strlen( $raw ) < 17 ) { return ''; }
	$iv  = substr( $raw, 0, 16 );
	$enc = substr( $raw, 16 );
	$out = openssl_decrypt( $enc, 'aes-256-cbc', pr_secret_key(), OPENSSL_RAW_DATA, $iv );
	return $out === false ? '' : $out;
}
}
function pr_set_encrypted_option( string $key, string $plain ): void {
	update_option( $key, $plain === '' ? '' : pr_encrypt( $plain ), false );
}
function pr_decrypt_option( string $key ): string {
	$raw = (string) get_option( $key, '' );
	return $raw === '' ? '' : pr_decrypt( $raw );
}


/**
 * Build a tagged Amazon affiliate URL for an ASIN.
 *
 * @param string $asin     Amazon Standard Identification Number.
 * @param string $subtag   Optional subtag for click attribution (e.g. page slug).
 */
function yadfood_amazon_url( $asin, $subtag = '' ) {
	$asin = strtoupper( preg_replace( '/[^A-Z0-9]/i', '', $asin ) );
	if ( empty( $asin ) ) {
		return '';
	}
	$args = array(
		'tag'      => yadfood_amazon_tag(),
		'linkCode' => 'osi',
		'th'       => '1',
		'psc'      => '1',
	);
	if ( ! empty( $subtag ) ) {
		$args['ascsubtag'] = sanitize_title( $subtag );
	}
	$url = add_query_arg( $args, "https://www.amazon.com/dp/{$asin}" );
	/**
	 * Filter the outbound Amazon URL (used by analytics to append UTM params).
	 */
	return apply_filters( 'pr_amazon_outbound_url', $url, $asin, $subtag );
}

/**
 * Format a number as a USD price string.
 */
function yadfood_format_price( $price ) {
	if ( ! is_numeric( $price ) ) {
		return '';
	}
	return '$' . number_format( (float) $price, 2 );
}

/**
 * Stars badge HTML.
 *
 * @param float $rating 0–5.
 */
function yadfood_render_stars( $rating ) {
	$rating = max( 0, min( 5, (float) $rating ) );
	$full   = floor( $rating );
	$half   = ( $rating - $full ) >= 0.25 && ( $rating - $full ) < 0.75 ? 1 : 0;
	$empty  = 5 - $full - $half;
	$out    = '<span class="yf-stars" aria-label="' . esc_attr( $rating . ' out of 5' ) . '">';
	$out   .= str_repeat( '<span class="yf-star yf-star--full">★</span>', (int) $full );
	$out   .= str_repeat( '<span class="yf-star yf-star--half">★</span>', (int) $half );
	$out   .= str_repeat( '<span class="yf-star yf-star--empty">☆</span>', (int) $empty );
	$out   .= '</span>';
	return $out;
}

/**
 * Always-fresh "Last updated" date — shows today.
 * Matches the React site behavior.
 */
function yadfood_last_updated() {
	return date_i18n( get_option( 'date_format' ) );
}

/**
 * Safe meta getter with default.
 */
function yadfood_meta( $post_id, $key, $default = '' ) {
	$v = get_post_meta( $post_id, $key, true );
	return ( '' === $v || null === $v ) ? $default : $v;
}

/**
 * Read all "review_product" rows for a review post.
 * Stored as repeating meta groups under _yadfood_products.
 *
 * @return array<int,array<string,mixed>>
 */
function yadfood_get_products( $post_id ) {
	$raw = get_post_meta( $post_id, '_yadfood_products', true );
	if ( empty( $raw ) || ! is_array( $raw ) ) {
		return array();
	}
	$out = array();
	foreach ( $raw as $i => $p ) {
		$out[] = wp_parse_args( $p, array(
			'rank'        => $i + 1,
			'title'       => '',
			'asin'        => '',
			'image'       => '',
			'price'       => '',
			'rating'      => '',
			'review_count' => '',
			'why'         => '',
			'pros'        => array(),
			'cons'        => array(),
			'features'    => array(),
			'badge'       => '', // editors_choice | best_value | premium | budget
		) );
	}
	return $out;
}

/**
 * Get FAQ pairs for a review post.
 */
function yadfood_get_faqs( $post_id ) {
	$raw = get_post_meta( $post_id, '_yadfood_faqs', true );
	if ( empty( $raw ) || ! is_array( $raw ) ) {
		return array();
	}
	return $raw;
}

/**
 * Get the configured AI provider settings.
 */
function yadfood_ai_settings() {
	return array(
		'provider' => get_option( 'yadfood_ai_provider', 'openai' ),
		'model'    => get_option( 'yadfood_ai_model', 'gpt-4o-mini' ),
		'api_key'  => get_option( 'yadfood_ai_api_key', '' ),
	);
}
