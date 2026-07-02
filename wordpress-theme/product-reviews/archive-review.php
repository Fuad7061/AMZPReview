<?php get_header(); ?>
<div class="yf-container yf-archive">
	<header class="yf-archive__head">
		<h1><?php
			if ( is_tax() ) {
				single_term_title();
			} else {
				post_type_archive_title();
			}
		?></h1>
		<?php the_archive_description( '<p class="yf-archive__desc">', '</p>' ); ?>
	</header>

	<div class="yf-archive__layout">
		<?php if ( function_exists( 'pr_facets_render' ) ) { pr_facets_render(); } ?>

		<div class="yf-archive__main">
			<div class="yf-grid yf-grid--3">
			<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
				<a class="yf-card" href="<?php the_permalink(); ?>">
					<?php if ( has_post_thumbnail() ) : ?>
						<div class="yf-card__img"><?php the_post_thumbnail( 'yadfood-card' ); ?></div>
					<?php endif; ?>
					<div class="yf-card__body">
						<h3 class="yf-card__title"><?php the_title(); ?></h3>
						<p class="yf-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 18 ) ); ?></p>
						<span class="yf-card__meta">Updated <?php echo esc_html( yadfood_last_updated() ); ?></span>
					</div>
				</a>
			<?php endwhile; else : ?>
				<p>No reviews matched these filters. Try widening your range or resetting.</p>
			<?php endif; ?>
			</div>

			<div class="yf-pagination"><?php the_posts_pagination(); ?></div>
		</div>
	</div>
</div>
<?php get_footer(); ?>
