<?php
/**
 * PR_Source — common interface every product-data driver implements.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface PR_Source {

	/** Stable driver id (e.g. "lambda", "creators", "paapi", "scrape"). */
	public function id(): string;

	/** Human label for admin UI. */
	public function label(): string;

	/** True when this driver has everything it needs to make a call. */
	public function is_configured(): bool;

	/**
	 * Search products for a keyword.
	 *
	 * @return array<int,array<string,mixed>>|WP_Error  Canonical product rows.
	 */
	public function search( string $keyword, int $page = 1, int $count = 10 );

	/** Single-item lookup by ASIN. */
	public function get_item( string $asin );

	/** Lightweight health probe; returns true|WP_Error. */
	public function health();
}

/**
 * Canonical product row shape returned by every driver:
 *
 *   array(
 *     'rank'         => int,
 *     'asin'         => string,
 *     'title'        => string,
 *     'brand'        => string,
 *     'image'        => string (https url),
 *     'images'       => string[],
 *     'price'        => string|float ("" if unknown),
 *     'list_price'   => string|float,
 *     'currency'     => string ("USD"),
 *     'rating'       => float|string,
 *     'review_count' => int|string,
 *     'prime'        => bool,
 *     'availability' => string,
 *     'features'     => string[],
 *     'description'  => string,
 *     'url'          => string (amazon detail url incl. tag),
 *     'source'       => string (driver id),
 *   )
 */
