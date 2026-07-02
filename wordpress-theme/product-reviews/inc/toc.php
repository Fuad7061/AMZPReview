<?php
/**
 * Table of Contents — scans rendered content for H2/H3, builds an anchored list,
 * adds slugged ids to headings, and auto-injects on long singular articles.
 *
 * @package ProductReviews
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function pr_toc_slugify( $text, &$seen ) {
	$slug = sanitize_title( wp_strip_all_tags( $text ) );
	if ( ! $slug ) { $slug = 'section'; }
	$base = $slug; $i = 2;
	while ( isset( $seen[ $slug ] ) ) { $slug = $base . '-' . $i++; }
	$seen[ $slug ] = true;
	return $slug;
}

function pr_toc_process( $content ) {
	if ( ! preg_match_all( '#<h([23])(\b[^>]*)>(.*?)</h\1>#is', $content, $m, PREG_SET_ORDER ) ) {
		return array( 'content' => $content, 'items' => array() );
	}
	$seen = array(); $items = array();
	foreach ( $m as $h ) {
		$level = (int) $h[1]; $attrs = $h[2]; $inner = $h[3];
		if ( preg_match( '#\bid=([\'"])([^\'"]+)\1#', $attrs, $idm ) ) {
			$id = $idm[2];
		} else {
			$id = pr_toc_slugify( $inner, $seen );
			$attrs .= ' id="' . esc_attr( $id ) . '"';
			$content = str_replace( $h[0], '<h' . $level . $attrs . '>' . $inner . '</h' . $level . '>', $content );
		}
		$items[] = array( 'level' => $level, 'id' => $id, 'text' => trim( wp_strip_all_tags( $inner ) ) );
	}
	return array( 'content' => $content, 'items' => $items );
}

function pr_toc_render_html( $items ) {
	if ( count( $items ) < 3 ) { return ''; }
	$title = apply_filters( 'pr_toc_title', __( 'Contents', 'product-reviews' ) );
	$html  = '<nav class="pr-toc" aria-label="' . esc_attr( $title ) . '">';
	$html .= '<h2 class="pr-toc-title">' . esc_html( $title ) . '</h2><ol class="pr-toc-list">';
	$open3 = false;
	foreach ( $items as $it ) {
		if ( $it['level'] === 2 ) {
			if ( $open3 ) { $html .= '</ol></li>'; $open3 = false; }
			$html .= '<li class="pr-toc-l2"><a href="#' . esc_attr( $it['id'] ) . '">' . esc_html( $it['text'] ) . '</a>';
		} else {
			if ( ! $open3 ) { $html .= '<ol class="pr-toc-sub">'; $open3 = true; }
			$html .= '<li class="pr-toc-l3"><a href="#' . esc_attr( $it['id'] ) . '">' . esc_html( $it['text'] ) . '</a></li>';
			continue;
		}
		if ( ! $open3 ) { $html .= '</li>'; }
	}
	if ( $open3 ) { $html .= '</ol></li>'; }
	$html .= '</ol></nav>';
	return $html;
}

function pr_toc_inject_content( $content ) {
	if ( ! is_singular( array( 'review', 'post', 'page' ) ) || ! in_the_loop() || ! is_main_query() ) { return $content; }
	if ( ! apply_filters( 'pr_toc_auto_inject', true, get_the_ID() ) ) { return $content; }
	$res = pr_toc_process( $content );
	$toc = pr_toc_render_html( $res['items'] );
	return $toc ? $toc . $res['content'] : $res['content'];
}
add_filter( 'the_content', 'pr_toc_inject_content', 9 );

function pr_toc_shortcode() {
	$post = get_post();
	if ( ! $post ) { return ''; }
	$res = pr_toc_process( apply_filters( 'the_content', $post->post_content ) );
	return pr_toc_render_html( $res['items'] );
}
add_shortcode( 'pr_toc', 'pr_toc_shortcode' );

function pr_toc_styles() {
	echo '<style>.pr-toc{border:1px solid var(--pr-border,#e5e7eb);background:var(--pr-surface,#f9fafb);padding:1rem 1.25rem;margin:1.25rem 0;border-radius:.5rem}.pr-toc-title{margin:0 0 .5rem;font-size:1rem;font-weight:700}.pr-toc-list,.pr-toc-sub{margin:.25rem 0 .25rem 1.1rem;padding:0}.pr-toc a{text-decoration:none}.pr-toc a:hover{text-decoration:underline}.pr-toc-sub{list-style:circle}html{scroll-behavior:smooth}</style>';
}
add_action( 'wp_head', 'pr_toc_styles', 97 );
