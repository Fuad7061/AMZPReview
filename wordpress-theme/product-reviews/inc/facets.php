<?php
/**
 * Faceted filters for review archives.
 *
 * Adds price / rating / brand filters to:
 *   - the `review` post-type archive
 *   - any `review_category` taxonomy archive
 *
 * Filters are GET-only (cache-friendly), translate into post-meta queries
 * against the same `_pr_products` array used by the compare page, and
 * scope the query to reviews that contain at least one matching product.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitize GET facet input.
 *
 * @return array{ brand:string, min_price:float, max_price:float, min_rating:float, sort:string }
 */
function pr_facets_input() {
	$brand      = isset( $_GET['brand'] )    ? sanitize_text_field( wp_unslash( $_GET['brand'] ) )    : '';
	$min_price  = isset( $_GET['min_price'] ) ? (float) $_GET['min_price'] : 0.0;
	$max_price  = isset( $_GET['max_price'] ) ? (float) $_GET['max_price'] : 0.0;
	$min_rating = isset( $_GET['min_rating'] ) ? (float) $_GET['min_rating'] : 0.0;
	$sort       = isset( $_GET['sort'] ) ? sanitize_key( $_GET['sort'] ) : '';
	return compact( 'brand', 'min_price', 'max_price', 'min_rating', 'sort' );
}

/**
 * Collect available facet values for the current archive scope.
 *
 * @param int|null $term_id Optional review_category term id.
 * @return array{ brands:array<string,int>, max_price:float, min_price:float }
 */
function pr_facets_collect( $term_id = null ) {
	$args = array(
		'post_type'      => 'review',
		'post_status'    => 'publish',
		'posts_per_page' => 300,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	);
	if ( $term_id ) {
		$args['tax_query'] = array( array(
			'taxonomy' => 'review_category',
			'field'    => 'term_id',
			'terms'    => (int) $term_id,
		) );
	}
	$ids = get_posts( $args );

	$brands = array();
	$min_p  = INF;
	$max_p  = 0.0;
	foreach ( $ids as $pid ) {
		$products = get_post_meta( $pid, '_pr_products', true );
		if ( empty( $products ) || ! is_array( $products ) ) {
			continue;
		}
		foreach ( $products as $p ) {
			$brand = isset( $p['brand'] ) ? trim( (string) $p['brand'] ) : '';
			if ( $brand ) {
				$brands[ $brand ] = isset( $brands[ $brand ] ) ? $brands[ $brand ] + 1 : 1;
			}
			$price = isset( $p['price'] ) ? (float) preg_replace( '/[^0-9.]/', '', (string) $p['price'] ) : 0.0;
			if ( $price > 0 ) {
				$min_p = min( $min_p, $price );
				$max_p = max( $max_p, $price );
			}
		}
	}
	if ( INF === $min_p ) { $min_p = 0.0; }
	ksort( $brands );
	return array( 'brands' => $brands, 'min_price' => $min_p, 'max_price' => $max_p );
}

/**
 * Decide whether a review post passes the active facets.
 */
function pr_facets_post_matches( $post_id, $facets ) {
	$active = ( $facets['brand'] || $facets['min_price'] || $facets['max_price'] || $facets['min_rating'] );
	if ( ! $active ) {
		return true;
	}
	$products = get_post_meta( $post_id, '_pr_products', true );
	if ( empty( $products ) || ! is_array( $products ) ) {
		return false;
	}
	$brand_needle = strtolower( $facets['brand'] );
	foreach ( $products as $p ) {
		if ( $brand_needle ) {
			$b = strtolower( (string) ( $p['brand'] ?? '' ) );
			if ( $b !== $brand_needle ) {
				continue;
			}
		}
		$price = isset( $p['price'] ) ? (float) preg_replace( '/[^0-9.]/', '', (string) $p['price'] ) : 0.0;
		if ( $facets['min_price'] && $price < $facets['min_price'] ) { continue; }
		if ( $facets['max_price'] && $price > $facets['max_price'] ) { continue; }
		$rating = isset( $p['rating'] ) ? (float) $p['rating'] : 0.0;
		if ( $facets['min_rating'] && $rating < $facets['min_rating'] ) { continue; }
		return true;
	}
	return false;
}

/**
 * Apply facets to the main archive query via a post__in narrow.
 */
function pr_facets_filter_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}
	$is_target = ( $query->is_post_type_archive( 'review' ) || $query->is_tax( 'review_category' ) );
	if ( ! $is_target ) {
		return;
	}
	$facets = pr_facets_input();

	// Sort.
	if ( 'price_asc' === $facets['sort'] || 'price_desc' === $facets['sort'] ) {
		$query->set( 'meta_key', '_pr_min_price' );
		$query->set( 'orderby', 'meta_value_num' );
		$query->set( 'order',   'price_asc' === $facets['sort'] ? 'ASC' : 'DESC' );
	} elseif ( 'rating_desc' === $facets['sort'] ) {
		$query->set( 'meta_key', '_pr_max_rating' );
		$query->set( 'orderby', 'meta_value_num' );
		$query->set( 'order',   'DESC' );
	} elseif ( 'newest' === $facets['sort'] ) {
		$query->set( 'orderby', 'date' );
		$query->set( 'order',   'DESC' );
	}

	$active = ( $facets['brand'] || $facets['min_price'] || $facets['max_price'] || $facets['min_rating'] );
	if ( ! $active ) {
		return;
	}

	// Narrow to a scoped set of candidate posts so filtering is cheap.
	$scope_args = array(
		'post_type'      => 'review',
		'post_status'    => 'publish',
		'posts_per_page' => 300,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	);
	if ( $query->is_tax( 'review_category' ) ) {
		$term = $query->get_queried_object();
		if ( $term && isset( $term->term_id ) ) {
			$scope_args['tax_query'] = array( array(
				'taxonomy' => 'review_category',
				'field'    => 'term_id',
				'terms'    => (int) $term->term_id,
			) );
		}
	}
	$candidates = get_posts( $scope_args );
	$matched    = array();
	foreach ( $candidates as $pid ) {
		if ( pr_facets_post_matches( $pid, $facets ) ) {
			$matched[] = $pid;
		}
	}
	$query->set( 'post__in', $matched ? $matched : array( 0 ) );
}
add_action( 'pre_get_posts', 'pr_facets_filter_query' );

/**
 * When a review is saved/imported, cache min_price + max_rating on the post
 * so we can sort with a meta_key without a per-row PHP scan.
 */
function pr_facets_cache_aggregates( $post_id ) {
	$products = get_post_meta( $post_id, '_pr_products', true );
	if ( empty( $products ) || ! is_array( $products ) ) {
		delete_post_meta( $post_id, '_pr_min_price' );
		delete_post_meta( $post_id, '_pr_max_rating' );
		return;
	}
	$min_p = INF;
	$max_r = 0.0;
	foreach ( $products as $p ) {
		$price = isset( $p['price'] ) ? (float) preg_replace( '/[^0-9.]/', '', (string) $p['price'] ) : 0.0;
		if ( $price > 0 ) { $min_p = min( $min_p, $price ); }
		$rating = isset( $p['rating'] ) ? (float) $p['rating'] : 0.0;
		if ( $rating > 0 ) { $max_r = max( $max_r, $rating ); }
	}
	update_post_meta( $post_id, '_pr_min_price', INF === $min_p ? 0 : $min_p );
	update_post_meta( $post_id, '_pr_max_rating', $max_r );
}
add_action( 'save_post_review', 'pr_facets_cache_aggregates' );
add_action( 'updated_post_meta', function ( $mid, $post_id, $key ) {
	if ( '_pr_products' === $key ) {
		pr_facets_cache_aggregates( $post_id );
	}
}, 10, 3 );

/**
 * Render the facets sidebar. Templates call pr_facets_render().
 */
function pr_facets_render() {
	$term_id = is_tax( 'review_category' ) ? (int) get_queried_object_id() : 0;
	$facets  = pr_facets_input();
	$avail   = pr_facets_collect( $term_id ?: null );
	$action  = is_tax( 'review_category' )
		? get_term_link( get_queried_object() )
		: get_post_type_archive_link( 'review' );
	if ( is_wp_error( $action ) || ! $action ) {
		$action = home_url( '/' );
	}
	?>
	<aside class="yf-facets" aria-label="<?php esc_attr_e( 'Filter reviews', 'product-reviews' ); ?>">
		<form class="yf-facets__form" method="get" action="<?php echo esc_url( $action ); ?>">
			<h3 class="yf-facets__title"><?php esc_html_e( 'Refine', 'product-reviews' ); ?></h3>

			<div class="yf-facets__group">
				<label for="pr-sort"><?php esc_html_e( 'Sort by', 'product-reviews' ); ?></label>
				<select id="pr-sort" name="sort">
					<option value=""><?php esc_html_e( 'Editor\'s pick', 'product-reviews' ); ?></option>
					<option value="newest" <?php selected( $facets['sort'], 'newest' ); ?>><?php esc_html_e( 'Newest', 'product-reviews' ); ?></option>
					<option value="rating_desc" <?php selected( $facets['sort'], 'rating_desc' ); ?>><?php esc_html_e( 'Highest rated', 'product-reviews' ); ?></option>
					<option value="price_asc" <?php selected( $facets['sort'], 'price_asc' ); ?>><?php esc_html_e( 'Price: low to high', 'product-reviews' ); ?></option>
					<option value="price_desc" <?php selected( $facets['sort'], 'price_desc' ); ?>><?php esc_html_e( 'Price: high to low', 'product-reviews' ); ?></option>
				</select>
			</div>

			<div class="yf-facets__group">
				<label><?php esc_html_e( 'Min rating', 'product-reviews' ); ?></label>
				<div class="yf-facets__pills">
					<?php foreach ( array( 0, 3.5, 4.0, 4.5 ) as $r ) :
						$active = (float) $facets['min_rating'] === (float) $r ? ' is-active' : ''; ?>
						<label class="yf-facets__pill<?php echo esc_attr( $active ); ?>">
							<input type="radio" name="min_rating" value="<?php echo esc_attr( $r ); ?>" <?php checked( (float) $facets['min_rating'], (float) $r ); ?> />
							<?php echo $r ? esc_html( number_format( $r, 1 ) ) . '+' : esc_html__( 'Any', 'product-reviews' ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="yf-facets__group">
				<label><?php esc_html_e( 'Price', 'product-reviews' ); ?></label>
				<div class="yf-facets__row">
					<input type="number" min="0" step="1" name="min_price" placeholder="Min" value="<?php echo $facets['min_price'] ? esc_attr( (int) $facets['min_price'] ) : ''; ?>" />
					<span>—</span>
					<input type="number" min="0" step="1" name="max_price" placeholder="Max" value="<?php echo $facets['max_price'] ? esc_attr( (int) $facets['max_price'] ) : ''; ?>" />
				</div>
				<?php if ( $avail['max_price'] > 0 ) : ?>
					<small class="yf-facets__hint"><?php
						printf(
							/* translators: 1: min price, 2: max price */
							esc_html__( 'Range in this view: %1$s – %2$s', 'product-reviews' ),
							esc_html( function_exists( 'yadfood_format_price' ) ? yadfood_format_price( $avail['min_price'] ) : '$' . (int) $avail['min_price'] ),
							esc_html( function_exists( 'yadfood_format_price' ) ? yadfood_format_price( $avail['max_price'] ) : '$' . (int) $avail['max_price'] )
						); ?></small>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $avail['brands'] ) ) : ?>
				<div class="yf-facets__group">
					<label for="pr-brand"><?php esc_html_e( 'Brand', 'product-reviews' ); ?></label>
					<select id="pr-brand" name="brand">
						<option value=""><?php esc_html_e( 'All brands', 'product-reviews' ); ?></option>
						<?php foreach ( $avail['brands'] as $brand => $count ) : ?>
							<option value="<?php echo esc_attr( $brand ); ?>" <?php selected( $facets['brand'], $brand ); ?>>
								<?php echo esc_html( $brand ) . ' (' . (int) $count . ')'; ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<div class="yf-facets__actions">
				<button type="submit" class="yf-cta yf-cta--sm"><?php esc_html_e( 'Apply', 'product-reviews' ); ?></button>
				<a class="yf-facets__reset" href="<?php echo esc_url( $action ); ?>"><?php esc_html_e( 'Reset', 'product-reviews' ); ?></a>
			</div>
		</form>
	</aside>
	<?php
}
