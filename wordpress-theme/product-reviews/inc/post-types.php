<?php
/**
 * Custom post type: review.
 *
 * @package YadFoodReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function yadfood_register_review_cpt() {
	$labels = array(
		'name'               => __( 'Reviews', 'yadfood-reviews' ),
		'singular_name'      => __( 'Review', 'yadfood-reviews' ),
		'add_new'            => __( 'Add New Review', 'yadfood-reviews' ),
		'add_new_item'       => __( 'Add New Review', 'yadfood-reviews' ),
		'edit_item'          => __( 'Edit Review', 'yadfood-reviews' ),
		'new_item'           => __( 'New Review', 'yadfood-reviews' ),
		'view_item'          => __( 'View Review', 'yadfood-reviews' ),
		'search_items'       => __( 'Search Reviews', 'yadfood-reviews' ),
		'menu_name'          => __( 'Reviews', 'yadfood-reviews' ),
	);

	register_post_type( 'review', array(
		'labels'             => $labels,
		'public'             => true,
		'has_archive'        => true,
		'show_in_rest'       => true,
		'menu_icon'          => 'dashicons-star-filled',
		'menu_position'      => 5,
		'rewrite'            => array( 'slug' => 'review', 'with_front' => false ),
		'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ),
	) );
}
add_action( 'init', 'yadfood_register_review_cpt' );

/**
 * Flush rewrite rules on activation.
 */
function yadfood_activation_flush() {
	yadfood_register_review_cpt();
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'yadfood_activation_flush' );
