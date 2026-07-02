<?php
/**
 * Topical authority — cluster wiring around the /best/{category}/ pillar.
 *
 *   Pillar (already exists): /best/{category}/  — Top 7 roundup
 *   Spokes: every review post tagged with that category
 *
 * This module adds:
 *   1. An "In this guide" jump-list on the pillar listing all spoke reviews
 *      (beyond the top 7) so Google sees the full cluster from one page.
 *   2. A "Part of the {Category} guide" chip on every spoke linking back to
 *      the pillar — bi-directional, contextual, single link, no spam.
 *   3. ItemList JSON-LD covering the full cluster (not just the top 7),
 *      hooked to the existing hub head output.
 *
 * Everything is computed on-demand and memoized per request. No cron.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const PR_CLUSTER_TRANSIENT_PREFIX = 'pr_cluster_v1_';
const PR_CLUSTER_TTL              = 6 * HOUR_IN_SECONDS;

/**
 * Get every published review in a category, lightest fields only.
 * Cached per-term; invalidated when a review or term changes.
 *
 * @return array<int,array{id:int,title:string,url:string}>
 */
function pr_cluster_spokes( WP_Term $term ): array {
	$key    = PR_CLUSTER_TRANSIENT_PREFIX . $term->term_id;
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) { return $cached; }

	$q = get_posts( array(
		'post_type'      => 'review',
		'post_status'    => 'publish',
		'posts_per_page' => 200,
		'no_found_rows'  => true,
		'fields'         => 'ids',
		'tax_query'      => array( array(
			'taxonomy' => 'review_category',
			'field'    => 'term_id',
			'terms'    => $term->term_id,
		) ),
		'orderby' => 'date',
		'order'   => 'DESC',
	) );

	$rows = array();
	foreach ( $q as $pid ) {
		$rows[] = array(
			'id'    => (int) $pid,
			'title' => get_the_title( $pid ),
			'url'   => get_permalink( $pid ),
		);
	}
	set_transient( $key, $rows, PR_CLUSTER_TTL );
	return $rows;
}

function pr_cluster_flush_all() {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_pr_cluster_v1_%' OR option_name LIKE '_transient_timeout_pr_cluster_v1_%'" );
}
add_action( 'save_post_review',        'pr_cluster_flush_all' );
add_action( 'deleted_post',            'pr_cluster_flush_all' );
add_action( 'edited_review_category',  'pr_cluster_flush_all' );
add_action( 'delete_review_category',  'pr_cluster_flush_all' );

/**
 * 1. Render "In this guide" on the pillar, after the Top-7 list.
 *    Listed via wp_footer wouldn't be crawler-friendly; we splice into
 *    the_content of the hub via an action hook the hub template fires.
 *    Since the hub uses a custom template (not the_content), we render
 *    directly by hooking 'pr_hub_after_list' — see hubs.php.
 *
 * Hub template doesn't fire that hook yet, so we use output buffering:
 * append our block right before </main class="pr-hub"> via template_redirect
 * is fragile. Instead, we render via the `loop_end` action and a small
 * shortcode the hub already supports? It doesn't. Cleanest: filter the
 * hub's rendered output through 'pr_hub_render' if available, else fall
 * back to printing through wp_footer position-scoped to hub pages.
 */
add_action( 'wp_footer', 'pr_cluster_pillar_inject', 5 );
function pr_cluster_pillar_inject() {
	$slug = get_query_var( 'pr_best_category' );
	if ( ! $slug ) { return; }
	$term = function_exists( 'pr_hubs_get_term' ) ? pr_hubs_get_term( $slug ) : null;
	if ( ! $term ) { return; }

	$spokes = pr_cluster_spokes( $term );
	if ( count( $spokes ) <= 7 ) { return; } // top-7 already covers it

	echo '<section class="pr-cluster" aria-labelledby="pr-cluster-title">';
	echo '<h2 id="pr-cluster-title">' . esc_html__( 'In this guide', 'product-reviews' ) . '</h2>';
	echo '<p class="pr-cluster__lede">' . sprintf(
		esc_html__( 'Every %s review we have published, sorted newest first.', 'product-reviews' ),
		esc_html( strtolower( $term->name ) )
	) . '</p>';
	echo '<ul class="pr-cluster__list">';
	foreach ( $spokes as $row ) {
		echo '<li><a href="' . esc_url( $row['url'] ) . '">' . esc_html( $row['title'] ) . '</a></li>';
	}
	echo '</ul></section>';

	echo "<style>
		.pr-cluster{max-width:960px;margin:3rem auto;padding:0 1rem}
		.pr-cluster__lede{color:var(--pr-muted,#666);margin:.25rem 0 1rem}
		.pr-cluster__list{columns:2;column-gap:2rem;list-style:none;padding:0;margin:0}
		.pr-cluster__list li{break-inside:avoid;padding:.35rem 0;border-bottom:1px solid rgba(0,0,0,.06)}
		.pr-cluster__list a{text-decoration:none}
		.pr-cluster__list a:hover{text-decoration:underline}
		@media (max-width:640px){.pr-cluster__list{columns:1}}
	</style>";
}

/**
 * 2. "Part of the {Category} guide" chip on every spoke. Inserts at the
 *    top of the_content so it sits above the fold and counts as an
 *    in-content contextual link to the pillar.
 */
add_filter( 'the_content', 'pr_cluster_spoke_chip', 9 ); // before related-links at 20
function pr_cluster_spoke_chip( $content ) {
	if ( ! is_singular( 'review' ) || ! is_main_query() || ! in_the_loop() ) { return $content; }
	$post_id = get_the_ID();
	if ( ! $post_id ) { return $content; }
	$primary = function_exists( 'pr_primary_review_category' )
		? pr_primary_review_category( $post_id )
		: null;
	if ( ! $primary ) {
		$terms = get_the_terms( $post_id, 'review_category' );
		$primary = ( is_array( $terms ) && $terms ) ? $terms[0] : null;
	}
	if ( ! $primary || is_wp_error( $primary ) ) { return $content; }

	$pillar = home_url( '/best/' . $primary->slug . '/' );
	$chip = '<aside class="pr-cluster-chip" aria-label="' . esc_attr__( 'Buying guide', 'product-reviews' ) . '">'
		. '<span class="pr-cluster-chip__label">' . esc_html__( 'Part of:', 'product-reviews' ) . '</span> '
		. '<a class="pr-cluster-chip__link" href="' . esc_url( $pillar ) . '">'
		. sprintf( esc_html__( 'The best %s — our top 7 ranked', 'product-reviews' ), esc_html( strtolower( $primary->name ) ) )
		. ' →</a></aside>';

	return $chip . $content;
}

add_action( 'wp_head', 'pr_cluster_spoke_styles', 40 );
function pr_cluster_spoke_styles() {
	if ( ! is_singular( 'review' ) ) { return; }
	echo "<style>
		.pr-cluster-chip{display:inline-flex;align-items:center;gap:.5rem;padding:.5rem .85rem;margin:0 0 1.25rem;
			background:var(--pr-surface-2,#f4f6f8);border:1px solid rgba(0,0,0,.08);border-radius:999px;font-size:.9rem}
		.pr-cluster-chip__label{color:var(--pr-muted,#666);font-weight:500}
		.pr-cluster-chip__link{font-weight:600;text-decoration:none}
		.pr-cluster-chip__link:hover{text-decoration:underline}
	</style>\n";
}

/**
 * 3. Extra JSON-LD on the pillar: full ItemList covering every spoke
 *    (caps at 50 to stay sane), so search engines see the cluster's
 *    real depth, not just the 7 visible cards.
 */
add_action( 'wp_head', 'pr_cluster_pillar_jsonld', 5 );
function pr_cluster_pillar_jsonld() {
	$slug = get_query_var( 'pr_best_category' );
	if ( ! $slug ) { return; }
	$term = function_exists( 'pr_hubs_get_term' ) ? pr_hubs_get_term( $slug ) : null;
	if ( ! $term ) { return; }
	$spokes = pr_cluster_spokes( $term );
	if ( count( $spokes ) <= 7 ) { return; } // already covered by hub's ItemList

	$items = array();
	foreach ( array_slice( $spokes, 0, 50 ) as $i => $row ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'url'      => $row['url'],
			'name'     => $row['title'],
		);
	}
	$ld = array(
		'@context'        => 'https://schema.org',
		'@type'           => 'ItemList',
		'name'            => sprintf( 'All %s reviews', $term->name ),
		'url'             => home_url( '/best/' . $term->slug . '/' ) . '#guide',
		'numberOfItems'   => count( $items ),
		'itemListElement' => $items,
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}
