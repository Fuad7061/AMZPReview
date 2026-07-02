<?php
/**
 * PR_Source_PAAPI — Amazon Product Advertising API 5.0 (legacy).
 *
 * Delegates to the existing SigV4 implementation in inc/amazon-api.php
 * (yadfood_amazon_search), so any existing approved PA-API account keeps
 * working unchanged.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PR_Source_PAAPI implements PR_Source {
	public function id(): string    { return 'paapi'; }
	public function label(): string { return __( 'PA-API 5.0 (deprecated 2026-05-15)', 'product-reviews' ); }

	public function is_configured(): bool {
		return (bool) ( trim( (string) get_option( 'yadfood_paapi_access_key', '' ) )
			&& trim( (string) get_option( 'yadfood_paapi_secret_key', '' ) ) );
	}

	public function search( string $keyword, int $page = 1, int $count = 10 ) {
		if ( ! function_exists( 'yadfood_amazon_search_paapi_impl' ) && function_exists( 'yadfood_amazon_search' ) ) {
			// Call the legacy path directly, bypassing the new manager to avoid recursion.
			remove_filter( 'pr_source_override', '__return_false' ); // no-op safety
			if ( ! defined( 'PR_BYPASS_MANAGER' ) ) { define( 'PR_BYPASS_MANAGER', true ); }
			$res = yadfood_amazon_search_legacy( $keyword, $count );
			if ( is_wp_error( $res ) ) { return $res; }
			foreach ( $res as &$r ) { $r['source'] = $this->id(); }
			return $res;
		}
		return new WP_Error( 'pr_paapi_unavailable', 'PA-API helper not loaded' );
	}
	public function get_item( string $asin ) {
		$res = $this->search( $asin, 1, 1 );
		if ( is_wp_error( $res ) || empty( $res ) ) { return $res ?: new WP_Error( 'pr_paapi_not_found', '' ); }
		return $res[0];
	}
	public function health() {
		return $this->is_configured() ? true : new WP_Error( 'pr_paapi_disabled', 'No PA-API keys' );
	}
}
