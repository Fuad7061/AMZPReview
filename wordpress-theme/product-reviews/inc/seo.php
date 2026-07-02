<?php
/**
 * SEO — meta tags, canonical, Open Graph, Twitter Card, sitemap tweaks.
 *
 * WordPress 5.5+ ships an XML sitemap at /wp-sitemap.xml that already
 * includes the `review` CPT and `review_category` / `review_tag`
 * taxonomies because they're registered public. This module:
 *
 *   - Adds <meta name="description">, canonical, Open Graph and Twitter
 *     Card tags on single-review posts and review-category archives.
 *   - Strengthens the default sitemap with priority/changefreq hints.
 *   - Exposes a helper that other modules (schema, internal-links) call
 *     to derive the canonical "best image" URL for a review post.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ============================================================
 * Meta tags (description / canonical / OG / Twitter)
 * ============================================================ */

add_action( 'wp_head', 'pr_seo_render_meta', 5 );

function pr_seo_render_meta(): void {
	$tags = pr_seo_collect_tags();
	if ( empty( $tags ) ) { return; }

	echo "\n<!-- Product Reviews SEO -->\n";
	foreach ( $tags as $tag ) {
		if ( ! empty( $tag['rel'] ) ) {
			printf( '<link rel="%s" href="%s">' . "\n", esc_attr( $tag['rel'] ), esc_url( $tag['href'] ) );
			continue;
		}
		$attr = ! empty( $tag['property'] ) ? 'property' : 'name';
		$key  = $tag[ $attr ] ?? '';
		$val  = (string) ( $tag['content'] ?? '' );
		if ( $key === '' || $val === '' ) { continue; }
		printf( '<meta %s="%s" content="%s">' . "\n", esc_attr( $attr ), esc_attr( $key ), esc_attr( $val ) );
	}
}

/**
 * Build the per-page tag list. Pure function (no echo) so it can be unit-tested.
 *
 * @return array<int,array<string,string>>
 */
function pr_seo_collect_tags(): array {
	$out = array();

	if ( is_singular( 'review' ) ) {
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) { return array(); }

		$title       = wp_strip_all_tags( get_the_title( $post ) );
		$description = pr_seo_post_description( $post );
		$canonical   = get_permalink( $post );
		$image       = pr_seo_post_image( $post );

		$out[] = array( 'name'     => 'description', 'content' => $description );
		$out[] = array( 'rel'      => 'canonical',   'href'    => $canonical );

		// Open Graph
		$out[] = array( 'property' => 'og:type',        'content' => 'article' );
		$out[] = array( 'property' => 'og:title',       'content' => $title );
		$out[] = array( 'property' => 'og:description', 'content' => $description );
		$out[] = array( 'property' => 'og:url',         'content' => $canonical );
		$out[] = array( 'property' => 'og:site_name',   'content' => get_bloginfo( 'name' ) );
		if ( $image ) {
			$out[] = array( 'property' => 'og:image',     'content' => $image );
			$out[] = array( 'property' => 'og:image:alt', 'content' => $title );
		}

		// Article timestamps
		$out[] = array( 'property' => 'article:published_time', 'content' => get_post_time( 'c', true, $post ) );
		$out[] = array( 'property' => 'article:modified_time',  'content' => get_post_modified_time( 'c', true, $post ) );
		$terms = wp_get_post_terms( $post->ID, 'review_category', array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$out[] = array( 'property' => 'article:section', 'content' => (string) $t );
			}
		}

		// Twitter
		$out[] = array( 'name' => 'twitter:card',        'content' => $image ? 'summary_large_image' : 'summary' );
		$out[] = array( 'name' => 'twitter:title',       'content' => $title );
		$out[] = array( 'name' => 'twitter:description', 'content' => $description );
		if ( $image ) {
			$out[] = array( 'name' => 'twitter:image',   'content' => $image );
		}
		return $out;
	}

	if ( is_tax( 'review_category' ) ) {
		$term = get_queried_object();
		if ( ! $term instanceof WP_Term ) { return array(); }
		$title       = sprintf( __( '%s reviews', 'product-reviews' ), $term->name );
		$description = $term->description !== ''
			? wp_strip_all_tags( $term->description )
			: sprintf( __( 'Hand-picked %s reviews, updated with the latest prices and ratings.', 'product-reviews' ), strtolower( $term->name ) );
		$canonical   = get_term_link( $term );

		$out[] = array( 'name' => 'description',        'content' => $description );
		$out[] = array( 'rel'  => 'canonical',          'href'    => is_string( $canonical ) ? $canonical : home_url( '/' ) );
		$out[] = array( 'property' => 'og:type',        'content' => 'website' );
		$out[] = array( 'property' => 'og:title',       'content' => $title );
		$out[] = array( 'property' => 'og:description', 'content' => $description );
		$out[] = array( 'property' => 'og:url',         'content' => is_string( $canonical ) ? $canonical : '' );
		$out[] = array( 'name'     => 'twitter:card',   'content' => 'summary' );
		return $out;
	}

	if ( is_post_type_archive( 'review' ) ) {
		$title = __( 'All reviews', 'product-reviews' );
		$desc  = __( 'Browse every review on the site, updated with live pricing and ratings.', 'product-reviews' );
		$out[] = array( 'name' => 'description',        'content' => $desc );
		$out[] = array( 'rel'  => 'canonical',          'href'    => get_post_type_archive_link( 'review' ) ?: home_url( '/' ) );
		$out[] = array( 'property' => 'og:type',        'content' => 'website' );
		$out[] = array( 'property' => 'og:title',       'content' => $title );
		$out[] = array( 'property' => 'og:description', 'content' => $desc );
		return $out;
	}

	return $out;
}

/**
 * Build a concise meta-description for a review post. Order of preference:
 *   1. Manual excerpt
 *   2. Autopilot's tldr (`_yadfood_tldr`)
 *   3. First sentence(s) of the intro
 *   4. Trimmed post content
 */
function pr_seo_post_description( WP_Post $post, int $max = 160 ): string {
	$src = '';
	if ( $post->post_excerpt !== '' ) {
		$src = $post->post_excerpt;
	} elseif ( ( $t = get_post_meta( $post->ID, '_yadfood_tldr', true ) ) ) {
		$src = (string) $t;
	} elseif ( ( $i = get_post_meta( $post->ID, '_yadfood_intro', true ) ) ) {
		$src = (string) $i;
	} else {
		$src = $post->post_content;
	}
	$src = wp_strip_all_tags( $src );
	$src = trim( preg_replace( '/\s+/', ' ', $src ) );
	if ( mb_strlen( $src ) <= $max ) { return $src; }
	$short = mb_substr( $src, 0, $max - 1 );
	$cut   = mb_strrpos( $short, ' ' );
	return ( $cut !== false ? mb_substr( $short, 0, $cut ) : $short ) . '…';
}

/**
 * Best image URL for a review: featured image → first product image → empty.
 */
function pr_seo_post_image( WP_Post $post ): string {
	if ( has_post_thumbnail( $post ) ) {
		$src = wp_get_attachment_image_url( get_post_thumbnail_id( $post ), 'large' );
		if ( $src ) { return $src; }
	}
	if ( function_exists( 'yadfood_get_products' ) ) {
		$products = (array) yadfood_get_products( $post->ID );
		foreach ( $products as $p ) {
			if ( ! empty( $p['image'] ) ) { return (string) $p['image']; }
		}
	}
	return '';
}

/* ============================================================
 * Sitemap polish — lastmod hints + ensure visibility.
 * ============================================================ */

// WP 5.5 already includes public CPTs/taxonomies in /wp-sitemap.xml.
// Tag pages are noisy on a review site — drop them by default.
add_filter( 'wp_sitemaps_taxonomies', function ( $taxes ) {
	unset( $taxes['post_tag'] );
	unset( $taxes['review_tag'] );
	return $taxes;
} );

// Add lastmod hints to the review sitemap entries (Google ignores
// `lastmod` from /wp-sitemap.xml by default, but search engines that
// honor it benefit, and validators stop warning).
add_filter( 'wp_sitemaps_posts_entry', function ( $entry, $post ) {
	if ( $post && $post->post_type === 'review' ) {
		$entry['lastmod'] = get_post_modified_time( 'c', true, $post );
	}
	return $entry;
}, 10, 2 );

/* ============================================================
 * robots.txt — point crawlers at the sitemap.
 * ============================================================ */
add_filter( 'robots_txt', function ( $output, $public ) {
	if ( ! $public ) { return $output; }
	$sitemap = home_url( '/wp-sitemap.xml' );
	if ( stripos( $output, $sitemap ) === false ) {
		$output .= "\nSitemap: {$sitemap}\n";
	}
	return $output;
}, 10, 2 );
