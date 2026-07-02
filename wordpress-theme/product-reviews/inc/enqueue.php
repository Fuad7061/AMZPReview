<?php
/**
 * Enqueue scripts and styles.
 *
 * @package YadFoodReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function yadfood_enqueue_assets() {
	$ver = YADFOOD_VERSION;

	wp_enqueue_style( 'yadfood-fonts',
		'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Instrument+Serif:ital@0;1&display=swap',
		array(), null );

	wp_enqueue_style( 'yadfood-main', YADFOOD_THEME_URI . '/assets/css/main.css', array(), $ver );

	wp_enqueue_script( 'yadfood-main', YADFOOD_THEME_URI . '/assets/js/main.js', array(), $ver, true );
	wp_localize_script( 'yadfood-main', 'YadFood', array(
		'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
		'restUrl'  => esc_url_raw( rest_url( 'yadfood/v1/' ) ),
		'nonce'    => wp_create_nonce( 'wp_rest' ),
	) );
}
add_action( 'wp_enqueue_scripts', 'yadfood_enqueue_assets' );

function yadfood_admin_enqueue( $hook ) {
	if ( false === strpos( $hook, 'yadfood' ) && 'post.php' !== $hook && 'post-new.php' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'yadfood-admin', YADFOOD_THEME_URI . '/assets/css/admin.css', array(), YADFOOD_VERSION );
	wp_enqueue_script( 'yadfood-admin', YADFOOD_THEME_URI . '/assets/js/admin.js', array( 'jquery' ), YADFOOD_VERSION, true );
	wp_localize_script( 'yadfood-admin', 'YadFoodAdmin', array(
		'restUrl' => esc_url_raw( rest_url( 'yadfood/v1/' ) ),
		'nonce'   => wp_create_nonce( 'wp_rest' ),
	) );
}
add_action( 'admin_enqueue_scripts', 'yadfood_admin_enqueue' );
