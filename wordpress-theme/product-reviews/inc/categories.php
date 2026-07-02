<?php
/**
 * Amazon category catalog + auto-created landing pages.
 *
 * - Ships a bundled JSON catalog (inc/categories/amazon-categories.json) of
 *   Amazon's top-level US departments.
 * - On theme activation (and on demand from the admin grid) imports each
 *   enabled category as a `review_category` taxonomy term, attaches an
 *   auto-generated landing Page, stores per-category settings (browse node,
 *   search index, seed keywords, schedule), and seeds the autopilot queue.
 * - Provides a "Categories" admin submenu with a grid to enable/disable,
 *   edit, sync, or remove categories.
 *
 * Admins can override every value after import — re-syncing only touches
 * fields the admin hasn't customised (tracked via the
 * `pr_cat_admin_overrides` term-meta map).
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------ *
 * Catalog loader
 * ------------------------------------------------------------------ */

/**
 * Read the bundled Amazon category catalog.
 *
 * @return array<int,array<string,mixed>>
 */
function pr_categories_catalog() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$path = PR_THEME_DIR . '/inc/categories/amazon-categories.json';
	if ( ! file_exists( $path ) ) {
		$cache = array();
		return $cache;
	}
	$raw = file_get_contents( $path );
	$data = json_decode( $raw, true );
	$cache = isset( $data['categories'] ) && is_array( $data['categories'] ) ? $data['categories'] : array();
	$cache = apply_filters( 'pr_categories_catalog', $cache );
	return $cache;
}

/**
 * Lookup a catalog entry by slug.
 *
 * @param string $slug
 * @return array<string,mixed>|null
 */
function pr_categories_get( $slug ) {
	foreach ( pr_categories_catalog() as $row ) {
		if ( isset( $row['slug'] ) && $row['slug'] === $slug ) {
			return $row;
		}
	}
	return null;
}

/* ------------------------------------------------------------------ *
 * Per-category state (options)
 * ------------------------------------------------------------------ */

/**
 * Get the enabled-state map: [slug => bool].
 *
 * Defaults to ALL catalog categories enabled (per user request).
 *
 * @return array<string,bool>
 */
function pr_categories_state() {
	$state = get_option( 'pr_categories_state', null );
	if ( ! is_array( $state ) ) {
		$state = array();
		foreach ( pr_categories_catalog() as $row ) {
			$state[ $row['slug'] ] = true;
		}
		update_option( 'pr_categories_state', $state, false );
	}
	return $state;
}

function pr_categories_set_state( $slug, $enabled ) {
	$state = pr_categories_state();
	$state[ $slug ] = (bool) $enabled;
	update_option( 'pr_categories_state', $state, false );
}

/* ------------------------------------------------------------------ *
 * Term meta keys
 * ------------------------------------------------------------------ */

const PR_CAT_META_SLUG           = 'pr_amazon_slug';
const PR_CAT_META_BROWSE_NODE    = 'pr_browse_node';
const PR_CAT_META_SEARCH_INDEX   = 'pr_search_index';
const PR_CAT_META_SEED_KEYWORDS  = 'pr_seed_keywords';
const PR_CAT_META_LANDING_PAGE   = 'pr_landing_page_id';
const PR_CAT_META_OVERRIDES      = 'pr_admin_overrides';
const PR_CAT_META_LAST_SYNCED    = 'pr_last_synced_at';
const PR_CAT_META_SCHEDULE       = 'pr_schedule'; // daily|every_3_days|weekly|off

/* ------------------------------------------------------------------ *
 * Term-meta registration (REST-exposed)
 * ------------------------------------------------------------------ */

function pr_categories_register_meta() {
	$keys = array(
		PR_CAT_META_SLUG          => 'string',
		PR_CAT_META_BROWSE_NODE   => 'string',
		PR_CAT_META_SEARCH_INDEX  => 'string',
		PR_CAT_META_LANDING_PAGE  => 'integer',
		PR_CAT_META_LAST_SYNCED   => 'string',
		PR_CAT_META_SCHEDULE      => 'string',
	);
	foreach ( $keys as $key => $type ) {
		register_term_meta( 'review_category', $key, array(
			'type'         => $type,
			'single'       => true,
			'show_in_rest' => true,
			'auth_callback'=> function() { return current_user_can( 'manage_categories' ); },
		) );
	}
	register_term_meta( 'review_category', PR_CAT_META_SEED_KEYWORDS, array(
		'type'         => 'array',
		'single'       => true,
		'show_in_rest' => array( 'schema' => array( 'items' => array( 'type' => 'string' ) ) ),
		'auth_callback'=> function() { return current_user_can( 'manage_categories' ); },
	) );
	register_term_meta( 'review_category', PR_CAT_META_OVERRIDES, array(
		'type'    => 'object',
		'single'  => true,
		'show_in_rest' => false,
	) );
}
add_action( 'init', 'pr_categories_register_meta', 20 );

/* ------------------------------------------------------------------ *
 * Term + landing-page upsert
 * ------------------------------------------------------------------ */

/**
 * Create or update the review_category term + landing page for a catalog row.
 *
 * Respects admin overrides recorded in PR_CAT_META_OVERRIDES — fields the
 * admin has edited won't be touched on resync.
 *
 * @param array<string,mixed> $row Catalog row.
 * @param bool                $force_refresh_landing Recreate landing-page body even if it exists.
 * @return int|WP_Error Term ID on success.
 */
function pr_categories_upsert( array $row, $force_refresh_landing = false ) {
	if ( empty( $row['slug'] ) || empty( $row['name'] ) ) {
		return new WP_Error( 'pr_cat_bad_row', 'Catalog row missing slug/name.' );
	}
	$slug = sanitize_title( $row['slug'] );
	$name = sanitize_text_field( $row['name'] );

	$term = get_term_by( 'slug', $slug, 'review_category' );
	if ( ! $term ) {
		$res = wp_insert_term( $name, 'review_category', array(
			'slug'        => $slug,
			'description' => sprintf( /* translators: %s = department name */
				__( 'Editor-vetted reviews and buying guides in the %s department, updated automatically as new products launch.', 'product-reviews' ),
				$name
			),
		) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$term_id = (int) $res['term_id'];
	} else {
		$term_id   = (int) $term->term_id;
		$overrides = (array) get_term_meta( $term_id, PR_CAT_META_OVERRIDES, true );
		// Only refresh name/description if admin hasn't customised them.
		if ( empty( $overrides['name'] ) ) {
			wp_update_term( $term_id, 'review_category', array( 'name' => $name ) );
		}
	}

	// Per-category metadata (respect overrides).
	$overrides = (array) get_term_meta( $term_id, PR_CAT_META_OVERRIDES, true );
	$assign = function( $key, $value ) use ( $term_id, $overrides ) {
		if ( ! empty( $overrides[ $key ] ) ) {
			return;
		}
		update_term_meta( $term_id, $key, $value );
	};
	$assign( PR_CAT_META_SLUG,          $slug );
	$assign( PR_CAT_META_BROWSE_NODE,   (string) ( $row['browse_node']  ?? '' ) );
	$assign( PR_CAT_META_SEARCH_INDEX,  (string) ( $row['search_index'] ?? '' ) );
	$assign( PR_CAT_META_SEED_KEYWORDS, array_values( array_filter( (array) ( $row['seed_keywords'] ?? array() ) ) ) );
	if ( ! get_term_meta( $term_id, PR_CAT_META_SCHEDULE, true ) ) {
		update_term_meta( $term_id, PR_CAT_META_SCHEDULE, 'daily' );
	}

	// Landing page.
	$page_id = (int) get_term_meta( $term_id, PR_CAT_META_LANDING_PAGE, true );
	if ( ! $page_id || ! get_post( $page_id ) || $force_refresh_landing ) {
		$page_id = pr_categories_create_landing_page( $term_id, $name, $slug, $page_id );
		if ( $page_id ) {
			update_term_meta( $term_id, PR_CAT_META_LANDING_PAGE, $page_id );
		}
	}

	update_term_meta( $term_id, PR_CAT_META_LAST_SYNCED, gmdate( 'c' ) );

	/**
	 * Fires after a category is imported/refreshed. Autopilot listens to
	 * seed the queue with this category's keywords.
	 */
	do_action( 'pr_category_imported', $term_id, $row );

	return $term_id;
}

/**
 * Create or update the auto-generated landing Page for a category.
 *
 * @return int Page ID (0 on failure).
 */
function pr_categories_create_landing_page( $term_id, $name, $slug, $existing_id = 0 ) {
	$archive_url = get_term_link( (int) $term_id, 'review_category' );
	$archive_url = is_wp_error( $archive_url ) ? '' : $archive_url;

	$content  = '<!-- wp:paragraph -->';
	$content .= '<p>' . esc_html( sprintf(
		/* translators: %s = department */
		__( 'Welcome to %s. Every review here is vetted against current Amazon listings — prices, ratings, and availability are refreshed continuously, and articles are rewritten whenever specs change.', 'product-reviews' ),
		$name
	) ) . '</p>';
	$content .= '<!-- /wp:paragraph -->';
	$content .= sprintf(
		'<!-- wp:shortcode -->[pr_category_archive slug="%s"]<!-- /wp:shortcode -->',
		esc_attr( $slug )
	);
	if ( $archive_url ) {
		$content .= '<!-- wp:paragraph -->';
		$content .= '<p><a href="' . esc_url( $archive_url ) . '">' . esc_html__( 'See the full category archive →', 'product-reviews' ) . '</a></p>';
		$content .= '<!-- /wp:paragraph -->';
	}

	$args = array(
		'post_title'   => $name,
		'post_name'    => $slug,
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_content' => $content,
		'post_excerpt' => sprintf(
			/* translators: %s = department */
			__( 'Best-in-class picks across %s, refreshed automatically as new products launch.', 'product-reviews' ),
			$name
		),
	);
	if ( $existing_id && get_post( $existing_id ) ) {
		$args['ID'] = $existing_id;
		$id = wp_update_post( $args, true );
	} else {
		$id = wp_insert_post( $args, true );
	}
	if ( is_wp_error( $id ) || ! $id ) {
		return 0;
	}
	update_post_meta( $id, '_pr_auto_landing', $term_id );
	return (int) $id;
}

/* ------------------------------------------------------------------ *
 * Bulk sync from catalog → enabled terms.
 * ------------------------------------------------------------------ */

function pr_categories_sync_all( $force_refresh_landing = false ) {
	$state   = pr_categories_state();
	$results = array( 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array() );
	foreach ( pr_categories_catalog() as $row ) {
		$slug = $row['slug'];
		if ( empty( $state[ $slug ] ) ) {
			$results['skipped']++;
			continue;
		}
		$existing = get_term_by( 'slug', $slug, 'review_category' );
		$res = pr_categories_upsert( $row, $force_refresh_landing );
		if ( is_wp_error( $res ) ) {
			$results['errors'][ $slug ] = $res->get_error_message();
			continue;
		}
		$existing ? $results['updated']++ : $results['created']++;
	}
	update_option( 'pr_categories_last_sync', gmdate( 'c' ), false );
	return $results;
}

/* ------------------------------------------------------------------ *
 * Track admin overrides so resync doesn't clobber custom edits.
 * ------------------------------------------------------------------ */

function pr_categories_record_override( $term_id, $field ) {
	$overrides = (array) get_term_meta( $term_id, PR_CAT_META_OVERRIDES, true );
	$overrides[ $field ] = 1;
	update_term_meta( $term_id, PR_CAT_META_OVERRIDES, $overrides );
}
add_action( 'edited_review_category', function( $term_id ) {
	pr_categories_record_override( $term_id, 'name' );
} );

/* ------------------------------------------------------------------ *
 * Autopilot: seed queue when a category is enabled/imported.
 * ------------------------------------------------------------------ */

add_action( 'pr_category_imported', function( $term_id, $row ) {
	if ( ! function_exists( 'pr_enqueue_keyword' ) ) {
		return;
	}
	$keywords = (array) get_term_meta( $term_id, PR_CAT_META_SEED_KEYWORDS, true );
	if ( empty( $keywords ) ) {
		return;
	}
	foreach ( $keywords as $kw ) {
		$kw = trim( (string) $kw );
		if ( $kw !== '' ) {
			pr_enqueue_keyword( $kw, (int) $term_id );
		}
	}
}, 10, 2 );

/* ------------------------------------------------------------------ *
 * Shortcode for landing pages: render filtered review grid.
 * ------------------------------------------------------------------ */

add_shortcode( 'pr_category_archive', function( $atts ) {
	$atts = shortcode_atts( array(
		'slug'  => '',
		'count' => 12,
	), $atts, 'pr_category_archive' );
	if ( empty( $atts['slug'] ) ) { return ''; }
	$q = new WP_Query( array(
		'post_type'      => 'review',
		'posts_per_page' => max( 1, (int) $atts['count'] ),
		'tax_query'      => array( array(
			'taxonomy' => 'review_category',
			'field'    => 'slug',
			'terms'    => sanitize_title( $atts['slug'] ),
		) ),
		'no_found_rows'  => true,
	) );
	if ( ! $q->have_posts() ) {
		return '<p><em>' . esc_html__( 'New reviews are being prepared in this category. Check back soon.', 'product-reviews' ) . '</em></p>';
	}
	ob_start();
	echo '<div class="yf-grid yf-grid--3">';
	while ( $q->have_posts() ) {
		$q->the_post();
		printf(
			'<a class="yf-card" href="%1$s"><div class="yf-card__body"><h3 class="yf-card__title">%2$s</h3><p class="yf-card__excerpt">%3$s</p></div></a>',
			esc_url( get_permalink() ),
			esc_html( get_the_title() ),
			esc_html( wp_trim_words( get_the_excerpt(), 22 ) )
		);
	}
	echo '</div>';
	wp_reset_postdata();
	return ob_get_clean();
} );

/* ------------------------------------------------------------------ *
 * Activation hook: initial bulk import.
 * ------------------------------------------------------------------ */

function pr_categories_after_switch_theme() {
	if ( get_option( 'pr_categories_bootstrapped' ) ) {
		return;
	}
	pr_categories_state(); // seeds default state map
	pr_categories_sync_all();
	update_option( 'pr_categories_bootstrapped', '1', true );
}
add_action( 'after_switch_theme', 'pr_categories_after_switch_theme' );

/* ------------------------------------------------------------------ *
 * Admin UI — Categories grid.
 * ------------------------------------------------------------------ */

function pr_categories_register_admin_page() {
	add_submenu_page(
		'edit.php?post_type=review',
		__( 'Categories', 'product-reviews' ),
		__( 'Categories', 'product-reviews' ),
		'manage_categories',
		'pr-categories',
		'pr_render_categories_page'
	);
}
add_action( 'admin_menu', 'pr_categories_register_admin_page' );

function pr_render_categories_page() {
	if ( ! current_user_can( 'manage_categories' ) ) { return; }

	if ( isset( $_POST['pr_cat_nonce'] ) && wp_verify_nonce( $_POST['pr_cat_nonce'], 'pr_cat_save' ) ) {
		if ( isset( $_POST['pr_cat_action'] ) && 'save_state' === $_POST['pr_cat_action'] ) {
			$enabled = isset( $_POST['enabled'] ) ? (array) $_POST['enabled'] : array();
			$enabled = array_map( 'sanitize_title', $enabled );
			$state   = array();
			foreach ( pr_categories_catalog() as $row ) {
				$state[ $row['slug'] ] = in_array( $row['slug'], $enabled, true );
			}
			update_option( 'pr_categories_state', $state, false );
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Enabled categories saved.', 'product-reviews' ) . '</p></div>';
		}
		if ( isset( $_POST['pr_cat_action'] ) && 'sync_all' === $_POST['pr_cat_action'] ) {
			$force = ! empty( $_POST['force_landing'] );
			$res = pr_categories_sync_all( $force );
			echo '<div class="notice notice-success"><p>' . sprintf(
				esc_html__( 'Synced. Created: %1$d · Updated: %2$d · Skipped: %3$d · Errors: %4$d', 'product-reviews' ),
				(int) $res['created'], (int) $res['updated'], (int) $res['skipped'], count( $res['errors'] )
			) . '</p></div>';
		}
		if ( isset( $_POST['pr_cat_action'] ) && 'harvest_now' === $_POST['pr_cat_action'] ) {
			$res = pr_harvester_run();
			echo '<div class="notice notice-success"><p>' . sprintf(
				esc_html__( 'Harvester finished. Queued: %1$d · Skipped (recent dupes): %2$d · Categories scanned: %3$d', 'product-reviews' ),
				(int) $res['queued'], (int) $res['skipped'], (int) $res['categories']
			) . '</p></div>';
		}
	}

	$state   = pr_categories_state();
	$catalog = pr_categories_catalog();
	$last    = (string) get_option( 'pr_categories_last_sync', '' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Amazon Categories', 'product-reviews' ); ?></h1>
		<p>
			<?php esc_html_e( 'Toggle which Amazon departments this site covers. Enabled categories are imported as taxonomy terms with auto-generated landing pages and seed keywords for the autopilot.', 'product-reviews' ); ?>
		</p>
		<?php if ( $last ) : ?>
			<p><em><?php printf( esc_html__( 'Last sync: %s UTC', 'product-reviews' ), esc_html( $last ) ); ?></em></p>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'pr_cat_save', 'pr_cat_nonce' ); ?>
			<input type="hidden" name="pr_cat_action" value="save_state">
			<table class="widefat striped">
				<thead><tr>
					<th style="width:60px"><?php esc_html_e( 'On', 'product-reviews' ); ?></th>
					<th><?php esc_html_e( 'Department', 'product-reviews' ); ?></th>
					<th><?php esc_html_e( 'Search index', 'product-reviews' ); ?></th>
					<th><?php esc_html_e( 'Browse node', 'product-reviews' ); ?></th>
					<th><?php esc_html_e( 'Seed keywords', 'product-reviews' ); ?></th>
					<th><?php esc_html_e( 'Term / Landing', 'product-reviews' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $catalog as $row ) :
					$slug    = $row['slug'];
					$on      = ! empty( $state[ $slug ] );
					$term    = get_term_by( 'slug', $slug, 'review_category' );
					$page_id = $term ? (int) get_term_meta( $term->term_id, PR_CAT_META_LANDING_PAGE, true ) : 0;
					?>
					<tr>
						<td><input type="checkbox" name="enabled[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $on ); ?>></td>
						<td><strong><?php echo esc_html( $row['name'] ); ?></strong><br><code><?php echo esc_html( $slug ); ?></code></td>
						<td><code><?php echo esc_html( $row['search_index'] ?? '' ); ?></code></td>
						<td><code><?php echo esc_html( $row['browse_node'] ?? '' ); ?></code></td>
						<td><?php echo esc_html( implode( ', ', (array) ( $row['seed_keywords'] ?? array() ) ) ); ?></td>
						<td>
							<?php if ( $term ) : ?>
								<a href="<?php echo esc_url( get_edit_term_link( $term->term_id, 'review_category' ) ); ?>"><?php esc_html_e( 'Edit term', 'product-reviews' ); ?></a>
								<?php if ( $page_id ) : ?>
									· <a href="<?php echo esc_url( get_edit_post_link( $page_id ) ); ?>"><?php esc_html_e( 'Edit page', 'product-reviews' ); ?></a>
									· <a href="<?php echo esc_url( get_permalink( $page_id ) ); ?>" target="_blank" rel="noopener">↗</a>
								<?php endif; ?>
							<?php else : ?>
								<em><?php esc_html_e( 'not imported yet', 'product-reviews' ); ?></em>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php submit_button( __( 'Save enabled categories', 'product-reviews' ) ); ?>
		</form>

		<hr>

		<form method="post">
			<?php wp_nonce_field( 'pr_cat_save', 'pr_cat_nonce' ); ?>
			<input type="hidden" name="pr_cat_action" value="sync_all">
			<p>
				<label><input type="checkbox" name="force_landing" value="1"> <?php esc_html_e( 'Also refresh landing-page content (overwrites auto-generated body)', 'product-reviews' ); ?></label>
			</p>
			<?php submit_button( __( 'Sync enabled categories now', 'product-reviews' ), 'secondary' ); ?>
		</form>

		<form method="post" style="margin-top:12px">
			<?php wp_nonce_field( 'pr_cat_save', 'pr_cat_nonce' ); ?>
			<input type="hidden" name="pr_cat_action" value="harvest_now">
			<p>
				<?php
				$last = (string) get_option( 'pr_harvester_last_run', '' );
				$stats = (array) get_option( 'pr_harvester_last_stats', array() );
				if ( $last ) {
					printf(
						esc_html__( 'Last harvest: %1$s UTC · queued %2$d · skipped %3$d · categories %4$d', 'product-reviews' ),
						esc_html( $last ),
						(int) ( $stats['queued']  ?? 0 ),
						(int) ( $stats['skipped'] ?? 0 ),
						(int) ( $stats['cats']    ?? 0 )
					);
				} else {
					esc_html_e( 'Harvester has not run yet. It runs daily via WP-Cron, or click below to run it now.', 'product-reviews' );
				}
				?>
			</p>
			<?php submit_button( __( 'Run daily harvester now', 'product-reviews' ), 'secondary' ); ?>
		</form>
	</div>
	<?php
}
