<?php
/**
 * Autopilot — fact-checking state handler + quality gate.
 *
 * For every product carried into `fact_checking` we:
 *   1. Score each material claim (title / price / rating / review_count /
 *      image / availability) on completeness + corroboration.
 *   2. Compute an aggregate confidence ∈ [0, 1] for the product.
 *   3. Drop products below `pr_factcheck_min_product_confidence` (default 0.50).
 *   4. Compute an article-level trust score (mean of survivors).
 *   5. If too few products survive OR article confidence < gate threshold,
 *      transition to NEEDS_REVIEW with a structured reason.
 *   6. Otherwise persist the verified list to payload and advance to WRITING.
 *
 * All thresholds are stored as options and surfaced on the Autopilot
 * settings UI later. Sensible defaults ship today.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const PR_FACTCHECK_DEFAULTS = array(
	'min_product_confidence' => 0.50,
	'min_article_confidence' => 0.65,
	'min_products'           => 3,
);

function pr_factcheck_threshold( string $key ): float {
	$opts = (array) get_option( 'pr_factcheck_thresholds', array() );
	$val  = $opts[ $key ] ?? PR_FACTCHECK_DEFAULTS[ $key ] ?? 0;
	return is_numeric( $val ) ? (float) $val : 0.0;
}

add_action( 'pr_handle_state_' . PR_STATE_FACT_CHECKING, function ( $job ) {
	$id      = (int) $job['id'];
	$payload = pr_job_payload( $job );
	$products = (array) ( $payload['products'] ?? array() );

	if ( empty( $products ) ) {
		PR_Queue::fail( $id, PR_STATE_FACT_CHECKING, 'no products to verify' );
		return;
	}

	$started = microtime( true );
	$report  = pr_factcheck_run( $products );
	$ms      = (int) ( ( microtime( true ) - $started ) * 1000 );

	$min_products            = (int) pr_factcheck_threshold( 'min_products' );
	$min_article_confidence  = pr_factcheck_threshold( 'min_article_confidence' );

	if ( count( $report['verified'] ) < max( 1, $min_products ) ) {
		PR_Queue::transition(
			$id, PR_STATE_FACT_CHECKING, PR_STATE_NEEDS_REVIEW,
			sprintf( 'only %d products passed fact-check (min %d)', count( $report['verified'] ), $min_products ),
			'factcheck', $ms, 0,
			wp_json_encode( $report['summary'] )
		);
		return;
	}
	if ( $report['article_confidence'] < $min_article_confidence ) {
		PR_Queue::transition(
			$id, PR_STATE_FACT_CHECKING, PR_STATE_NEEDS_REVIEW,
			sprintf( 'article confidence %.2f below gate %.2f', $report['article_confidence'], $min_article_confidence ),
			'factcheck', $ms, 0,
			wp_json_encode( $report['summary'] )
		);
		return;
	}

	pr_queue_set_payload( $id, array_merge( $payload, array(
		'products'           => $report['verified'],
		'rejected'           => $report['rejected'],
		'article_confidence' => $report['article_confidence'],
		'factcheck_summary'  => $report['summary'],
	) ) );

	PR_Queue::transition(
		$id, PR_STATE_FACT_CHECKING, PR_STATE_WRITING,
		sprintf( '%d verified · conf %.2f · %d rejected', count( $report['verified'] ), $report['article_confidence'], count( $report['rejected'] ) ),
		'factcheck', $ms
	);
} );

/* ---------------------------------------------------------------- *
 * Core scoring
 * ---------------------------------------------------------------- */

/**
 * Score every product, partition by threshold, and compute an article-level
 * confidence.
 *
 * @param array<int,array<string,mixed>> $products
 * @return array{verified:array, rejected:array, article_confidence:float, summary:array}
 */
function pr_factcheck_run( array $products ): array {
	$verified = array();
	$rejected = array();
	$summary  = array();
	$min_p    = pr_factcheck_threshold( 'min_product_confidence' );

	foreach ( $products as $p ) {
		$scored = pr_factcheck_score_product( $p );
		$summary[ $scored['asin'] ?: '(no-asin)' ] = array(
			'confidence' => $scored['confidence'],
			'missing'    => $scored['missing'],
			'flags'      => $scored['flags'],
		);
		if ( $scored['confidence'] >= $min_p ) {
			$p['_confidence'] = $scored['confidence'];
			$p['_flags']      = $scored['flags'];
			$verified[] = $p;
		} else {
			$rejected[] = array( 'asin' => $scored['asin'], 'reason' => $scored['flags'], 'confidence' => $scored['confidence'] );
		}
	}

	$article_conf = 0.0;
	if ( ! empty( $verified ) ) {
		$sum = 0.0;
		foreach ( $verified as $v ) { $sum += (float) $v['_confidence']; }
		$article_conf = round( $sum / count( $verified ), 3 );
	}

	return array(
		'verified'           => $verified,
		'rejected'           => $rejected,
		'article_confidence' => $article_conf,
		'summary'            => $summary,
	);
}

/**
 * Per-product confidence score.
 *
 * Required claims (each contributes evenly to base score):
 *   title, image, url, price, rating, review_count, brand
 *
 * Bonuses:
 *   + corroboration: each additional driver corroborating a field adds 0.02
 *     (capped at +0.10)
 *   + reasonable price (>0): +0.02
 *   + rating between 0–5 and review_count ≥ 25: +0.04
 *
 * Penalties:
 *   - title too short (<15 chars): -0.10
 *   - placeholder image (no http): -0.05
 *   - rating outside 0–5: -0.20 (hard flag)
 *
 * @return array{asin:string,confidence:float,missing:string[],flags:string[]}
 */
function pr_factcheck_score_product( array $p ): array {
	$asin = isset( $p['asin'] ) ? PR_Dedup::normalize( (string) $p['asin'] ) : '';
	$required = array( 'title', 'image', 'url', 'price', 'rating', 'review_count', 'brand' );
	$missing = array();
	$flags   = array();

	$present = 0;
	foreach ( $required as $key ) {
		$v = $p[ $key ] ?? null;
		$has = ! ( $v === null || $v === '' || $v === 0 || $v === '0' || $v === array() );
		if ( $has ) { $present++; } else { $missing[] = $key; }
	}
	$base = $present / count( $required );

	// Corroboration bonus.
	$corrob_fields = (array) ( $p['_corroborated_fields'] ?? array() );
	$corrob_hits   = array_sum( $corrob_fields );
	$base += min( 0.10, $corrob_hits * 0.02 );

	// Bonuses.
	if ( isset( $p['price'] ) && (float) $p['price'] > 0 ) { $base += 0.02; }
	$rating = isset( $p['rating'] ) ? (float) $p['rating'] : -1;
	$rc     = isset( $p['review_count'] ) ? (int) $p['review_count'] : 0;
	if ( $rating >= 0 && $rating <= 5 && $rc >= 25 ) { $base += 0.04; }

	// Penalties / hard flags.
	if ( isset( $p['title'] ) && mb_strlen( (string) $p['title'] ) < 15 ) {
		$base -= 0.10;
		$flags[] = 'title_too_short';
	}
	if ( isset( $p['image'] ) && stripos( (string) $p['image'], 'http' ) !== 0 ) {
		$base -= 0.05;
		$flags[] = 'image_not_url';
	}
	if ( $rating !== -1 && ( $rating < 0 || $rating > 5 ) ) {
		$base -= 0.20;
		$flags[] = 'rating_out_of_range';
	}

	$confidence = max( 0.0, min( 1.0, round( $base, 3 ) ) );
	return array(
		'asin'       => $asin,
		'confidence' => $confidence,
		'missing'    => $missing,
		'flags'      => $flags,
	);
}
