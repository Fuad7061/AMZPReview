<?php
/**
 * Final verdict — role-based recap (Best overall / Best value / Best budget).
 * Expects: yf_products (array), yf_post_id (int)
 */
$products = get_query_var( 'yf_products' );
$post_id  = (int) get_query_var( 'yf_post_id' );
if ( empty( $products ) || count( $products ) < 2 ) return;
$slug = get_post_field( 'post_name', $post_id );

// Assign roles: overall = editor's choice / rank 1; value = best_value; budget = cheapest not-yet-used.
$overall = null; $value = null; $budget = null;
foreach ( $products as $p ) {
	if ( ! $overall && ( ( isset( $p['badge'] ) && 'editors_choice' === $p['badge'] ) || (int) $p['rank'] === 1 ) ) { $overall = $p; break; }
}
if ( ! $overall ) $overall = $products[0];
foreach ( $products as $p ) {
	if ( $p['asin'] === $overall['asin'] ) continue;
	if ( isset( $p['badge'] ) && 'best_value' === $p['badge'] ) { $value = $p; break; }
}
if ( ! $value ) foreach ( $products as $p ) { if ( $p['asin'] !== $overall['asin'] ) { $value = $p; break; } }
$sorted = $products;
usort( $sorted, fn( $a, $b ) => (float) ( $a['price'] ?? 9999 ) <=> (float) ( $b['price'] ?? 9999 ) );
foreach ( $sorted as $p ) {
	if ( ! empty( $overall ) && $p['asin'] === $overall['asin'] ) continue;
	if ( ! empty( $value )   && $p['asin'] === $value['asin']   ) continue;
	$budget = $p; break;
}

$roles = array();
if ( $overall ) $roles[] = array( 'icon' => '🏆', 'label' => 'If you want the best overall', 'blurb' => 'Top scores across our tests — the safest pick for most shoppers.', 'p' => $overall );
if ( $value   ) $roles[] = array( 'icon' => '💰', 'label' => 'If you want the best value',   'blurb' => 'Nearly the same experience for noticeably less money.',        'p' => $value );
if ( $budget  ) $roles[] = array( 'icon' => '👛', 'label' => 'If you\'re on a budget',       'blurb' => 'Lowest price here that still meets our quality bar.',         'p' => $budget );

$product_name = get_the_title( $post_id );
?>
<section class="yf-verdict" id="verdict" aria-labelledby="verdict-heading">
	<div class="yf-verdict__head">
		<span class="yf-verdict__badge">Final verdict</span>
		<h2 id="verdict-heading" class="yf-verdict__title">Which one should you buy?</h2>
	</div>
	<ul class="yf-verdict__list">
		<?php foreach ( $roles as $r ) :
			$p = $r['p'];
			$url = yadfood_amazon_url( $p['asin'], $slug );
			$price = ! empty( $p['price'] ) ? ( function_exists( 'pr_price_display' ) ? pr_price_display( $p['price'] ) : yadfood_format_price( $p['price'] ) ) : ''; ?>
			<li class="yf-verdict__row">
				<div class="yf-verdict__main">
					<span class="yf-verdict__icon" aria-hidden="true"><?php echo esc_html( $r['icon'] ); ?></span>
					<div style="min-width:0;">
						<div class="yf-verdict__role"><?php echo esc_html( $r['label'] ); ?></div>
						<div class="yf-verdict__prod"><?php if ( ! empty( $p['brand'] ) ) echo esc_html( $p['brand'] ) . ' — '; ?><?php echo esc_html( $p['title'] ); ?></div>
						<p class="yf-verdict__blurb"><?php echo esc_html( $r['blurb'] ); ?></p>
					</div>
				</div>
				<div class="yf-verdict__end">
					<?php if ( $price ) : ?>
						<div class="yf-verdict__price">
							<?php echo esc_html( $price ); ?>
							<?php if ( ! empty( $p['savings_percentage'] ) ) : ?>
								<span class="yf-verdict__save">Save <?php echo (int) $p['savings_percentage']; ?>%</span>
							<?php endif; ?>
						</div>
					<?php endif; ?>
					<?php if ( $url ) : ?>
						<a class="yf-cta yf-cta--sm yf-cta--ink" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="nofollow sponsored noopener">Check price ↗</a>
					<?php endif; ?>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
	<p class="yf-verdict__note">We earn a commission from qualifying purchases at no extra cost to you.</p>
</section>
