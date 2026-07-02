<?php
/**
 * JSON-LD structured data output for SEO.
 *
 * Emits ItemList + Product/Review schemas on single-review pages and
 * FAQPage when the post has FAQs. Also outputs BreadcrumbList.
 *
 * @package YadFoodReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function yadfood_output_jsonld() {
	if ( is_singular( 'review' ) ) {
		pr_jsonld_single_review();
		return;
	}
	if ( is_tax( 'review_category' ) ) {
		pr_jsonld_category_archive();
		return;
	}
}
add_action( 'wp_head', 'yadfood_output_jsonld', 50 );

/**
 * JSON-LD payload for a single review post.
 *
 * Emits, in order: Article, Product+Review for each item, ItemList,
 * FAQPage (if any), BreadcrumbList.
 */
function pr_jsonld_single_review(): void {
	$post_id  = get_queried_object_id();
	$products = (array) yadfood_get_products( $post_id );
	$faqs     = (array) yadfood_get_faqs( $post_id );
	$title    = wp_strip_all_tags( get_the_title( $post_id ) );
	$url      = get_permalink( $post_id );
	$url      = is_string( $url ) && '' !== $url ? $url : home_url( '/' );
	$image    = pr_seo_post_image( get_post( $post_id ) );
	$site     = get_bloginfo( 'name' );
	$archive  = get_post_type_archive_link( 'review' );
	$archive  = is_string( $archive ) && '' !== $archive ? $archive : home_url( '/' );

	$ld = array();

	// ----- Article -----
	$article = array(
		'@context'         => 'https://schema.org',
		'@type'            => 'Article',
		'headline'         => $title,
		'description'      => pr_seo_post_description( get_post( $post_id ) ),
		'datePublished'    => get_post_time( 'c', true, $post_id ),
		'dateModified'     => get_post_modified_time( 'c', true, $post_id ),
		'mainEntityOfPage' => $url,
		'author'    => array( '@type' => 'Organization', 'name' => $site, 'url' => home_url( '/' ) ),
		'publisher' => array( '@type' => 'Organization', 'name' => $site, 'url' => home_url( '/' ) ),
	);
	if ( $image ) { $article['image'] = $image; }
	$ld[] = $article;

	// ----- Product + Review per item -----
	$list_items = array();
	foreach ( $products as $i => $p ) {
		$p_url = ! empty( $p['asin'] )
			? yadfood_amazon_url( $p['asin'], get_post_field( 'post_name', $post_id ) )
			: $url;

		$product = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Product',
			'name'     => (string) ( $p['title'] ?? '' ),
		);
		if ( ! empty( $p['brand'] ) )  { $product['brand'] = array( '@type' => 'Brand', 'name' => (string) $p['brand'] ); }
		if ( ! empty( $p['image'] ) )  { $product['image'] = (string) $p['image']; }
		if ( ! empty( $p['asin'] ) )   { $product['sku']   = strtoupper( (string) $p['asin'] ); }
		$product['url'] = $p_url;

		if ( ! empty( $p['rating'] ) ) {
			$product['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => (string) $p['rating'],
				'reviewCount' => (string) max( 1, (int) ( $p['review_count'] ?? 1 ) ),
				'bestRating'  => '5',
				'worstRating' => '1',
			);
		}
		if ( ! empty( $p['price'] ) ) {
			$product['offers'] = array(
				'@type'         => 'Offer',
				'price'         => number_format( (float) $p['price'], 2, '.', '' ),
				'priceCurrency' => (string) ( $p['currency'] ?? 'USD' ),
				'availability'  => 'https://schema.org/InStock',
				'url'           => $p_url,
				'priceValidUntil' => gmdate( 'Y-12-31' ),
			);
		}
		// Nested editorial review (uses the per-product `why` paragraph).
		if ( ! empty( $p['why'] ) || ! empty( $p['rating'] ) ) {
			$review = array(
				'@type'  => 'Review',
				'author' => array( '@type' => 'Organization', 'name' => $site ),
				'datePublished' => get_post_time( 'c', true, $post_id ),
				'reviewBody'    => wp_strip_all_tags( (string) ( $p['why'] ?? '' ) ),
			);
			if ( ! empty( $p['rating'] ) ) {
				$review['reviewRating'] = array(
					'@type'       => 'Rating',
					'ratingValue' => (string) $p['rating'],
					'bestRating'  => '5',
					'worstRating' => '1',
				);
			}
			if ( ! empty( $p['pros'] ) && is_array( $p['pros'] ) ) {
				$review['positiveNotes'] = array(
					'@type'           => 'ItemList',
					'itemListElement' => array_map( static function ( $v, $k ) {
						return array( '@type' => 'ListItem', 'position' => $k + 1, 'name' => (string) $v );
					}, $p['pros'], array_keys( $p['pros'] ) ),
				);
			}
			if ( ! empty( $p['cons'] ) && is_array( $p['cons'] ) ) {
				$review['negativeNotes'] = array(
					'@type'           => 'ItemList',
					'itemListElement' => array_map( static function ( $v, $k ) {
						return array( '@type' => 'ListItem', 'position' => $k + 1, 'name' => (string) $v );
					}, $p['cons'], array_keys( $p['cons'] ) ),
				);
			}
			$product['review'] = $review;
		}
		$ld[] = $product;

		$list_items[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'url'      => $p_url,
			'name'     => (string) ( $p['title'] ?? '' ),
		);
	}

	if ( ! empty( $list_items ) ) {
		$ld[] = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'ItemList',
			'itemListOrder'   => 'https://schema.org/ItemListOrderAscending',
			'numberOfItems'   => count( $list_items ),
			'itemListElement' => $list_items,
		);
	}

	// ----- FAQPage -----
	if ( ! empty( $faqs ) ) {
		$entities = array();
		foreach ( $faqs as $f ) {
			if ( empty( $f['q'] ) || empty( $f['a'] ) ) { continue; }
			$entities[] = array(
				'@type' => 'Question',
				'name'  => (string) $f['q'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => wp_strip_all_tags( (string) $f['a'] ),
				),
			);
		}
		if ( ! empty( $entities ) ) {
			$ld[] = array(
				'@context'   => 'https://schema.org',
				'@type'      => 'FAQPage',
				'mainEntity' => $entities,
			);
		}
	}

	// ----- BreadcrumbList -----
	$crumbs   = array(
		array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Home',    'item' => home_url( '/' ) ),
		array( '@type' => 'ListItem', 'position' => 2, 'name' => 'Reviews', 'item' => $archive ),
	);
	$primary  = pr_primary_review_category( $post_id );
	if ( $primary ) {
		$primary_link = get_term_link( $primary );
		$primary_link = ( is_wp_error( $primary_link ) || ! is_string( $primary_link ) || '' === $primary_link ) ? home_url( '/' ) : $primary_link;
		$crumbs[] = array( '@type' => 'ListItem', 'position' => 3, 'name' => $primary->name, 'item' => $primary_link );
		$crumbs[] = array( '@type' => 'ListItem', 'position' => 4, 'name' => $title,         'item' => $url );
	} else {
		$crumbs[] = array( '@type' => 'ListItem', 'position' => 3, 'name' => $title,         'item' => $url );
	}
	$ld[] = array(
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $crumbs,
	);

	pr_jsonld_print( $ld );
}

/**
 * JSON-LD payload for a review_category archive page.
 */
function pr_jsonld_category_archive(): void {
	$term = get_queried_object();
	if ( ! $term instanceof WP_Term ) { return; }

	$url      = get_term_link( $term );
	$url      = ( is_wp_error( $url ) || ! is_string( $url ) || '' === $url ) ? home_url( '/' ) : $url;
	$site     = get_bloginfo( 'name' );
	$archive  = get_post_type_archive_link( 'review' );
	$archive  = is_string( $archive ) && '' !== $archive ? $archive : home_url( '/' );
	$reviews = get_posts( array(
		'post_type'      => 'review',
		'posts_per_page' => 20,
		'tax_query'      => array( array(
			'taxonomy' => 'review_category',
			'field'    => 'term_id',
			'terms'    => $term->term_id,
		) ),
		'orderby' => 'date',
		'order'   => 'DESC',
	) );

	$items = array();
	foreach ( $reviews as $i => $p ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'url'      => ( is_string( get_permalink( $p ) ) && get_permalink( $p ) ) ? get_permalink( $p ) : home_url( '/' ),
			'name'     => wp_strip_all_tags( get_the_title( $p ) ),
		);
	}

	pr_jsonld_print( array(
		array(
			'@context'    => 'https://schema.org',
			'@type'       => 'CollectionPage',
			'name'        => sprintf( __( '%s reviews', 'product-reviews' ), $term->name ),
			'description' => wp_strip_all_tags( $term->description ?: '' ),
			'url'         => $url,
			'isPartOf'    => array( '@type' => 'WebSite', 'name' => $site, 'url' => home_url( '/' ) ),
		),
		array(
			'@context'        => 'https://schema.org',
			'@type'           => 'ItemList',
			'numberOfItems'   => count( $items ),
			'itemListElement' => $items,
		),
		array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => array(
				array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Home',    'item' => home_url( '/' ) ),
				array( '@type' => 'ListItem', 'position' => 2, 'name' => 'Reviews', 'item' => $archive ),
				array( '@type' => 'ListItem', 'position' => 3, 'name' => $term->name, 'item' => $url ),
			),
		),
	) );
}

function pr_jsonld_print( array $ld ): void {
	foreach ( $ld as $blob ) {
		echo '<script type="application/ld+json">' . wp_json_encode( $blob, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
	}
}

/** Pick a single primary review_category for breadcrumbs / linking. */
function pr_primary_review_category( int $post_id ): ?WP_Term {
	$terms = wp_get_post_terms( $post_id, 'review_category' );
	if ( is_wp_error( $terms ) || empty( $terms ) ) { return null; }
	// Prefer the term flagged as primary via meta if present.
	$primary_id = (int) get_post_meta( $post_id, '_pr_primary_category', true );
	if ( $primary_id ) {
		foreach ( $terms as $t ) { if ( (int) $t->term_id === $primary_id ) { return $t; } }
	}
	return $terms[0];
}
