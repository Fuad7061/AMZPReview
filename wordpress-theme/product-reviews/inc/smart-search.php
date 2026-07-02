<?php
/**
 * Smart-search query normalizer — PHP port of src/lib/utils.ts.
 *
 * Strips noise words ("best", "review", "for men", "in 2026", etc.) and
 * normalizes the query before it hits WP_Query / the AI generator / PA-API.
 *
 * @package YadFoodReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalize a raw search string into a tight product keyword.
 *
 * @param string $q
 * @return string
 */
function yadfood_normalize_query( $q ) {
	$q = (string) $q;
	$q = strtolower( $q );
	$q = preg_replace( '/[?!.,;:\'"()\[\]]/u', ' ', $q );
	$q = preg_replace( '/\s+/u', ' ', $q );
	$q = trim( $q );

	// Drop year tokens (2020–2099).
	$q = preg_replace( '/\b(20\d{2})\b/u', '', $q );

	// Noise words / phrases.
	$noise = array(
		'what is the', 'what is', 'which is the', 'which is',
		'top 10', 'top ten', 'top 5', 'top five',
		'best of', 'best',
		'review', 'reviews', 'rating', 'ratings', 'guide', 'buying guide',
		'recommendation', 'recommendations', 'recommended',
		'comparison', 'comparisons', 'compared',
		'in', 'for', 'to', 'a', 'an', 'the', 'my', 'me',
		'cheap', 'cheapest', 'budget', 'affordable',
		'this year', 'right now', 'today',
		'should i buy', 'should i', 'how to choose', 'how to pick',
		'how to', 'tips',
	);

	// Keep audience modifiers as a single token so we can re-attach later if useful.
	// (For now we drop them; the AI prompt can re-add the audience.)
	$audience = array( 'for men', 'for women', 'for kids', 'for girls', 'for boys', 'for beginners', 'for pros', 'for seniors' );

	$tokens = explode( ' ', $q );
	$out    = array();
	$skip   = 0;
	for ( $i = 0; $i < count( $tokens ); $i++ ) {
		if ( $skip > 0 ) { $skip--; continue; }

		// Try multi-word phrases first (greedy, up to 3 words).
		$matched = false;
		for ( $len = 3; $len >= 1; $len-- ) {
			$slice = implode( ' ', array_slice( $tokens, $i, $len ) );
			if ( in_array( $slice, $noise, true ) || in_array( $slice, $audience, true ) ) {
				$skip = $len - 1;
				$matched = true;
				break;
			}
		}
		if ( ! $matched ) {
			$out[] = $tokens[ $i ];
		}
	}

	$result = trim( implode( ' ', $out ) );
	$result = preg_replace( '/\s+/u', ' ', $result );

	// Final safety: if we stripped everything, fall back to original.
	if ( '' === $result ) {
		return trim( strtolower( $q ) );
	}
	return $result;
}

/**
 * Turn a normalized keyword into a nice review title.
 *  "coffee grinder" → "The 10 Best Coffee Grinders of 2026"
 */
function yadfood_make_title( $keyword, $count = 10 ) {
	$keyword = trim( $keyword );
	if ( '' === $keyword ) {
		return '';
	}
	// Title-case each word.
	$keyword = ucwords( $keyword );
	// Naive plural: don't double-pluralize.
	if ( ! preg_match( '/(s|x|z)$/i', $keyword ) ) {
		$keyword .= 's';
	}
	$year = date( 'Y' );
	return sprintf( 'The %d Best %s of %s', $count, $keyword, $year );
}

/**
 * Hook into native WP search: also search the review CPT's keyword meta,
 * and rewrite the query to the normalized form for better matches.
 */
function yadfood_filter_search_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return;
	}
	$raw  = (string) $query->get( 's' );
	$norm = yadfood_normalize_query( $raw );
	if ( $norm && $norm !== $raw ) {
		$query->set( 's', $norm );
		// Expose original to templates if needed.
		$query->set( 'yadfood_raw_search', $raw );
	}
	$query->set( 'post_type', array( 'review', 'post', 'page' ) );
}
add_action( 'pre_get_posts', 'yadfood_filter_search_query' );
