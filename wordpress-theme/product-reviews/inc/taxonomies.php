<?php
/**
 * Taxonomies for the review CPT.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function pr_register_taxonomies() {
	register_taxonomy( 'review_category', 'review', array(
		'label'             => __( 'Categories', 'product-reviews' ),
		'hierarchical'      => true,
		'show_in_rest'      => true,
		'show_admin_column' => true,
		'rewrite'           => array( 'slug' => 'category', 'with_front' => false ),
	) );

	register_taxonomy( 'review_tag', 'review', array(
		'label'             => __( 'Tags', 'product-reviews' ),
		'hierarchical'      => false,
		'show_in_rest'      => true,
		'show_admin_column' => true,
		'rewrite'           => array( 'slug' => 'tag', 'with_front' => false ),
	) );
}
add_action( 'init', 'pr_register_taxonomies' );

// Legacy alias.
function yadfood_register_taxonomies() { pr_register_taxonomies(); }
