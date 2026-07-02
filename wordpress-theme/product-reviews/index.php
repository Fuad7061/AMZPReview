<?php get_header(); ?>

<div class="yf-container">
	<section class="yf-hero">
		<span class="yf-hero__badge">Updated every hour with live Amazon data</span>
		<h1 class="yf-hero__title">Find the <span class="yf-accent">best</span> product.<br>Skip the research.</h1>
		<p class="yf-hero__sub">Search any product. We instantly rank the top 10 on Amazon, compare them side-by-side, and show you the best value — fact-checked against live listings.</p>
		<form class="yf-hero__search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<input type="search" name="s" placeholder="What are you shopping for?">
			<button type="submit">Search</button>
		</form>
		<?php if ( function_exists( 'pr_default_product_catalog' ) ) : ?>
			<div class="yf-quick-search" aria-label="Trending searches">
				<span class="yf-quick-search__label">Trending:</span>
				<?php foreach ( pr_default_product_catalog() as $group ) : ?>
					<a class="yf-quick-search__pill" href="<?php echo esc_url( home_url( '/?s=' . rawurlencode( $group['keyword'] ) ) ); ?>"><?php echo esc_html( $group['keyword'] ); ?></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>

	<?php
	$cat_terms = get_terms( array( 'taxonomy' => 'review_category', 'hide_empty' => false, 'number' => 12 ) );
	$emojis = array( '🎧', '⌚', '🍟', '🤖', '📺', '☕', '🧹', '🛴', '✂️', '🛏️', '🥤', '👟' );
	if ( ! is_wp_error( $cat_terms ) && ! empty( $cat_terms ) ) : ?>
		<section class="yf-categories">
			<h2 class="yf-section-title">Popular categories</h2>
			<p style="color:var(--yf-ink-soft);margin-top:-.5rem;font-size:.9rem;">Start with one of these, or search anything above.</p>
			<div class="yf-cat-grid">
				<?php foreach ( $cat_terms as $i => $term ) :
					$link = get_term_link( $term );
					if ( is_wp_error( $link ) ) continue;
					$emoji = $emojis[ $i % count( $emojis ) ]; ?>
					<a class="yf-cat-tile" href="<?php echo esc_url( $link ); ?>">
						<span class="yf-cat-tile__emoji" aria-hidden="true"><?php echo esc_html( $emoji ); ?></span>
						<span class="yf-cat-tile__name"><?php echo esc_html( $term->name ); ?></span>
						<span class="yf-cat-tile__count">Top 10 picks →</span>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<section class="yf-trust">
		<div class="yf-trust__card">
			<span class="yf-trust__icon">🏆</span>
			<h3 class="yf-trust__title">Expert-curated rankings</h3>
			<p class="yf-trust__body">Rule-based scoring that's the same for every product. No paid placements, no surprise sponsors.</p>
		</div>
		<div class="yf-trust__card">
			<span class="yf-trust__icon">📉</span>
			<h3 class="yf-trust__title">Live price tracking</h3>
			<p class="yf-trust__body">Prices and availability refresh hourly. We show timestamps so you always know how fresh the data is.</p>
		</div>
		<div class="yf-trust__card">
			<span class="yf-trust__icon">🛡️</span>
			<h3 class="yf-trust__title">Fact-grounded reviews</h3>
			<p class="yf-trust__body">Every spec on this site comes from the Amazon listing itself — we never invent claims we can't back up.</p>
		</div>
	</section>

	<section class="yf-latest">
		<h2 class="yf-section-title">Latest reviews</h2>
		<div class="yf-grid yf-grid--3">
		<?php
		$latest = new WP_Query( array( 'post_type' => 'review', 'posts_per_page' => 9, 'no_found_rows' => true ) );
		if ( $latest->have_posts() ) :
			while ( $latest->have_posts() ) : $latest->the_post(); ?>
			<a class="yf-card" href="<?php the_permalink(); ?>">
				<?php if ( has_post_thumbnail() ) : ?>
					<div class="yf-card__img"><?php the_post_thumbnail( 'pr-card' ); ?></div>
				<?php endif; ?>
				<div class="yf-card__body">
					<h3 class="yf-card__title"><?php the_title(); ?></h3>
					<p class="yf-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
					<span class="yf-card__meta">Updated <?php echo esc_html( function_exists( 'yadfood_last_updated' ) ? yadfood_last_updated() : get_the_modified_date() ); ?></span>
				</div>
			</a>
			<?php endwhile; wp_reset_postdata();
		else :
			$fallback_groups = function_exists( 'pr_default_product_catalog' ) ? pr_default_product_catalog() : array();
			foreach ( $fallback_groups as $group ) :
				$top = isset( $group['products'][0] ) ? $group['products'][0] : array();
				$url = home_url( '/?s=' . rawurlencode( $group['keyword'] ) ); ?>
				<a class="yf-card" href="<?php echo esc_url( $url ); ?>">
					<?php if ( ! empty( $top['image'] ) ) : ?>
						<div class="yf-card__img"><img src="<?php echo esc_url( $top['image'] ); ?>" alt="<?php echo esc_attr( $group['title'] ); ?>" loading="lazy"></div>
					<?php endif; ?>
					<div class="yf-card__body">
						<h3 class="yf-card__title"><?php echo esc_html( $group['title'] ); ?></h3>
						<p class="yf-card__excerpt"><?php echo esc_html( wp_trim_words( $group['tldr'], 18 ) ); ?></p>
						<span class="yf-card__meta">Sample review — edit anytime</span>
					</div>
				</a>
			<?php endforeach;
		endif; ?>
		</div>
	</section>
</div>

<?php get_footer(); ?>
