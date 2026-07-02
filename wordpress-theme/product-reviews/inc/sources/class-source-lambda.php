<?php
/**
 * PR_Source_Lambda — default zero-config driver.
 *
 * Calls the built-in Lambda endpoint. URL is kept server-side and can be
 * overridden by defining PR_LAMBDA_URL in wp-config.php. Not exposed in
 * the admin UI.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PR_Source_Lambda implements PR_Source {

	const DEFAULT_URL = 'https://4pobkr5oa4olwuvhx625uiozay0rrcuu.lambda-url.us-east-1.on.aws/';

	public function id(): string    { return 'lambda'; }
	public function label(): string { return __( 'Built-in Product Data (default)', 'product-reviews' ); }
	public function is_configured(): bool { return true; }

	private function base_url(): string {
		if ( defined( 'PR_LAMBDA_URL' ) && PR_LAMBDA_URL ) {
			return PR_LAMBDA_URL;
		}
		return self::DEFAULT_URL;
	}

	public function search( string $keyword, int $page = 1, int $count = 10 ) {
		$tag = pr_affiliate_tag();
		$cache_key = 'pr_lambda_' . md5( strtolower( $keyword ) . '|' . $page . '|' . $tag );
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$url = add_query_arg( array(
			'q'    => $keyword,
			'tag'  => $tag,
			'page' => max( 1, $page ),
		), $this->base_url() );

		$response = wp_remote_get( $url, array(
			'timeout' => 25,
			'headers' => array(
				'Accept'     => 'application/json',
				'User-Agent' => 'product-reviews/' . PR_VERSION . '; ' . home_url(),
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$body = json_decode( $raw, true );
		if ( $code >= 400 || ! is_array( $body ) ) {
			return new WP_Error( 'pr_lambda_error', sprintf( 'Lambda HTTP %d', $code ), array( 'body' => $raw ) );
		}

		$items = $this->normalize( $body, $count, $tag );
		if ( $items ) {
			set_transient( $cache_key, $items, HOUR_IN_SECONDS );
		}
		return $items;
	}

	public function get_item( string $asin ) {
		// Lambda has no by-ASIN endpoint; do a targeted search.
		$res = $this->search( $asin, 1, 1 );
		if ( is_wp_error( $res ) || empty( $res ) ) {
			return $res ?: new WP_Error( 'pr_lambda_not_found', 'ASIN not found' );
		}
		return $res[0];
	}

	public function health() {
		$res = $this->search( 'usb cable', 1, 1 );
		return is_wp_error( $res ) ? $res : true;
	}

	/** Normalise a Lambda response into canonical rows. Tolerates several shapes. */
	private function normalize( $body, int $count, string $tag ): array {
		$rows = array();
		// Common shapes: { results: [...] } or { items: [...] } or top-level array.
		$raw  = array();
		if ( isset( $body['results'] ) && is_array( $body['results'] ) ) { $raw = $body['results']; }
		elseif ( isset( $body['items'] ) && is_array( $body['items'] ) ) { $raw = $body['items']; }
		elseif ( isset( $body['data'] ) && is_array( $body['data'] ) )   { $raw = $body['data']; }
		elseif ( is_array( $body ) && isset( $body[0] ) )                { $raw = $body; }

		$rank = 0;
		foreach ( $raw as $it ) {
			if ( ! is_array( $it ) ) { continue; }
			$asin = (string) ( $it['asin'] ?? $it['ASIN'] ?? '' );
			if ( $asin === '' ) { continue; }
			$rank++;

			$title = (string) ( $it['title'] ?? $it['name'] ?? '' );
			$image = (string) ( $it['image'] ?? $it['thumbnail'] ?? $it['img'] ?? '' );
			$imgs  = isset( $it['images'] ) && is_array( $it['images'] ) ? array_values( array_filter( array_map( 'strval', $it['images'] ) ) ) : ( $image ? array( $image ) : array() );

			$price = $it['price'] ?? $it['price_value'] ?? $it['price_string'] ?? '';
			if ( is_array( $price ) ) {
				$price = $price['value'] ?? $price['amount'] ?? $price['raw'] ?? '';
			}
			$list_price = $it['list_price'] ?? $it['original_price'] ?? '';

			$rating = $it['rating'] ?? $it['stars'] ?? '';
			if ( is_array( $rating ) ) { $rating = $rating['value'] ?? ''; }

			$review_count = $it['rating_count'] ?? $it['review_count'] ?? $it['reviews'] ?? '';
			if ( is_array( $review_count ) ) { $review_count = $review_count['count'] ?? ''; }

			$url = (string) ( $it['url'] ?? $it['link'] ?? '' );
			if ( $url === '' && $asin ) {
				$url = 'https://www.amazon.com/dp/' . rawurlencode( $asin ) . '/?tag=' . rawurlencode( $tag );
			} elseif ( $url && strpos( $url, 'tag=' ) === false ) {
				$url = add_query_arg( 'tag', $tag, $url );
			}

			$rows[] = array(
				'rank'         => $rank,
				'asin'         => $asin,
				'title'        => $title,
				'brand'        => (string) ( $it['brand'] ?? '' ),
				'image'        => $image,
				'images'       => $imgs,
				'price'        => is_numeric( $price ) ? (float) $price : (string) $price,
				'list_price'   => is_numeric( $list_price ) ? (float) $list_price : (string) $list_price,
				'currency'     => (string) ( $it['currency'] ?? 'USD' ),
				'rating'       => is_numeric( $rating ) ? (float) $rating : (string) $rating,
				'review_count' => is_numeric( $review_count ) ? (int) $review_count : (string) $review_count,
				'prime'        => (bool) ( $it['prime'] ?? $it['is_prime'] ?? false ),
				'availability' => (string) ( $it['availability'] ?? '' ),
				'features'     => isset( $it['features'] ) && is_array( $it['features'] ) ? array_values( array_map( 'strval', $it['features'] ) ) : array(),
				'description'  => (string) ( $it['description'] ?? '' ),
				'url'          => $url,
				'source'       => $this->id(),
			);

			if ( count( $rows ) >= $count ) { break; }
		}
		return $rows;
	}
}
