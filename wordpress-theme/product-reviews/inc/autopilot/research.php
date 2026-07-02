<?php
/**
 * Autopilot — research state handler.
 *
 * `researching` enriches each novel ASIN discovered in the previous step:
 *   - Pulls the next ranked driver in the chain (if available) for the
 *     same keyword and merges any extra fields (bullets, brand, image,
 *     review_count, features).
 *   - Stores a per-ASIN snapshot through PR_Facts so price/rating diffs
 *     can be detected on future runs.
 *   - Hands the enriched product list to fact-checking via job payload.
 *
 * Output payload keys: keyword, category_id, products (enriched),
 *                      research_drivers (list of drivers contacted),
 *                      research_latency_ms.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'pr_handle_state_' . PR_STATE_RESEARCHING, function ( $job ) {
	$id      = (int) $job['id'];
	$payload = pr_job_payload( $job );
	$keyword = (string) ( $payload['keyword'] ?? $job['keyword'] );
	$cat_id  = isset( $payload['category_id'] ) ? (int) $payload['category_id'] : null;
	$products = (array) ( $payload['products'] ?? array() );

	if ( empty( $products ) ) {
		PR_Queue::fail( $id, PR_STATE_RESEARCHING, 'no products carried over from discovery' );
		return;
	}

	$started = microtime( true );
	list( $enriched, $drivers_used ) = pr_research_enrich( $keyword, $products );
	$ms = (int) ( ( microtime( true ) - $started ) * 1000 );

	// Persist a fresh facts snapshot per ASIN (article_id=0 until the post exists).
	foreach ( $enriched as $p ) {
		PR_Facts::record_snapshot( 0, $p, (string) ( $p['_primary_driver'] ?? 'lambda' ) );
	}

	pr_queue_set_payload( $id, array_merge( $payload, array(
		'products'             => $enriched,
		'research_drivers'     => $drivers_used,
		'research_latency_ms'  => $ms,
	) ) );

	PR_Queue::transition(
		$id, PR_STATE_RESEARCHING, PR_STATE_FACT_CHECKING,
		sprintf( 'enriched %d products via %s', count( $enriched ), implode( '+', $drivers_used ) ),
		end( $drivers_used ) ?: null, $ms
	);
} );

/* ---------------------------------------------------------------- *
 * Enrichment: merge secondary driver fields into the primary list.
 * ---------------------------------------------------------------- */

/**
 * @return array{0: array<int,array<string,mixed>>, 1: string[]}
 */
function pr_research_enrich( string $keyword, array $primary_products ): array {
	$drivers_used = array( 'lambda' );
	$primary_by_asin = array();
	foreach ( $primary_products as $p ) {
		$asin = isset( $p['asin'] ) ? PR_Dedup::normalize( (string) $p['asin'] ) : '';
		if ( $asin === '' ) { continue; }
		$p['_primary_driver'] = 'lambda';
		$primary_by_asin[ $asin ] = $p;
	}

	// Walk the chain (skipping the first driver — already used at discovery).
	$chain = PR_Source_Manager::chain();
	foreach ( array_slice( $chain, 1 ) as $driver ) {
		if ( ! method_exists( $driver, 'is_configured' ) || ! $driver->is_configured() ) { continue; }
		$res = $driver->search( $keyword, 1, max( 5, count( $primary_by_asin ) ) );
		if ( is_wp_error( $res ) || empty( $res ) ) { continue; }
		$drivers_used[] = $driver->id();

		$norm = pr_discovery_normalize( $res );
		foreach ( $norm as $extra ) {
			$asin = $extra['asin'] ?? '';
			if ( ! isset( $primary_by_asin[ $asin ] ) ) { continue; }
			$primary_by_asin[ $asin ] = pr_merge_product( $primary_by_asin[ $asin ], $extra, $driver->id() );
		}
		// One enrichment driver is enough for this pass.
		break;
	}

	return array( array_values( $primary_by_asin ), $drivers_used );
}

/**
 * Merge an extra-driver dict into the primary one without overwriting
 * non-empty primary fields. Records corroboration for fact-check scoring.
 */
function pr_merge_product( array $base, array $extra, string $extra_driver ): array {
	$base['_corroborated_by'] = array_values( array_unique( array_merge(
		(array) ( $base['_corroborated_by'] ?? array() ),
		array( $extra_driver )
	) ) );
	$base['_corroborated_fields'] = (array) ( $base['_corroborated_fields'] ?? array() );

	foreach ( PR_Facts::FACT_KEYS as $key ) {
		$has  = isset( $base[ $key ] )  && $base[ $key ]  !== '' && $base[ $key ]  !== null && $base[ $key ]  !== array();
		$gives = isset( $extra[ $key ] ) && $extra[ $key ] !== '' && $extra[ $key ] !== null && $extra[ $key ] !== array();

		if ( $gives && ! $has ) {
			$base[ $key ] = $extra[ $key ];
		} elseif ( $has && $gives ) {
			$base['_corroborated_fields'][ $key ] = ( $base['_corroborated_fields'][ $key ] ?? 0 ) + 1;
		}
	}
	return $base;
}

/* ---------------------------------------------------------------- *
 * Shared payload helpers.
 * ---------------------------------------------------------------- */

function pr_job_payload( array $job ): array {
	$raw = $job['payload'] ?? '';
	if ( ! is_string( $raw ) || $raw === '' ) { return array(); }
	$out = json_decode( $raw, true );
	return is_array( $out ) ? $out : array();
}
