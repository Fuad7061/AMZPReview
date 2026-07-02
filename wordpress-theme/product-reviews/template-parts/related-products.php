<?php
$post_id = (int) get_query_var( 'yf_post_id' );
$cats    = wp_get_post_terms( $post_id, 'review_category', array( 'fields' => 'ids' ) );
if ( empty( $cats ) ) return;

$related = new WP_Query( array(
	'post_type'      => 'review',
	'posts_per_page' => 3,
	'post__not_in'   => array( $post_id ),
	'tax_query'      => array( array(
		'taxonomy' => 'review_category',
		'terms'    => $cats,
	) ),
	'no_found_rows'  => true,
) );

if ( ! $related->have_posts() ) { wp_reset_postdata(); return; }
?>
<section class="yf-related">
	<h2 class="yf-section-title">You might also like</h2>
	<div class="yf-grid yf-grid--3">
	<?php while ( $related->have_posts() ) : $related->the_post(); ?>
		<a class="yf-card" href="<?php the_permalink(); ?>">
			<?php if ( has_post_thumbnail() ) : ?>
				<div class="yf-card__img"><?php the_post_thumbnail( 'yadfood-card' ); ?></div>
			<?php endif; ?>
			<div class="yf-card__body">
				<h3 class="yf-card__title"><?php the_title(); ?></h3>
			</div>
		</a>
	<?php endwhile; wp_reset_postdata(); ?>
	</div>
</section>
