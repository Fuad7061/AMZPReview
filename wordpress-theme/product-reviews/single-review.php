<?php
/**
 * Single review template — the main editorial product-review page.
 */
get_header();

while ( have_posts() ) : the_post();
	$post_id  = get_the_ID();
	$products = yadfood_get_products( $post_id );
	if ( function_exists( 'pr_score_list' ) && ! empty( $products ) ) {
		$scored   = pr_score_list( $products );
		$products = $scored['products'];
	}
	$faqs     = yadfood_get_faqs( $post_id );
	$tldr     = yadfood_meta( $post_id, '_yadfood_tldr' );
	$intro    = yadfood_meta( $post_id, '_yadfood_intro' );
	$buyers   = get_post_meta( $post_id, '_yadfood_buyers', true );
	?>

	<article class="yf-review">
		<header class="yf-review__hero yf-container">
			<div class="yf-disclosure"><?php echo wp_kses_post( get_option( 'yadfood_disclosure', '' ) ); ?></div>
			<h1 class="yf-review__title"><?php the_title(); ?></h1>
			<p class="yf-review__meta">
				By <?php the_author(); ?> · Updated <?php echo esc_html( yadfood_last_updated() ); ?>
				<?php if ( function_exists( 'pr_render_freshness' ) ) echo ' · ' . pr_render_freshness( get_the_ID() ); // phpcs:ignore ?>
				<?php if ( ! empty( $products ) ) echo ' · ' . count( $products ) . ' picks compared'; ?>
			</p>
		</header>

		<?php if ( function_exists( 'pr_render_author_card' ) ) {
			echo '<div class="yf-container">' . pr_render_author_card( $post_id ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} ?>

		<div class="yf-container yf-review__layout">
			<aside class="yf-toc" aria-label="Table of contents">
				<h2>On this page</h2>
				<ol>
					<li><a href="#tldr">Quick answer</a></li>
					<li><a href="#picks">Our top picks</a></li>
					<li><a href="#compare">Compare side-by-side</a></li>
					<li><a href="#guide">Buyer's guide</a></li>
					<li><a href="#faq">FAQ</a></li>
				</ol>
			</aside>

			<div class="yf-review__main">

				<?php if ( $tldr ) : ?>
					<section id="tldr" class="yf-tldr">
						<h2>Quick answer</h2>
						<p><?php echo esc_html( $tldr ); ?></p>
					</section>
				<?php endif; ?>

				<?php if ( $intro ) : ?>
					<section class="yf-intro">
						<?php echo wp_kses_post( wpautop( $intro ) ); ?>
					</section>
				<?php endif; ?>

				<?php if ( ! empty( $products ) ) : ?>
					<section id="picks" class="yf-picks">
						<h2 class="yf-section-title">Our top picks</h2>
						<?php foreach ( $products as $p ) :
							set_query_var( 'yf_product', $p );
							set_query_var( 'yf_post_id', $post_id );
							get_template_part( 'template-parts/product-card' );
						endforeach; ?>
					</section>

					<section id="compare">
						<?php
						set_query_var( 'yf_products', $products );
						set_query_var( 'yf_post_id', $post_id );
						get_template_part( 'template-parts/comparison-table' );
						?>
					</section>

					<?php
					set_query_var( 'yf_products', $products );
					set_query_var( 'yf_post_id', $post_id );
					get_template_part( 'template-parts/final-verdict' );
					?>
				<?php endif; ?>

				<?php if ( is_array( $buyers ) && ! empty( $buyers ) ) : ?>
					<section id="guide" class="yf-guide">
						<h2 class="yf-section-title">Buyer's guide</h2>
						<ul class="yf-guide__list">
						<?php foreach ( $buyers as $b ) : ?>
							<li><?php echo esc_html( $b ); ?></li>
						<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>

				<?php if ( ! empty( $faqs ) ) : ?>
					<section id="faq" class="yf-faq">
						<h2 class="yf-section-title">Frequently asked</h2>
						<?php foreach ( $faqs as $f ) : if ( empty( $f['q'] ) ) continue; ?>
							<details class="yf-faq__item">
								<summary><?php echo esc_html( $f['q'] ); ?></summary>
								<p><?php echo esc_html( $f['a'] ); ?></p>
							</details>
						<?php endforeach; ?>
					</section>
				<?php endif; ?>

				<section class="yf-content"><?php the_content(); ?></section>

				<?php
				set_query_var( 'yf_post_id', $post_id );
				get_template_part( 'template-parts/affiliate-disclosure' );
				get_template_part( 'template-parts/related-products' );
				?>
			</div>
		</div>
	</article>

<?php endwhile; ?>

<?php get_footer(); ?>
