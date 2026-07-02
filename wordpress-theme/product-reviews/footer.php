</main>

<footer class="yf-footer" role="contentinfo">
	<div class="yf-container yf-footer__inner">
		<div class="yf-footer__brand">
			<div style="display:flex;align-items:center;gap:.5rem;">
				<?php
				$pr_logo = pr_logo_url();
				if ( $pr_logo ) {
					echo '<img src="' . esc_url( $pr_logo ) . '" alt="" style="max-height:36px;width:auto;">';
				} else {
					$initials = strtoupper( mb_substr( pr_short_name(), 0, 2 ) );
					echo '<span class="yf-logo__mark" aria-hidden="true">' . esc_html( $initials ) . '</span>';
				}
				?>
				<strong><?php echo esc_html( pr_brand() ); ?></strong>
			</div>
			<p><?php echo esc_html( pr_tagline() ); ?></p>
			<p style="font-size:.75rem;font-style:italic;margin-top:.75rem;">
				As an Amazon Associate, <?php echo esc_html( pr_short_name() ); ?> earns from qualifying purchases. Prices and availability are accurate as of the date shown and are subject to change.
			</p>
		</div>

		<div class="yf-footer__col">
			<h3>Browse</h3>
			<ul class="yf-footer__list">
				<?php
				$terms = get_terms( array( 'taxonomy' => 'review_category', 'hide_empty' => false, 'number' => 6 ) );
				if ( ! is_wp_error( $terms ) ) {
					foreach ( $terms as $t ) {
						$link = get_term_link( $t );
						if ( is_wp_error( $link ) ) continue;
						echo '<li><a href="' . esc_url( $link ) . '">' . esc_html( $t->name ) . '</a></li>';
					}
				}
				?>
			</ul>
		</div>

		<div class="yf-footer__col">
			<h3>About</h3>
			<ul class="yf-footer__list">
				<?php
				$pages = array( 'about' => 'About', 'methodology' => 'How we rank', 'disclosure' => 'Affiliate disclosure', 'privacy' => 'Privacy', 'terms' => 'Terms' );
				foreach ( $pages as $slug => $label ) {
					$p = get_page_by_path( $slug );
					if ( $p ) echo '<li><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( $label ) . '</a></li>';
				}
				wp_nav_menu( array( 'theme_location' => 'footer', 'container' => false, 'items_wrap' => '%3$s', 'fallback_cb' => '__return_empty_string' ) );
				?>
			</ul>
		</div>

		<div class="yf-footer__disclosure">
			<?php echo wp_kses_post( get_option( 'yadfood_disclosure', get_theme_mod( 'yadfood_disclosure', '' ) ) ); ?>
		</div>
	</div>

	<div class="yf-footer__bar">
		<div class="yf-container yf-footer__copy">
			<span><?php echo esc_html( pr_footer_copyright() ); ?></span>
			<span>Amazon and the Amazon logo are trademarks of Amazon.com, Inc. or its affiliates.</span>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
