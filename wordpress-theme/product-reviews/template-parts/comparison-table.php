<?php
/**
 * Side-by-side comparison table — React-parity feature matrix.
 * Expects: yf_products (array), yf_post_id (int)
 */
$products = get_query_var( 'yf_products' );
$post_id  = (int) get_query_var( 'yf_post_id' );
if ( empty( $products ) ) return;
$slug = get_post_field( 'post_name', $post_id );

// Aggregate feature list across all products (cap to 6 for readability).
$all_features = array();
foreach ( $products as $p ) {
	if ( empty( $p['features'] ) || ! is_array( $p['features'] ) ) continue;
	foreach ( $p['features'] as $f ) {
		$f = trim( (string) $f );
		if ( $f && ! in_array( $f, $all_features, true ) ) $all_features[] = $f;
	}
}
$all_features = array_slice( $all_features, 0, 6 );
?>
<section class="yf-compare" id="compare">
	<h2 class="yf-section-title">Compare side-by-side</h2>
	<div class="yf-compare__scroll">
		<table class="yf-compare__table">
			<thead>
				<tr>
					<th>Compare</th>
					<?php foreach ( $products as $p ) : ?>
						<th class="yf-compare__prodcell">
							<a href="#rank-<?php echo (int) $p['rank']; ?>">
								<?php if ( ! empty( $p['image'] ) ) : ?>
									<span class="yf-compare__thumb"><img src="<?php echo esc_url( $p['image'] ); ?>" alt=""></span>
								<?php endif; ?>
								<strong>#<?php echo (int) $p['rank']; ?></strong>
								<span class="yf-compare__prodname"><?php echo esc_html( wp_trim_words( $p['title'], 8 ) ); ?></span>
							</a>
						</th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<tr>
					<th>Price</th>
					<?php foreach ( $products as $p ) : ?>
						<td><strong><?php echo ! empty( $p['price'] ) ? esc_html( function_exists( 'pr_price_display' ) ? pr_price_display( $p['price'] ) : yadfood_format_price( $p['price'] ) ) : '—'; ?></strong></td>
					<?php endforeach; ?>
				</tr>
				<tr>
					<th>Rating</th>
					<?php foreach ( $products as $p ) : ?>
						<td><?php echo ! empty( $p['rating'] ) ? esc_html( number_format( (float) $p['rating'], 1 ) . '★' ) : '—'; ?></td>
					<?php endforeach; ?>
				</tr>
				<tr>
					<th>Brand</th>
					<?php foreach ( $products as $p ) : ?>
						<td><?php echo ! empty( $p['brand'] ) ? esc_html( $p['brand'] ) : '—'; ?></td>
					<?php endforeach; ?>
				</tr>
				<tr>
					<th>Free shipping</th>
					<?php foreach ( $products as $p ) : ?>
						<td><?php echo ! empty( $p['free_shipping'] ) ? '<span class="yf-compare__check">✓</span>' : '<span class="yf-compare__minus">—</span>'; ?></td>
					<?php endforeach; ?>
				</tr>
				<?php foreach ( $all_features as $feat ) : ?>
					<tr>
						<th><?php echo esc_html( $feat ); ?></th>
						<?php foreach ( $products as $p ) :
							$has = ! empty( $p['features'] ) && is_array( $p['features'] ) && in_array( $feat, array_map( 'trim', $p['features'] ), true ); ?>
							<td><?php echo $has ? '<span class="yf-compare__check">✓</span>' : '<span class="yf-compare__minus">—</span>'; ?></td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th>Buy</th>
					<?php foreach ( $products as $p ) :
						$url = yadfood_amazon_url( $p['asin'], $slug ); ?>
						<td><?php if ( $url ) : ?><a class="yf-cta yf-cta--sm yf-cta--ink" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="nofollow sponsored noopener">View ↗</a><?php endif; ?></td>
					<?php endforeach; ?>
				</tr>
			</tbody>
		</table>
	</div>
</section>
