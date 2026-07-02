<?php
/**
 * Buyer's Guide block. Reads _pr_buyers_guide meta and renders a styled
 * advisory section. Provides [pr_buyers_guide] shortcode.
 *
 * @package ProductReviews
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function pr_buyers_guide_text( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	$txt = get_post_meta( $post_id, '_pr_buyers_guide', true );
	return is_string( $txt ) ? trim( $txt ) : '';
}

function pr_buyers_guide_render_html( $post_id = 0 ) {
	$txt = pr_buyers_guide_text( $post_id );
	if ( $txt === '' ) { return ''; }
	$title = apply_filters( 'pr_buyers_guide_title', __( "Buyer's Guide", 'product-reviews' ), $post_id );
	return '<section class="pr-bguide" aria-label="' . esc_attr( $title ) . '">'
		. '<h2 class="pr-bguide-title">' . esc_html( $title ) . '</h2>'
		. '<div class="pr-bguide-body">' . wp_kses_post( wpautop( $txt ) ) . '</div>'
		. '</section>';
}

function pr_buyers_guide_shortcode( $atts ) {
	$a = shortcode_atts( array( 'id' => 0 ), $atts, 'pr_buyers_guide' );
	return pr_buyers_guide_render_html( (int) $a['id'] );
}
add_shortcode( 'pr_buyers_guide', 'pr_buyers_guide_shortcode' );

function pr_buyers_guide_styles() {
	echo '<style>.pr-bguide{border:1px solid var(--pr-border,#e5e7eb);padding:1.25rem;margin:1.5rem 0;border-radius:.5rem;background:#fff}.pr-bguide-title{margin:0 0 .75rem;font-size:1.25rem;font-weight:700}</style>';
}
add_action( 'wp_head', 'pr_buyers_guide_styles', 98 );

function pr_buyers_guide_meta_box() {
	foreach ( array( 'review', 'post', 'page' ) as $pt ) {
		add_meta_box( 'pr_buyers_guide', __( "Buyer's Guide", 'product-reviews' ), 'pr_buyers_guide_meta_box_cb', $pt, 'normal', 'default' );
	}
}
add_action( 'add_meta_boxes', 'pr_buyers_guide_meta_box' );

function pr_buyers_guide_meta_box_cb( $post ) {
	wp_nonce_field( 'pr_buyers_guide_save', 'pr_buyers_guide_nonce' );
	$val = get_post_meta( $post->ID, '_pr_buyers_guide', true );
	echo '<textarea name="pr_buyers_guide" rows="6" style="width:100%">' . esc_textarea( (string) $val ) . '</textarea>';
}

function pr_buyers_guide_save( $post_id ) {
	if ( ! isset( $_POST['pr_buyers_guide_nonce'] ) || ! wp_verify_nonce( $_POST['pr_buyers_guide_nonce'], 'pr_buyers_guide_save' ) ) { return; }
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
	if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
	$val = isset( $_POST['pr_buyers_guide'] ) ? wp_kses_post( wp_unslash( $_POST['pr_buyers_guide'] ) ) : '';
	if ( $val === '' ) { delete_post_meta( $post_id, '_pr_buyers_guide' ); } else { update_post_meta( $post_id, '_pr_buyers_guide', $val ); }
}
add_action( 'save_post', 'pr_buyers_guide_save' );
