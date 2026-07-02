<?php
/**
 * Autopilot — monitor + update/refresh loop + change log helpers.
 *
 * Cron event `pr_monitor_tick` walks published review posts in batches,
 * re-fetches each tracked ASIN via PR_Source_Manager, records a fresh
 * snapshot into pr_facts, and enqueues an UPDATE job whenever
 * PR_Facts::detect_changes() reports a material diff.
 *
 * Update jobs are enqueued with payload { mode: 'update', review_post_id,
 * products: [...refreshed...] } and post_id on the queue row so they hand
 * straight to the writer (skipping discovery) and overwrite the existing
 * review post.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const PR_MONITOR_DEFAULTS = array(
	'interval'         => 'pr_6hour', // pr_1hour | pr_6hour | daily | weekly
	'posts_per_tick'   => 8,
	'min_age_hours'    => 6,   // don't re-check freshly published posts
	'cooldown_hours'   => 12,  // gap between rechecks for the same post
);

function pr_monitor_setting( string $key ) {
	$o = (array) get_option( 'pr_monitor', array() );
	return $o[ $key ] ?? PR_MONITOR_DEFAULTS[ $key ] ?? null;
}

/* ---------------------------------------------------------------- *
 * Cron registration
 * ---------------------------------------------------------------- */
add_filter( 'cron_schedules', function ( $s ) {
	if ( ! isset( $s['weekly'] ) ) {
		$s['weekly'] = array( 'interval' => WEEK_IN_SECONDS, 'display' => __( 'Weekly (Product Reviews)', 'product-reviews' ) );
	}
	return $s;
} );

function pr_monitor_install_cron(): void {
	$interval = (string) pr_monitor_setting( 'interval' );
	$valid    = array( 'pr_1hour', 'pr_6hour', 'daily', 'weekly' );
	if ( ! in_array( $interval, $valid, true ) ) { $interval = 'pr_6hour'; }
	$existing = wp_next_scheduled( 'pr_monitor_tick' );
	if ( $existing ) { wp_unschedule_event( $existing, 'pr_monitor_tick' ); }
	wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, $interval, 'pr_monitor_tick' );
}
add_action( 'init', 'pr_monitor_install_cron', 30 );
add_action( 'update_option_pr_monitor', 'pr_monitor_install_cron' );
add_action( 'switch_theme', function () {
	$ts = wp_next_scheduled( 'pr_monitor_tick' );
	if ( $ts ) { wp_unschedule_event( $ts, 'pr_monitor_tick' ); }
} );

/* ---------------------------------------------------------------- *
 * Tick
 * ---------------------------------------------------------------- */
add_action( 'pr_monitor_tick', 'pr_monitor_run' );

/**
 * @return array{checked:int, changed:int, jobs:int}
 */
function pr_monitor_run(): array {
	if ( ! pr_acquire_lock( 'monitor', 20 * MINUTE_IN_SECONDS ) ) {
		return array( 'checked' => 0, 'changed' => 0, 'jobs' => 0 );
	}
	try {
		$per_tick = max( 1, (int) pr_monitor_setting( 'posts_per_tick' ) );
		$min_age  = max( 0, (int) pr_monitor_setting( 'min_age_hours' ) );
		$cooldown = max( 1, (int) pr_monitor_setting( 'cooldown_hours' ) );

		$posts = pr_monitor_pick_posts( $per_tick, $min_age, $cooldown );
		$checked = 0; $changed = 0; $jobs = 0;

		foreach ( $posts as $post_id ) {
			$checked++;
			$products = (array) get_post_meta( (int) $post_id, '_yadfood_products', true );
			if ( empty( $products ) ) {
				update_post_meta( (int) $post_id, '_pr_monitor_last', gmdate( 'c' ) );
				continue;
			}
			$refreshed = pr_monitor_refetch( $products );
			if ( empty( $refreshed ) ) {
				update_post_meta( (int) $post_id, '_pr_monitor_last', gmdate( 'c' ) );
				continue;
			}

			// Record snapshots — also writes change_log rows automatically.
			foreach ( $refreshed as $p ) {
				PR_Facts::record_snapshot( (int) $post_id, $p, 'monitor' );
			}
			$diffs = PR_Facts::detect_changes( (int) $post_id, $refreshed );

			if ( ! empty( $diffs ) ) {
				$changed++;
				if ( pr_monitor_enqueue_update( (int) $post_id, $refreshed, $diffs ) ) {
					$jobs++;
				}
			}
			update_post_meta( (int) $post_id, '_pr_monitor_last', gmdate( 'c' ) );
		}

		update_option( 'pr_monitor_last_run',   gmdate( 'c' ), false );
		update_option( 'pr_monitor_last_stats', compact( 'checked', 'changed', 'jobs' ), false );
		return array( 'checked' => $checked, 'changed' => $changed, 'jobs' => $jobs );
	} finally {
		pr_release_lock( 'monitor' );
	}
}

/**
 * Pick the next batch of published review posts that are due for a recheck.
 *
 * @return int[] List of post IDs.
 */
function pr_monitor_pick_posts( int $limit, int $min_age_hours, int $cooldown_hours ): array {
	global $wpdb;
	$min_age_cut  = gmdate( 'Y-m-d H:i:s', time() - $min_age_hours  * HOUR_IN_SECONDS );
	$cooldown_cut = gmdate( 'Y-m-d H:i:s', time() - $cooldown_hours * HOUR_IN_SECONDS );
	$sql = $wpdb->prepare(
		"SELECT p.ID
		   FROM {$wpdb->posts} p
		   LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_pr_monitor_last'
		  WHERE p.post_type = 'review' AND p.post_status = 'publish'
		    AND p.post_date_gmt <= %s
		    AND ( m.meta_value IS NULL OR STR_TO_DATE( m.meta_value, %s ) <= %s )
		  ORDER BY COALESCE( STR_TO_DATE( m.meta_value, %s ), '0000-00-00' ) ASC, p.ID ASC
		  LIMIT %d",
		$min_age_cut, '%Y-%m-%dT%H:%i:%s+00:00', $cooldown_cut, '%Y-%m-%dT%H:%i:%s+00:00', $limit
	);
	$ids = $wpdb->get_col( $sql );
	return array_map( 'intval', (array) $ids );
}

/**
 * Re-fetch the freshest fact set for a list of stored products. Uses the
 * source chain via PR_Source_Manager::lookup() and merges fresh fields onto
 * the stored product dicts so editorial copy is preserved.
 *
 * @return array<int,array<string,mixed>>
 */
function pr_monitor_refetch( array $products ): array {
	$asins = array();
	foreach ( $products as $p ) {
		$a = isset( $p['asin'] ) ? PR_Dedup::normalize( (string) $p['asin'] ) : '';
		if ( $a !== '' ) { $asins[] = $a; }
	}
	if ( empty( $asins ) ) { return array(); }

	$by_asin = array();
	if ( method_exists( 'PR_Source_Manager', 'lookup' ) ) {
		$fresh = PR_Source_Manager::lookup( $asins );
		if ( ! is_wp_error( $fresh ) ) {
			$normalized = pr_discovery_normalize( $fresh );
			foreach ( $normalized as $row ) {
				$by_asin[ $row['asin'] ] = $row;
			}
		}
	}
	if ( empty( $by_asin ) ) { return array(); }

	$out = array();
	foreach ( $products as $p ) {
		$a = isset( $p['asin'] ) ? PR_Dedup::normalize( (string) $p['asin'] ) : '';
		if ( $a === '' || ! isset( $by_asin[ $a ] ) ) { continue; }
		// Merge fresh facts over stored ones, preserve editorial fields.
		$merged = array_merge( $p, array_filter( $by_asin[ $a ], static function ( $v ) {
			return $v !== '' && $v !== null && $v !== array();
		} ) );
		$out[] = $merged;
	}
	return $out;
}

/**
 * Enqueue (or reuse) an update job for this post. Returns true when a NEW
 * job was created, false if one is already pending.
 */
function pr_monitor_enqueue_update( int $post_id, array $refreshed, array $diffs ): bool {
	global $wpdb;
	$existing = $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM " . pr_table( 'run_queue' ) . "
		 WHERE post_id = %d AND state NOT IN (%s, %s) LIMIT 1",
		$post_id, PR_STATE_PUBLISHED, PR_STATE_NEEDS_REVIEW
	) );
	if ( $existing ) { return false; }

	$post    = get_post( $post_id );
	$keyword = $post ? (string) ( get_post_meta( $post_id, '_yadfood_keyword', true ) ?: $post->post_title ) : 'update';

	$payload = array(
		'mode'           => 'update',
		'keyword'        => $keyword,
		'review_post_id' => $post_id,
		'products'       => $refreshed,
		'diffs'          => $diffs,
	);
	$id = PR_Queue::enqueue( $keyword, null, $payload, 3 );
	if ( $id ) {
		$wpdb->update( pr_table( 'run_queue' ),
			array( 'post_id' => $post_id, 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => $id )
		);
	}
	return (bool) $id;
}

/* ---------------------------------------------------------------- *
 * Update-job routing lives in discovery.php (queued→writing branch).
 * ---------------------------------------------------------------- */


/* ---------------------------------------------------------------- *
 * Change-log query helpers.
 * ---------------------------------------------------------------- */

/** Recent change-log rows globally. */
function pr_changelog_recent( int $limit = 100, ?string $field = null ): array {
	global $wpdb;
	$tbl = pr_table( 'change_log' );
	$where = '';
	$args  = array();
	if ( $field ) {
		$where = 'WHERE field = %s';
		$args[] = $field;
	}
	$args[] = $limit;
	return (array) $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$tbl} {$where} ORDER BY id DESC LIMIT %d",
		$args
	), ARRAY_A );
}

/** Change-log rows for a specific review post. */
function pr_changelog_for_post( int $post_id, int $limit = 50 ): array {
	global $wpdb;
	return (array) $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM " . pr_table( 'change_log' ) . "
		 WHERE article_id = %d ORDER BY id DESC LIMIT %d",
		$post_id, $limit
	), ARRAY_A );
}
