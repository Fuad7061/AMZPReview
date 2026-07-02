<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="yf-skip" href="#yf-main"><?php esc_html_e( 'Skip to content', 'product-reviews' ); ?></a>

<header class="yf-header" role="banner">
	<div class="yf-container yf-header__inner">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="yf-logo" aria-label="<?php echo esc_attr( pr_brand() ); ?> home">
			<?php
			$pr_logo = pr_logo_url();
			if ( $pr_logo ) {
				echo '<img src="' . esc_url( $pr_logo ) . '" alt="' . esc_attr( pr_brand() ) . '" class="yf-logo__img">';
			} else {
				$initials = strtoupper( mb_substr( pr_short_name(), 0, 2 ) );
				echo '<span class="yf-logo__mark" aria-hidden="true">' . esc_html( $initials ) . '</span>';
				echo '<span class="yf-logo__text">' . esc_html( pr_short_name() ) . '</span>';
			}
			?>
		</a>

		<form class="yf-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label for="yf-search-input" class="screen-reader-text">Search</label>
			<input id="yf-search-input" type="search" name="s" placeholder="Search any product…" value="<?php echo esc_attr( get_search_query() ); ?>">
			<button type="submit" aria-label="Search">Search</button>
		</form>

		<div style="display:flex;align-items:center;gap:.5rem;">
			<nav class="yf-nav" aria-label="<?php esc_attr_e( 'Primary', 'product-reviews' ); ?>">
				<?php
				wp_nav_menu( array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'yf-nav__list',
					'fallback_cb'    => function () {
						$about = get_page_by_path( 'about' );
						$disc  = get_page_by_path( 'disclosure' );
						echo '<ul class="yf-nav__list">';
						echo '<li><a href="' . esc_url( home_url( '/' ) ) . '">Home</a></li>';
						if ( $about ) echo '<li><a href="' . esc_url( get_permalink( $about ) ) . '">About</a></li>';
						if ( $disc  ) echo '<li><a href="' . esc_url( get_permalink( $disc  ) ) . '">Disclosure</a></li>';
						echo '</ul>';
					},
				) );
				?>
			</nav>
			<button type="button" class="yf-menu-toggle" aria-label="Toggle menu" aria-expanded="false" data-yf-toggle="mobile">☰</button>
		</div>
	</div>

	<?php
	$dept_terms = get_terms( array( 'taxonomy' => 'review_category', 'hide_empty' => false, 'number' => 10, 'orderby' => 'count', 'order' => 'DESC' ) );
	if ( ! is_wp_error( $dept_terms ) && ! empty( $dept_terms ) ) : ?>
		<div class="yf-deptbar" data-yf-deptbar>
			<div class="yf-container yf-deptbar__inner">
				<button type="button" class="yf-deptbar__btn" data-yf-deptbtn aria-expanded="false">
					<span aria-hidden="true">▤</span> Shop by Department <span aria-hidden="true">▾</span>
				</button>
				<span class="yf-deptbar__divider" aria-hidden="true"></span>
				<?php foreach ( array_slice( $dept_terms, 0, 6 ) as $t ) :
					$lnk = get_term_link( $t );
					if ( is_wp_error( $lnk ) ) continue; ?>
					<a class="yf-deptbar__pill" href="<?php echo esc_url( $lnk ); ?>"><?php echo esc_html( $t->name ); ?></a>
				<?php endforeach; ?>
				<div class="yf-deptbar__panel" role="menu">
					<?php foreach ( $dept_terms as $t ) :
						$lnk = get_term_link( $t );
						if ( is_wp_error( $lnk ) ) continue; ?>
						<a href="<?php echo esc_url( $lnk ); ?>"><span aria-hidden="true">›</span> <?php echo esc_html( $t->name ); ?></a>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<div class="yf-mobile" data-yf-mobile>
		<form class="yf-mobile__search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<input type="search" name="s" placeholder="Search any product…" value="<?php echo esc_attr( get_search_query() ); ?>">
			<button type="submit" class="yf-cta yf-cta--sm yf-cta--ink">Go</button>
		</form>
		<?php if ( ! is_wp_error( $dept_terms ) && ! empty( $dept_terms ) ) : ?>
			<div style="display:flex;flex-wrap:wrap;gap:.4rem;">
				<?php foreach ( $dept_terms as $t ) :
					$lnk = get_term_link( $t );
					if ( is_wp_error( $lnk ) ) continue; ?>
					<a class="yf-quick-search__pill" href="<?php echo esc_url( $lnk ); ?>"><?php echo esc_html( $t->name ); ?></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<div class="yf-mobile__links">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
			<?php $mp = get_page_by_path( 'methodology' ); if ( $mp ) : ?><a href="<?php echo esc_url( get_permalink( $mp ) ); ?>">How we rank</a><?php endif; ?>
			<?php $ap = get_page_by_path( 'about' ); if ( $ap ) : ?><a href="<?php echo esc_url( get_permalink( $ap ) ); ?>">About</a><?php endif; ?>
			<?php $dp = get_page_by_path( 'disclosure' ); if ( $dp ) : ?><a href="<?php echo esc_url( get_permalink( $dp ) ); ?>">Disclosure</a><?php endif; ?>
		</div>
	</div>
</header>

<main id="yf-main" class="yf-main" role="main">
