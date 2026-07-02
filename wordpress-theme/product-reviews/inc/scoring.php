<?php
/**
 * Editorial Scoring + Ranking Transparency (Milestone 16).
 *
 * Computes a 0-100 editorial score per product from Amazon signals
 * (rating, review volume, price competitiveness, freshness, Prime/badges)
 * and exposes a "Why #1?" breakdown tooltip + factor weights.
 *
 * Pure-PHP, no extra cron, no extra HTTP. Cached per ASIN in a transient.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! defined( 'PR_SCORE_TTL' ) )    { define( 'PR_SCORE_TTL', 6 * HOUR_IN_SECONDS ); }
if ( ! defined( 'PR_SCORE_VERSION' ) ){ define( 'PR_SCORE_VERSION', '1' ); }

/**
 * Default weights. Sum should be 100. Filterable per category.
 */
function pr_score_weights( $category_slug = '' ) {
	$w = array(
		'rating'   => 35, // average customer rating
		'reviews'  => 25, // log-scaled review volume
		'price'    => 15, // relative price within the list
		'badges'   => 10, // Amazon's Choice / Best Seller / Climate Pledge
		'prime'    => 5,  // Prime eligibility
		'fresh'    => 10, // data freshness (last checked)
	);
	return apply_filters( 'pr_score_weights', $w, $category_slug );
}

/**
 * Compute the breakdown for a single product within a list context.
 *
 * @param array $p          product row (asin,title,rating,review_count,price,prime,badges...)
 * @param array $list_stats stats over the surrounding list (price_min,price_max,reviews_max)
 * @return array{score:int, factors:array<string,array{value:float,weight:int,contribution:float,label:string}>}
 */
function pr_score_product( $p, $list_stats = array() ) {
	$weights = pr_score_weights( isset( $p['_category'] ) ? $p['_category'] : '' );

	$rating       = isset( $p['rating'] ) ? (float) $p['rating'] : 0.0;
	$review_count = isset( $p['review_count'] ) ? (int) $p['review_count'] : 0;
	$price        = isset( $p['price'] ) ? (float) $p['price'] : 0.0;
	$prime        = ! empty( $p['prime'] );
	$badges       = isset( $p['badges'] ) && is_array( $p['badges'] ) ? $p['badges'] : array();
	$last_checked = isset( $p['_last_checked'] ) ? (int) $p['_last_checked'] : 0;

	$price_min   = isset( $list_stats['price_min'] )   ? (float) $list_stats['price_min']   : $price;
	$price_max   = isset( $list_stats['price_max'] )   ? (float) $list_stats['price_max']   : $price;
	$reviews_max = isset( $list_stats['reviews_max'] ) ? (int) $list_stats['reviews_max']   : max( 1, $review_count );

	// Normalize each factor to 0..1.
	$f_rating  = max( 0, min( 1, ( $rating - 3.0 ) / 2.0 ) );             // 3.0 → 0, 5.0 → 1
	$f_reviews = $reviews_max > 1
		? min( 1, log10( max( 1, $review_count ) ) / log10( max( 10, $reviews_max ) ) )
		: 0.0;
	$f_price   = ( $price_max > $price_min && $price > 0 )
		? 1 - ( ( $price - $price_min ) / ( $price_max - $price_min ) )
		: ( $price > 0 ? 1 : 0 );
	$f_badges  = min( 1, count( array_intersect( $badges, array( 'amazons_choice', 'best_seller', 'climate_pledge' ) ) ) / 2 );
	$f_prime   = $prime ? 1 : 0;
	$age_days  = $last_checked ? max( 0, ( time() - $last_checked ) / DAY_IN_SECONDS ) : 999;
	$f_fresh   = max( 0, min( 1, 1 - ( $age_days / 180 ) ) );

	$factors = array(
		'rating'  => array( 'label' => 'Customer rating',  'value' => $f_rating,  'weight' => $weights['rating'] ),
		'reviews' => array( 'label' => 'Review volume',    'value' => $f_reviews, 'weight' => $weights['reviews'] ),
		'price'   => array( 'label' => 'Price position',   'value' => $f_price,   'weight' => $weights['price'] ),
		'badges'  => array( 'label' => 'Amazon badges',    'value' => $f_badges,  'weight' => $weights['badges'] ),
		'prime'   => array( 'label' => 'Prime shipping',   'value' => $f_prime,   'weight' => $weights['prime'] ),
		'fresh'   => array( 'label' => 'Data freshness',   'value' => $f_fresh,   'weight' => $weights['fresh'] ),
	);

	$score = 0.0;
	foreach ( $factors as $k => $row ) {
		$contrib = $row['value'] * $row['weight'];
		$factors[ $k ]['contribution'] = round( $contrib, 1 );
		$score += $contrib;
	}

	return array(
		'score'   => (int) round( $score ),
		'factors' => $factors,
	);
}

/**
 * Compute scores for an entire list (one pass) and return list_stats + scored rows.
 *
 * @param array $products
 * @return array{products:array, stats:array}
 */
function pr_score_list( $products ) {
	if ( empty( $products ) || ! is_array( $products ) ) {
		return array( 'products' => array(), 'stats' => array() );
	}
	$prices  = array_filter( wp_list_pluck( $products, 'price' ), 'is_numeric' );
	$reviews = array_filter( wp_list_pluck( $products, 'review_count' ), 'is_numeric' );
	$stats = array(
		'price_min'   => $prices  ? min( $prices )  : 0,
		'price_max'   => $prices  ? max( $prices )  : 0,
		'reviews_max' => $reviews ? max( $reviews ) : 1,
	);
	foreach ( $products as $i => $p ) {
		$result = pr_score_product( $p, $stats );
		$products[ $i ]['_score']    = $result['score'];
		$products[ $i ]['_factors']  = $result['factors'];
	}
	return array( 'products' => $products, 'stats' => $stats );
}

/**
 * Render an inline "Why #1?" breakdown.
 *
 * @param array $p product with _score + _factors set
 */
function pr_render_score_breakdown( $p ) {
	if ( empty( $p['_factors'] ) ) { return ''; }
	$score = isset( $p['_score'] ) ? (int) $p['_score'] : 0;
	$rank  = isset( $p['rank'] ) ? (int) $p['rank'] : 0;

	ob_start();
	?>
	<details class="pr-score" data-rank="<?php echo esc_attr( $rank ); ?>">
		<summary class="pr-score__summary">
			<span class="pr-score__num"><?php echo esc_html( $score ); ?><small>/100</small></span>
			<span class="pr-score__label">
				<?php echo 1 === $rank ? esc_html__( 'Why #1?', 'product-reviews' ) : esc_html__( 'Why this rank?', 'product-reviews' ); ?>
			</span>
		</summary>
		<div class="pr-score__panel" role="region" aria-label="<?php esc_attr_e( 'Editorial score breakdown', 'product-reviews' ); ?>">
			<table class="pr-score__table">
				<thead><tr>
					<th><?php esc_html_e( 'Factor', 'product-reviews' ); ?></th>
					<th><?php esc_html_e( 'Weight', 'product-reviews' ); ?></th>
					<th><?php esc_html_e( 'Score', 'product-reviews' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $p['_factors'] as $row ) :
					$pct = (int) round( $row['value'] * 100 ); ?>
					<tr>
						<td><?php echo esc_html( $row['label'] ); ?></td>
						<td><?php echo (int) $row['weight']; ?>%</td>
						<td>
							<div class="pr-score__bar"><span style="width:<?php echo (int) $pct; ?>%"></span></div>
							<span class="pr-score__pts"><?php echo esc_html( number_format_i18n( $row['contribution'], 1 ) ); ?></span>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="pr-score__note"><?php esc_html_e( 'Scores are computed from live Amazon signals (rating, review volume, price position, badges, Prime, data freshness). Weights are editorial and may vary by category.', 'product-reviews' ); ?></p>
		</div>
	</details>
	<?php
	return ob_get_clean();
}
