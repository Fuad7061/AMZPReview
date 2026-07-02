<?php
/**
 * Internal linking — append a "Related reviews" block to every single
 * review post, and inject one contextual link inside the intro to a
 * sibling review when the title overlap is strong enough.
 *
 * Hooks the_content so the related block survives content edits and
 * shows up under any template that uses the_content() (which the theme's
 * single-review.php template does).
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const PR_RELATED_DEFAULTS = array(
	'count'        => 5,
	'inline_link'  => true,   // try to inject one inline link into the intro
	'min_overlap'  => 2,      // word-overlap threshold for the inline link
);

function pr_related_setting( string $key ) {
	$o = (array) get_option( 'pr_internal_links', array() );
	return $o[ $key ] ?? PR_RELATED_DEFAULTS[ $key ] ?? null;
}

add_filter( 'the_content', 'pr_internal_links_filter', 20 );

function pr_internal_links_filter( $content ) {
	if ( ! is_singular( 'review' ) || ! is_main_query() || ! in_the_loop() ) { return $content; }
	$post_id = get_the_ID();
	if ( ! $post_id ) { return $content; }

	$related = pr_related_reviews( $post_id, (int) pr_related_setting( 'count' ) );
	if ( pr_related_setting( 'inline_link' ) && ! empty( $related ) ) {
		$content = pr_inject_inline_link( $content, $post_id, $related );
	}
	if ( ! empty( $related ) ) {
		$content .= pr_render_related_block( $related );
	}
	return $content;
}

/**
 * Find related review posts. Strategy:
 *   1. Same primary review_category, excluding the current post.
 *   2. If not enough, fall back to any review in the same review_category set.
 *   3. Score remaining candidates by title word overlap, return the top N.
 *
 * @return WP_Post[]
 */
function pr_related_reviews( int $post_id, int $limit ): array {
	$limit = max( 1, min( 12, $limit ) );

	$primary = function_exists( 'pr_primary_review_category' ) ? pr_primary_review_category( $post_id ) : null;
	$term_ids = $primary ? array( $primary->term_id ) : wp_get_post_terms( $post_id, 'review_category', array( 'fields' => 'ids' ) );
	$term_ids = is_array( $term_ids ) ? array_map( 'intval', $term_ids ) : array();

	$candidates = array();
	if ( ! empty( $term_ids ) ) {
		$candidates = get_posts( array(
			'post_type'      => 'review',
			'posts_per_page' => $limit * 3,
			'post__not_in'   => array( $post_id ),
			'tax_query'      => array( array(
				'taxonomy' => 'review_category',
				'field'    => 'term_id',
				'terms'    => $term_ids,
			) ),
			'orderby' => 'date',
			'order'   => 'DESC',
		) );
	}
	if ( count( $candidates ) < $limit ) {
		$extra = get_posts( array(
			'post_type'      => 'review',
			'posts_per_page' => $limit * 2,
			'post__not_in'   => array_merge( array( $post_id ), wp_list_pluck( $candidates, 'ID' ) ),
			'orderby' => 'date',
			'order'   => 'DESC',
		) );
		$candidates = array_merge( $candidates, $extra );
	}
	if ( empty( $candidates ) ) { return array(); }

	$mine = pr_title_tokens( get_the_title( $post_id ) );
	$scored = array();
	foreach ( $candidates as $p ) {
		$theirs  = pr_title_tokens( get_the_title( $p ) );
		$overlap = count( array_intersect( $mine, $theirs ) );
		$scored[] = array( 'p' => $p, 'score' => $overlap );
	}
	usort( $scored, static function ( $a, $b ) { return $b['score'] <=> $a['score']; } );
	return array_slice( array_column( $scored, 'p' ), 0, $limit );
}

function pr_title_tokens( string $title ): array {
	$title = strtolower( wp_strip_all_tags( $title ) );
	$title = preg_replace( '/[^a-z0-9 ]+/', ' ', $title );
	$stop  = array( 'the','a','an','and','or','for','of','to','in','on','best','top','review','reviews','vs','with','our' );
	$words = array_filter( explode( ' ', $title ), static function ( $w ) use ( $stop ) {
		return $w !== '' && strlen( $w ) > 2 && ! in_array( $w, $stop, true );
	} );
	return array_values( array_unique( $words ) );
}

/** Render the "Related reviews" HTML block appended to the_content. */
function pr_render_related_block( array $posts ): string {
	if ( empty( $posts ) ) { return ''; }
	$html  = '<aside class="pr-related" aria-labelledby="pr-related-title">';
	$html .= '<h2 id="pr-related-title">' . esc_html__( 'Related reviews', 'product-reviews' ) . '</h2>';
	$html .= '<ul class="pr-related__list">';
	foreach ( $posts as $p ) {
		$thumb = get_the_post_thumbnail( $p, 'medium', array( 'loading' => 'lazy', 'class' => 'pr-related__img' ) );
		$html .= '<li class="pr-related__item">';
		$html .= '<a class="pr-related__link" href="' . esc_url( get_permalink( $p ) ) . '">';
		if ( $thumb ) { $html .= $thumb; }
		$html .= '<span class="pr-related__title">' . esc_html( get_the_title( $p ) ) . '</span>';
		$html .= '</a></li>';
	}
	$html .= '</ul></aside>';
	return $html;
}

/**
 * Inject one inline link into the first paragraph of the content. Finds
 * the related post whose title shares the most words with the current
 * post, then turns the first occurrence of that overlap word in the
 * content into a link. Skips if no usable overlap or anchor exists.
 */
function pr_inject_inline_link( string $content, int $post_id, array $related ): string {
	$mine = pr_title_tokens( get_the_title( $post_id ) );
	$best = null; $best_score = 0; $best_anchor = '';
	foreach ( $related as $p ) {
		$theirs = pr_title_tokens( get_the_title( $p ) );
		$shared = array_values( array_intersect( $theirs, $mine ) );
		if ( count( $shared ) > $best_score ) {
			$best_score  = count( $shared );
			$best        = $p;
			$best_anchor = $shared[0];
		}
	}
	if ( ! $best || $best_score < (int) pr_related_setting( 'min_overlap' ) || $best_anchor === '' ) {
		return $content;
	}
	$url   = esc_url( get_permalink( $best ) );
	$title = esc_attr( get_the_title( $best ) );
	$pattern = '/\b(' . preg_quote( $best_anchor, '/' ) . ')\b(?![^<]*>|[^<>]*<\/a>)/i';
	$replaced = preg_replace( $pattern, '<a href="' . $url . '" title="' . $title . '" class="pr-inline-link">$1</a>', $content, 1, $count );
	return $count > 0 && is_string( $replaced ) ? $replaced : $content;
}
