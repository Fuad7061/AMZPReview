<?php
/**
 * Autopilot — queue API (enqueue, claim, transition, log, retry).
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PR_Queue {

	/** Enqueue a new job; returns row id. */
	public static function enqueue( string $keyword, ?int $category_id = null, array $payload = array(), int $priority = 5 ): int {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$wpdb->insert( pr_table( 'run_queue' ), array(
			'keyword'     => $keyword,
			'category_id' => $category_id,
			'state'       => PR_STATE_QUEUED,
			'priority'    => $priority,
			'attempts'    => 0,
			'next_run_at' => $now,
			'updated_at'  => $now,
			'payload'     => wp_json_encode( $payload ),
		) );
		$id = (int) $wpdb->insert_id;
		self::log( $id, null, PR_STATE_QUEUED, null, 0, 'enqueued: ' . $keyword );
		return $id;
	}

	/** Atomically claim the next ready job (or null). */
	public static function claim_next(): ?array {
		global $wpdb;
		$tbl  = pr_table( 'run_queue' );
		$now  = current_time( 'mysql', true );
		$active = "'" . implode( "','", array_map( 'esc_sql', pr_active_states() ) ) . "'";
		// Pick highest-priority, oldest next_run_at, currently due.
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$tbl}
			 WHERE state IN ({$active}) AND next_run_at <= %s AND state != %s
			 ORDER BY priority ASC, next_run_at ASC
			 LIMIT 1",
			$now, PR_STATE_SCHEDULED
		), ARRAY_A );
		if ( ! $row ) { return null; }

		// Optimistic claim: bump started_at; if no row affected, someone else got it.
		$affected = $wpdb->update(
			$tbl,
			array( 'started_at' => $now, 'updated_at' => $now, 'attempts' => (int) $row['attempts'] + 1 ),
			array( 'id' => $row['id'], 'updated_at' => $row['updated_at'] )
		);
		if ( ! $affected ) { return null; }
		$row['attempts']   = (int) $row['attempts'] + 1;
		$row['started_at'] = $now;
		return $row;
	}

	/** Move job to a new state. $delay_seconds schedules a retry. */
	public static function transition( int $id, string $from, string $to, string $message = '', ?string $driver = null, int $latency_ms = 0, int $delay_seconds = 0, ?string $error = null ): void {
		global $wpdb;
		$now      = current_time( 'mysql', true );
		$next_run = $delay_seconds > 0
			? gmdate( 'Y-m-d H:i:s', time() + $delay_seconds )
			: $now;
		$wpdb->update( pr_table( 'run_queue' ), array(
			'state'       => $to,
			'next_run_at' => $next_run,
			'updated_at'  => $now,
			'error'       => $error,
		), array( 'id' => $id ) );
		self::log( $id, $from, $to, $driver, $latency_ms, $message );
	}

	public static function log( int $run_id, ?string $from, string $to, ?string $driver, int $latency_ms, string $message ): void {
		global $wpdb;
		$wpdb->insert( pr_table( 'run_log' ), array(
			'run_id'     => $run_id,
			'ts'         => current_time( 'mysql', true ),
			'state_from' => $from,
			'state_to'   => $to,
			'driver'     => $driver,
			'latency_ms' => $latency_ms,
			'message'    => mb_substr( $message, 0, 500 ),
		) );
	}

	public static function fail( int $id, string $from, string $reason, int $max_attempts = 5 ): void {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT attempts FROM " . pr_table( 'run_queue' ) . " WHERE id = %d", $id ), ARRAY_A );
		$attempts = (int) ( $row['attempts'] ?? 0 );
		if ( $attempts >= $max_attempts ) {
			self::transition( $id, $from, PR_STATE_NEEDS_REVIEW, 'dead-letter after ' . $attempts . ' attempts: ' . $reason, null, 0, 0, $reason );
			return;
		}
		// Exponential backoff with jitter: 60s, 2m, 5m, 15m, 60m.
		$schedule = array( 60, 120, 300, 900, 3600 );
		$delay    = $schedule[ min( count( $schedule ) - 1, max( 0, $attempts - 1 ) ) ];
		$delay   += wp_rand( 0, max( 5, (int) ( $delay * 0.2 ) ) );
		self::transition( $id, $from, $from, "retry in {$delay}s: {$reason}", null, 0, $delay, $reason );
	}

	public static function retry( int $id ): void {
		global $wpdb;
		$wpdb->update( pr_table( 'run_queue' ), array(
			'state'       => PR_STATE_QUEUED,
			'next_run_at' => current_time( 'mysql', true ),
			'updated_at'  => current_time( 'mysql', true ),
			'error'       => null,
		), array( 'id' => $id ) );
		self::log( $id, PR_STATE_NEEDS_REVIEW, PR_STATE_QUEUED, null, 0, 'manual retry' );
	}

	public static function count_by_state(): array {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT state, COUNT(*) c FROM " . pr_table( 'run_queue' ) . " GROUP BY state", ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) { $out[ $r['state'] ] = (int) $r['c']; }
		return $out;
	}
}
