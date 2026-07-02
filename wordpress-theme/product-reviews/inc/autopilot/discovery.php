<?php
/**
 * Autopilot — discovery state handler.
 *
 * `discovering` state:
 *   1. Resolve the job's keyword via PR_Source_Manager (Lambda first).
 *   2. Normalize results into the shared product dict shape.
 *   3. Partition ASINs through PR_Dedup.
 *      - All-novel       → stash payload, advance to researching.
 *      - Has duplicates  → mark them seen (refresh last_seen). If the
 *                          existing review's facts diverge materially,
 *                          spawn an UPDATE job for that review post.
 *      - All-duplicate, no changes → finish job as `monitoring`.
 *   4. Empty driver response → fail with retryable error.
 *
 * The handler ONLY touches discovery — research/writing happen in later
 * milestones via their own state handlers.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'pr_handle_state_' . PR_STATE_QUEUED, function ( $job ) {
	$payload = pr_job_payload( $job );
	if ( ( $payload['mode'] ?? '' ) === 'update' ) {
		// Update jobs are seeded with refreshed products; skip discovery and
		// hand straight to the writer. The monitor module spawns these.
		if ( empty( $payload['products'] ) ) {
			PR_Queue::fail( (int) $job['id'], PR_STATE_QUEUED, 'update job has no products' );
			return;
		}
		PR_Queue::transition( (int) $job['id'], PR_STATE_QUEUED, PR_STATE_WRITING, 'update job → writer (skipped discovery)' );
		return;
	}
	PR_Queue::transition( (int) $job['id'], PR_STATE_QUEUED, PR_STATE_DISCOVERING, 'starting discovery' );
} );

add_action( 'pr_handle_state_' . PR_STATE_DISCOVERING, function ( $job ) {
	$id      = (int) $job['id'];
	$keyword = (string) $job['keyword'];
	$cat_id  = $job['category_id'] ? (int) $job['category_id'] : null;
	$started = microtime( true );

	$res = PR_Source_Manager::search( $keyword, 1, (int) apply_filters( 'pr_discovery_count', 10 ) );
	$ms  = (int) ( ( microtime( true ) - $started ) * 1000 );

	if ( is_wp_error( $res ) ) {
		PR_Queue::fail( $id, PR_STATE_DISCOVERING, 'driver error: ' . $res->get_error_message() );
		return;
	}
	$products = pr_discovery_normalize( $res );
	if ( empty( $products ) ) {
		PR_Queue::fail( $id, PR_STATE_DISCOVERING, 'driver returned 0 products' );
		return;
	}
	$asins = array_filter( array_map( static function ( $p ) {
		return isset( $p['asin'] ) ? PR_Dedup::normalize( (string) $p['asin'] ) : '';
	}, $products ) );

	$part = PR_Dedup::partition( $asins );

	// Refresh last_seen for every duplicate; capture change-detection opportunities.
	$update_jobs = array();
	foreach ( $part['duplicates'] as $asin => $review_post_id ) {
		PR_Dedup::mark_seen( $asin, $cat_id, $review_post_id ?: null );
		if ( $review_post_id ) {
			$matching = array_values( array_filter( $products, static function ( $p ) use ( $asin ) {
				return isset( $p['asin'] ) && PR_Dedup::normalize( (string) $p['asin'] ) === $asin;
			} ) );
			if ( ! empty( $matching ) ) {
				$diffs = PR_Facts::detect_changes( $review_post_id, $matching );
				if ( ! empty( $diffs ) ) {
					$update_jobs[ $review_post_id ] = $diffs;
				}
			}
		}
	}

	// Spawn one UPDATE job per affected review post.
	foreach ( $update_jobs as $review_post_id => $diffs ) {
		PR_Queue::enqueue( $keyword, $cat_id, array(
			'mode'           => 'update',
			'review_post_id' => (int) $review_post_id,
			'diffs'          => $diffs,
		), 3 );
	}

	// All-duplicate path with no diffs → close this job out.
	if ( empty( $part['novel'] ) ) {
		PR_Queue::transition(
			$id, PR_STATE_DISCOVERING, PR_STATE_MONITORING,
			sprintf( 'all %d ASINs already seen%s', count( $asins ), $update_jobs ? '; spawned ' . count( $update_jobs ) . ' update job(s)' : '' ),
			'lambda', $ms
		);
		return;
	}

	// Novel ASINs → mark seen (without post id yet) and pass to research.
	$novel_products = array_values( array_filter( $products, static function ( $p ) use ( $part ) {
		$a = isset( $p['asin'] ) ? PR_Dedup::normalize( (string) $p['asin'] ) : '';
		return $a !== '' && in_array( $a, $part['novel'], true );
	} ) );
	foreach ( $part['novel'] as $asin ) {
		PR_Dedup::mark_seen( $asin, $cat_id, null );
	}

	pr_queue_set_payload( $id, array(
		'keyword'        => $keyword,
		'category_id'    => $cat_id,
		'novel_asins'    => $part['novel'],
		'dup_count'      => count( $part['duplicates'] ),
		'products'       => $novel_products,
		'driver_latency' => $ms,
	) );
	PR_Queue::transition(
		$id, PR_STATE_DISCOVERING, PR_STATE_RESEARCHING,
		sprintf( '%d novel / %d duplicates', count( $part['novel'] ), count( $part['duplicates'] ) ),
		'lambda', $ms
	);
} );

/* ---------------------------------------------------------------- *
 * Helpers
 * ---------------------------------------------------------------- */

/**
 * Persist a payload back to the queue row (keeps PR_Queue::transition lean).
 */
function pr_queue_set_payload( int $id, array $payload ): void {
	global $wpdb;
	$wpdb->update( pr_table( 'run_queue' ),
		array( 'payload' => wp_json_encode( $payload ), 'updated_at' => current_time( 'mysql', true ) ),
		array( 'id' => $id )
	);
}

/**
 * Normalize driver output into the canonical product dict.
 *
 * Accepts either a list of dicts (Lambda v1) or a wrapper { items: [...] }.
 *
 * @return array<int,array<string,mixed>>
 */
function pr_discovery_normalize( $raw ): array {
	if ( is_array( $raw ) && isset( $raw['items'] ) && is_array( $raw['items'] ) ) {
		$raw = $raw['items'];
	}
	if ( ! is_array( $raw ) ) { return array(); }

	$out = array();
	foreach ( $raw as $p ) {
		if ( ! is_array( $p ) ) { continue; }
		$asin = (string) ( $p['asin'] ?? $p['ASIN'] ?? $p['id'] ?? '' );
		$asin = PR_Dedup::normalize( $asin );
		if ( $asin === '' ) { continue; }
		$out[] = array(
			'asin'         => $asin,
			'title'        => (string)  ( $p['title']        ?? $p['name']        ?? '' ),
			'brand'        => (string)  ( $p['brand']        ?? $p['manufacturer']?? '' ),
			'image'        => (string)  ( $p['image']        ?? $p['image_url']   ?? $p['thumbnail'] ?? '' ),
			'url'          => (string)  ( $p['url']          ?? $p['link']        ?? '' ),
			'price'        => isset( $p['price'] ) ? (float) preg_replace( '/[^0-9.]/', '', (string) $p['price'] ) : null,
			'currency'     => (string)  ( $p['currency']     ?? 'USD' ),
			'rating'       => isset( $p['rating'] ) ? (float) $p['rating'] : null,
			'review_count' => isset( $p['review_count'] ) ? (int) $p['review_count'] : ( isset( $p['reviews'] ) ? (int) $p['reviews'] : null ),
			'availability' => (string)  ( $p['availability'] ?? '' ),
			'bullets'      => isset( $p['bullets'] ) && is_array( $p['bullets'] ) ? array_values( $p['bullets'] ) : array(),
		);
	}
	return $out;
}
