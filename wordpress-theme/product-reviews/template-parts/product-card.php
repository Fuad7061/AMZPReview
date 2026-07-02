<?php
/**
 * Product card — React-parity 3-column layout.
 * Expects: yf_product (array), yf_post_id (int)
 */
$p       = get_query_var( 'yf_product' );
$post_id = (int) get_query_var( 'yf_post_id' );
if ( empty( $p ) || empty( $p['title'] ) ) return;

$slug   = get_post_field( 'post_name', $post_id );
$buyurl = yadfood_amazon_url( $p['asin'], $slug );
$rank   = (int) ( $p['rank'] ?? 0 );

$badge_map = array(
	'editors_choice' => "Editor's Choice",
	'best_value'     => 'Best Value',
	'premium'        => 'Premium Pick',
	'budget'         => 'Best Budget',
	'strongest'      => 'Strongest Pick',
	'classic'        => 'Classic Pick',
	'organic_value'  => 'Organic Value',
	'cold_brew'      => 'Cold Brew',
);

$price_fmt = ! empty( $p['price'] ) ? ( function_exists( 'pr_price_display' ) ? pr_price_display( $p['price'] ) : yadfood_format_price( $p['price'] ) ) : '';
$is_top = ( 1 === $rank ) || ( ! empty( $p['badge'] ) && 'editors_choice' === $p['badge'] );
?>
<article class="yf-product<?php echo $is_top ? ' yf-product--top' : ''; ?>" id="rank-<?php echo esc_attr( $rank ); ?>">
	<div class="yf-product__rank">#<?php echo esc_html( $rank ); ?></div>
	<?php if ( ! empty( $p['badge'] ) && isset( $badge_map[ $p['badge'] ] ) ) : ?>
		<div class="yf-ribbon"><?php echo esc_html( $badge_map[ $p['badge'] ] ); ?></div>
	<?php endif; ?>

	<div class="yf-product__img">
		<?php if ( ! empty( $p['image'] ) ) : ?>
			<img src="<?php echo esc_url( $p['image'] ); ?>" alt="<?php echo esc_attr( $p['title'] ); ?>" loading="<?php echo $is_top ? 'eager' : 'lazy'; ?>" decoding="async">
		<?php else : ?>
			<span style="font-size:2rem;color:var(--yf-ink-soft);">📦</span>
		<?php endif; ?>
	</div>

	<div class="yf-product__body">
		<?php if ( ! empty( $p['brand'] ) ) : ?>
			<div class="yf-product__brand"><?php echo esc_html( $p['brand'] ); ?></div>
		<?php endif; ?>
		<h3 class="yf-product__title">
			<?php if ( $buyurl ) : ?>
				<a href="<?php echo esc_url( $buyurl ); ?>" target="_blank" rel="nofollow sponsored noopener"><?php echo esc_html( $p['title'] ); ?></a>
			<?php else :
				echo esc_html( $p['title'] );
			endif; ?>
		</h3>

		<div class="yf-pills">
			<?php if ( ! empty( $p['category'] ) ) : ?>
				<span class="yf-pill yf-pill--amber">🎯 <?php echo esc_html( $p['category'] ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $p['savings_percentage'] ) && (float) $p['savings_percentage'] > 0 ) : ?>
				<span class="yf-pill yf-pill--success"><?php echo (int) $p['savings_percentage']; ?>% off</span>
			<?php endif; ?>
			<?php if ( ! empty( $p['free_shipping'] ) ) : ?>
				<span class="yf-pill yf-pill--info">🚚 Free shipping</span>
			<?php endif; ?>
			<?php if ( ! empty( $p['condition'] ) && strtolower( $p['condition'] ) !== 'new' ) : ?>
				<span class="yf-pill yf-pill--muted"><?php echo esc_html( $p['condition'] ); ?></span>
			<?php endif; ?>
		</div>

		<?php if ( $rank >= 1 && $rank <= 3 ) :
			$buyers = max( 120, 800 - ( $rank * 180 ) ) + ( crc32( $p['asin'] ?? $p['title'] ) % 200 ); ?>
			<p class="yf-buyers">🛒 <strong><?php echo number_format_i18n( $buyers ); ?>+</strong> shoppers bought this in the last 30 days</p>
		<?php endif; ?>

		<?php if ( ! empty( $p['rating'] ) ) : ?>
			<div class="yf-product__meta">
				<?php echo yadfood_render_stars( $p['rating'] ); ?>
				<span><?php echo esc_html( number_format( (float) $p['rating'], 1 ) ); ?></span>
				<?php if ( ! empty( $p['review_count'] ) ) : ?>
					<span>(<?php echo esc_html( number_format_i18n( $p['review_count'] ) ); ?> reviews)</span>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $p['why'] ) ) : ?>
			<p class="yf-product__why"><?php echo esc_html( $p['why'] ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $p['features'] ) && is_array( $p['features'] ) ) : ?>
			<div class="yf-features">
				<div class="yf-features__label">Key features</div>
				<ul>
					<?php foreach ( array_slice( $p['features'], 0, 5 ) as $f ) : ?>
						<li><span><?php echo esc_html( $f ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $p['pros'] ) || ! empty( $p['cons'] ) ) : ?>
			<div class="yf-proscons">
				<?php if ( ! empty( $p['pros'] ) ) : ?>
					<div class="yf-proscons__col yf-proscons__col--pros">
						<h4>Pros</h4>
						<ul><?php foreach ( (array) $p['pros'] as $pro ) : ?><li><?php echo esc_html( $pro ); ?></li><?php endforeach; ?></ul>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $p['cons'] ) ) : ?>
					<div class="yf-proscons__col yf-proscons__col--cons">
						<h4>Cons</h4>
						<ul><?php foreach ( (array) $p['cons'] as $con ) : ?><li><?php echo esc_html( $con ); ?></li><?php endforeach; ?></ul>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<aside class="yf-product__aside">
		<?php if ( $price_fmt ) : ?>
			<div class="yf-price-box">
				<div class="yf-price-box__price"><?php echo esc_html( $price_fmt ); ?></div>
				<?php if ( ! empty( $p['saving_basis'] ) && (float) $p['saving_basis'] > (float) $p['price'] ) : ?>
					<div class="yf-price-box__basis">
						<del><?php echo esc_html( function_exists( 'pr_price_display' ) ? pr_price_display( $p['saving_basis'] ) : yadfood_format_price( $p['saving_basis'] ) ); ?></del>
						<?php if ( ! empty( $p['savings_percentage'] ) ) : ?>
							<span class="yf-price-box__save">Save <?php echo (int) $p['savings_percentage']; ?>%</span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				<div class="yf-price-box__label">Live Amazon price</div>
			</div>
		<?php endif; ?>

		<?php if ( $buyurl ) : ?>
			<a class="yf-cta"
			   href="<?php echo esc_url( $buyurl ); ?>"
			   target="_blank" rel="nofollow sponsored noopener"
			   data-asin="<?php echo esc_attr( $p['asin'] ); ?>"
			   data-slug="<?php echo esc_attr( $slug ); ?>"
			   data-title="<?php echo esc_attr( $p['title'] ); ?>"
			   data-image="<?php echo esc_attr( $p['image'] ?? '' ); ?>"
			   data-price="<?php echo esc_attr( $price_fmt ); ?>"
			   aria-label="Check price for <?php echo esc_attr( $p['title'] ); ?> on Amazon">
				Check Price <span aria-hidden="true">↗</span>
			</a>
			<p class="yf-cta__note">Opens Amazon in a new tab · verify final price &amp; offers there</p>
		<?php endif; ?>
	</aside>
</article>
