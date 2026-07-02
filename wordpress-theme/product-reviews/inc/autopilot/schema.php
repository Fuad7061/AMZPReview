<?php
/**
 * Autopilot — DB schema + activation/upgrade routine.
 *
 * Tables (with $wpdb->prefix):
 *   pr_run_queue   — every job moving through the state machine.
 *   pr_run_log     — append-only audit trail of state transitions.
 *   pr_seen_asins  — global ASIN dedup set.
 *   pr_facts       — per-article extracted + verified claims.
 *   pr_change_log  — per-article field-level change history.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'PR_SCHEMA_VERSION', '1.0' );

// State machine.
define( 'PR_STATE_QUEUED',         'queued' );
define( 'PR_STATE_DISCOVERING',    'discovering' );
define( 'PR_STATE_RESEARCHING',    'researching' );
define( 'PR_STATE_FACT_CHECKING',  'fact_checking' );
define( 'PR_STATE_WRITING',        'writing' );
define( 'PR_STATE_EDITING',        'editing' );
define( 'PR_STATE_QUALITY_GATE',   'quality_gate' );
define( 'PR_STATE_SCHEDULED',      'scheduled' );
define( 'PR_STATE_PUBLISHED',      'published' );
define( 'PR_STATE_MONITORING',     'monitoring' );
define( 'PR_STATE_UPDATING',       'updating' );
define( 'PR_STATE_NEEDS_REVIEW',   'needs_review' );

function pr_active_states(): array {
	return array(
		PR_STATE_QUEUED, PR_STATE_DISCOVERING, PR_STATE_RESEARCHING,
		PR_STATE_FACT_CHECKING, PR_STATE_WRITING, PR_STATE_EDITING,
		PR_STATE_QUALITY_GATE, PR_STATE_SCHEDULED, PR_STATE_MONITORING,
		PR_STATE_UPDATING,
	);
}

function pr_table( string $name ): string {
	global $wpdb;
	return $wpdb->prefix . 'pr_' . $name;
}

function pr_install_schema(): void {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();

	$queue = pr_table( 'run_queue' );
	$log   = pr_table( 'run_log' );
	$seen  = pr_table( 'seen_asins' );
	$facts = pr_table( 'facts' );
	$chg   = pr_table( 'change_log' );

	$sql = array();
	$sql[] = "CREATE TABLE {$queue} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		keyword VARCHAR(255) NOT NULL,
		category_id BIGINT UNSIGNED NULL,
		post_id BIGINT UNSIGNED NULL,
		state VARCHAR(32) NOT NULL DEFAULT 'queued',
		priority TINYINT NOT NULL DEFAULT 5,
		attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		next_run_at DATETIME NOT NULL,
		started_at DATETIME NULL,
		updated_at DATETIME NOT NULL,
		payload LONGTEXT NULL,
		error TEXT NULL,
		PRIMARY KEY (id),
		KEY state_run (state, next_run_at),
		KEY post_id (post_id)
	) {$charset};";

	$sql[] = "CREATE TABLE {$log} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		run_id BIGINT UNSIGNED NOT NULL,
		ts DATETIME NOT NULL,
		state_from VARCHAR(32) NULL,
		state_to VARCHAR(32) NOT NULL,
		driver VARCHAR(32) NULL,
		latency_ms INT NULL,
		message TEXT NULL,
		PRIMARY KEY (id),
		KEY run_id (run_id),
		KEY ts (ts)
	) {$charset};";

	$sql[] = "CREATE TABLE {$seen} (
		asin VARCHAR(20) NOT NULL,
		first_seen DATETIME NOT NULL,
		last_seen DATETIME NOT NULL,
		category_id BIGINT UNSIGNED NULL,
		review_post_id BIGINT UNSIGNED NULL,
		PRIMARY KEY (asin),
		KEY category_id (category_id),
		KEY review_post_id (review_post_id)
	) {$charset};";

	$sql[] = "CREATE TABLE {$facts} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		article_id BIGINT UNSIGNED NOT NULL,
		asin VARCHAR(20) NULL,
		fact_key VARCHAR(64) NOT NULL,
		fact_type VARCHAR(32) NOT NULL,
		value TEXT NULL,
		source_driver VARCHAR(32) NULL,
		source_url TEXT NULL,
		confidence DECIMAL(3,2) NOT NULL DEFAULT 0.00,
		checked_at DATETIME NOT NULL,
		PRIMARY KEY (id),
		KEY article_id (article_id),
		KEY asin (asin)
	) {$charset};";

	$sql[] = "CREATE TABLE {$chg} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		article_id BIGINT UNSIGNED NOT NULL,
		asin VARCHAR(20) NULL,
		field VARCHAR(64) NOT NULL,
		old_value TEXT NULL,
		new_value TEXT NULL,
		ts DATETIME NOT NULL,
		PRIMARY KEY (id),
		KEY article_id (article_id),
		KEY ts (ts)
	) {$charset};";

	foreach ( $sql as $stmt ) { dbDelta( $stmt ); }

	update_option( 'pr_schema_version', PR_SCHEMA_VERSION, false );
}

/** Run on theme activation and on every load if the schema version is stale. */
add_action( 'after_switch_theme', 'pr_install_schema' );
add_action( 'admin_init', function () {
	if ( get_option( 'pr_schema_version' ) !== PR_SCHEMA_VERSION ) {
		pr_install_schema();
	}
} );
