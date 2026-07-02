<?php
/**
 * Autopilot bootloader.
 *
 * @package ProductReviews
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once PR_THEME_DIR . '/inc/autopilot/schema.php';
require_once PR_THEME_DIR . '/inc/autopilot/queue.php';
require_once PR_THEME_DIR . '/inc/autopilot/dedup.php';
require_once PR_THEME_DIR . '/inc/autopilot/facts.php';
require_once PR_THEME_DIR . '/inc/autopilot/orchestrator.php';
require_once PR_THEME_DIR . '/inc/autopilot/discovery.php';
require_once PR_THEME_DIR . '/inc/autopilot/research.php';
require_once PR_THEME_DIR . '/inc/autopilot/factcheck.php';
require_once PR_THEME_DIR . '/inc/autopilot/writer.php';
require_once PR_THEME_DIR . '/inc/autopilot/harvester.php';
require_once PR_THEME_DIR . '/inc/autopilot/monitor.php';

/**
 * Public helper used by admin UI and category-discovery (milestone 4+).
 */
function pr_enqueue_keyword( string $keyword, ?int $category_id = null, int $priority = 5 ): int {
	return PR_Queue::enqueue( $keyword, $category_id, array(), $priority );
}
