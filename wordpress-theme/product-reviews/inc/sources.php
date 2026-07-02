<?php
/**
 * Sources bootloader — loads interface + all drivers + the manager.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once PR_THEME_DIR . '/inc/sources/interface-source.php';
require_once PR_THEME_DIR . '/inc/sources/class-source-lambda.php';
require_once PR_THEME_DIR . '/inc/sources/class-source-scrape.php';
require_once PR_THEME_DIR . '/inc/sources/class-source-creators.php';
require_once PR_THEME_DIR . '/inc/sources/class-source-paapi.php';
require_once PR_THEME_DIR . '/inc/sources/class-source-fallback.php';
require_once PR_THEME_DIR . '/inc/sources/class-source-manager.php';

/**
 * Public façade: pr_product_search() is the one entry point all theme code
 * (AI generator, REST, blocks, smart-search) should use going forward.
 */
function pr_product_search( string $keyword, int $count = 10, int $page = 1 ) {
	return PR_Source_Manager::search( $keyword, $page, $count );
}

/**
 * AJAX: Test a driver connection from the admin Settings page.
 */
add_action( 'wp_ajax_pr_test_source', function () {
	check_ajax_referer( 'pr_test_source', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}
	$id     = sanitize_key( $_POST['driver'] ?? '' );
	$driver = PR_Source_Manager::get( $id );
	if ( ! $driver ) {
		wp_send_json_error( array( 'message' => 'unknown driver' ) );
	}
	$started = microtime( true );
	$res     = $driver->search( sanitize_text_field( $_POST['q'] ?? 'usb cable' ), 1, 3 );
	$ms      = (int) ( ( microtime( true ) - $started ) * 1000 );
	if ( is_wp_error( $res ) ) {
		wp_send_json_error( array( 'message' => $res->get_error_message(), 'latency_ms' => $ms ) );
	}
	wp_send_json_success( array(
		'count'      => count( $res ),
		'latency_ms' => $ms,
		'sample'     => array_slice( array_map( function ( $r ) {
			return array( 'asin' => $r['asin'], 'title' => mb_substr( $r['title'], 0, 80 ) );
		}, $res ), 0, 3 ),
	) );
} );
