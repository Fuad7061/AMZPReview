<?php
/**
 * Sitemap provider for the two programmatic hubs.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_Sitemaps_Provider' ) ) {
	return;
}

if ( ! class_exists( 'PR_Hubs_Sitemap_Provider' ) ) {
class PR_Hubs_Sitemap_Provider extends WP_Sitemaps_Provider {

	public function __construct( $name ) {
		$this->name        = $name;
		$this->object_type = 'pr_hubs';
	}

	public function get_url_list( $page_num, $object_subtype = '' ) {
		$urls  = array();
		$terms = get_terms( array( 'taxonomy' => 'review_category', 'hide_empty' => true ) );
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return $urls;
		}
		foreach ( $terms as $term ) {
			$urls[] = array( 'loc' => home_url( '/best/' . $term->slug . '/' ) );
			$term_link = get_term_link( $term );
			if ( ! is_wp_error( $term_link ) && is_string( $term_link ) && '' !== $term_link ) {
				$urls[] = array( 'loc' => $term_link );
			}
		}
		return $urls;
	}

	public function get_max_num_pages( $object_subtype = '' ) {
		return 1;
	}

	public function get_object_subtypes() {
		return array();
	}
}
}
