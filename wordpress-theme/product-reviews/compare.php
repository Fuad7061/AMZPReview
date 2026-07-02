<?php
/**
 * Compare page template — rendered at /compare/?asins=A,B,C
 *
 * @package ProductReviews
 */

get_header();
$products = function_exists( 'pr_compare_get_requested_products' ) ? pr_compare_get_requested_products() : array();
$slug     = 'compare';
?>
<div class="yf-container yf-compare-page">
	<header class="yf-archive__head">
		<h1><?php esc_html_e( 'Compare products', 'product-reviews' ); ?></h1>
		<p class="yf-archive__desc">
			<?php esc_html_e( 'Side-by-side comparison of up to four Amazon products from our reviews. Add ASINs to the URL like /compare/?asins=B0XXXX,B0YYYY.', 'product-reviews' ); ?>
		</p>
	</header>

	<form class="yf-compare-form" method="get" action="<?php echo esc_url( home_url( '/compare/' ) ); ?>">
		<label for="pr-compare-asins"><?php esc_html_e( 'ASINs (comma-separated, up to 4):', 'product-reviews' ); ?></label>
		<input id="pr-compare-asins" type="text" name="asins" value="<?php echo esc_attr( isset( $_GET['asins'] ) ? wp_unslash( $_GET['asins'] ) : '' ); ?>" placeholder="B0XXXXXXXX, B0YYYYYYYY" />
		<button type="submit" class="yf-cta yf-cta--sm"><?php esc_html_e( 'Compare', 'product-reviews' ); ?></button>
	</form>

	<?php if ( empty( $products ) ) : ?>
		<p class="yf-compare-empty"><?php esc_html_e( 'No products to compare yet. Paste a few ASINs above, or visit a review and use the share-to-compare buttons.', 'product-reviews' ); ?></p>
	<?php else : ?>
		<div class="yf-compare__scroll">
			<table class="yf-compare__table yf-compare__table--full">
				<thead>
					<tr>
						<th></th>
						<?php foreach ( $products as $p ) : ?>
							<th>
								<?php $img = isset( $p['image'] ) ? (string) $p['image'] : ''; ?>
								<?php if ( $img && function_exists( 'pr_render_remote_image' ) ) :
									echo pr_render_remote_image( $img, array( 'alt' => $p['title'] ?? '', 'class' => 'yf-compare__thumb', 'sizes' => '160px', 'width' => 160 ) ); // phpcs:ignore
								elseif ( $img ) : ?>
									<img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $p['title'] ?? '' ); ?>" class="yf-compare__thumb" loading="lazy" />
								<?php endif; ?>
								<div class="yf-compare__name"><?php echo esc_html( wp_trim_words( (string) ( $p['title'] ?? '' ), 10 ) ); ?></div>
							</th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Brand', 'product-reviews' ); ?></th>
						<?php foreach ( $products as $p ) : ?>
							<td><?php echo esc_html( $p['brand'] ?? '—' ); ?></td>
						<?php endforeach; ?>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Rating', 'product-reviews' ); ?></th>
						<?php foreach ( $products as $p ) : ?>
							<td><?php echo ! empty( $p['rating'] ) ? esc_html( number_format( (float) $p['rating'], 1 ) ) . ' / 5' : '—'; ?></td>
						<?php endforeach; ?>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Price', 'product-reviews' ); ?></th>
						<?php foreach ( $products as $p ) : ?>
							<td><?php echo ! empty( $p['price'] ) && function_exists( 'yadfood_format_price' ) ? esc_html( yadfood_format_price( $p['price'] ) ) : esc_html( $p['price'] ?? '—' ); ?></td>
						<?php endforeach; ?>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Pros', 'product-reviews' ); ?></th>
						<?php foreach ( $products as $p ) :
							$pros = isset( $p['pros'] ) && is_array( $p['pros'] ) ? array_slice( $p['pros'], 0, 4 ) : array(); ?>
							<td>
								<?php if ( $pros ) : ?>
									<ul class="yf-compare__list">
										<?php foreach ( $pros as $pro ) : ?>
											<li>+ <?php echo esc_html( $pro ); ?></li>
										<?php endforeach; ?>
									</ul>
								<?php else : ?>—<?php endif; ?>
							</td>
						<?php endforeach; ?>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cons', 'product-reviews' ); ?></th>
						<?php foreach ( $products as $p ) :
							$cons = isset( $p['cons'] ) && is_array( $p['cons'] ) ? array_slice( $p['cons'], 0, 4 ) : array(); ?>
							<td>
								<?php if ( $cons ) : ?>
									<ul class="yf-compare__list">
										<?php foreach ( $cons as $con ) : ?>
											<li>− <?php echo esc_html( $con ); ?></li>
										<?php endforeach; ?>
									</ul>
								<?php else : ?>—<?php endif; ?>
							</td>
						<?php endforeach; ?>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Reviewed in', 'product-reviews' ); ?></th>
						<?php foreach ( $products as $p ) : ?>
							<td>
								<?php if ( ! empty( $p['post_url'] ) ) : ?>
									<a href="<?php echo esc_url( $p['post_url'] ); ?>"><?php esc_html_e( 'Read review', 'product-reviews' ); ?></a>
								<?php else : ?>—<?php endif; ?>
							</td>
						<?php endforeach; ?>
					</tr>
					<tr>
						<th></th>
						<?php foreach ( $products as $p ) :
							$url = function_exists( 'yadfood_amazon_url' ) ? yadfood_amazon_url( $p['asin'] ?? '', $p['post_slug'] ?? $slug ) : ''; ?>
							<td>
								<?php if ( $url ) : ?>
									<a class="yf-cta yf-cta--sm" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="nofollow sponsored noopener"><?php esc_html_e( 'Check price', 'product-reviews' ); ?></a>
								<?php endif; ?>
							</td>
						<?php endforeach; ?>
					</tr>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
<?php
get_footer();
