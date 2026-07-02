<?php
/**
 * FAQ consolidation — visible FAQ block + FAQPage JSON-LD.
 *
 * Source of truth: post meta `_pr_faq` = array of {q, a}. Editors can
 * also use a shortcode [pr_faq] to render the block manually. When meta
 * is present we auto-append the block to the_content on singular
 * reviews/pages and emit FAQPage JSON-LD.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read & normalize FAQ items for a post.
 *
 * @param int $post_id
 * @return array<int,array{q:string,a:string}>
 */
function pr_faq_items( $post_id ) {
	$raw = get_post_meta( $post_id, '_pr_faq', true );
	if ( ! is_array( $raw ) ) {
		return array();
	}
	$out = array();
	foreach ( $raw as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$q = isset( $row['q'] ) ? trim( wp_strip_all_tags( (string) $row['q'] ) ) : '';
		$a = isset( $row['a'] ) ? trim( (string) $row['a'] ) : '';
		if ( $q === '' || $a === '' ) {
			continue;
		}
		$out[] = array( 'q' => $q, 'a' => $a );
	}
	return $out;
}

/**
 * Render visible FAQ block.
 */
function pr_faq_render_html( $post_id ) {
	$items = pr_faq_items( $post_id );
	if ( empty( $items ) ) {
		return '';
	}
	$out  = '<section class="pr-faq" aria-labelledby="pr-faq-heading">';
	$out .= '<h2 id="pr-faq-heading" class="pr-faq-title">' . esc_html__( 'Frequently asked questions', 'product-reviews' ) . '</h2>';
	$out .= '<div class="pr-faq-list">';
	foreach ( $items as $i => $item ) {
		$id   = 'pr-faq-' . $post_id . '-' . $i;
		$out .= '<details class="pr-faq-item" id="' . esc_attr( $id ) . '">';
		$out .= '<summary>' . esc_html( $item['q'] ) . '</summary>';
		$out .= '<div class="pr-faq-answer">' . wp_kses_post( wpautop( $item['a'] ) ) . '</div>';
		$out .= '</details>';
	}
	$out .= '</div></section>';
	return $out;
}

/**
 * Shortcode wrapper.
 */
function pr_faq_shortcode( $atts ) {
	$atts    = shortcode_atts( array( 'id' => 0 ), $atts, 'pr_faq' );
	$post_id = (int) $atts['id'] ?: get_the_ID();
	if ( ! $post_id ) {
		return '';
	}
	return pr_faq_render_html( $post_id );
}
add_shortcode( 'pr_faq', 'pr_faq_shortcode' );

/**
 * Auto-append to the_content on singular reviews/pages when meta exists
 * and the content does not already contain the [pr_faq] shortcode.
 */
function pr_faq_inject_content( $content ) {
	if ( ! is_singular() || is_admin() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	if ( ! apply_filters( 'pr_faq_auto_inject', true ) ) {
		return $content;
	}
	if ( has_shortcode( $content, 'pr_faq' ) ) {
		return $content;
	}
	$post_id = get_the_ID();
	$html    = pr_faq_render_html( $post_id );
	if ( ! $html ) {
		return $content;
	}
	return $content . $html;
}
add_filter( 'the_content', 'pr_faq_inject_content', 14 );

/**
 * Scoped styles.
 */
function pr_faq_styles() {
	echo '<style id="pr-faq-styles">'
		. '.pr-faq{margin:2rem 0;padding:1.25rem;border:1px solid #e5e7eb;border-radius:.75rem;background:#fafafa;}'
		. '.pr-faq-title{margin:0 0 1rem;font-size:1.25rem;}'
		. '.pr-faq-list{display:flex;flex-direction:column;gap:.5rem;}'
		. '.pr-faq-item{border:1px solid #e5e7eb;border-radius:.5rem;background:#fff;padding:.5rem .85rem;}'
		. '.pr-faq-item>summary{cursor:pointer;font-weight:600;list-style:none;padding:.4rem 0;display:flex;justify-content:space-between;align-items:center;gap:1rem;}'
		. '.pr-faq-item>summary::-webkit-details-marker{display:none;}'
		. '.pr-faq-item>summary::after{content:"+";font-weight:400;opacity:.6;transition:transform .2s;}'
		. '.pr-faq-item[open]>summary::after{content:"−";}'
		. '.pr-faq-answer{padding:.25rem 0 .5rem;color:#374151;}'
		. '.pr-faq-answer p:last-child{margin-bottom:0;}'
		. '</style>';
}
add_action( 'wp_head', 'pr_faq_styles', 93 );

/**
 * Emit FAQPage JSON-LD on singular views that have FAQ meta.
 */
function pr_faq_jsonld() {
	if ( ! is_singular() ) {
		return;
	}
	$post_id = get_queried_object_id();
	$items   = pr_faq_items( $post_id );
	if ( empty( $items ) ) {
		return;
	}
	$main = array();
	foreach ( $items as $item ) {
		$main[] = array(
			'@type'          => 'Question',
			'name'           => $item['q'],
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => wp_strip_all_tags( $item['a'] ),
			),
		);
	}
	$data = array(
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'@id'        => get_permalink( $post_id ) . '#faq',
		'mainEntity' => $main,
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}
add_action( 'wp_head', 'pr_faq_jsonld', 83 );

/**
 * Admin meta box for FAQ entries.
 */
function pr_faq_register_metabox() {
	foreach ( array( 'review', 'post', 'page' ) as $pt ) {
		add_meta_box(
			'pr_faq_box',
			__( 'FAQ', 'product-reviews' ),
			'pr_faq_render_metabox',
			$pt,
			'normal',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', 'pr_faq_register_metabox' );

function pr_faq_render_metabox( $post ) {
	wp_nonce_field( 'pr_faq_save', 'pr_faq_nonce' );
	$items = pr_faq_items( $post->ID );
	if ( empty( $items ) ) {
		$items = array( array( 'q' => '', 'a' => '' ) );
	}
	echo '<p class="description">' . esc_html__( 'Each Q&A becomes a Schema.org Question/Answer pair. Leave both fields blank to remove a row.', 'product-reviews' ) . '</p>';
	echo '<div id="pr-faq-rows">';
	foreach ( $items as $i => $item ) {
		echo '<div class="pr-faq-row" style="margin-bottom:1em;padding:.75em;border:1px solid #ddd;background:#fff;">';
		echo '<p><label><strong>' . esc_html__( 'Question', 'product-reviews' ) . '</strong><br>';
		echo '<input type="text" name="pr_faq[' . (int) $i . '][q]" value="' . esc_attr( $item['q'] ) . '" style="width:100%;"></label></p>';
		echo '<p><label><strong>' . esc_html__( 'Answer', 'product-reviews' ) . '</strong><br>';
		echo '<textarea name="pr_faq[' . (int) $i . '][a]" rows="3" style="width:100%;">' . esc_textarea( $item['a'] ) . '</textarea></label></p>';
		echo '</div>';
	}
	// Append 3 extra blank rows for easy adds.
	$next = count( $items );
	for ( $k = 0; $k < 3; $k++ ) {
		$idx = $next + $k;
		echo '<div class="pr-faq-row" style="margin-bottom:1em;padding:.75em;border:1px dashed #ccc;background:#fafafa;">';
		echo '<p><label>' . esc_html__( 'Question', 'product-reviews' ) . '<br>';
		echo '<input type="text" name="pr_faq[' . (int) $idx . '][q]" value="" style="width:100%;"></label></p>';
		echo '<p><label>' . esc_html__( 'Answer', 'product-reviews' ) . '<br>';
		echo '<textarea name="pr_faq[' . (int) $idx . '][a]" rows="3" style="width:100%;"></textarea></label></p>';
		echo '</div>';
	}
	echo '</div>';
}

function pr_faq_save( $post_id ) {
	if ( ! isset( $_POST['pr_faq_nonce'] ) || ! wp_verify_nonce( $_POST['pr_faq_nonce'], 'pr_faq_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$raw = isset( $_POST['pr_faq'] ) && is_array( $_POST['pr_faq'] ) ? wp_unslash( $_POST['pr_faq'] ) : array();
	$out = array();
	foreach ( $raw as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$q = isset( $row['q'] ) ? trim( wp_strip_all_tags( (string) $row['q'] ) ) : '';
		$a = isset( $row['a'] ) ? trim( wp_kses_post( (string) $row['a'] ) ) : '';
		if ( $q === '' || $a === '' ) {
			continue;
		}
		$out[] = array( 'q' => $q, 'a' => $a );
	}
	if ( empty( $out ) ) {
		delete_post_meta( $post_id, '_pr_faq' );
	} else {
		update_post_meta( $post_id, '_pr_faq', $out );
	}
}
add_action( 'save_post', 'pr_faq_save' );
