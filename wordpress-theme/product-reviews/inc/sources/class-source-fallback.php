<?php
/**
 * PR_Source_Fallback — bundled zero-config product rows.
 *
 * Used only when live sources return nothing or fail. This keeps a fresh theme
 * install useful in shared hosting environments where outbound HTTP, Lambda,
 * PA-API, or scraping credentials may not be ready yet.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PR_Source_Fallback implements PR_Source {

	public function id(): string    { return 'fallback'; }
	public function label(): string { return __( 'Bundled Fallback Products', 'product-reviews' ); }
	public function is_configured(): bool { return true; }

	public function search( string $keyword, int $page = 1, int $count = 10 ) {
		$rows = function_exists( 'pr_default_products_for_keyword' )
			? pr_default_products_for_keyword( $keyword, $count )
			: array();

		if ( empty( $rows ) ) {
			return new WP_Error( 'pr_fallback_empty', __( 'No fallback products available.', 'product-reviews' ) );
		}

		foreach ( $rows as &$row ) {
			$row['source'] = $this->id();
			if ( empty( $row['url'] ) && ! empty( $row['asin'] ) ) {
				$row['url'] = yadfood_amazon_url( $row['asin'], sanitize_title( $keyword ) );
			} elseif ( empty( $row['url'] ) && function_exists( 'pr_default_amazon_search_url' ) ) {
				$row['url'] = pr_default_amazon_search_url( $keyword, 'fallback-source' );
			}
		}

		return array_slice( $rows, 0, max( 1, min( 10, $count ) ) );
	}

	public function get_item( string $asin ) {
		$asin = strtoupper( preg_replace( '/[^A-Z0-9]/i', '', $asin ) );
		foreach ( pr_default_product_catalog() as $group ) {
			foreach ( (array) $group['products'] as $row ) {
				if ( strtoupper( (string) ( $row['asin'] ?? '' ) ) === $asin ) {
					$row['source'] = $this->id();
					return $row;
				}
			}
		}
		return new WP_Error( 'pr_fallback_not_found', __( 'Fallback product not found.', 'product-reviews' ) );
	}

	public function health() {
		return true;
	}
}
