<?php
/**
 * Admin pages: "Auto Generator" + "Auto Queue".
 *
 * Auto Generator — on-demand: type keyword, click Generate, get a draft review.
 * Auto Queue     — list of keywords processed daily by cron (one per day).
 *
 * @package YadFoodReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function yadfood_register_admin_pages() {
	add_submenu_page(
		'edit.php?post_type=review',
		__( 'Auto Generator', 'yadfood-reviews' ),
		__( 'Auto Generator', 'yadfood-reviews' ),
		'edit_posts',
		'yadfood-generator',
		'yadfood_render_generator_page'
	);
	add_submenu_page(
		'edit.php?post_type=review',
		__( 'Auto Queue', 'yadfood-reviews' ),
		__( 'Auto Queue', 'yadfood-reviews' ),
		'manage_options',
		'yadfood-queue',
		'yadfood_render_queue_page'
	);
}
add_action( 'admin_menu', 'yadfood_register_admin_pages' );

function yadfood_render_generator_page() {
	$last_result = get_transient( 'yadfood_last_gen_result' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'YadFood — Auto Generator', 'yadfood-reviews' ); ?></h1>
		<p><?php esc_html_e( 'Type a keyword (e.g. "best coffee grinder") and click Generate. The theme will fetch live Amazon products and use AI to write a full review article saved as a draft.', 'yadfood-reviews' ); ?></p>

		<?php if ( $last_result ) : ?>
			<div class="notice notice-<?php echo esc_attr( $last_result['type'] ); ?>">
				<p><?php echo wp_kses_post( $last_result['msg'] ); ?></p>
			</div>
			<?php delete_transient( 'yadfood_last_gen_result' ); ?>
		<?php endif; ?>

		<form method="post" action="">
			<?php wp_nonce_field( 'yadfood_generate', 'yadfood_generate_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="yf-kw"><?php esc_html_e( 'Keyword', 'yadfood-reviews' ); ?></label></th>
					<td><input id="yf-kw" type="text" name="keyword" class="regular-text" placeholder="best coffee grinder" required></td>
				</tr>
				<tr>
					<th><label for="yf-count"><?php esc_html_e( 'Number of products', 'yadfood-reviews' ); ?></label></th>
					<td><input id="yf-count" type="number" name="count" value="10" min="3" max="10"></td>
				</tr>
				<tr>
					<th><label for="yf-status"><?php esc_html_e( 'Publish status', 'yadfood-reviews' ); ?></label></th>
					<td>
						<select id="yf-status" name="status">
							<option value="draft"><?php esc_html_e( 'Draft (recommended)', 'yadfood-reviews' ); ?></option>
							<option value="publish"><?php esc_html_e( 'Publish immediately', 'yadfood-reviews' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Generate Article', 'yadfood-reviews' ) ); ?>
		</form>
	</div>
	<?php
}

function yadfood_handle_generate_submit() {
	if ( ! isset( $_POST['yadfood_generate_nonce'] ) || ! wp_verify_nonce( $_POST['yadfood_generate_nonce'], 'yadfood_generate' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$kw     = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';
	$count  = isset( $_POST['count'] ) ? max( 3, min( 10, intval( $_POST['count'] ) ) ) : 10;
	$status = isset( $_POST['status'] ) && 'publish' === $_POST['status'] ? 'publish' : 'draft';

	if ( '' === $kw ) {
		set_transient( 'yadfood_last_gen_result', array( 'type' => 'error', 'msg' => __( 'Keyword is required.', 'yadfood-reviews' ) ), 60 );
		return;
	}
	$post_id = yadfood_generate_review( $kw, $count, $status );
	if ( is_wp_error( $post_id ) ) {
		set_transient( 'yadfood_last_gen_result', array(
			'type' => 'error',
			'msg'  => esc_html( $post_id->get_error_message() ),
		), 60 );
		return;
	}
	$edit = get_edit_post_link( $post_id, '' );
	set_transient( 'yadfood_last_gen_result', array(
		'type' => 'success',
		'msg'  => sprintf( __( 'Article generated. <a href="%s">Edit it here</a>.', 'yadfood-reviews' ), esc_url( $edit ) ),
	), 60 );
}
add_action( 'admin_init', 'yadfood_handle_generate_submit' );

/* ---------------- Auto Queue ---------------- */

function yadfood_render_queue_page() {
	if ( isset( $_POST['yadfood_queue_nonce'] ) && wp_verify_nonce( $_POST['yadfood_queue_nonce'], 'yadfood_queue' ) ) {
		$raw = isset( $_POST['queue'] ) ? wp_unslash( $_POST['queue'] ) : '';
		$lines = array_filter( array_map( 'trim', preg_split( '/\r?\n/', $raw ) ) );
		update_option( 'yadfood_keyword_queue', array_values( $lines ) );
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Queue saved.', 'yadfood-reviews' ) . '</p></div>';
	}
	$queue = (array) get_option( 'yadfood_keyword_queue', array() );
	$cron_enabled = get_option( 'yadfood_cron_enabled', '0' );
	$next  = wp_next_scheduled( 'yadfood_daily_generate' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'YadFood — Auto Queue', 'yadfood-reviews' ); ?></h1>
		<p>
			<?php esc_html_e( 'Add one keyword per line. When the daily cron runs, the first queued keyword is generated as a draft, then removed from the queue.', 'yadfood-reviews' ); ?>
		</p>
		<p>
			<?php if ( '1' === $cron_enabled && $next ) : ?>
				<strong><?php esc_html_e( 'Cron status:', 'yadfood-reviews' ); ?></strong>
				<?php echo esc_html__( 'Enabled. Next run: ', 'yadfood-reviews' ) . esc_html( date_i18n( 'Y-m-d H:i', $next ) ); ?>
			<?php else : ?>
				<em><?php esc_html_e( 'Cron is disabled. Turn it on under Appearance → Customize → AI Article Generation.', 'yadfood-reviews' ); ?></em>
			<?php endif; ?>
		</p>

		<form method="post">
			<?php wp_nonce_field( 'yadfood_queue', 'yadfood_queue_nonce' ); ?>
			<textarea name="queue" rows="14" style="width:100%;font-family:monospace;"><?php
				echo esc_textarea( implode( "\n", $queue ) );
			?></textarea>
			<?php submit_button( __( 'Save Queue', 'yadfood-reviews' ) ); ?>
		</form>
	</div>
	<?php
}

/* ============================================================
 * Product Reviews — Settings (Milestone 2: source layer).
 * ============================================================ */
function pr_register_settings_page() {
	add_submenu_page(
		'edit.php?post_type=review',
		__( 'Settings', 'product-reviews' ),
		__( 'Settings', 'product-reviews' ),
		'manage_options',
		'pr-settings',
		'pr_render_settings_page'
	);
}
add_action( 'admin_menu', 'pr_register_settings_page' );

function pr_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	if ( isset( $_POST['pr_settings_nonce'] ) && wp_verify_nonce( $_POST['pr_settings_nonce'], 'pr_save_settings' ) ) {
		update_option( 'pr_affiliate_tag', sanitize_text_field( $_POST['pr_affiliate_tag'] ?? 'YOUR-TAG-20' ) );
		update_option( 'pr_source_chain', sanitize_text_field( $_POST['pr_source_chain'] ?? 'lambda,scrape,creators,paapi,fallback' ) );
		update_option( 'pr_firecrawl_api_key', sanitize_text_field( $_POST['pr_firecrawl_api_key'] ?? '' ) );
		update_option( 'pr_creators_client_id', sanitize_text_field( $_POST['pr_creators_client_id'] ?? '' ) );
		update_option( 'pr_creators_client_secret', sanitize_text_field( $_POST['pr_creators_client_secret'] ?? '' ) );
		$cookie = trim( wp_unslash( $_POST['pr_amazon_cookie'] ?? '' ) );
		if ( $cookie !== '___keep___' ) {
			pr_set_encrypted_option( 'pr_amazon_cookie', $cookie );
		}
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'product-reviews' ) . '</p></div>';
	}

	$tag         = pr_affiliate_tag();
	$chain       = (string) get_option( 'pr_source_chain', 'lambda,scrape,creators,paapi,fallback' );
	$firecrawl   = (string) get_option( 'pr_firecrawl_api_key', '' );
	$creators_id = (string) get_option( 'pr_creators_client_id', '' );
	$creators_sk = (string) get_option( 'pr_creators_client_secret', '' );
	$cookie_set  = pr_decrypt_option( 'pr_amazon_cookie' ) !== '';
	$drivers     = PR_Source_Manager::drivers();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Product Reviews — Settings', 'product-reviews' ); ?></h1>
		<p><?php esc_html_e( 'Product data flows through the driver chain below. The first driver that returns results wins; on failure the manager falls through to the next. The built-in Lambda driver works with no credentials, and the fallback driver keeps the site usable if live data is unavailable.', 'product-reviews' ); ?></p>

		<form method="post">
			<?php wp_nonce_field( 'pr_save_settings', 'pr_settings_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="pr_affiliate_tag"><?php esc_html_e( 'Amazon Associates Tag', 'product-reviews' ); ?></label></th>
					<td>
						<input type="text" id="pr_affiliate_tag" name="pr_affiliate_tag" value="<?php echo esc_attr( $tag ); ?>" class="regular-text" placeholder="YOUR-TAG-20">
						<p class="description"><?php esc_html_e( 'Applied to every affiliate link. Default: YOUR-TAG-20 (change to your real tag).', 'product-reviews' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="pr_source_chain"><?php esc_html_e( 'Driver chain (priority order)', 'product-reviews' ); ?></label></th>
					<td>
						<input type="text" id="pr_source_chain" name="pr_source_chain" value="<?php echo esc_attr( $chain ); ?>" class="large-text" placeholder="lambda,scrape,creators,paapi,fallback">
						<p class="description"><?php esc_html_e( 'Comma-separated. Available: lambda, scrape, creators, paapi, fallback. Keep fallback last for safe fresh-install results.', 'product-reviews' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="pr_firecrawl_api_key"><?php esc_html_e( 'Firecrawl API key (for scrape driver)', 'product-reviews' ); ?></label></th>
					<td><input type="password" id="pr_firecrawl_api_key" name="pr_firecrawl_api_key" value="<?php echo esc_attr( $firecrawl ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="pr_amazon_cookie"><?php esc_html_e( 'Amazon Cookie header (optional, for scrape)', 'product-reviews' ); ?></label></th>
					<td>
						<textarea id="pr_amazon_cookie" name="pr_amazon_cookie" rows="4" class="large-text code" placeholder="session-id=...; ubid-main=...; i18n-prefs=USD; ..."><?php echo $cookie_set ? '___keep___' : ''; ?></textarea>
						<p class="description">
							<?php echo $cookie_set
								? esc_html__( 'A cookie is currently stored (encrypted with AUTH_KEY). Leave "___keep___" to keep it, clear the field to remove it, or paste a new one to replace it.', 'product-reviews' )
								: esc_html__( 'Paste the full Cookie header from amazon.com. Encrypted at rest using AUTH_KEY.', 'product-reviews' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Creators API (recommended)', 'product-reviews' ); ?></th>
					<td>
						<input type="text" name="pr_creators_client_id" value="<?php echo esc_attr( $creators_id ); ?>" placeholder="Client ID" class="regular-text"><br>
						<input type="password" name="pr_creators_client_secret" value="<?php echo esc_attr( $creators_sk ); ?>" placeholder="Client Secret" class="regular-text" style="margin-top:6px">
						<p class="description"><?php esc_html_e( 'Live HTTP wiring lands in a later milestone. Stored values are picked up automatically when the driver is ready.', 'product-reviews' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save Settings', 'product-reviews' ) ); ?>
		</form>

		<h2><?php esc_html_e( 'Driver status & test', 'product-reviews' ); ?></h2>
		<table class="widefat striped" id="pr-driver-table">
			<thead><tr>
				<th><?php esc_html_e( 'Driver', 'product-reviews' ); ?></th>
				<th><?php esc_html_e( 'Configured', 'product-reviews' ); ?></th>
				<th><?php esc_html_e( 'OK / Err', 'product-reviews' ); ?></th>
				<th><?php esc_html_e( 'Last (ms)', 'product-reviews' ); ?></th>
				<th><?php esc_html_e( 'Last message', 'product-reviews' ); ?></th>
				<th><?php esc_html_e( 'Test', 'product-reviews' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $drivers as $id => $d ) :
				$h = PR_Source_Manager::health( $id ); ?>
				<tr>
					<td><strong><?php echo esc_html( $d->label() ); ?></strong><br><code><?php echo esc_html( $id ); ?></code></td>
					<td><?php echo $d->is_configured() ? '✅' : '—'; ?></td>
					<td><?php echo (int) $h['ok'] . ' / ' . (int) $h['err']; ?></td>
					<td><?php echo (int) $h['last_ms']; ?></td>
					<td><code><?php echo esc_html( mb_substr( (string) $h['last_msg'], 0, 90 ) ); ?></code></td>
					<td><button type="button" class="button pr-test-source" data-driver="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Test', 'product-reviews' ); ?></button>
						<span class="pr-test-result" style="margin-left:8px"></span></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<script>
		(function(){
			const nonce = <?php echo wp_json_encode( wp_create_nonce( 'pr_test_source' ) ); ?>;
			document.querySelectorAll('.pr-test-source').forEach(btn => {
				btn.addEventListener('click', async () => {
					const driver = btn.dataset.driver;
					const out = btn.nextElementSibling;
					out.textContent = 'Testing…';
					const fd = new FormData();
					fd.append('action', 'pr_test_source');
					fd.append('nonce', nonce);
					fd.append('driver', driver);
					fd.append('q', 'usb cable');
					try {
						const r = await fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' });
						const j = await r.json();
						if (j.success) {
							out.innerHTML = '✅ ' + j.data.count + ' items in ' + j.data.latency_ms + 'ms';
						} else {
							out.innerHTML = '❌ ' + (j.data && j.data.message ? j.data.message : 'failed');
						}
					} catch (e) {
						out.textContent = '❌ ' + e.message;
					}
				});
			});
		})();
		</script>
	</div>
	<?php
}

/* ============================================================
 * Autopilot admin page — queue health, run log, dead-letter.
 * Settings additions: enable + tick interval.
 * ============================================================ */
function pr_register_autopilot_page() {
	add_submenu_page(
		'edit.php?post_type=review',
		__( 'Autopilot', 'product-reviews' ),
		__( 'Autopilot', 'product-reviews' ),
		'manage_options',
		'pr-autopilot',
		'pr_render_autopilot_page'
	);
}
add_action( 'admin_menu', 'pr_register_autopilot_page' );

function pr_render_autopilot_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	global $wpdb;

	// Handle actions.
	if ( isset( $_POST['pr_autopilot_nonce'] ) && wp_verify_nonce( $_POST['pr_autopilot_nonce'], 'pr_autopilot' ) ) {
		if ( isset( $_POST['pr_save_settings'] ) ) {
			update_option( 'pr_autopilot_enabled', isset( $_POST['pr_autopilot_enabled'] ) ? '1' : '0' );
			$interval = sanitize_text_field( $_POST['pr_autopilot_interval'] ?? 'pr_15min' );
			update_option( 'pr_autopilot_interval', $interval );
			$thresholds = array(
				'min_product_confidence' => isset( $_POST['pr_fc_min_product'] ) ? max( 0.0, min( 1.0, (float) $_POST['pr_fc_min_product'] ) ) : PR_FACTCHECK_DEFAULTS['min_product_confidence'],
				'min_article_confidence' => isset( $_POST['pr_fc_min_article'] ) ? max( 0.0, min( 1.0, (float) $_POST['pr_fc_min_article'] ) ) : PR_FACTCHECK_DEFAULTS['min_article_confidence'],
				'min_products'           => isset( $_POST['pr_fc_min_products'] ) ? max( 1, (int) $_POST['pr_fc_min_products'] ) : PR_FACTCHECK_DEFAULTS['min_products'],
			);
			update_option( 'pr_factcheck_thresholds', $thresholds, false );
			$publishing = array(
				'mode'            => ( ( $_POST['pr_publish_mode'] ?? 'publish' ) === 'draft' ) ? 'draft' : 'publish',
				'min_words_intro' => max( 10, (int) ( $_POST['pr_min_words_intro'] ?? PR_PUBLISH_DEFAULTS['min_words_intro'] ) ),
				'max_words_intro' => max( 50, (int) ( $_POST['pr_max_words_intro'] ?? PR_PUBLISH_DEFAULTS['max_words_intro'] ) ),
				'min_products'    => max( 1,  (int) ( $_POST['pr_min_products']    ?? PR_PUBLISH_DEFAULTS['min_products'] ) ),
				'min_faqs'        => max( 0,  (int) ( $_POST['pr_min_faqs']        ?? PR_PUBLISH_DEFAULTS['min_faqs'] ) ),
			);
			update_option( 'pr_publishing', $publishing, false );
			do_action( 'pr_autopilot_settings_save' );
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Saved.', 'product-reviews' ) . '</p></div>';
		}
		if ( isset( $_POST['pr_enqueue_keyword'] ) ) {
			$kw = trim( sanitize_text_field( $_POST['pr_enqueue_keyword'] ) );
			if ( $kw ) {
				$id = pr_enqueue_keyword( $kw );
				echo '<div class="notice notice-success"><p>' . sprintf( esc_html__( 'Queued #%d: %s', 'product-reviews' ), $id, esc_html( $kw ) ) . '</p></div>';
			}
		}
		if ( isset( $_POST['pr_run_now'] ) ) {
			pr_autopilot_run_tick();
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Tick executed.', 'product-reviews' ) . '</p></div>';
		}
		if ( isset( $_POST['pr_retry_id'] ) ) {
			PR_Queue::retry( (int) $_POST['pr_retry_id'] );
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Job re-queued.', 'product-reviews' ) . '</p></div>';
		}
	}

	$enabled  = (string) get_option( 'pr_autopilot_enabled', '0' ) === '1';
	$interval = pr_autopilot_schedule();
	$next     = wp_next_scheduled( 'pr_autopilot_tick' );
	$counts   = PR_Queue::count_by_state();
	$queue_tbl = pr_table( 'run_queue' );
	$log_tbl   = pr_table( 'run_log' );
	$dead    = $wpdb->get_results( "SELECT * FROM {$queue_tbl} WHERE state = '" . PR_STATE_NEEDS_REVIEW . "' ORDER BY updated_at DESC LIMIT 25", ARRAY_A );
	$active  = $wpdb->get_results( "SELECT * FROM {$queue_tbl} WHERE state != '" . PR_STATE_NEEDS_REVIEW . "' AND state != '" . PR_STATE_PUBLISHED . "' ORDER BY updated_at DESC LIMIT 25", ARRAY_A );
	$recent  = $wpdb->get_results( "SELECT * FROM {$log_tbl} ORDER BY id DESC LIMIT 60", ARRAY_A );

	$intervals = array(
		'pr_1min'  => '1 minute',
		'pr_5min'  => '5 minutes',
		'pr_15min' => '15 minutes',
		'pr_30min' => '30 minutes',
		'pr_1hour' => '1 hour',
		'pr_6hour' => '6 hours',
		'daily'    => '24 hours',
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Autopilot', 'product-reviews' ); ?></h1>

		<form method="post" style="background:#fff;padding:12px 16px;border:1px solid #ccd0d4;border-radius:4px">
			<?php wp_nonce_field( 'pr_autopilot', 'pr_autopilot_nonce' ); ?>
			<p>
				<label><input type="checkbox" name="pr_autopilot_enabled" value="1" <?php checked( $enabled ); ?>> <?php esc_html_e( 'Enable autopilot cron', 'product-reviews' ); ?></label>
			</p>
			<p>
				<label for="pr_autopilot_interval"><?php esc_html_e( 'Tick interval:', 'product-reviews' ); ?></label>
				<select name="pr_autopilot_interval" id="pr_autopilot_interval">
					<?php foreach ( $intervals as $k => $label ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $interval, $k ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Save', 'product-reviews' ), 'primary', 'pr_save_settings', false ); ?>
				<?php submit_button( __( 'Run tick now', 'product-reviews' ), 'secondary', 'pr_run_now', false ); ?>
			</p>
			<p class="description">
				<?php if ( $next ) {
					printf( esc_html__( 'Next scheduled tick: %s UTC', 'product-reviews' ), esc_html( gmdate( 'Y-m-d H:i:s', $next ) ) );
				} else {
					esc_html_e( 'No tick scheduled.', 'product-reviews' );
				} ?>
			</p>

			<h3 style="margin-top:16px"><?php esc_html_e( 'Fact-check quality gate', 'product-reviews' ); ?></h3>
			<p>
				<label><?php esc_html_e( 'Min product confidence', 'product-reviews' ); ?>
					<input type="number" step="0.01" min="0" max="1" name="pr_fc_min_product" value="<?php echo esc_attr( pr_factcheck_threshold( 'min_product_confidence' ) ); ?>" style="width:90px">
				</label>
				&nbsp;
				<label><?php esc_html_e( 'Min article confidence', 'product-reviews' ); ?>
					<input type="number" step="0.01" min="0" max="1" name="pr_fc_min_article" value="<?php echo esc_attr( pr_factcheck_threshold( 'min_article_confidence' ) ); ?>" style="width:90px">
				</label>
				&nbsp;
				<label><?php esc_html_e( 'Min products', 'product-reviews' ); ?>
					<input type="number" min="1" max="20" name="pr_fc_min_products" value="<?php echo esc_attr( (int) pr_factcheck_threshold( 'min_products' ) ); ?>" style="width:70px">
				</label>
			</p>
			<p class="description"><?php esc_html_e( 'Articles failing the gate are routed to "Needs review" instead of being published.', 'product-reviews' ); ?></p>

			<h3 style="margin-top:16px"><?php esc_html_e( 'Publishing', 'product-reviews' ); ?></h3>
			<p>
				<label><?php esc_html_e( 'On quality-gate pass:', 'product-reviews' ); ?>
					<select name="pr_publish_mode">
						<option value="publish" <?php selected( pr_publish_setting( 'mode' ), 'publish' ); ?>><?php esc_html_e( 'Auto-publish', 'product-reviews' ); ?></option>
						<option value="draft"   <?php selected( pr_publish_setting( 'mode' ), 'draft' ); ?>><?php esc_html_e( 'Save as draft', 'product-reviews' ); ?></option>
					</select>
				</label>
				&nbsp;
				<label><?php esc_html_e( 'Intro words (min)', 'product-reviews' ); ?>
					<input type="number" min="10" name="pr_min_words_intro" value="<?php echo esc_attr( (int) pr_publish_setting( 'min_words_intro' ) ); ?>" style="width:80px">
				</label>
				<label><?php esc_html_e( '(max)', 'product-reviews' ); ?>
					<input type="number" min="50" name="pr_max_words_intro" value="<?php echo esc_attr( (int) pr_publish_setting( 'max_words_intro' ) ); ?>" style="width:80px">
				</label>
				&nbsp;
				<label><?php esc_html_e( 'Min products', 'product-reviews' ); ?>
					<input type="number" min="1" max="20" name="pr_min_products" value="<?php echo esc_attr( (int) pr_publish_setting( 'min_products' ) ); ?>" style="width:70px">
				</label>
				&nbsp;
				<label><?php esc_html_e( 'Min FAQs', 'product-reviews' ); ?>
					<input type="number" min="0" max="20" name="pr_min_faqs" value="<?php echo esc_attr( (int) pr_publish_setting( 'min_faqs' ) ); ?>" style="width:70px">
				</label>
			</p>

			<?php do_action( 'pr_autopilot_settings_extra' ); ?>
		</form>

		<h2><?php esc_html_e( 'Queue', 'product-reviews' ); ?></h2>
		<p>
			<?php foreach ( $counts as $st => $n ) : ?>
				<span class="button button-small" style="margin-right:4px"><?php echo esc_html( $st ); ?>: <strong><?php echo (int) $n; ?></strong></span>
			<?php endforeach; ?>
		</p>

		<form method="post" style="margin:10px 0">
			<?php wp_nonce_field( 'pr_autopilot', 'pr_autopilot_nonce' ); ?>
			<input type="text" name="pr_enqueue_keyword" placeholder="<?php esc_attr_e( 'best wireless earbuds', 'product-reviews' ); ?>" class="regular-text">
			<?php submit_button( __( 'Enqueue keyword', 'product-reviews' ), 'secondary', '', false ); ?>
		</form>

		<h3><?php esc_html_e( 'Active jobs', 'product-reviews' ); ?></h3>
		<table class="widefat striped">
			<thead><tr>
				<th>ID</th><th><?php esc_html_e( 'Keyword', 'product-reviews' ); ?></th><th><?php esc_html_e( 'State', 'product-reviews' ); ?></th>
				<th><?php esc_html_e( 'Attempts', 'product-reviews' ); ?></th><th><?php esc_html_e( 'Next run', 'product-reviews' ); ?></th><th><?php esc_html_e( 'Updated', 'product-reviews' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( (array) $active as $r ) : ?>
				<tr>
					<td>#<?php echo (int) $r['id']; ?></td>
					<td><?php echo esc_html( $r['keyword'] ); ?></td>
					<td><code><?php echo esc_html( $r['state'] ); ?></code></td>
					<td><?php echo (int) $r['attempts']; ?></td>
					<td><?php echo esc_html( $r['next_run_at'] ); ?></td>
					<td><?php echo esc_html( $r['updated_at'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if ( empty( $active ) ) : ?>
				<tr><td colspan="6"><em><?php esc_html_e( 'No active jobs.', 'product-reviews' ); ?></em></td></tr>
			<?php endif; ?>
			</tbody>
		</table>

		<h3 style="margin-top:24px"><?php esc_html_e( 'Needs review (dead-letter)', 'product-reviews' ); ?></h3>
		<table class="widefat striped">
			<thead><tr>
				<th>ID</th><th><?php esc_html_e( 'Keyword', 'product-reviews' ); ?></th><th><?php esc_html_e( 'Attempts', 'product-reviews' ); ?></th>
				<th><?php esc_html_e( 'Error', 'product-reviews' ); ?></th><th></th>
			</tr></thead>
			<tbody>
			<?php foreach ( (array) $dead as $r ) : ?>
				<tr>
					<td>#<?php echo (int) $r['id']; ?></td>
					<td><?php echo esc_html( $r['keyword'] ); ?></td>
					<td><?php echo (int) $r['attempts']; ?></td>
					<td><code><?php echo esc_html( mb_substr( (string) $r['error'], 0, 120 ) ); ?></code></td>
					<td>
						<form method="post" style="display:inline">
							<?php wp_nonce_field( 'pr_autopilot', 'pr_autopilot_nonce' ); ?>
							<input type="hidden" name="pr_retry_id" value="<?php echo (int) $r['id']; ?>">
							<button class="button button-small"><?php esc_html_e( 'Retry', 'product-reviews' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			<?php if ( empty( $dead ) ) : ?>
				<tr><td colspan="5"><em><?php esc_html_e( 'No dead-letter jobs. 🎉', 'product-reviews' ); ?></em></td></tr>
			<?php endif; ?>
			</tbody>
		</table>

		<h3 style="margin-top:24px"><?php esc_html_e( 'Recent run log', 'product-reviews' ); ?></h3>
		<table class="widefat striped">
			<thead><tr><th>Time</th><th>Run</th><th>From → To</th><th>Driver</th><th>Latency</th><th>Message</th></tr></thead>
			<tbody>
			<?php foreach ( (array) $recent as $r ) : ?>
				<tr>
					<td><?php echo esc_html( $r['ts'] ); ?></td>
					<td>#<?php echo (int) $r['run_id']; ?></td>
					<td><code><?php echo esc_html( (string) $r['state_from'] ); ?></code> → <code><?php echo esc_html( $r['state_to'] ); ?></code></td>
					<td><?php echo esc_html( (string) $r['driver'] ); ?></td>
					<td><?php echo (int) $r['latency_ms']; ?>ms</td>
					<td><?php echo esc_html( (string) $r['message'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}
