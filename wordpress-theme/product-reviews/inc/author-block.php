<?php
/**
 * Author / E-E-A-T byline block. Renders an attractive author card with
 * avatar, bio, credentials, and "Reviewed by" metadata for trust signals.
 *
 * @package ProductReviews
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function pr_author_block_render_html( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	if ( ! $post_id ) { return ''; }
	$author_id = (int) get_post_field( 'post_author', $post_id );
	if ( ! $author_id ) { return ''; }
	$name   = get_the_author_meta( 'display_name', $author_id );
	$bio    = get_the_author_meta( 'description', $author_id );
	$url    = get_author_posts_url( $author_id );
	$avatar = get_avatar( $author_id, 80, '', $name, array( 'class' => 'pr-author-avatar' ) );
	$creds  = get_user_meta( $author_id, 'pr_credentials', true );
	$reviewer = get_post_meta( $post_id, '_pr_reviewer', true );
	$updated  = get_the_modified_date( '', $post_id );

	$html  = '<aside class="pr-author" itemscope itemtype="https://schema.org/Person">';
	$html .= '<div class="pr-author-head">' . $avatar;
	$html .= '<div class="pr-author-meta"><a class="pr-author-name" href="' . esc_url( $url ) . '" itemprop="url"><span itemprop="name">' . esc_html( $name ) . '</span></a>';
	if ( $creds ) { $html .= '<div class="pr-author-creds" itemprop="jobTitle">' . esc_html( $creds ) . '</div>'; }
	$html .= '<div class="pr-author-sub">' . sprintf( esc_html__( 'Updated %s', 'product-reviews' ), esc_html( $updated ) );
	if ( $reviewer ) { $html .= ' · ' . sprintf( esc_html__( 'Reviewed by %s', 'product-reviews' ), esc_html( $reviewer ) ); }
	$html .= '</div></div></div>';
	if ( $bio ) { $html .= '<p class="pr-author-bio" itemprop="description">' . esc_html( $bio ) . '</p>'; }
	$html .= '</aside>';
	return $html;
}

function pr_author_block_shortcode( $atts ) {
	$a = shortcode_atts( array( 'id' => 0 ), $atts, 'pr_author' );
	return pr_author_block_render_html( (int) $a['id'] );
}
add_shortcode( 'pr_author', 'pr_author_block_shortcode' );

function pr_author_block_inject_content( $content ) {
	if ( ! is_singular( 'review' ) || ! in_the_loop() || ! is_main_query() ) { return $content; }
	if ( ! apply_filters( 'pr_author_auto_inject', true, get_the_ID() ) ) { return $content; }
	return pr_author_block_render_html( get_the_ID() ) . $content;
}
add_filter( 'the_content', 'pr_author_block_inject_content', 7 );

function pr_author_block_styles() {
	echo '<style>.pr-author{border:1px solid var(--pr-border,#e5e7eb);padding:1rem;border-radius:.5rem;margin:1rem 0;background:var(--pr-surface,#f9fafb)}.pr-author-head{display:flex;gap:1rem;align-items:center}.pr-author-avatar{border-radius:50%}.pr-author-name{font-weight:700;text-decoration:none;color:inherit}.pr-author-creds{font-size:.875rem;color:var(--pr-muted,#6b7280)}.pr-author-sub{font-size:.8rem;color:var(--pr-muted,#6b7280);margin-top:.125rem}.pr-author-bio{margin:.75rem 0 0;font-size:.95rem;color:var(--pr-fg,#111)}</style>';
}
add_action( 'wp_head', 'pr_author_block_styles', 92 );

function pr_author_block_profile_field( $user ) {
	$val = get_user_meta( $user->ID, 'pr_credentials', true );
	echo '<h2>' . esc_html__( 'Reviewer Credentials', 'product-reviews' ) . '</h2>';
	echo '<table class="form-table"><tr><th><label for="pr_credentials">' . esc_html__( 'Credentials / Title', 'product-reviews' ) . '</label></th>';
	echo '<td><input type="text" id="pr_credentials" name="pr_credentials" value="' . esc_attr( $val ) . '" class="regular-text" /></td></tr></table>';
}
add_action( 'show_user_profile', 'pr_author_block_profile_field' );
add_action( 'edit_user_profile', 'pr_author_block_profile_field' );

function pr_author_block_save_profile( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) ) { return; }
	if ( isset( $_POST['pr_credentials'] ) ) {
		update_user_meta( $user_id, 'pr_credentials', sanitize_text_field( wp_unslash( $_POST['pr_credentials'] ) ) );
	}
}
add_action( 'personal_options_update', 'pr_author_block_save_profile' );
add_action( 'edit_user_profile_update', 'pr_author_block_save_profile' );
