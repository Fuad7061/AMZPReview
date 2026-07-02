<?php
/**
 * E-E-A-T enrichment — supplemental JSON-LD that augments the core schema
 * emitted by inc/schema.php. Every field is opt-in: if the underlying
 * data is missing the node is silently skipped, so missing data never
 * breaks the page, the layout, or the existing schema graph.
 *
 * Emitted (when data permits):
 *  - Organization graph node on every page (sameAs + logo + contact).
 *  - Supplemental Product nodes on single reviews with mpn / gtin13 /
 *    gtin / category, and an AggregateRating derived from on-site
 *    review meta when the product itself has none.
 *
 * Runs at wp_head priority 60 — strictly AFTER inc/schema.php (priority 50)
 * so we never collide with or overwrite its output.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 *  Organization sameAs graph (sitewide)
 * ------------------------------------------------------------------------- */

/**
 * Collect social / authority URLs from the Customizer. Every field is
 * optional; the returned array contains only non-empty, valid URLs.
 *
 * @return string[]
 */
function pr_eeat_same_as(): array {
	$keys = array(
		'pr_brand_twitter',
		'pr_brand_facebook',
		'pr_brand_instagram',
		'pr_brand_youtube',
		'pr_brand_linkedin',
		'pr_brand_wikipedia',
		'pr_brand_crunchbase',
	);
	$out = array();
	foreach ( $keys as $k ) {
		$v = trim( (string) get_theme_mod( $k, '' ) );
		if ( $v === '' ) { continue; }
		$v = esc_url_raw( $v );
		if ( $v ) { $out[] = $v; }
	}
	return array_values( array_unique( $out ) );
}

/**
 * Emit the Organization node once per page.
 */
function pr_eeat_org_jsonld(): void {
	$name = function_exists( 'pr_brand' ) ? pr_brand() : (string) get_bloginfo( 'name' );
	if ( $name === '' ) { return; }

	$org = array(
		'@context' => 'https://schema.org',
		'@type'    => 'Organization',
		'name'     => $name,
		'url'      => home_url( '/' ),
	);

	$logo = function_exists( 'pr_logo_url' ) ? pr_logo_url() : '';
	if ( $logo ) {
		$org['logo'] = array( '@type' => 'ImageObject', 'url' => (string) $logo );
	}

	$same = pr_eeat_same_as();
	if ( ! empty( $same ) ) { $org['sameAs'] = $same; }

	$email = trim( (string) get_theme_mod( 'pr_brand_contact_email', '' ) );
	if ( $email && is_email( $email ) ) {
		$org['contactPoint'] = array(
			'@type'        => 'ContactPoint',
			'contactType'  => 'customer support',
			'email'        => $email,
		);
	}

	echo '<script type="application/ld+json">'
		. wp_json_encode( $org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		. "</script>\n";
}

/* -------------------------------------------------------------------------
 *  Per-product enrichment on single reviews
 * ------------------------------------------------------------------------- */

/**
 * Walk products on the current review and emit a supplemental Product
 * node ONLY when at least one enrichment field is present. Skipped
 * cleanly when no products / no enrichable fields exist.
 */
function pr_eeat_products_jsonld(): void {
	if ( ! is_singular( 'review' ) ) { return; }
	if ( ! function_exists( 'yadfood_get_products' ) ) { return; }

	$post_id  = get_queried_object_id();
	$products = (array) yadfood_get_products( $post_id );
	if ( empty( $products ) ) { return; }

	$post_url = get_permalink( $post_id );

	foreach ( $products as $p ) {
		if ( ! is_array( $p ) ) { continue; }

		$enrich = array();

		foreach ( array( 'mpn', 'gtin13', 'gtin12', 'gtin8', 'gtin' ) as $k ) {
			if ( ! empty( $p[ $k ] ) ) {
				$enrich[ $k ] = (string) $p[ $k ];
			}
		}
		if ( ! empty( $p['category'] ) ) {
			$enrich['category'] = (string) $p['category'];
		}
		if ( ! empty( $p['model'] ) ) {
			$enrich['model'] = (string) $p['model'];
		}
		if ( ! empty( $p['color'] ) ) {
			$enrich['color'] = (string) $p['color'];
		}
		if ( ! empty( $p['material'] ) ) {
			$enrich['material'] = (string) $p['material'];
		}

		// Derived aggregateRating: only if product carries no rating but
		// we have an editorial score. Skip silently otherwise.
		if ( empty( $p['rating'] ) && ! empty( $p['score'] ) ) {
			$score = (float) $p['score'];
			if ( $score > 0 && $score <= 10 ) {
				$enrich['aggregateRating'] = array(
					'@type'       => 'AggregateRating',
					'ratingValue' => (string) round( $score / 2, 1 ),
					'reviewCount' => '1',
					'bestRating'  => '5',
					'worstRating' => '1',
				);
			}
		}

		if ( empty( $enrich ) ) { continue; }

		$name = (string) ( $p['title'] ?? '' );
		if ( $name === '' ) { continue; }

		$node = array_merge(
			array(
				'@context' => 'https://schema.org',
				'@type'    => 'Product',
				'name'     => $name,
				'url'      => ! empty( $p['asin'] ) && function_exists( 'yadfood_amazon_url' )
					? yadfood_amazon_url( $p['asin'], get_post_field( 'post_name', $post_id ) )
					: $post_url,
			),
			$enrich
		);

		if ( ! empty( $p['brand'] ) ) {
			$node['brand'] = array( '@type' => 'Brand', 'name' => (string) $p['brand'] );
		}

		echo '<script type="application/ld+json">'
			. wp_json_encode( $node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			. "</script>\n";
	}
}

/* -------------------------------------------------------------------------
 *  Customizer fields (all optional, all safely empty by default)
 * ------------------------------------------------------------------------- */

add_action( 'customize_register', function ( $wp_customize ) {
	if ( ! $wp_customize->get_section( 'pr_brand_section' ) ) {
		$wp_customize->add_section( 'pr_brand_section', array(
			'title'    => __( 'Brand', 'product-reviews' ),
			'priority' => 30,
		) );
	}
	$fields = array(
		'pr_brand_contact_email' => __( 'Contact email (E-E-A-T)', 'product-reviews' ),
		'pr_brand_twitter'       => __( 'Twitter / X URL', 'product-reviews' ),
		'pr_brand_facebook'      => __( 'Facebook URL', 'product-reviews' ),
		'pr_brand_instagram'     => __( 'Instagram URL', 'product-reviews' ),
		'pr_brand_youtube'       => __( 'YouTube URL', 'product-reviews' ),
		'pr_brand_linkedin'      => __( 'LinkedIn URL', 'product-reviews' ),
		'pr_brand_wikipedia'     => __( 'Wikipedia URL', 'product-reviews' ),
		'pr_brand_crunchbase'    => __( 'Crunchbase URL', 'product-reviews' ),
	);
	foreach ( $fields as $id => $label ) {
		$wp_customize->add_setting( $id, array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
			'transport'         => 'refresh',
		) );
		$wp_customize->add_control( $id, array(
			'label'   => $label,
			'section' => 'pr_brand_section',
			'type'    => 'url',
		) );
	}
} );

/* -------------------------------------------------------------------------
 *  Hooks — priority 60 = after inc/schema.php (priority 50)
 * ------------------------------------------------------------------------- */

add_action( 'wp_head', 'pr_eeat_org_jsonld', 60 );
add_action( 'wp_head', 'pr_eeat_products_jsonld', 61 );
