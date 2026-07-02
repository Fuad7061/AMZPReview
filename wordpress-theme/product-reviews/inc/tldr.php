<?php
/**
 * TL;DR summary block. Reads _pr_tldr meta and renders an accessible card,
 * exposes [pr_tldr] shortcode, auto-injects above the_content on singular reviews.
 *
 * @package ProductReviews
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function pr_tldr_text( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	if ( ! $post_id ) { return ''; }
	$txt = get_post_meta( $post_id, '_pr_tldr', true );
	return is_string( $txt ) ? trim( $txt ) : '';
}

function pr_tldr_render_html( $post_id = 0 ) {
	$txt = pr_tldr_text( $post_id );
	if ( $txt === '' ) { return ''; }
	$title = apply_filters( 'pr_tldr_title', __( 'TL;DR', 'product-reviews' ), $post_id );
	return '<aside class="pr-tldr" aria-label="' . esc_attr( $title ) . '">'
		. '<h2 class="pr-tldr-title">' . esc_html( $title ) . '</h2>'
		. '<div class="pr-tldr-body">' . wp_kses_post( wpautop( $txt ) ) . '</div>'
		. '</aside>';
}

function pr_tldr_shortcode( $atts ) {
	$a = shortcode_atts( array( 'id' => 0 ), $atts, 'pr_tldr' );
	return pr_tldr_render_html( (int) $a['id'] );
}
add_shortcode( 'pr_tldr', 'pr_tldr_shortcode' );

function pr_tldr_inject_content( $content ) {
	if ( ! is_singular( array( 'review', 'post' ) ) || ! in_the_loop() || ! is_main_query() ) { return $content; }
	if ( ! apply_filters( 'pr_tldr_auto_inject', true, get_the_ID() ) ) { return $content; }
	$block = pr_tldr_render_html( get_the_ID() );
	return $block ? $block . $content : $content;
}
add_filter( 'the_content', 'pr_tldr_inject_content', 8 );

function pr_tldr_styles() {
	echo '<style>.pr-tldr{border-left:4px solid var(--pr-accent,#2563eb);background:rgba(37,99,235,.06);padding:1rem 1.25rem;margin:1.25rem 0;border-radius:.5rem}.pr-tldr-title{margin:0 0 .5rem;font-size:1.05rem;font-weight:700}.pr-tldr-body p:last-child{margin-bottom:0}</style>';
}
add_action( 'wp_head', 'pr_tldr_styles', 96 );

function pr_tldr_meta_box() {
	foreach ( array( 'review', 'post' ) as $pt ) {
		add_meta_box( 'pr_tldr', __( 'TL;DR', 'product-reviews' ), 'pr_tldr_meta_box_cb', $pt, 'normal', 'high' );
	}
}
add_action( 'add_meta_boxes', 'pr_tldr_meta_box' );

function pr_tldr_meta_box_cb( $post ) {
	wp_nonce_field( 'pr_tldr_save', 'pr_tldr_nonce' );
	$val = get_post_meta( $post->ID, '_pr_tldr', true );
	echo '<textarea name="pr_tldr" rows="4" style="width:100%">' . esc_textarea( (string) $val ) . '</textarea>';
	echo '<p class="description">' . esc_html__( 'A 1–3 sentence summary shown above the article.', 'product-reviews' ) . '</p>';
}

function pr_tldr_save( $post_id ) {
	if ( ! isset( $_POST['pr_tldr_nonce'] ) || ! wp_verify_nonce( $_POST['pr_tldr_nonce'], 'pr_tldr_save' ) ) { return; }
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
	if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
	$val = isset( $_POST['pr_tldr'] ) ? wp_kses_post( wp_unslash( $_POST['pr_tldr'] ) ) : '';
	if ( $val === '' ) { delete_post_meta( $post_id, '_pr_tldr' ); } else { update_post_meta( $post_id, '_pr_tldr', $val ); }
}
add_action( 'save_post', 'pr_tldr_save' );
