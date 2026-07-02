<?php
/**
 * Autopilot — orchestrator tick + worker + watchdog.
 *
 * One tick handler (pr_autopilot_tick) runs on a configurable WP-Cron
 * schedule. Settings → "Tick interval" picks the cadence (1m / 5m / 15m
 * / 30m / 1h / 6h / 24h). A distributed lock prevents overlap.
 *
 * The handlers below are SKELETONS that just transition state and log.
 * Milestones 4–7 fill in the real research / fact-checking / writing
 * implementations behind these handler hooks.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ---------------------------------------------------------------- *
 * Schedules — register custom intervals so admins can pick freely. *
 * ---------------------------------------------------------------- */
add_filter( 'cron_schedules', function ( $s ) {
	$add = array(
		'pr_1min'  => array( 'interval' => 60,        'display' => __( 'Every minute (Product Reviews)', 'product-reviews' ) ),
		'pr_5min'  => array( 'interval' => 5 * 60,    'display' => __( 'Every 5 minutes (Product Reviews)', 'product-reviews' ) ),
		'pr_15min' => array( 'interval' => 15 * 60,   'display' => __( 'Every 15 minutes (Product Reviews)', 'product-reviews' ) ),
		'pr_30min' => array( 'interval' => 30 * 60,   'display' => __( 'Every 30 minutes (Product Reviews)', 'product-reviews' ) ),
		'pr_1hour' => array( 'interval' => HOUR_IN_SECONDS,     'display' => __( 'Hourly (Product Reviews)', 'product-reviews' ) ),
		'pr_6hour' => array( 'interval' => 6 * HOUR_IN_SECONDS, 'display' => __( 'Every 6 hours (Product Reviews)', 'product-reviews' ) ),
	);
	foreach ( $add as $k => $v ) {
		if ( ! isset( $s[ $k ] ) ) { $s[ $k ] = $v; }
	}
	return $s;
} );

function pr_autopilot_schedule(): string {
	$valid = array( 'pr_1min', 'pr_5min', 'pr_15min', 'pr_30min', 'pr_1hour', 'pr_6hour', 'daily' );
	$s     = (string) get_option( 'pr_autopilot_interval', 'pr_15min' );
	return in_array( $s, $valid, true ) ? $s : 'pr_15min';
}

function pr_autopilot_install_cron(): void {
	$enabled  = (string) get_option( 'pr_autopilot_enabled', '0' ) === '1';
	$existing = wp_next_scheduled( 'pr_autopilot_tick' );
	if ( $enabled ) {
		if ( $existing ) { wp_unschedule_event( $existing, 'pr_autopilot_tick' ); }
		wp_schedule_event( time() + 30, pr_autopilot_schedule(), 'pr_autopilot_tick' );
	} elseif ( $existing ) {
		wp_unschedule_event( $existing, 'pr_autopilot_tick' );
	}
}
add_action( 'init', 'pr_autopilot_install_cron', 20 );
add_action( 'update_option_pr_autopilot_enabled', 'pr_autopilot_install_cron' );
add_action( 'update_option_pr_autopilot_interval', function () {
	$ts = wp_next_scheduled( 'pr_autopilot_tick' );
	if ( $ts ) { wp_unschedule_event( $ts, 'pr_autopilot_tick' ); }
	pr_autopilot_install_cron();
} );
add_action( 'switch_theme', function () {
	$ts = wp_next_scheduled( 'pr_autopilot_tick' );
	if ( $ts ) { wp_unschedule_event( $ts, 'pr_autopilot_tick' ); }
} );

/* ---------------------------------------------------------------- *
 * Distributed lock                                                  *
 * ---------------------------------------------------------------- */
function pr_acquire_lock( string $name, int $ttl = 240 ): bool {
	$key = 'pr_lock_' . $name;
	$now = time();
	$cur = (int) get_option( $key, 0 );
	if ( $cur > $now ) { return false; }
	// Best-effort CAS via update_option.
	update_option( $key, $now + $ttl, false );
	$re = (int) get_option( $key, 0 );
	return $re === $now + $ttl;
}
function pr_release_lock( string $name ): void {
	delete_option( 'pr_lock_' . $name );
}

/* ---------------------------------------------------------------- *
 * Tick handler                                                      *
 * ---------------------------------------------------------------- */
add_action( 'pr_autopilot_tick', 'pr_autopilot_run_tick' );
function pr_autopilot_run_tick(): void {
	if ( ! pr_acquire_lock( 'autopilot_tick', 240 ) ) { return; }
	try {
		pr_autopilot_watchdog();

		// Process up to N jobs per tick (cap so a tick never runs forever).
		$cap = (int) apply_filters( 'pr_autopilot_jobs_per_tick', 5 );
		for ( $i = 0; $i < $cap; $i++ ) {
			$job = PR_Queue::claim_next();
			if ( ! $job ) { break; }
			pr_autopilot_process_job( $job );
		}
	} finally {
		pr_release_lock( 'autopilot_tick' );
	}
}

/* ---------------------------------------------------------------- *
 * Watchdog — requeue stuck jobs.                                    *
 * ---------------------------------------------------------------- */
function pr_autopilot_watchdog(): void {
	global $wpdb;
	$cutoff = gmdate( 'Y-m-d H:i:s', time() - 30 * MINUTE_IN_SECONDS );
	$rows   = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, state FROM " . pr_table( 'run_queue' ) . "
		 WHERE started_at IS NOT NULL AND updated_at < %s
		 AND state NOT IN (%s, %s, %s)",
		$cutoff, PR_STATE_PUBLISHED, PR_STATE_NEEDS_REVIEW, PR_STATE_MONITORING
	), ARRAY_A );
	foreach ( (array) $rows as $r ) {
		PR_Queue::transition( (int) $r['id'], $r['state'], PR_STATE_QUEUED, 'watchdog requeue (stuck >30min)' );
	}
}

/* ---------------------------------------------------------------- *
 * Job dispatcher — routes by state to a per-state handler hook.    *
 * Milestones 4–7 register the real implementations:                *
 *   add_action( 'pr_handle_state_discovering', fn($job) => ... );  *
 * ---------------------------------------------------------------- */
function pr_autopilot_process_job( array $job ): void {
	$id    = (int) $job['id'];
	$state = (string) $job['state'];
	try {
		// Default chain: queued → discovering → researching → fact_checking
		//              → writing → editing → quality_gate → scheduled → published → monitoring.
		$has_handler = has_action( 'pr_handle_state_' . $state );
		if ( $has_handler ) {
			do_action( 'pr_handle_state_' . $state, $job );
			return; // The handler is responsible for calling PR_Queue::transition().
		}
		// Skeleton fallback for milestone 3: just advance one step so the
		// pipeline visibly flows in the UI while later milestones land.
		$next = pr_next_default_state( $state );
		if ( $next === null ) {
			PR_Queue::transition( $id, $state, PR_STATE_NEEDS_REVIEW, 'no handler and no default next state' );
			return;
		}
		PR_Queue::transition( $id, $state, $next, 'skeleton advance (no handler yet)' );
	} catch ( \Throwable $e ) {
		PR_Queue::fail( $id, $state, $e->getMessage() );
	}
}

function pr_next_default_state( string $state ): ?string {
	static $map = null;
	if ( $map === null ) {
		$map = array(
			PR_STATE_QUEUED        => PR_STATE_DISCOVERING,
			PR_STATE_DISCOVERING   => PR_STATE_RESEARCHING,
			PR_STATE_RESEARCHING   => PR_STATE_FACT_CHECKING,
			PR_STATE_FACT_CHECKING => PR_STATE_WRITING,
			PR_STATE_WRITING       => PR_STATE_EDITING,
			PR_STATE_EDITING       => PR_STATE_QUALITY_GATE,
			PR_STATE_QUALITY_GATE  => PR_STATE_SCHEDULED,
			PR_STATE_SCHEDULED     => PR_STATE_PUBLISHED,
			PR_STATE_PUBLISHED     => PR_STATE_MONITORING,
			PR_STATE_UPDATING      => PR_STATE_PUBLISHED,
		);
	}
	return $map[ $state ] ?? null;
}
