<?php
/**
 * Product Reviews — theme bootstrap.
 *
 * Loads all theme modules in a predictable order. Each module is a single-
 * responsibility file under inc/. Keep this file thin.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PR_VERSION', '1.1.12' );
define( 'PR_THEME_DIR', get_template_directory() );
define( 'PR_THEME_URI', get_template_directory_uri() );

// Backwards-compat aliases so existing yadfood_* code keeps working
// while we migrate function/constant names module-by-module.
define( 'YADFOOD_VERSION', PR_VERSION );
define( 'YADFOOD_THEME_DIR', PR_THEME_DIR );
define( 'YADFOOD_THEME_URI', PR_THEME_URI );

/**
 * Theme support + i18n.
 */
function pr_setup() {
	load_theme_textdomain( 'product-reviews', PR_THEME_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'custom-logo', array(
		'height'      => 48,
		'width'       => 200,
		'flex-height' => true,
		'flex-width'  => true,
	) );

	register_nav_menus( array(
		'primary' => __( 'Primary Menu', 'product-reviews' ),
		'footer'  => __( 'Footer Menu', 'product-reviews' ),
	) );

	add_image_size( 'pr-card', 640, 480, true );
	add_image_size( 'pr-hero', 1600, 900, true );

	// Legacy image-size aliases.
	add_image_size( 'yadfood-card', 640, 480, true );
	add_image_size( 'yadfood-hero', 1600, 900, true );
}
add_action( 'after_setup_theme', 'pr_setup' );
// Legacy hook name for any third-party code.
function yadfood_setup() { pr_setup(); }

/**
 * Load theme modules. branding.php first so pr_brand() is available everywhere.
 */
require_once PR_THEME_DIR . '/inc/branding.php';
require_once PR_THEME_DIR . '/inc/helpers.php';
require_once PR_THEME_DIR . '/inc/default-content.php';
require_once PR_THEME_DIR . '/inc/sources.php';
require_once PR_THEME_DIR . '/inc/autopilot.php';
require_once PR_THEME_DIR . '/inc/enqueue.php';
require_once PR_THEME_DIR . '/inc/performance.php';
require_once PR_THEME_DIR . '/inc/post-types.php';
require_once PR_THEME_DIR . '/inc/taxonomies.php';
require_once PR_THEME_DIR . '/inc/categories.php';
require_once PR_THEME_DIR . '/inc/meta-fields.php';
require_once PR_THEME_DIR . '/inc/customizer.php';
require_once PR_THEME_DIR . '/inc/smart-search.php';
require_once PR_THEME_DIR . '/inc/seo.php';
require_once PR_THEME_DIR . '/inc/schema.php';
require_once PR_THEME_DIR . '/inc/internal-links.php';
require_once PR_THEME_DIR . '/inc/amazon-api.php';
require_once PR_THEME_DIR . '/inc/amazon-geo.php';
require_once PR_THEME_DIR . '/inc/amazon-refresh.php';
require_once PR_THEME_DIR . '/inc/amazon-badges.php';
require_once PR_THEME_DIR . '/inc/amazon-sparkline.php';
require_once PR_THEME_DIR . '/inc/amazon-variations.php';
require_once PR_THEME_DIR . '/inc/freshness.php';
require_once PR_THEME_DIR . '/inc/scoring.php';
require_once PR_THEME_DIR . '/inc/ai-generator.php';
require_once PR_THEME_DIR . '/inc/admin-page.php';
require_once PR_THEME_DIR . '/inc/change-log-admin.php';
require_once PR_THEME_DIR . '/inc/cron.php';
require_once PR_THEME_DIR . '/inc/rest-api.php';
require_once PR_THEME_DIR . '/inc/click-analytics.php';
require_once PR_THEME_DIR . '/inc/compare.php';
require_once PR_THEME_DIR . '/inc/facets.php';
require_once PR_THEME_DIR . '/inc/search-suggest.php';
require_once PR_THEME_DIR . '/inc/blocks.php';
require_once PR_THEME_DIR . '/inc/deals.php';
require_once PR_THEME_DIR . '/inc/author.php';
require_once PR_THEME_DIR . '/inc/trust.php';
require_once PR_THEME_DIR . '/inc/hubs.php';
require_once PR_THEME_DIR . '/inc/internal-graph.php';
require_once PR_THEME_DIR . '/inc/cluster.php';
require_once PR_THEME_DIR . '/inc/eeat.php';
require_once PR_THEME_DIR . '/inc/review-quality.php';
require_once PR_THEME_DIR . '/inc/schema-extras.php';
require_once PR_THEME_DIR . '/inc/media-schema.php';
require_once PR_THEME_DIR . '/inc/aggregate-score.php';
require_once PR_THEME_DIR . '/inc/price-availability.php';
require_once PR_THEME_DIR . '/inc/entity-graph.php';
require_once PR_THEME_DIR . '/inc/breadcrumbs.php';
require_once PR_THEME_DIR . '/inc/faq.php';
require_once PR_THEME_DIR . '/inc/howto.php';
require_once PR_THEME_DIR . '/inc/pros-cons.php';
require_once PR_THEME_DIR . '/inc/author-block.php';
require_once PR_THEME_DIR . '/inc/tldr.php';
require_once PR_THEME_DIR . '/inc/toc.php';
require_once PR_THEME_DIR . '/inc/buyers-guide.php';
require_once PR_THEME_DIR . '/inc/related-products.php';
require_once PR_THEME_DIR . '/inc/email-alert.php';
require_once PR_THEME_DIR . '/inc/admin-email-alerts.php';
