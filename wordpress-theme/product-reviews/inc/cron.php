<?php
/**
 * WP-Cron: daily auto-generation from the keyword queue.
 *
 * @package YadFoodReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function yadfood_schedule_cron() {
	if ( '1' === get_option( 'yadfood_cron_enabled', '0' ) && ! wp_next_scheduled( 'yadfood_daily_generate' ) ) {
		wp_schedule_event( time() + 60, 'daily', 'yadfood_daily_generate' );
	}
	if ( '1' !== get_option( 'yadfood_cron_enabled', '0' ) ) {
		$ts = wp_next_scheduled( 'yadfood_daily_generate' );
		if ( $ts ) { wp_unschedule_event( $ts, 'yadfood_daily_generate' ); }
	}
}
add_action( 'init', 'yadfood_schedule_cron' );

function yadfood_daily_generate_handler() {
	$queue = (array) get_option( 'yadfood_keyword_queue', array() );
	if ( empty( $queue ) ) {
		return;
	}
	$keyword = array_shift( $queue );
	update_option( 'yadfood_keyword_queue', array_values( $queue ) );

	$post_id = yadfood_generate_review( $keyword, 10, 'draft' );
	if ( is_wp_error( $post_id ) ) {
		error_log( '[YadFood] Auto-generate failed for "' . $keyword . '": ' . $post_id->get_error_message() );
	}
}
add_action( 'yadfood_daily_generate', 'yadfood_daily_generate_handler' );

/**
 * Unschedule on theme switch.
 */
function yadfood_clear_cron() {
	$ts = wp_next_scheduled( 'yadfood_daily_generate' );
	if ( $ts ) { wp_unschedule_event( $ts, 'yadfood_daily_generate' ); }
}
add_action( 'switch_theme', 'yadfood_clear_cron' );
