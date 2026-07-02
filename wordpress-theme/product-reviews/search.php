<?php get_header(); ?>
<div class="yf-container">
	<header class="yf-archive__head">
		<h1>Results for "<?php echo esc_html( get_search_query() ); ?>"</h1>
		<?php
		$q_raw = get_search_query();
		$norm  = function_exists( 'yadfood_normalize_query' ) ? yadfood_normalize_query( $q_raw ) : strtolower( $q_raw );
		if ( $norm && $norm !== strtolower( $q_raw ) ) : ?>
			<p class="yf-archive__desc">We searched for <strong><?php echo esc_html( $norm ); ?></strong> to get you the most relevant picks.</p>
		<?php endif; ?>
	</header>

	<?php if ( have_posts() ) : ?>
		<div class="yf-grid yf-grid--3">
		<?php while ( have_posts() ) : the_post(); ?>
			<a class="yf-card" href="<?php the_permalink(); ?>">
				<?php if ( has_post_thumbnail() ) : ?>
					<div class="yf-card__img"><?php the_post_thumbnail( 'pr-card' ); ?></div>
				<?php endif; ?>
				<div class="yf-card__body">
					<h3 class="yf-card__title"><?php the_title(); ?></h3>
					<p class="yf-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 18 ) ); ?></p>
				</div>
			</a>
		<?php endwhile; ?>
		</div>
		<div class="yf-pagination"><?php the_posts_pagination(); ?></div>
	<?php else :
		$product_results = function_exists( 'pr_product_search' ) ? pr_product_search( $norm ?: $q_raw, 7 ) : array();
		if ( is_wp_error( $product_results ) ) {
			$product_results = function_exists( 'pr_default_products_for_keyword' ) ? pr_default_products_for_keyword( $norm ?: $q_raw, 7 ) : array();
		}
		if ( ! empty( $product_results ) ) : ?>
			<div class="yf-grid yf-grid--3">
			<?php foreach ( $product_results as $p ) :
				$asin = isset( $p['asin'] ) ? (string) $p['asin'] : '';
				$url  = ! empty( $p['url'] ) ? (string) $p['url'] : ( function_exists( 'yadfood_amazon_url' ) ? yadfood_amazon_url( $asin, sanitize_title( $norm ?: $q_raw ) ) : '' );
				?>
				<article class="yf-card yf-card--product-result">
					<?php if ( ! empty( $p['image'] ) ) : ?>
						<div class="yf-card__img">
							<img src="<?php echo esc_url( $p['image'] ); ?>" alt="<?php echo esc_attr( $p['title'] ?? '' ); ?>" loading="lazy">
						</div>
					<?php endif; ?>
					<div class="yf-card__body">
						<h3 class="yf-card__title"><?php echo esc_html( $p['title'] ?? '' ); ?></h3>
						<p class="yf-card__excerpt">
							<?php if ( ! empty( $p['rating'] ) ) : ?><?php echo esc_html( number_format( (float) $p['rating'], 1 ) ); ?>★<?php endif; ?>
							<?php if ( ! empty( $p['review_count'] ) ) : ?> · <?php echo esc_html( number_format_i18n( (int) $p['review_count'] ) ); ?> reviews<?php endif; ?>
							<?php if ( ! empty( $p['price'] ) ) : ?> · <?php echo esc_html( function_exists( 'pr_price_display' ) ? pr_price_display( $p['price'] ) : (string) $p['price'] ); ?><?php endif; ?>
						</p>
						<?php if ( $url ) : ?><a class="yf-cta yf-cta--sm" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="nofollow sponsored noopener"><?php esc_html_e( 'Check Price', 'product-reviews' ); ?></a><?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p class="yf-empty">No reviews matched your search. Try one of the popular categories below.</p>
		<?php endif; ?>

		<?php
		// Always show category browse + related reviews on empty/fallback searches.
		$cat_terms = get_terms( array( 'taxonomy' => 'review_category', 'hide_empty' => false, 'number' => 12 ) );
		if ( ! is_wp_error( $cat_terms ) && ! empty( $cat_terms ) ) : ?>
			<section class="yf-categories">
				<h2 class="yf-section-title">Browse Categories</h2>
				<div class="yf-cat-grid">
					<?php foreach ( $cat_terms as $term ) :
						$link = get_term_link( $term );
						if ( is_wp_error( $link ) ) { continue; }
						?>
						<a class="yf-cat-tile" href="<?php echo esc_url( $link ); ?>">
							<span class="yf-cat-tile__name"><?php echo esc_html( $term->name ); ?></span>
							<span class="yf-cat-tile__count"><?php echo (int) $term->count; ?> reviews</span>
						</a>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php
		$related = new WP_Query( array( 'post_type' => 'review', 'posts_per_page' => 6, 'no_found_rows' => true ) );
		if ( $related->have_posts() ) : ?>
			<section class="yf-latest">
				<h2 class="yf-section-title">Popular Reviews</h2>
				<div class="yf-grid yf-grid--3">
					<?php while ( $related->have_posts() ) : $related->the_post(); ?>
						<a class="yf-card yf-card--text" href="<?php the_permalink(); ?>">
							<div class="yf-card__body">
								<h3 class="yf-card__title"><?php the_title(); ?></h3>
								<p class="yf-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 18 ) ); ?></p>
							</div>
						</a>
					<?php endwhile; wp_reset_postdata(); ?>
				</div>
			</section>
		<?php endif; ?>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
