<?php
/**
 * Click analytics — affiliate click tracking, conversion dashboard,
 * outbound UTM tagging, and a public JSON reporting API.
 *
 * Tables:
 *   {$wpdb->prefix}pr_clicks
 *     id, asin, post_id, slug, source, utm_source, utm_medium, utm_campaign,
 *     referer, ua_hash, ip_hash, country, clicked_at
 *
 * REST:
 *   POST /wp-json/yadfood/v1/click                      — beacon logger (public)
 *   GET  /wp-json/yadfood/v1/analytics/clicks?from&to   — JSON report
 *        Auth: header `X-API-Key: sk-default-key`  OR  ?api_key=sk-default-key
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PR_Click_Analytics {

	const TABLE        = 'pr_clicks';
	const OPT_API_KEY  = 'pr_analytics_api_key';
	const DEFAULT_KEY  = 'sk-default-key';

	public static function init() {
		add_action( 'after_switch_theme', array( __CLASS__, 'install' ) );
		add_action( 'init',               array( __CLASS__, 'maybe_install' ) );

		add_action( 'rest_api_init',      array( __CLASS__, 'register_routes' ) );
		add_action( 'yadfood_affiliate_click', array( __CLASS__, 'log_click' ), 10, 2 );

		add_filter( 'pr_amazon_outbound_url', array( __CLASS__, 'append_utm' ), 10, 3 );

		add_action( 'admin_menu',         array( __CLASS__, 'admin_menu' ) );
	}

	/* ---------- Schema ---------- */

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	public static function maybe_install() {
		if ( get_option( 'pr_clicks_schema_version' ) !== '1' ) {
			self::install();
		}
		if ( ! get_option( self::OPT_API_KEY ) ) {
			update_option( self::OPT_API_KEY, self::DEFAULT_KEY, false );
		}
	}

	public static function install() {
		global $wpdb;
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			asin VARCHAR(20) NOT NULL,
			post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			slug VARCHAR(200) NOT NULL DEFAULT '',
			source VARCHAR(40) NOT NULL DEFAULT 'web',
			utm_source VARCHAR(80) NOT NULL DEFAULT '',
			utm_medium VARCHAR(80) NOT NULL DEFAULT '',
			utm_campaign VARCHAR(120) NOT NULL DEFAULT '',
			referer VARCHAR(500) NOT NULL DEFAULT '',
			ua_hash CHAR(32) NOT NULL DEFAULT '',
			ip_hash CHAR(32) NOT NULL DEFAULT '',
			country VARCHAR(8) NOT NULL DEFAULT '',
			clicked_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY asin (asin),
			KEY post_id (post_id),
			KEY clicked_at (clicked_at)
		) {$charset};" );
		update_option( 'pr_clicks_schema_version', '1', false );
	}

	/* ---------- Logging ---------- */

	public static function log_click( $asin, $slug ) {
		global $wpdb;
		$asin = strtoupper( preg_replace( '/[^A-Z0-9]/i', '', (string) $asin ) );
		if ( '' === $asin ) {
			return;
		}

		$post_id = 0;
		if ( $slug ) {
			$p = get_page_by_path( $slug, OBJECT, 'review' );
			if ( $p ) $post_id = (int) $p->ID;
		}

		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( $_SERVER['HTTP_REFERER'] ) : '';
		$ua      = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
		$ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		$salt    = wp_salt( 'nonce' );

		$utm = array( 'source' => '', 'medium' => '', 'campaign' => '' );
		if ( $referer ) {
			$qs = wp_parse_url( $referer, PHP_URL_QUERY );
			if ( $qs ) {
				parse_str( $qs, $q );
				$utm['source']   = isset( $q['utm_source'] )   ? sanitize_text_field( $q['utm_source'] )   : '';
				$utm['medium']   = isset( $q['utm_medium'] )   ? sanitize_text_field( $q['utm_medium'] )   : '';
				$utm['campaign'] = isset( $q['utm_campaign'] ) ? sanitize_text_field( $q['utm_campaign'] ) : '';
			}
		}

		$wpdb->insert( self::table(), array(
			'asin'         => $asin,
			'post_id'      => $post_id,
			'slug'         => sanitize_title( (string) $slug ),
			'source'       => 'web',
			'utm_source'   => $utm['source'],
			'utm_medium'   => $utm['medium'],
			'utm_campaign' => $utm['campaign'],
			'referer'      => $referer,
			'ua_hash'      => $ua ? md5( $salt . '|' . $ua ) : '',
			'ip_hash'      => $ip ? md5( $salt . '|' . $ip ) : '',
			'country'      => '',
			'clicked_at'   => current_time( 'mysql', true ),
		) );
	}

	/* ---------- Outbound UTM tagging ---------- */

	public static function append_utm( $url, $asin, $subtag ) {
		if ( ! $url ) return $url;
		$args = array(
			'utm_source'   => 'productreviews',
			'utm_medium'   => 'affiliate',
			'utm_campaign' => $subtag ? sanitize_title( $subtag ) : 'review',
		);
		return add_query_arg( $args, $url );
	}

	/* ---------- REST routes ---------- */

	public static function register_routes() {
		register_rest_route( 'yadfood/v1', '/analytics/clicks', array(
			'methods'             => 'GET',
			'permission_callback' => array( __CLASS__, 'check_api_key' ),
			'callback'            => array( __CLASS__, 'rest_report' ),
			'args' => array(
				'from'     => array( 'type' => 'string' ),
				'to'       => array( 'type' => 'string' ),
				'group_by' => array( 'type' => 'string' ),
				'asin'     => array( 'type' => 'string' ),
				'post_id'  => array( 'type' => 'integer' ),
				'limit'    => array( 'type' => 'integer' ),
			),
		) );
	}

	public static function check_api_key( WP_REST_Request $req ) {
		$expected = (string) get_option( self::OPT_API_KEY, self::DEFAULT_KEY );
		$provided = (string) $req->get_header( 'x_api_key' );
		if ( '' === $provided ) {
			$provided = (string) $req->get_param( 'api_key' );
		}
		if ( '' === $provided ) {
			$auth = (string) $req->get_header( 'authorization' );
			if ( 0 === stripos( $auth, 'Bearer ' ) ) {
				$provided = trim( substr( $auth, 7 ) );
			}
		}
		if ( ! hash_equals( $expected, $provided ) ) {
			return new WP_Error( 'unauthorized', 'Invalid or missing API key.', array( 'status' => 401 ) );
		}
		return true;
	}

	public static function rest_report( WP_REST_Request $req ) {
		global $wpdb;
		$table = self::table();

		$to   = self::parse_date( $req->get_param( 'to' ),   'now' );
		$from = self::parse_date( $req->get_param( 'from' ), '-29 days' );
		if ( ! $from || ! $to ) {
			return new WP_REST_Response( array( 'error' => 'Invalid from/to date.' ), 400 );
		}
		if ( $from > $to ) { list( $from, $to ) = array( $to, $from ); }

		$group_by = in_array( $req->get_param( 'group_by' ), array( 'day', 'asin', 'post', 'campaign' ), true )
			? $req->get_param( 'group_by' ) : 'day';

		$asin    = strtoupper( preg_replace( '/[^A-Z0-9]/i', '', (string) $req->get_param( 'asin' ) ) );
		$post_id = (int) $req->get_param( 'post_id' );
		$limit   = max( 1, min( 1000, (int) ( $req->get_param( 'limit' ) ?: 500 ) ) );

		$where = array( 'clicked_at BETWEEN %s AND %s' );
		$args  = array( $from . ' 00:00:00', $to . ' 23:59:59' );
		if ( $asin )    { $where[] = 'asin = %s';    $args[] = $asin; }
		if ( $post_id ) { $where[] = 'post_id = %d'; $args[] = $post_id; }
		$where_sql = implode( ' AND ', $where );

		$total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $args
		) );
		$uniques = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT ip_hash) FROM {$table} WHERE {$where_sql}", $args
		) );

		switch ( $group_by ) {
			case 'asin':
				$rows = $wpdb->get_results( $wpdb->prepare(
					"SELECT asin, COUNT(*) AS clicks, COUNT(DISTINCT ip_hash) AS unique_clicks
					 FROM {$table} WHERE {$where_sql}
					 GROUP BY asin ORDER BY clicks DESC LIMIT %d",
					array_merge( $args, array( $limit ) )
				), ARRAY_A );
				break;
			case 'post':
				$rows = $wpdb->get_results( $wpdb->prepare(
					"SELECT post_id, slug, COUNT(*) AS clicks, COUNT(DISTINCT ip_hash) AS unique_clicks
					 FROM {$table} WHERE {$where_sql}
					 GROUP BY post_id, slug ORDER BY clicks DESC LIMIT %d",
					array_merge( $args, array( $limit ) )
				), ARRAY_A );
				foreach ( $rows as &$r ) {
					$r['post_id']   = (int) $r['post_id'];
					$r['permalink'] = $r['post_id'] ? get_permalink( $r['post_id'] ) : '';
					$r['title']     = $r['post_id'] ? get_the_title( $r['post_id'] ) : '';
				}
				unset( $r );
				break;
			case 'campaign':
				$rows = $wpdb->get_results( $wpdb->prepare(
					"SELECT utm_source, utm_medium, utm_campaign, COUNT(*) AS clicks
					 FROM {$table} WHERE {$where_sql}
					 GROUP BY utm_source, utm_medium, utm_campaign ORDER BY clicks DESC LIMIT %d",
					array_merge( $args, array( $limit ) )
				), ARRAY_A );
				break;
			case 'day':
			default:
				$rows = $wpdb->get_results( $wpdb->prepare(
					"SELECT DATE(clicked_at) AS date, COUNT(*) AS clicks, COUNT(DISTINCT ip_hash) AS unique_clicks
					 FROM {$table} WHERE {$where_sql}
					 GROUP BY DATE(clicked_at) ORDER BY date ASC LIMIT %d",
					array_merge( $args, array( $limit ) )
				), ARRAY_A );
				break;
		}

		// Cast numerics.
		foreach ( $rows as &$r ) {
			if ( isset( $r['clicks'] ) )         $r['clicks']        = (int) $r['clicks'];
			if ( isset( $r['unique_clicks'] ) )  $r['unique_clicks'] = (int) $r['unique_clicks'];
		}
		unset( $r );

		return new WP_REST_Response( array(
			'range'    => array( 'from' => $from, 'to' => $to ),
			'group_by' => $group_by,
			'totals'   => array( 'clicks' => $total, 'unique_clicks' => $uniques ),
			'rows'     => $rows,
		), 200 );
	}

	private static function parse_date( $val, $fallback ) {
		$ts = $val ? strtotime( (string) $val ) : strtotime( $fallback );
		return $ts ? gmdate( 'Y-m-d', $ts ) : '';
	}

	/* ---------- Admin dashboard ---------- */

	public static function admin_menu() {
		add_submenu_page(
			'edit.php?post_type=review',
			__( 'Click Analytics', 'product-reviews' ),
			__( 'Click Analytics', 'product-reviews' ),
			'edit_posts',
			'pr-click-analytics',
			array( __CLASS__, 'render_admin' )
		);
	}

	public static function render_admin() {
		global $wpdb;
		$table = self::table();

		// Save API key.
		if ( ! empty( $_POST['pr_save_key'] ) && check_admin_referer( 'pr_save_api_key' ) ) {
			$new = sanitize_text_field( wp_unslash( $_POST['pr_api_key'] ?? '' ) );
			if ( $new ) update_option( self::OPT_API_KEY, $new, false );
			echo '<div class="notice notice-success"><p>API key updated.</p></div>';
		}

		$api_key = (string) get_option( self::OPT_API_KEY, self::DEFAULT_KEY );
		$from    = gmdate( 'Y-m-d', strtotime( '-29 days' ) );
		$to      = gmdate( 'Y-m-d' );

		$totals = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) AS c, COUNT(DISTINCT ip_hash) AS u
			 FROM {$table} WHERE clicked_at BETWEEN %s AND %s",
			$from . ' 00:00:00', $to . ' 23:59:59'
		), ARRAY_A );

		$by_day = $wpdb->get_results( $wpdb->prepare(
			"SELECT DATE(clicked_at) d, COUNT(*) c FROM {$table}
			 WHERE clicked_at BETWEEN %s AND %s GROUP BY DATE(clicked_at) ORDER BY d ASC",
			$from . ' 00:00:00', $to . ' 23:59:59'
		), ARRAY_A );

		$top_asins = $wpdb->get_results( $wpdb->prepare(
			"SELECT asin, COUNT(*) c FROM {$table}
			 WHERE clicked_at BETWEEN %s AND %s
			 GROUP BY asin ORDER BY c DESC LIMIT 20",
			$from . ' 00:00:00', $to . ' 23:59:59'
		), ARRAY_A );

		$top_posts = $wpdb->get_results( $wpdb->prepare(
			"SELECT post_id, COUNT(*) c FROM {$table}
			 WHERE clicked_at BETWEEN %s AND %s AND post_id > 0
			 GROUP BY post_id ORDER BY c DESC LIMIT 20",
			$from . ' 00:00:00', $to . ' 23:59:59'
		), ARRAY_A );

		$rest_url = esc_url( rest_url( 'yadfood/v1/analytics/clicks' ) );
		?>
		<div class="wrap">
			<h1>Click Analytics</h1>
			<p>Last 30 days · <strong><?php echo (int) $totals['c']; ?></strong> clicks · <strong><?php echo (int) $totals['u']; ?></strong> unique visitors</p>

			<h2>API access</h2>
			<form method="post" style="margin-bottom:24px;max-width:680px;">
				<?php wp_nonce_field( 'pr_save_api_key' ); ?>
				<p>
					<label><strong>API key</strong></label><br>
					<input type="text" name="pr_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text code" style="width:100%;">
				</p>
				<p><button class="button button-primary" name="pr_save_key" value="1">Save</button></p>
				<p><strong>Endpoint:</strong></p>
				<pre style="background:#f6f7f7;padding:10px;overflow:auto;"><?php
					echo esc_html( "GET {$rest_url}?from=2026-01-01&to=2026-01-31&group_by=asin\nHeader: X-API-Key: {$api_key}" );
				?></pre>
				<p>Supported <code>group_by</code>: <code>day</code> (default), <code>asin</code>, <code>post</code>, <code>campaign</code>. Optional filters: <code>asin</code>, <code>post_id</code>, <code>limit</code> (max 1000). Auth via header <code>X-API-Key</code>, query <code>?api_key=</code>, or <code>Authorization: Bearer</code>.</p>
			</form>

			<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
				<div>
					<h2>Top ASINs</h2>
					<table class="widefat striped"><thead><tr><th>ASIN</th><th>Clicks</th></tr></thead><tbody>
					<?php foreach ( $top_asins as $r ) : ?>
						<tr><td><code><?php echo esc_html( $r['asin'] ); ?></code></td><td><?php echo (int) $r['c']; ?></td></tr>
					<?php endforeach; ?>
					<?php if ( ! $top_asins ) echo '<tr><td colspan="2">No clicks yet.</td></tr>'; ?>
					</tbody></table>
				</div>
				<div>
					<h2>Top reviews</h2>
					<table class="widefat striped"><thead><tr><th>Review</th><th>Clicks</th></tr></thead><tbody>
					<?php foreach ( $top_posts as $r ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( get_edit_post_link( (int) $r['post_id'] ) ); ?>"><?php echo esc_html( get_the_title( (int) $r['post_id'] ) ?: '#' . $r['post_id'] ); ?></a></td>
							<td><?php echo (int) $r['c']; ?></td>
						</tr>
					<?php endforeach; ?>
					<?php if ( ! $top_posts ) echo '<tr><td colspan="2">No clicks yet.</td></tr>'; ?>
					</tbody></table>
				</div>
			</div>

			<h2 style="margin-top:32px;">Daily clicks</h2>
			<table class="widefat striped" style="max-width:480px;"><thead><tr><th>Date</th><th>Clicks</th></tr></thead><tbody>
			<?php foreach ( $by_day as $r ) : ?>
				<tr><td><?php echo esc_html( $r['d'] ); ?></td><td><?php echo (int) $r['c']; ?></td></tr>
			<?php endforeach; ?>
			<?php if ( ! $by_day ) echo '<tr><td colspan="2">No clicks yet.</td></tr>'; ?>
			</tbody></table>
		</div>
		<?php
	}
}

PR_Click_Analytics::init();
