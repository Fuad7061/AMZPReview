<?php
/**
 * Aggregate score consolidation.
 *
 * Computes a canonical aggregate score per review from product scores and
 * exposes it via meta, a small "score summary" block, and JSON-LD Review
 * aggregateRating. Every output is no-op safe when data is missing — the
 * page design remains identical when there are no products or no scores.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collect numeric product scores from the post's products meta.
 * Returns array of floats in 0..10 (best effort), empty if none.
 */
function pr_agg_collect_scores( $post_id ) {
	$products = get_post_meta( $post_id, '_pr_products', true );
	if ( ! is_array( $products ) || empty( $products ) ) {
		return array();
	}

	$scores = array();
	foreach ( $products as $p ) {
		if ( ! is_array( $p ) ) {
			continue;
		}
		$s = null;
		foreach ( array( 'score', 'rating', 'overall' ) as $k ) {
			if ( isset( $p[ $k ] ) && is_numeric( $p[ $k ] ) ) {
				$s = (float) $p[ $k ];
				break;
			}
		}
		if ( $s === null ) {
			continue;
		}
		// Normalize: accept 0..5 or 0..10; coerce to 0..10.
		if ( $s > 0 && $s <= 5 ) {
			$s = $s * 2;
		}
		if ( $s < 0 ) { $s = 0; }
		if ( $s > 10 ) { $s = 10; }
		$scores[] = round( $s, 1 );
	}
	return $scores;
}

/**
 * Compute aggregate stats. Returns array{avg,best,worst,count} or null.
 */
function pr_agg_stats( $post_id ) {
	$scores = pr_agg_collect_scores( $post_id );
	if ( empty( $scores ) ) {
		return null;
	}
	$count = count( $scores );
	$avg   = array_sum( $scores ) / $count;
	return array(
		'avg'   => round( $avg, 1 ),
		'best'  => max( $scores ),
		'worst' => min( $scores ),
		'count' => $count,
	);
}

/**
 * Persist computed aggregate to post meta on save (best effort, no failure).
 */
function pr_agg_save_meta( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( get_post_type( $post_id ) !== 'review' ) {
		return;
	}
	$stats = pr_agg_stats( $post_id );
	if ( ! $stats ) {
		delete_post_meta( $post_id, '_pr_agg_score' );
		delete_post_meta( $post_id, '_pr_agg_count' );
		return;
	}
	update_post_meta( $post_id, '_pr_agg_score', $stats['avg'] );
	update_post_meta( $post_id, '_pr_agg_count', $stats['count'] );
}
add_action( 'save_post', 'pr_agg_save_meta', 30 );

/**
 * Small "score summary" block injected after content. Empty when no data.
 */
function pr_agg_render( $content ) {
	if ( ! is_singular( 'review' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$stats = pr_agg_stats( get_the_ID() );
	if ( ! $stats ) {
		return $content;
	}
	$html  = '<aside class="pr-agg-score" aria-label="' . esc_attr__( 'Score summary', 'product-reviews' ) . '">';
	$html .= '<div class="pr-agg-row">';
	$html .= '<span class="pr-agg-pill pr-agg-avg"><strong>' . esc_html( number_format_i18n( $stats['avg'], 1 ) ) . '</strong><small>/10</small></span>';
	$html .= '<span class="pr-agg-meta">' . sprintf(
		/* translators: 1: count, 2: best, 3: worst */
		esc_html__( 'Across %1$d picks · best %2$s · lowest %3$s', 'product-reviews' ),
		(int) $stats['count'],
		esc_html( number_format_i18n( $stats['best'], 1 ) ),
		esc_html( number_format_i18n( $stats['worst'], 1 ) )
	) . '</span>';
	$html .= '</div></aside>';
	return $content . $html;
}
add_filter( 'the_content', 'pr_agg_render', 12 );

/**
 * Inline CSS. Tiny, scoped, design-safe.
 */
function pr_agg_styles() {
	if ( ! is_singular( 'review' ) ) {
		return;
	}
	$css = '.pr-agg-score{margin:1.5rem 0;padding:.9rem 1rem;border:1px solid rgba(0,0,0,.08);border-radius:.6rem;background:rgba(0,0,0,.02);}'
		. '.pr-agg-row{display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;}'
		. '.pr-agg-pill{display:inline-flex;align-items:baseline;gap:.15rem;padding:.35rem .6rem;border-radius:.4rem;background:#0f766e;color:#fff;font-weight:600;}'
		. '.pr-agg-pill small{opacity:.8;font-weight:500;}'
		. '.pr-agg-meta{color:inherit;opacity:.8;font-size:.95em;}';
	echo '<style id="pr-agg-score-css">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wp_head', 'pr_agg_styles', 90 );

/**
 * JSON-LD aggregateRating on the Review node. No-op when no scores.
 * Emits a standalone Review with aggregateRating to complement existing schema.
 */
function pr_agg_jsonld() {
	if ( ! is_singular( 'review' ) ) {
		return;
	}
	$post_id = get_the_ID();
	$stats   = pr_agg_stats( $post_id );
	if ( ! $stats ) {
		return;
	}
	$data = array(
		'@context'        => 'https://schema.org',
		'@type'           => 'Review',
		'name'            => get_the_title( $post_id ),
		'url'             => get_permalink( $post_id ),
		'reviewRating'    => array(
			'@type'       => 'Rating',
			'ratingValue' => $stats['avg'],
			'bestRating'  => 10,
			'worstRating' => 0,
		),
		'aggregateRating' => array(
			'@type'       => 'AggregateRating',
			'ratingValue' => $stats['avg'],
			'bestRating'  => 10,
			'worstRating' => 0,
			'reviewCount' => $stats['count'],
		),
	);
	echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wp_head', 'pr_agg_jsonld', 81 );
