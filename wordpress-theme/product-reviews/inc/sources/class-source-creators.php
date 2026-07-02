<?php
/**
 * PR_Source_Creators — Amazon Creators API (replaces PA-API 5.0 in 2026).
 *
 * Stub: OAuth2 client-credentials flow. Settings stored as options
 * pr_creators_client_id / pr_creators_client_secret. Implementation will
 * land in a later milestone once the Creators API has public access.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PR_Source_Creators implements PR_Source {
	public function id(): string    { return 'creators'; }
	public function label(): string { return __( 'Amazon Creators API (recommended)', 'product-reviews' ); }

	public function is_configured(): bool {
		return (bool) ( trim( (string) get_option( 'pr_creators_client_id', '' ) )
			&& trim( (string) get_option( 'pr_creators_client_secret', '' ) ) );
	}

	public function search( string $keyword, int $page = 1, int $count = 10 ) {
		return new WP_Error( 'pr_creators_pending', __( 'Creators API driver is wired but the live HTTP call lands in a later milestone. Use Lambda or Scrape for now.', 'product-reviews' ) );
	}
	public function get_item( string $asin ) { return $this->search( $asin ); }
	public function health() {
		return $this->is_configured() ? true : new WP_Error( 'pr_creators_disabled', 'No credentials' );
	}
}
