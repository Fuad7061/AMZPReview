<?php
/**
 * Autopilot — daily harvester.
 *
 * Walks every ENABLED Amazon category, expands its seed keywords, and
 * enqueues a discovery job per keyword (skipping ones already queued in the
 * recent window). Designed to be idempotent — running it twice in the same
 * day won't double-queue.
 *
 * Trigger:
 *   - `pr_harvester_daily` cron event (added at activation, fired by WP-Cron).
 *   - Manual button on the Categories admin page (handled there).
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_filter( 'cron_schedules', function ( $s ) {
	if ( ! isset( $s['pr_daily'] ) ) {
		$s['pr_daily'] = array( 'interval' => DAY_IN_SECONDS, 'display' => __( 'Daily (Product Reviews harvester)', 'product-reviews' ) );
	}
	return $s;
} );

function pr_harvester_install_cron(): void {
	if ( ! wp_next_scheduled( 'pr_harvester_daily' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'pr_daily', 'pr_harvester_daily' );
	}
}
add_action( 'init', 'pr_harvester_install_cron', 25 );
add_action( 'switch_theme', function () {
	$ts = wp_next_scheduled( 'pr_harvester_daily' );
	if ( $ts ) { wp_unschedule_event( $ts, 'pr_harvester_daily' ); }
} );

add_action( 'pr_harvester_daily', 'pr_harvester_run' );

/**
 * Walk every enabled category and queue novel keyword jobs.
 *
 * @return array{queued:int, skipped:int, categories:int}
 */
function pr_harvester_run(): array {
	if ( ! pr_acquire_lock( 'harvester', 30 * MINUTE_IN_SECONDS ) ) {
		return array( 'queued' => 0, 'skipped' => 0, 'categories' => 0 );
	}
	try {
		$state   = pr_categories_state();
		$catalog = pr_categories_catalog();
		$queued = 0; $skipped = 0; $cats = 0;

		foreach ( $catalog as $row ) {
			$slug = (string) ( $row['slug'] ?? '' );
			if ( $slug === '' || empty( $state[ $slug ] ) ) { continue; }

			$term = get_term_by( 'slug', $slug, 'review_category' );
			if ( ! $term ) { continue; } // not imported yet
			$term_id  = (int) $term->term_id;
			$schedule = (string) get_term_meta( $term_id, PR_CAT_META_SCHEDULE, true ) ?: 'daily';
			if ( $schedule === 'off' ) { continue; }
			if ( ! pr_harvester_due( $term_id, $schedule ) ) { continue; }

			$keywords = (array) get_term_meta( $term_id, PR_CAT_META_SEED_KEYWORDS, true );
			if ( empty( $keywords ) ) { continue; }
			$cats++;

			foreach ( $keywords as $kw ) {
				$kw = trim( (string) $kw );
				if ( $kw === '' ) { continue; }
				if ( pr_harvester_recently_queued( $kw, $term_id ) ) { $skipped++; continue; }
				pr_enqueue_keyword( $kw, $term_id );
				$queued++;
			}
			update_term_meta( $term_id, 'pr_last_harvested_at', gmdate( 'c' ) );
		}

		update_option( 'pr_harvester_last_run', gmdate( 'c' ), false );
		update_option( 'pr_harvester_last_stats', compact( 'queued', 'skipped', 'cats' ), false );
		return array( 'queued' => $queued, 'skipped' => $skipped, 'categories' => $cats );
	} finally {
		pr_release_lock( 'harvester' );
	}
}

/** Has this exact (keyword, category) been queued in the last 20 hours? */
function pr_harvester_recently_queued( string $keyword, int $term_id ): bool {
	global $wpdb;
	$cutoff = gmdate( 'Y-m-d H:i:s', time() - 20 * HOUR_IN_SECONDS );
	$row = $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM " . pr_table( 'run_queue' ) . "
		 WHERE keyword = %s AND ( category_id = %d OR (%d = 0 AND category_id IS NULL) )
		   AND updated_at >= %s
		 LIMIT 1",
		$keyword, $term_id, $term_id, $cutoff
	) );
	return (bool) $row;
}

/** Is this category due according to its per-category schedule? */
function pr_harvester_due( int $term_id, string $schedule ): bool {
	$last = (string) get_term_meta( $term_id, 'pr_last_harvested_at', true );
	if ( $last === '' ) { return true; }
	$last_ts  = strtotime( $last ) ?: 0;
	$interval = array(
		'daily'        => DAY_IN_SECONDS,
		'every_3_days' => 3 * DAY_IN_SECONDS,
		'weekly'       => WEEK_IN_SECONDS,
	)[ $schedule ] ?? DAY_IN_SECONDS;
	return ( time() - $last_ts ) >= ( $interval - 10 * MINUTE_IN_SECONDS );
}
