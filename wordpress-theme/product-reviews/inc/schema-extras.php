<?php
/**
 * Schema extras — Speakable hardening.
 *
 * The full HowTo editor, visible block, and HowTo JSON-LD now live in
 * inc/howto.php. Keeping HowTo logic in one canonical module prevents duplicate
 * callbacks/redeclaration fatals while preserving this file's supplemental
 * SpeakableSpecification output.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Emit a SpeakableSpecification JSON-LD node on single reviews.
 *
 * Uses cssSelector values that already exist in theme templates; if a selector
 * is missing on a given page, search engines simply ignore it.
 */
if ( ! function_exists( 'pr_speakable_jsonld' ) ) {
function pr_speakable_jsonld(): void {
	if ( ! is_singular( 'review' ) ) {
		return;
	}
	$post_id = (int) get_queried_object_id();
	$url     = get_permalink( $post_id );
	if ( ! is_string( $url ) || '' === $url ) {
		return;
	}

	$ld = array(
		'@context'  => 'https://schema.org',
		'@type'     => 'WebPage',
		'url'       => $url,
		'speakable' => array(
			'@type'       => 'SpeakableSpecification',
			'cssSelector' => array( '.pr-tldr', '.pr-intro-brief', '.pr-verdict', '.entry-title' ),
		),
	);

	echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}
}
add_action( 'wp_head', 'pr_speakable_jsonld', 56 );
