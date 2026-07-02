<?php
/**
 * Related Products — derives related reviews by shared taxonomy terms and
 * renders a grid. Auto-appends to the_content on singular reviews.
 *
 * @package ProductReviews
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function pr_related_query( $post_id, $limit = 4 ) {
	$post_id = (int) $post_id; $limit = max( 1, (int) $limit );
	if ( ! $post_id ) { return array(); }
	$tax = taxonomy_exists( 'review_category' ) ? 'review_category' : 'category';
	$terms = wp_get_post_terms( $post_id, $tax, array( 'fields' => 'ids' ) );
	$args = array(
		'post_type'           => 'review',
		'posts_per_page'      => $limit,
		'post__not_in'        => array( $post_id ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
		'orderby'             => 'date',
		'order'               => 'DESC',
	);
	if ( ! empty( $terms ) ) {
		$args['tax_query'] = array( array( 'taxonomy' => $tax, 'field' => 'term_id', 'terms' => $terms ) );
	}
	$q = new WP_Query( $args );
	return $q->posts;
}

function pr_related_render_html( $post_id = 0, $limit = 4 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	$posts = pr_related_query( $post_id, $limit );
	if ( empty( $posts ) ) { return ''; }
	$title = apply_filters( 'pr_related_title', __( 'Related reviews', 'product-reviews' ), $post_id );
	$html = '<section class="pr-related" aria-label="' . esc_attr( $title ) . '">';
	$html .= '<h2 class="pr-related-title">' . esc_html( $title ) . '</h2>';
	$html .= '<div class="pr-related-grid">';
	foreach ( $posts as $p ) {
		$link = get_permalink( $p );
		$thumb = get_the_post_thumbnail( $p, 'pr-card', array( 'loading' => 'lazy', 'class' => 'pr-related-thumb' ) );
		$html .= '<a class="pr-related-card" href="' . esc_url( $link ) . '">';
		if ( $thumb ) { $html .= $thumb; }
		$html .= '<span class="pr-related-name">' . esc_html( get_the_title( $p ) ) . '</span>';
		$html .= '</a>';
	}
	$html .= '</div></section>';
	return $html;
}

function pr_related_shortcode( $atts ) {
	$a = shortcode_atts( array( 'id' => 0, 'limit' => 4 ), $atts, 'pr_related' );
	return pr_related_render_html( (int) $a['id'], (int) $a['limit'] );
}
add_shortcode( 'pr_related', 'pr_related_shortcode' );

function pr_related_inject_content( $content ) {
	if ( ! is_singular( 'review' ) || ! in_the_loop() || ! is_main_query() ) { return $content; }
	if ( ! apply_filters( 'pr_related_auto_inject', true, get_the_ID() ) ) { return $content; }
	$block = pr_related_render_html( get_the_ID(), (int) apply_filters( 'pr_related_limit', 4 ) );
	return $block ? $content . $block : $content;
}
add_filter( 'the_content', 'pr_related_inject_content', 20 );

function pr_related_styles() {
	echo '<style>.pr-related{margin:2rem 0}.pr-related-title{margin:0 0 .75rem;font-size:1.25rem;font-weight:700}.pr-related-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem}.pr-related-card{display:flex;flex-direction:column;gap:.5rem;border:1px solid var(--pr-border,#e5e7eb);padding:.75rem;border-radius:.5rem;text-decoration:none;color:inherit;background:#fff}.pr-related-card:hover{border-color:var(--pr-accent,#2563eb)}.pr-related-thumb{width:100%;height:auto;border-radius:.375rem}.pr-related-name{font-weight:600;font-size:.95rem}</style>';
}
add_action( 'wp_head', 'pr_related_styles', 99 );
