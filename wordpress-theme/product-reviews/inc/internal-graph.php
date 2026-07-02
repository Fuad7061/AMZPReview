<?php
/**
 * Internal linking graph — auto-link product and category mentions
 * inside review content to their canonical destinations.
 *
 *   Product name mention → /review/{slug}/
 *   Category name mention → /best/{category}/  (the top-7 roundup hub)
 *
 * Strict per-post link budget so it stays natural and never spammy.
 * Linking index is cached in a transient and rebuilt only when posts or
 * terms change — no cron, no per-request DB sweep.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const PR_GRAPH_TRANSIENT = 'pr_graph_index_v1';
const PR_GRAPH_TTL       = 12 * HOUR_IN_SECONDS;
const PR_GRAPH_BUDGET    = 4;   // max auto links injected per post
const PR_GRAPH_PER_TERM  = 1;   // max 1 link per target URL per post
const PR_GRAPH_MIN_LEN   = 4;   // ignore very short anchors

/**
 * Build / fetch the linking index.
 *
 *   [
 *     'products'   => [ ['anchor'=>'Acme X1 Pro', 'url'=>'/review/acme-x1-pro/', 'id'=>123], ... ],
 *     'categories' => [ ['anchor'=>'Mattresses',  'url'=>'/best/mattresses/',    'id'=>45 ], ... ],
 *   ]
 *
 * Longer anchors first so "Acme X1 Pro" matches before "Acme".
 */
function pr_graph_index(): array {
	$cached = get_transient( PR_GRAPH_TRANSIENT );
	if ( is_array( $cached ) ) { return $cached; }

	$products = array();
	$reviews  = get_posts( array(
		'post_type'      => 'review',
		'post_status'    => 'publish',
		'posts_per_page' => 500,
		'no_found_rows'  => true,
		'fields'         => 'ids',
	) );
	foreach ( $reviews as $rid ) {
		$title = wp_strip_all_tags( get_the_title( $rid ) );
		// Strip trailing " Review" / " — Review" noise so anchors look natural.
		$title = preg_replace( '/\s*[\-–—:]?\s*review\s*$/i', '', $title );
		$title = trim( $title );
		if ( strlen( $title ) < PR_GRAPH_MIN_LEN ) { continue; }
		$products[] = array(
			'anchor' => $title,
			'url'    => get_permalink( $rid ),
			'id'     => (int) $rid,
		);
	}

	$categories = array();
	$terms = get_terms( array(
		'taxonomy'   => 'review_category',
		'hide_empty' => true,
	) );
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $t ) {
			$name = trim( wp_strip_all_tags( $t->name ) );
			if ( strlen( $name ) < PR_GRAPH_MIN_LEN ) { continue; }
			$categories[] = array(
				'anchor' => $name,
				'url'    => home_url( '/best/' . $t->slug . '/' ),
				'id'     => (int) $t->term_id,
			);
		}
	}

	$sort_long = static function ( $a, $b ) {
		return strlen( $b['anchor'] ) <=> strlen( $a['anchor'] );
	};
	usort( $products,   $sort_long );
	usort( $categories, $sort_long );

	$index = array( 'products' => $products, 'categories' => $categories );
	set_transient( PR_GRAPH_TRANSIENT, $index, PR_GRAPH_TTL );
	return $index;
}

/**
 * Invalidate the index when reviews or category terms change.
 */
function pr_graph_flush() { delete_transient( PR_GRAPH_TRANSIENT ); }
add_action( 'save_post_review',          'pr_graph_flush' );
add_action( 'deleted_post',              'pr_graph_flush' );
add_action( 'created_review_category',   'pr_graph_flush' );
add_action( 'edited_review_category',    'pr_graph_flush' );
add_action( 'delete_review_category',    'pr_graph_flush' );

/**
 * Walk the content and replace the first non-linked occurrence of each
 * anchor with an <a>. Respects the per-post budget and skips existing
 * links, headings, scripts, code blocks, and shortcode payloads.
 */
function pr_graph_apply( string $content, int $post_id ): string {
	if ( $content === '' ) { return $content; }
	$index = pr_graph_index();
	$current_url = get_permalink( $post_id );

	$slots = array();
	// Categories first (broader anchors), then products (specific names).
	foreach ( $index['categories'] as $row ) { $slots[] = $row + array( 'type' => 'cat' ); }
	foreach ( $index['products']   as $row ) {
		if ( (int) $row['id'] === $post_id ) { continue; } // never self-link
		$slots[] = $row + array( 'type' => 'prod' );
	}

	$used_urls = array();
	$budget    = PR_GRAPH_BUDGET;

	foreach ( $slots as $slot ) {
		if ( $budget <= 0 ) { break; }
		if ( $slot['url'] === $current_url ) { continue; }
		if ( ( $used_urls[ $slot['url'] ] ?? 0 ) >= PR_GRAPH_PER_TERM ) { continue; }

		$replaced = pr_graph_replace_first( $content, $slot['anchor'], $slot['url'], $slot['type'] );
		if ( $replaced !== null ) {
			$content = $replaced;
			$used_urls[ $slot['url'] ] = ( $used_urls[ $slot['url'] ] ?? 0 ) + 1;
			$budget--;
		}
	}
	return $content;
}

/**
 * Replace the first eligible occurrence of $anchor in $content with a link.
 * Returns the new content or null when no safe replacement was possible.
 *
 * Eligibility: not inside <a>, <h1-6>, <code>, <pre>, an HTML tag, or a
 * shortcode bracket. We tokenize on '<' so the regex only fires inside
 * text nodes, not attributes.
 */
function pr_graph_replace_first( string $content, string $anchor, string $url, string $type ): ?string {
	$class   = 'pr-auto-link pr-auto-link--' . $type;
	$pattern = '/\b' . preg_quote( $anchor, '/' ) . '\b/i';

	$parts = preg_split( '/(<[^>]+>)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
	if ( ! is_array( $parts ) ) { return null; }

	$depth_skip = 0; // inside <a>, <h1-6>, <code>, <pre>, [shortcode]
	$skip_re    = '/^<\s*(a|h[1-6]|code|pre)\b/i';
	$end_re     = '/^<\s*\/\s*(a|h[1-6]|code|pre)\s*>/i';

	for ( $i = 0, $n = count( $parts ); $i < $n; $i++ ) {
		$chunk = $parts[ $i ];
		if ( $chunk === '' ) { continue; }
		if ( $chunk[0] === '<' ) {
			if ( preg_match( $end_re,  $chunk ) ) { $depth_skip = max( 0, $depth_skip - 1 ); continue; }
			if ( preg_match( $skip_re, $chunk ) ) { $depth_skip++; continue; }
			continue; // other tag, leave untouched
		}
		if ( $depth_skip > 0 ) { continue; }
		// Skip shortcode payloads.
		if ( strpos( $chunk, '[' ) !== false && preg_match( '/\[[a-z0-9_\-]+/i', $chunk ) ) { continue; }

		$new = preg_replace_callback( $pattern, static function ( $m ) use ( $url, $class, $anchor ) {
			return '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '" title="' . esc_attr( $anchor ) . '">' . $m[0] . '</a>';
		}, $chunk, 1, $count );
		if ( $count > 0 && is_string( $new ) ) {
			$parts[ $i ] = $new;
			return implode( '', $parts );
		}
	}
	return null;
}

/**
 * Hook into the_content. Runs after the existing internal-links filter
 * (priority 20) but before wpautop (which is at 10 but on a different
 * stack); we use priority 22 to stay deterministic.
 */
add_filter( 'the_content', 'pr_graph_filter', 22 );
function pr_graph_filter( $content ) {
	if ( ! is_singular( 'review' ) || ! is_main_query() || ! in_the_loop() ) { return $content; }
	$post_id = get_the_ID();
	if ( ! $post_id ) { return $content; }
	return pr_graph_apply( (string) $content, (int) $post_id );
}

/**
 * Minimal style hook so auto links are visually distinct but not loud.
 */
add_action( 'wp_head', static function () {
	if ( ! is_singular( 'review' ) ) { return; }
	echo "<style>.pr-auto-link{border-bottom:1px dotted currentColor;text-decoration:none}.pr-auto-link:hover{border-bottom-style:solid}</style>\n";
}, 30 );
