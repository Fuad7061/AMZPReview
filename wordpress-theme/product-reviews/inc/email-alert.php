<?php
/**
 * Price-drop email alerts (transport-agnostic).
 *
 * Pipeline:
 *   subscribe (AJAX)  → insert pending row  → pr_send_email(..., 'confirm')
 *   /?pr_alert=confirm&t=… → flip status to 'confirmed'
 *   /?pr_alert=unsub&t=…   → flip to 'unsub' + pr_send_email(..., 'unsub')
 *   hourly WP-Cron    → batch 50 confirmed rows, re-check price via the
 *                       source manager, fire pr_send_email(..., 'price_drop')
 *                       when drop% ≥ min_drop_percent and cooldown elapsed.
 *
 * @package ProductReviews
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once PR_THEME_DIR . '/inc/email-transport.php';
require_once PR_THEME_DIR . '/inc/transport-smtp.php';
require_once PR_THEME_DIR . '/inc/transport-mailchimp.php';

/* ---------------------------------------------------------------------------
 * DB.
 * ------------------------------------------------------------------------- */
define( 'PR_ALERTS_DB_VERSION', '1.0.0' );
function pr_alerts_table() { global $wpdb; return $wpdb->prefix . 'pr_alert_subs'; }

function pr_alerts_install() {
	if ( get_option( 'pr_alerts_db_version' ) === PR_ALERTS_DB_VERSION ) { return; }
	global $wpdb;
	$table   = pr_alerts_table();
	$charset = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		email VARCHAR(190) NOT NULL,
		post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		product_slug VARCHAR(190) NOT NULL DEFAULT '',
		product_name VARCHAR(255) NOT NULL DEFAULT '',
		current_price DECIMAL(12,2) NULL,
		currency VARCHAR(8) NOT NULL DEFAULT 'USD',
		status VARCHAR(16) NOT NULL DEFAULT 'pending',
		confirm_token CHAR(32) NOT NULL DEFAULT '',
		unsub_token CHAR(32) NOT NULL DEFAULT '',
		last_notified_at DATETIME NULL,
		ip VARCHAR(45) NOT NULL DEFAULT '',
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY uniq_email_post (email, post_id),
		KEY idx_status (status),
		KEY idx_post (post_id)
	) {$charset};" );
	update_option( 'pr_alerts_db_version', PR_ALERTS_DB_VERSION, false );
}
add_action( 'after_switch_theme', 'pr_alerts_install' );
add_action( 'admin_init', 'pr_alerts_install' );

/* ---------------------------------------------------------------------------
 * Cron schedule.
 * ------------------------------------------------------------------------- */
add_action( 'after_switch_theme', function () {
	if ( ! wp_next_scheduled( 'pr_alert_check' ) ) {
		wp_schedule_event( time() + 600, 'hourly', 'pr_alert_check' );
	}
} );
add_action( 'switch_theme', function () { wp_clear_scheduled_hook( 'pr_alert_check' ); } );
add_action( 'pr_alert_check', 'pr_alerts_run_cron' );

/* ---------------------------------------------------------------------------
 * Form rendering (used by template-parts and shortcode).
 * ------------------------------------------------------------------------- */
function pr_email_alert_render_html( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	if ( ! $post_id ) { return ''; }
	$title = apply_filters( 'pr_email_alert_title', __( 'Get price-drop alerts', 'product-reviews' ), $post_id );
	$nonce = wp_create_nonce( 'pr_email_alert' );
	$html  = '<form class="pr-alert" data-post="' . esc_attr( $post_id ) . '" data-nonce="' . esc_attr( $nonce ) . '" method="post" action="">';
	$html .= '<label class="pr-alert-title" for="pr-alert-' . esc_attr( $post_id ) . '">' . esc_html( $title ) . '</label>';
	$html .= '<div class="pr-alert-row">';
	$html .= '<input id="pr-alert-' . esc_attr( $post_id ) . '" type="email" name="email" required placeholder="' . esc_attr__( 'you@example.com', 'product-reviews' ) . '" />';
	// Honeypot.
	$html .= '<input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true" />';
	$html .= '<button type="submit">' . esc_html__( 'Notify me', 'product-reviews' ) . '</button>';
	$html .= '</div><p class="pr-alert-msg" role="status" aria-live="polite"></p>';
	$html .= '<p class="pr-alert-note">' . esc_html__( 'Double opt-in: we send a confirmation email first. Unsubscribe any time.', 'product-reviews' ) . '</p>';
	$html .= '</form>';
	return $html;
}
function pr_email_alert_shortcode( $atts ) {
	$a = shortcode_atts( array( 'id' => 0 ), $atts, 'pr_email_alert' );
	return pr_email_alert_render_html( (int) $a['id'] );
}
add_shortcode( 'pr_email_alert', 'pr_email_alert_shortcode' );

function pr_email_alert_styles() {
	echo '<style>.pr-alert{border:1px solid var(--pr-border,#e5e7eb);padding:1rem;border-radius:.5rem;margin:1.5rem 0;background:#fff;position:relative}.pr-alert-title{display:block;font-weight:600;margin-bottom:.5rem}.pr-alert-row{display:flex;gap:.5rem}.pr-alert input[type=email]{flex:1;padding:.5rem .75rem;border:1px solid var(--pr-border,#e5e7eb);border-radius:.375rem}.pr-alert button{padding:.5rem 1rem;background:var(--pr-accent,#2563eb);color:#fff;border:0;border-radius:.375rem;cursor:pointer}.pr-alert-msg{margin:.5rem 0 0;font-size:.875rem;min-height:1.1em}.pr-alert-note{margin:.35rem 0 0;font-size:.75rem;color:#6b7280}</style>';
}
add_action( 'wp_head', 'pr_email_alert_styles', 100 );

function pr_email_alert_script() {
	if ( ! is_singular() ) { return; }
	$url = admin_url( 'admin-ajax.php' );
	echo '<script>(function(){document.addEventListener("submit",function(e){var f=e.target.closest(".pr-alert");if(!f)return;e.preventDefault();var m=f.querySelector(".pr-alert-msg");m.textContent="…";var fd=new FormData(f);fd.append("action","pr_email_alert");fd.append("post_id",f.dataset.post);fd.append("nonce",f.dataset.nonce);fetch(' . wp_json_encode( $url ) . ',{method:"POST",body:fd,credentials:"same-origin"}).then(function(r){return r.json()}).then(function(j){m.textContent=j&&j.message?j.message:(j&&j.success?"Subscribed.":"Error.")}).catch(function(){m.textContent="Network error."})});})();</script>';
}
add_action( 'wp_footer', 'pr_email_alert_script', 99 );

/* ---------------------------------------------------------------------------
 * Subscribe handler.
 * ------------------------------------------------------------------------- */
function pr_email_alert_handle() {
	check_ajax_referer( 'pr_email_alert', 'nonce' );

	// Honeypot.
	if ( ! empty( $_POST['website'] ) ) {
		wp_send_json( array( 'success' => true, 'message' => __( 'Thanks!', 'product-reviews' ) ) );
	}
	// Rate limit per IP — 10 / 10 min.
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '0';
	$rk = 'pr_alert_rl_' . md5( $ip );
	$hits = (int) get_transient( $rk );
	if ( $hits > 10 ) { wp_send_json( array( 'success' => false, 'message' => __( 'Too many attempts. Try again later.', 'product-reviews' ) ) ); }
	set_transient( $rk, $hits + 1, 600 );

	$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
	$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	if ( ! $post_id || ! is_email( $email ) ) {
		wp_send_json( array( 'success' => false, 'message' => __( 'Please enter a valid email.', 'product-reviews' ) ) );
	}

	$post = get_post( $post_id );
	if ( ! $post ) { wp_send_json( array( 'success' => false, 'message' => __( 'Unknown product.', 'product-reviews' ) ) ); }

	global $wpdb;
	$table = pr_alerts_table();
	$slug  = $post->post_name;
	$name  = $post->post_title;

	$price    = (float) get_post_meta( $post_id, '_pr_current_price', true );
	$currency = get_post_meta( $post_id, '_pr_currency', true );
	if ( ! $currency ) { $currency = 'USD'; }

	$confirm = wp_generate_password( 32, false, false );
	$unsub   = wp_generate_password( 32, false, false );

	// Upsert.
	$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, status FROM {$table} WHERE email=%s AND post_id=%d", $email, $post_id ) );
	if ( $existing ) {
		if ( 'confirmed' === $existing->status ) {
			wp_send_json( array( 'success' => true, 'message' => __( "You're already subscribed.", 'product-reviews' ) ) );
		}
		$wpdb->update( $table, array(
			'confirm_token' => $confirm,
			'unsub_token'   => $unsub,
			'status'        => 'pending',
			'current_price' => $price ? $price : null,
			'currency'      => $currency,
			'ip'            => $ip,
		), array( 'id' => $existing->id ) );
	} else {
		$wpdb->insert( $table, array(
			'email'         => $email,
			'post_id'       => $post_id,
			'product_slug'  => $slug,
			'product_name'  => $name,
			'current_price' => $price ? $price : null,
			'currency'      => $currency,
			'status'        => 'pending',
			'confirm_token' => $confirm,
			'unsub_token'   => $unsub,
			'ip'            => $ip,
		) );
	}

	// Fire confirmation.
	$confirm_url = add_query_arg( array( 'pr_alert' => 'confirm', 't' => $confirm ), home_url( '/' ) );
	$unsub_url   = add_query_arg( array( 'pr_alert' => 'unsub',   't' => $unsub   ), home_url( '/' ) );
	$buy_url     = get_post_meta( $post_id, '_pr_buy_url', true );
	if ( ! $buy_url ) { $buy_url = get_permalink( $post_id ); }

	$subject = sprintf( __( 'Confirm price alerts for %s', 'product-reviews' ), $name );
	$html    = pr_alerts_email_html( 'confirm', array(
		'name'        => $name,
		'confirm_url' => $confirm_url,
		'unsub_url'   => $unsub_url,
	) );
	pr_send_email( $email, $subject, $html, '', 'confirm', array(
		'PRODUCT' => $name, 'PSLUG' => $slug, 'PRICE' => $price, 'CURRENCY' => $currency, 'BUY_URL' => $buy_url,
	) );

	wp_send_json( array( 'success' => true, 'message' => __( 'Check your inbox to confirm.', 'product-reviews' ) ) );
}
add_action( 'wp_ajax_pr_email_alert', 'pr_email_alert_handle' );
add_action( 'wp_ajax_nopriv_pr_email_alert', 'pr_email_alert_handle' );

/* ---------------------------------------------------------------------------
 * Confirm / unsubscribe endpoints.
 * ------------------------------------------------------------------------- */
add_action( 'template_redirect', function () {
	if ( empty( $_GET['pr_alert'] ) ) { return; }
	$action = sanitize_key( $_GET['pr_alert'] );
	$token  = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : '';
	if ( '' === $token || strlen( $token ) > 64 ) { return; }
	global $wpdb;
	$table = pr_alerts_table();

	if ( 'confirm' === $action ) {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE confirm_token=%s", $token ) );
		if ( $row ) {
			$wpdb->update( $table, array( 'status' => 'confirmed', 'confirm_token' => '' ), array( 'id' => $row->id ) );
		}
		pr_alerts_render_static_page(
			__( 'Subscription confirmed', 'product-reviews' ),
			$row
				? sprintf( __( "You'll get an email when the price on %s drops.", 'product-reviews' ), esc_html( $row->product_name ) )
				: __( 'This confirmation link has already been used or is invalid.', 'product-reviews' )
		);
	}
	if ( 'unsub' === $action ) {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE unsub_token=%s", $token ) );
		if ( $row ) {
			$wpdb->update( $table, array( 'status' => 'unsub' ), array( 'id' => $row->id ) );
			pr_send_email( $row->email, __( 'Unsubscribed', 'product-reviews' ),
				pr_alerts_email_html( 'unsub', array( 'name' => $row->product_name ) ),
				'', 'unsub', array( 'PRODUCT' => $row->product_name ) );
		}
		pr_alerts_render_static_page(
			__( 'Unsubscribed', 'product-reviews' ),
			__( "You won't receive any more price alerts for this product.", 'product-reviews' )
		);
	}
} );

function pr_alerts_render_static_page( $title, $body ) {
	status_header( 200 );
	nocache_headers();
	get_header();
	echo '<main class="pr-container" style="max-width:640px;margin:3rem auto;padding:1rem"><h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $body ) . '</p><p><a href="' . esc_url( home_url( '/' ) ) . '">&larr; ' . esc_html__( 'Back to home', 'product-reviews' ) . '</a></p></main>';
	get_footer();
	exit;
}

/* ---------------------------------------------------------------------------
 * Cron worker — 50 rows / run.
 * ------------------------------------------------------------------------- */
function pr_alerts_run_cron() {
	$settings = pr_email_settings();
	if ( empty( $settings['enabled'] ) ) { return; }
	$min      = max( 1, (int) $settings['min_drop_percent'] );
	$cooldown = max( 1, (int) $settings['cooldown_hours'] ) * HOUR_IN_SECONDS;

	global $wpdb;
	$table = pr_alerts_table();
	$rows  = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$table} WHERE status='confirmed'
		 AND (last_notified_at IS NULL OR last_notified_at < %s)
		 ORDER BY id ASC LIMIT 50",
		gmdate( 'Y-m-d H:i:s', time() - $cooldown )
	) );
	if ( ! $rows ) { return; }

	foreach ( $rows as $row ) {
		$post = get_post( (int) $row->post_id );
		if ( ! $post ) { continue; }
		$new_price = (float) get_post_meta( $row->post_id, '_pr_current_price', true );
		$old_price = (float) $row->current_price;
		if ( $new_price <= 0 || $old_price <= 0 ) {
			// Initialise baseline.
			$wpdb->update( $table, array( 'current_price' => $new_price ? $new_price : null ), array( 'id' => $row->id ) );
			continue;
		}
		$drop_pct = round( ( ( $old_price - $new_price ) / $old_price ) * 100, 1 );
		if ( $drop_pct < $min ) { continue; }

		$buy_url   = get_post_meta( $row->post_id, '_pr_buy_url', true );
		if ( ! $buy_url ) { $buy_url = get_permalink( $row->post_id ); }
		$unsub_url = add_query_arg( array( 'pr_alert' => 'unsub', 't' => $row->unsub_token ), home_url( '/' ) );

		$subject = sprintf( __( 'Price drop: %s now %s', 'product-reviews' ),
			$row->product_name, pr_alerts_format_price( $new_price, $row->currency ) );
		$html    = pr_alerts_email_html( 'price_drop', array(
			'name'      => $row->product_name,
			'old_price' => pr_alerts_format_price( $old_price, $row->currency ),
			'new_price' => pr_alerts_format_price( $new_price, $row->currency ),
			'drop_pct'  => $drop_pct,
			'buy_url'   => $buy_url,
			'unsub_url' => $unsub_url,
		) );
		$res = pr_send_email( $row->email, $subject, $html, '', 'price_drop', array(
			'PRODUCT' => $row->product_name, 'PSLUG' => $row->product_slug,
			'OLD_PRICE' => pr_alerts_format_price( $old_price, $row->currency ),
			'NEW_PRICE' => pr_alerts_format_price( $new_price, $row->currency ),
			'DROP_PCT' => $drop_pct, 'BUY_URL' => $buy_url, 'CURRENCY' => $row->currency,
		) );
		if ( ! empty( $res['success'] ) ) {
			$wpdb->update( $table, array(
				'last_notified_at' => current_time( 'mysql', true ),
				'current_price'    => $new_price,
			), array( 'id' => $row->id ) );
		}
	}
}

function pr_alerts_format_price( $v, $currency = 'USD' ) {
	$sym = array( 'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'CAD' => 'C$', 'AUD' => 'A$', 'INR' => '₹' );
	$s   = isset( $sym[ $currency ] ) ? $sym[ $currency ] : ( $currency . ' ' );
	return $s . number_format( (float) $v, 2 );
}

/* ---------------------------------------------------------------------------
 * Minimal branded HTML templates.
 * ------------------------------------------------------------------------- */
function pr_alerts_email_html( $kind, $vars ) {
	$site   = esc_html( get_bloginfo( 'name' ) );
	$brand  = '#2563eb';
	$shell  = function( $body ) use ( $site, $brand ) {
		return '<!doctype html><html><body style="margin:0;background:#f6f7f9;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Arial,sans-serif;color:#111">'
			. '<div style="max-width:560px;margin:24px auto;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb">'
			. '<div style="padding:14px 20px;background:' . $brand . ';color:#fff;font-weight:700">' . $site . '</div>'
			. '<div style="padding:24px 20px;font-size:15px;line-height:1.55">' . $body . '</div>'
			. '<div style="padding:14px 20px;font-size:12px;color:#6b7280;border-top:1px solid #f0f0f0">' . esc_html__( 'You are receiving this because you opted in to price alerts.', 'product-reviews' ) . '</div>'
			. '</div></body></html>';
	};

	if ( 'confirm' === $kind ) {
		return $shell(
			'<h2 style="margin:0 0 12px;font-size:20px">' . esc_html__( 'One last step', 'product-reviews' ) . '</h2>'
			. '<p>' . sprintf( esc_html__( 'Confirm your subscription to price-drop alerts for %s.', 'product-reviews' ), '<strong>' . esc_html( $vars['name'] ) . '</strong>' ) . '</p>'
			. '<p style="margin:22px 0"><a href="' . esc_url( $vars['confirm_url'] ) . '" style="background:' . $brand . ';color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:600">' . esc_html__( 'Confirm subscription', 'product-reviews' ) . '</a></p>'
			. '<p style="font-size:12px;color:#6b7280">' . esc_html__( 'If you did not request this, ignore this email.', 'product-reviews' ) . ' &middot; <a href="' . esc_url( $vars['unsub_url'] ) . '">' . esc_html__( 'Unsubscribe', 'product-reviews' ) . '</a></p>'
		);
	}
	if ( 'price_drop' === $kind ) {
		return $shell(
			'<h2 style="margin:0 0 12px;font-size:22px;color:#059669">' . sprintf( esc_html__( 'Price dropped %s%%', 'product-reviews' ), esc_html( $vars['drop_pct'] ) ) . '</h2>'
			. '<p style="margin:0 0 10px"><strong>' . esc_html( $vars['name'] ) . '</strong></p>'
			. '<p style="font-size:16px"><span style="text-decoration:line-through;color:#9ca3af">' . esc_html( $vars['old_price'] ) . '</span> &nbsp;→&nbsp; <strong style="font-size:22px;color:#059669">' . esc_html( $vars['new_price'] ) . '</strong></p>'
			. '<p style="margin:22px 0"><a href="' . esc_url( $vars['buy_url'] ) . '" style="background:#f59e0b;color:#111;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:700">' . esc_html__( 'Check current price', 'product-reviews' ) . '</a></p>'
			. '<p style="font-size:12px;color:#6b7280">' . esc_html__( 'Prices change frequently. Verify on the merchant site before buying.', 'product-reviews' ) . ' &middot; <a href="' . esc_url( $vars['unsub_url'] ) . '">' . esc_html__( 'Unsubscribe', 'product-reviews' ) . '</a></p>'
		);
	}
	// unsub
	return $shell(
		'<h2 style="margin:0 0 12px;font-size:20px">' . esc_html__( 'You have been unsubscribed', 'product-reviews' ) . '</h2>'
		. '<p>' . sprintf( esc_html__( 'You will no longer receive price-drop alerts for %s.', 'product-reviews' ), '<strong>' . esc_html( $vars['name'] ) . '</strong>' ) . '</p>'
	);
}
