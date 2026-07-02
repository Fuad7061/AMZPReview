<?php get_header(); ?>
<div class="yf-container yf-page">
	<?php while ( have_posts() ) : the_post(); ?>
		<article>
			<h1 class="yf-page__title"><?php the_title(); ?></h1>
			<div class="yf-page__content"><?php the_content(); ?></div>
		</article>
	<?php endwhile; ?>
</div>
<?php get_footer(); ?>
