<?php
/**
 * Programmatic SEO hubs — two crawlable hubs only.
 *
 *   /best/{category}/      "Top 7 Best {Category}" auto-roundup (NEW, virtual)
 *   /reviews/{category}/   Archive of individual review articles (review_category)
 *   /review/{slug}/        Individual review article (existing CPT)
 *
 * No price-tier URLs (prices change too often; would create thin, unstable pages).
 * No deeper nesting — Google's John Mueller has repeatedly noted that URL depth
 * is irrelevant to ranking, but stability + clarity matter, so we keep things flat.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Move the review_category archive to /reviews/{slug}/ so it lives in the same
 * namespace as individual /review/{slug}/ articles. Crawlers see a clean
 * hub→article relationship.
 */
function pr_hubs_retag_taxonomy_slug( $args, $taxonomy ) {
	if ( 'review_category' === $taxonomy ) {
		$args['rewrite'] = array( 'slug' => 'reviews', 'with_front' => false );
	}
	return $args;
}
add_filter( 'register_taxonomy_args', 'pr_hubs_retag_taxonomy_slug', 10, 2 );

/**
 * Register the /best/{category}/ rewrite + query var.
 */
function pr_hubs_rewrites() {
	add_rewrite_tag( '%pr_best_category%', '([^&]+)' );
	add_rewrite_rule( '^best/([^/]+)/?$', 'index.php?pr_best_category=$matches[1]', 'top' );
}
add_action( 'init', 'pr_hubs_rewrites', 20 );

function pr_hubs_query_vars( $vars ) {
	$vars[] = 'pr_best_category';
	return $vars;
}
add_filter( 'query_vars', 'pr_hubs_query_vars' );

/**
 * One-shot rewrite flush when this module's version bumps.
 */
function pr_hubs_maybe_flush() {
	if ( get_option( 'pr_hubs_rw_version' ) !== '2' ) {
		flush_rewrite_rules( false );
		update_option( 'pr_hubs_rw_version', '2', false );
	}
}
add_action( 'init', 'pr_hubs_maybe_flush', 99 );

/**
 * Resolve a category slug to a review_category term.
 */
function pr_hubs_get_term( $slug ) {
	$term = get_term_by( 'slug', sanitize_title( $slug ), 'review_category' );
	return ( $term && ! is_wp_error( $term ) ) ? $term : null;
}

/**
 * Safely resolve a review_category URL. get_term_link() may return WP_Error.
 */
function pr_hubs_term_url( $term ): string {
	$link = get_term_link( $term );
	return ( is_wp_error( $link ) || ! is_string( $link ) || '' === $link ) ? home_url( '/' ) : $link;
}

/**
 * Fetch the top-N review posts in a category, ranked by editorial score.
 */
function pr_hubs_top_reviews( $term, $limit = 7 ) {
	$q = new WP_Query( array(
		'post_type'      => 'review',
		'post_status'    => 'publish',
		'posts_per_page' => 30, // pull a wider set, then score+slice
		'tax_query'      => array( array(
			'taxonomy' => 'review_category',
			'field'    => 'term_id',
			'terms'    => $term->term_id,
		) ),
		'no_found_rows'  => true,
	) );

	$rows = array();
	foreach ( $q->posts as $post ) {
		$products = get_post_meta( $post->ID, '_yadfood_products', true );
		$top      = 0.0;
		if ( is_array( $products ) && function_exists( 'pr_score_list' ) ) {
			$scored = pr_score_list( $products );
			foreach ( $scored['products'] as $p ) {
				if ( isset( $p['_score'] ) && $p['_score'] > $top ) {
					$top = (float) $p['_score'];
				}
			}
		}
		$rows[] = array( 'post' => $post, 'score' => $top );
	}
	usort( $rows, function ( $a, $b ) { return $b['score'] <=> $a['score']; } );
	return array_slice( $rows, 0, $limit );
}

/**
 * Render the /best/{category}/ hub.
 */
function pr_hubs_template_include( $template ) {
	$slug = get_query_var( 'pr_best_category' );
	if ( ! $slug ) {
		return $template;
	}
	$term = pr_hubs_get_term( $slug );
	if ( ! $term ) {
		status_header( 404 );
		nocache_headers();
		return get_query_template( '404' );
	}

	status_header( 200 );

	$rows  = pr_hubs_top_reviews( $term, 7 );
	$title = sprintf( __( 'Top 7 Best %s (Reviewed %s)', 'product-reviews' ), $term->name, date_i18n( 'F Y' ) );
	$desc  = sprintf( __( 'Independently tested and ranked: our top 7 %s picks, updated with live Amazon prices, ratings, and availability.', 'product-reviews' ), strtolower( $term->name ) );
	$url   = home_url( '/best/' . $term->slug . '/' );

	get_header();
	?>
	<main id="primary" class="site-main pr-hub">
		<header class="pr-hub__header">
			<nav class="pr-breadcrumbs" aria-label="Breadcrumb">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'product-reviews' ); ?></a> ›
				<a href="<?php echo esc_url( pr_hubs_term_url( $term ) ); ?>"><?php echo esc_html( $term->name ); ?></a> ›
				<span><?php esc_html_e( 'Best', 'product-reviews' ); ?></span>
			</nav>
			<h1><?php echo esc_html( $title ); ?></h1>
			<p class="pr-hub__lede"><?php echo esc_html( $desc ); ?></p>
		</header>

		<?php if ( empty( $rows ) ) : ?>
			<p><?php esc_html_e( 'No reviews in this category yet.', 'product-reviews' ); ?></p>
		<?php else : ?>
			<ol class="pr-hub__list">
				<?php foreach ( $rows as $i => $row ) : $post = $row['post']; setup_postdata( $post ); ?>
					<li class="pr-hub__item">
						<span class="pr-hub__rank">#<?php echo (int) ( $i + 1 ); ?></span>
						<?php if ( has_post_thumbnail( $post ) ) : ?>
							<a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="pr-hub__thumb">
								<?php echo get_the_post_thumbnail( $post, 'pr-card', array( 'loading' => 'lazy' ) ); ?>
							</a>
						<?php endif; ?>
						<div class="pr-hub__body">
							<h2><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></h2>
							<p><?php echo esc_html( wp_trim_words( get_the_excerpt( $post ), 28 ) ); ?></p>
							<a class="pr-hub__cta" href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php esc_html_e( 'Read the full review →', 'product-reviews' ); ?></a>
						</div>
					</li>
				<?php endforeach; wp_reset_postdata(); ?>
			</ol>
			<p class="pr-hub__foot">
				<a href="<?php echo esc_url( pr_hubs_term_url( $term ) ); ?>"><?php printf( esc_html__( 'See all %s reviews →', 'product-reviews' ), esc_html( $term->name ) ); ?></a>
			</p>
		<?php endif; ?>
	</main>
	<?php
	get_footer();
	return '';
}
add_filter( 'template_include', 'pr_hubs_template_include', 99 );

/**
 * Head tags + JSON-LD for the hub.
 */
function pr_hubs_head() {
	$slug = get_query_var( 'pr_best_category' );
	if ( ! $slug ) {
		return;
	}
	$term = pr_hubs_get_term( $slug );
	if ( ! $term ) {
		return;
	}
	$url   = home_url( '/best/' . $term->slug . '/' );
	$title = sprintf( '%s — Top 7 Best %s (Reviewed %s)', get_bloginfo( 'name' ), $term->name, date_i18n( 'F Y' ) );
	$desc  = sprintf( 'Independently tested and ranked: our top 7 %s picks, updated with live Amazon prices and ratings.', strtolower( $term->name ) );

	echo "<title>" . esc_html( $title ) . "</title>\n";
	echo '<meta name="description" content="' . esc_attr( $desc ) . "\">\n";
	echo '<link rel="canonical" href="' . esc_url( $url ) . "\">\n";
	echo '<meta property="og:type" content="website">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . "\">\n";
	echo '<meta property="og:description" content="' . esc_attr( $desc ) . "\">\n";
	echo '<meta property="og:url" content="' . esc_url( $url ) . "\">\n";

	$rows  = pr_hubs_top_reviews( $term, 7 );
	$items = array();
	foreach ( $rows as $i => $row ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'url'      => get_permalink( $row['post'] ),
			'name'     => get_the_title( $row['post'] ),
		);
	}
	$ld = array(
		'@context'        => 'https://schema.org',
		'@type'           => 'ItemList',
		'itemListOrder'   => 'https://schema.org/ItemListOrderDescending',
		'numberOfItems'   => count( $items ),
		'name'            => sprintf( 'Top %d Best %s', count( $items ), $term->name ),
		'url'             => $url,
		'itemListElement' => $items,
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";

	// BreadcrumbList
	$crumbs = array(
		array( 'name' => 'Home',       'url' => home_url( '/' ) ),
		array( 'name' => $term->name,  'url' => pr_hubs_term_url( $term ) ),
		array( 'name' => 'Best',       'url' => $url ),
	);
	$bc = array( '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => array() );
	foreach ( $crumbs as $i => $c ) {
		$bc['itemListElement'][] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => $c['name'],
			'item'     => $c['url'],
		);
	}
	echo '<script type="application/ld+json">' . wp_json_encode( $bc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}
add_action( 'wp_head', 'pr_hubs_head', 1 );

/**
 * Inject the /best/{category}/ hub into the XML sitemap automatically.
 *
 * The correct API in WP 5.5+ is `wp_sitemaps_init`, which fires with the
 * sitemap server as its argument. The `wp_sitemaps_add_provider` filter
 * passes the provider object being added (not an array), so treating it as
 * an array causes a fatal error.
 */
function pr_hubs_register_sitemap_provider( $wp_sitemaps ) {
	if ( ! class_exists( 'PR_Hubs_Sitemap_Provider' ) ) {
		require_once __DIR__ . '/hubs-sitemap.php';
	}
	if ( class_exists( 'PR_Hubs_Sitemap_Provider' ) && is_object( $wp_sitemaps ) && isset( $wp_sitemaps->registry ) && method_exists( $wp_sitemaps->registry, 'add_provider' ) ) {
		$wp_sitemaps->registry->add_provider( 'pr_hubs', new PR_Hubs_Sitemap_Provider( 'pr_hubs' ) );
	}
}
add_action( 'wp_sitemaps_init', 'pr_hubs_register_sitemap_provider' );
