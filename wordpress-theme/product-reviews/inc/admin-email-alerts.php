<?php
/**
 * Admin UI: Reviews → Email Alerts.
 *
 * Tabs: Transports | Routing | Settings | Subscribers | Logs
 *
 * @package ProductReviews
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=review',
		__( 'Email Alerts', 'product-reviews' ),
		__( 'Email Alerts', 'product-reviews' ),
		'manage_options',
		'pr-email-alerts',
		'pr_alerts_admin_render'
	);
} );

function pr_alerts_admin_url( $args = array() ) {
	return add_query_arg( wp_parse_args( $args, array(
		'post_type' => 'review',
		'page'      => 'pr-email-alerts',
	) ), admin_url( 'edit.php' ) );
}

function pr_alerts_admin_render() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'transports';

	// Handle POSTs.
	if ( ! empty( $_POST['pr_alerts_action'] ) && check_admin_referer( 'pr_alerts_admin' ) ) {
		pr_alerts_admin_handle_post( sanitize_key( $_POST['pr_alerts_action'] ) );
	}

	$tabs = array(
		'transports'  => __( 'Transports', 'product-reviews' ),
		'routing'     => __( 'Routing', 'product-reviews' ),
		'settings'    => __( 'Settings', 'product-reviews' ),
		'subscribers' => __( 'Subscribers', 'product-reviews' ),
		'logs'        => __( 'Logs', 'product-reviews' ),
	);
	echo '<div class="wrap"><h1>' . esc_html__( 'Email Alerts', 'product-reviews' ) . '</h1>';
	echo '<nav class="nav-tab-wrapper">';
	foreach ( $tabs as $k => $label ) {
		$cls = $k === $tab ? 'nav-tab nav-tab-active' : 'nav-tab';
		printf( '<a class="%s" href="%s">%s</a>', esc_attr( $cls ), esc_url( pr_alerts_admin_url( array( 'tab' => $k ) ) ), esc_html( $label ) );
	}
	echo '</nav><div style="margin-top:1.25rem">';
	$fn = 'pr_alerts_admin_tab_' . $tab;
	if ( function_exists( $fn ) ) { $fn(); }
	echo '</div></div>';
}

/* ---------------------------------------------------------------------------
 * POST handlers.
 * ------------------------------------------------------------------------- */
function pr_alerts_admin_handle_post( $action ) {
	$s = pr_email_settings();

	if ( 'save_settings' === $action ) {
		$s['enabled']          = ! empty( $_POST['enabled'] ) ? 1 : 0;
		$s['min_drop_percent'] = max( 1, min( 95, (int) ( $_POST['min_drop_percent'] ?? 5 ) ) );
		$s['cooldown_hours']   = max( 1, min( 720, (int) ( $_POST['cooldown_hours'] ?? 72 ) ) );
		$s['from_name']        = sanitize_text_field( wp_unslash( $_POST['from_name'] ?? '' ) );
		$s['from_email']       = sanitize_email( wp_unslash( $_POST['from_email'] ?? '' ) );
		pr_email_settings_save( $s );
		add_settings_error( 'pr_alerts', 'saved', __( 'Settings saved.', 'product-reviews' ), 'updated' );
	}

	if ( 'save_routing' === $action ) {
		foreach ( array( 'confirm', 'price_drop', 'unsub', 'test' ) as $p ) {
			$s['routing'][ $p ] = array(
				'primary'  => sanitize_key( $_POST['routing'][ $p ]['primary']  ?? 'wpmail' ),
				'fallback' => sanitize_key( $_POST['routing'][ $p ]['fallback'] ?? '' ),
			);
		}
		pr_email_settings_save( $s );
		add_settings_error( 'pr_alerts', 'saved', __( 'Routing saved.', 'product-reviews' ), 'updated' );
	}

	if ( 'add_transport' === $action || 'edit_transport' === $action ) {
		$id   = sanitize_key( $_POST['id'] ?? '' );
		$type = sanitize_key( $_POST['type'] ?? '' );
		if ( '' === $id || ! in_array( $type, array( 'smtp', 'mailchimp', 'wpmail' ), true ) ) {
			add_settings_error( 'pr_alerts', 'err', __( 'Invalid transport.', 'product-reviews' ) );
		} else {
			$existing = $s['transports'][ $id ] ?? array();
			$cfg = array( 'type' => $type, 'label' => sanitize_text_field( wp_unslash( $_POST['label'] ?? $id ) ) );
			if ( 'smtp' === $type ) {
				$cfg['preset']     = sanitize_key( $_POST['preset'] ?? 'custom' );
				$cfg['host']       = sanitize_text_field( wp_unslash( $_POST['host'] ?? '' ) );
				$cfg['port']       = max( 1, (int) ( $_POST['port'] ?? 587 ) );
				$cfg['encryption'] = in_array( $_POST['encryption'] ?? 'tls', array( 'tls', 'ssl', 'none' ), true ) ? $_POST['encryption'] : 'tls';
				$cfg['username']   = sanitize_text_field( wp_unslash( $_POST['username'] ?? '' ) );
				$pw = (string) ( $_POST['password'] ?? '' );
				$cfg['password']   = ( '' === $pw && ! empty( $existing['password'] ) ) ? $existing['password'] : pr_encrypt( $pw );
				$cfg['from_email'] = sanitize_email( wp_unslash( $_POST['from_email'] ?? '' ) );
				$cfg['from_name']  = sanitize_text_field( wp_unslash( $_POST['from_name'] ?? '' ) );
			}
			if ( 'mailchimp' === $type ) {
				$key = (string) ( $_POST['api_key'] ?? '' );
				$cfg['api_key']     = ( '' === $key && ! empty( $existing['api_key'] ) ) ? $existing['api_key'] : pr_encrypt( $key );
				$cfg['audience_id'] = sanitize_text_field( wp_unslash( $_POST['audience_id'] ?? '' ) );
				$cfg['tag_drop']    = sanitize_text_field( wp_unslash( $_POST['tag_drop'] ?? 'price-drop-fired' ) );
			}
			$s['transports'][ $id ] = $cfg;
			pr_email_settings_save( $s );
			add_settings_error( 'pr_alerts', 'saved', __( 'Transport saved.', 'product-reviews' ), 'updated' );
		}
	}

	if ( 'delete_transport' === $action ) {
		$id = sanitize_key( $_POST['id'] ?? '' );
		if ( $id && 'wpmail' !== $id ) {
			unset( $s['transports'][ $id ] );
			pr_email_settings_save( $s );
			add_settings_error( 'pr_alerts', 'saved', __( 'Transport deleted.', 'product-reviews' ), 'updated' );
		}
	}

	if ( 'test_transport' === $action ) {
		$id   = sanitize_key( $_POST['id'] ?? '' );
		$to   = sanitize_email( wp_unslash( $_POST['to'] ?? get_option( 'admin_email' ) ) );
		$cfg  = $s['transports'][ $id ] ?? null;
		$reg  = pr_email_transport_registry();
		if ( $cfg && isset( $reg[ $cfg['type'] ] ) && is_email( $to ) ) {
			$res = call_user_func( $reg[ $cfg['type'] ]['send'], $cfg, array(
				'to' => $to,
				'subject' => '[Test] ' . get_bloginfo( 'name' ) . ' email alerts',
				'html' => '<p>This is a test from your Email Alerts settings via transport <strong>' . esc_html( $cfg['label'] ?? $id ) . '</strong>.</p>',
				'text' => 'Test email',
				'from_name' => $s['from_name'],
				'from_email' => $s['from_email'],
				'extra' => array(),
				'purpose' => 'test',
			) );
			pr_email_log( $id, $cfg['type'], 'test', $to, $res );
			if ( ! empty( $res['success'] ) ) {
				add_settings_error( 'pr_alerts', 'ok', sprintf( __( 'Test email sent to %s.', 'product-reviews' ), $to ), 'updated' );
			} else {
				add_settings_error( 'pr_alerts', 'err', sprintf( __( 'Test failed: %s', 'product-reviews' ), $res['error'] ?? 'unknown' ) );
			}
		}
	}

	if ( 'delete_sub' === $action ) {
		global $wpdb;
		$wpdb->delete( pr_alerts_table(), array( 'id' => (int) ( $_POST['sub_id'] ?? 0 ) ) );
		add_settings_error( 'pr_alerts', 'saved', __( 'Subscriber deleted.', 'product-reviews' ), 'updated' );
	}
}

/* ---------------------------------------------------------------------------
 * Tabs.
 * ------------------------------------------------------------------------- */
function pr_alerts_admin_tab_transports() {
	settings_errors( 'pr_alerts' );
	$s        = pr_email_settings();
	$presets  = pr_smtp_presets();
	$edit_id  = isset( $_GET['edit'] ) ? sanitize_key( $_GET['edit'] ) : '';
	$edit     = $edit_id ? ( $s['transports'][ $edit_id ] ?? null ) : null;

	echo '<h2>' . esc_html__( 'Configured transports', 'product-reviews' ) . '</h2>';
	echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Type</th><th>Label</th><th>Details</th><th></th></tr></thead><tbody>';
	foreach ( $s['transports'] as $id => $cfg ) {
		echo '<tr><td><code>' . esc_html( $id ) . '</code></td><td>' . esc_html( $cfg['type'] ) . '</td><td>' . esc_html( $cfg['label'] ?? '' ) . '</td><td>';
		if ( 'smtp' === $cfg['type'] ) {
			echo esc_html( ( $cfg['host'] ?? '' ) . ':' . ( $cfg['port'] ?? '' ) . ' / ' . ( $cfg['encryption'] ?? '' ) );
		} elseif ( 'mailchimp' === $cfg['type'] ) {
			echo 'audience ' . esc_html( $cfg['audience_id'] ?? '—' );
		} else {
			echo '—';
		}
		echo '</td><td style="white-space:nowrap">';
		printf( '<a class="button" href="%s">%s</a> ', esc_url( pr_alerts_admin_url( array( 'tab' => 'transports', 'edit' => $id ) ) ), esc_html__( 'Edit', 'product-reviews' ) );
		echo '<form method="post" style="display:inline">';
		wp_nonce_field( 'pr_alerts_admin' );
		echo '<input type="hidden" name="pr_alerts_action" value="test_transport"><input type="hidden" name="id" value="' . esc_attr( $id ) . '">';
		echo '<input type="email" name="to" placeholder="' . esc_attr( get_option( 'admin_email' ) ) . '" style="width:160px">';
		submit_button( __( 'Send test', 'product-reviews' ), 'secondary small', '', false );
		echo '</form> ';
		if ( 'wpmail' !== $id ) {
			echo '<form method="post" style="display:inline" onsubmit="return confirm(\'Delete transport?\')">';
			wp_nonce_field( 'pr_alerts_admin' );
			echo '<input type="hidden" name="pr_alerts_action" value="delete_transport"><input type="hidden" name="id" value="' . esc_attr( $id ) . '">';
			submit_button( __( 'Delete', 'product-reviews' ), 'delete small', '', false );
			echo '</form>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';

	echo '<h2 style="margin-top:2rem">' . ( $edit ? esc_html__( 'Edit transport', 'product-reviews' ) : esc_html__( 'Add transport', 'product-reviews' ) ) . '</h2>';
	echo '<form method="post" class="card" style="padding:1rem;max-width:760px">';
	wp_nonce_field( 'pr_alerts_admin' );
	echo '<input type="hidden" name="pr_alerts_action" value="' . ( $edit ? 'edit_transport' : 'add_transport' ) . '">';
	$type = $edit ? $edit['type'] : 'smtp';
	?>
	<table class="form-table">
		<tr><th><label>ID</label></th><td><input type="text" name="id" value="<?php echo esc_attr( $edit_id ); ?>" pattern="[a-z0-9_\-]+" required <?php echo $edit ? 'readonly' : ''; ?> /><p class="description">Lowercase letters, numbers, dashes/underscores. Used internally only.</p></td></tr>
		<tr><th><label>Type</label></th><td>
			<select name="type" onchange="document.querySelectorAll('.pr-t-block').forEach(function(b){b.style.display='none'});var v=this.value;var el=document.querySelector('.pr-t-'+v);if(el)el.style.display='';">
				<option value="smtp"      <?php selected( $type, 'smtp' ); ?>>SMTP (Brevo, SendGrid, Gmail, custom…)</option>
				<option value="mailchimp" <?php selected( $type, 'mailchimp' ); ?>>Mailchimp (API + Journey)</option>
				<option value="wpmail"    <?php selected( $type, 'wpmail' ); ?>>WordPress default (wp_mail)</option>
			</select>
		</td></tr>
		<tr><th><label>Label</label></th><td><input type="text" name="label" value="<?php echo esc_attr( $edit['label'] ?? '' ); ?>" style="width:100%"/></td></tr>
	</table>

	<div class="pr-t-block pr-t-smtp" style="<?php echo 'smtp' === $type ? '' : 'display:none'; ?>">
		<h3>SMTP credentials</h3>
		<table class="form-table">
			<tr><th>Preset</th><td>
				<select name="preset" onchange="(function(p){var f={<?php foreach ( $presets as $k => $v ) { echo "'" . esc_js( $k ) . "':" . wp_json_encode( $v ) . ','; } ?>}[p];if(!f)return;document.querySelector('[name=host]').value=f.host;document.querySelector('[name=port]').value=f.port;document.querySelector('[name=encryption]').value=f.encryption;})(this.value)">
					<?php foreach ( $presets as $k => $v ) {
						printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $edit['preset'] ?? 'custom', $k, false ), esc_html( $v['label'] ) );
					} ?>
				</select>
			</td></tr>
			<tr><th>Host</th><td><input type="text" name="host" value="<?php echo esc_attr( $edit['host'] ?? '' ); ?>" style="width:300px"/></td></tr>
			<tr><th>Port</th><td><input type="number" name="port" value="<?php echo esc_attr( $edit['port'] ?? 587 ); ?>" /></td></tr>
			<tr><th>Encryption</th><td>
				<select name="encryption">
					<?php foreach ( array( 'tls', 'ssl', 'none' ) as $e ) { printf( '<option value="%s" %s>%s</option>', $e, selected( $edit['encryption'] ?? 'tls', $e, false ), esc_html( strtoupper( $e ) ) ); } ?>
				</select>
			</td></tr>
			<tr><th>Username</th><td><input type="text" name="username" value="<?php echo esc_attr( $edit['username'] ?? '' ); ?>" style="width:300px" autocomplete="off"/></td></tr>
			<tr><th>Password / API key</th><td>
				<input type="password" name="password" placeholder="<?php echo $edit && ! empty( $edit['password'] ) ? esc_attr__( '•••••• (leave blank to keep)', 'product-reviews' ) : ''; ?>" style="width:300px" autocomplete="new-password"/>
				<p class="description">Stored encrypted with AUTH_KEY.</p>
			</td></tr>
			<tr><th>From email</th><td><input type="email" name="from_email" value="<?php echo esc_attr( $edit['from_email'] ?? '' ); ?>" style="width:300px"/><p class="description">Optional. Falls back to global From email.</p></td></tr>
			<tr><th>From name</th><td><input type="text"  name="from_name"  value="<?php echo esc_attr( $edit['from_name']  ?? '' ); ?>" style="width:300px"/></td></tr>
		</table>
	</div>

	<div class="pr-t-block pr-t-mailchimp" style="<?php echo 'mailchimp' === $type ? '' : 'display:none'; ?>">
		<h3>Mailchimp</h3>
		<table class="form-table">
			<tr><th>API key</th><td><input type="password" name="api_key" placeholder="<?php echo $edit && ! empty( $edit['api_key'] ) ? '•••••• (leave blank to keep)' : 'xxxxxxxx-us21'; ?>" style="width:380px" autocomplete="new-password"/><p class="description">Mailchimp → Profile → Extras → API keys.</p></td></tr>
			<tr><th>Audience ID</th><td><input type="text" name="audience_id" value="<?php echo esc_attr( $edit['audience_id'] ?? '' ); ?>" style="width:200px"/><p class="description">Audience → Settings → Audience name and defaults.</p></td></tr>
			<tr><th>Drop tag</th><td><input type="text" name="tag_drop" value="<?php echo esc_attr( $edit['tag_drop'] ?? 'price-drop-fired' ); ?>" style="width:240px"/><p class="description">Build a Customer Journey: trigger "Tag added → this tag" → action "Send email" using merge tags <code>*|PRODUCT|*</code>, <code>*|OLD_PRICE|*</code>, <code>*|NEW_PRICE|*</code>, <code>*|BUY_URL|*</code> → action "Remove tag" → publish.</p></td></tr>
		</table>
	</div>

	<?php submit_button( $edit ? __( 'Update transport', 'product-reviews' ) : __( 'Add transport', 'product-reviews' ) ); ?>
	</form>
	<?php
}

function pr_alerts_admin_tab_routing() {
	settings_errors( 'pr_alerts' );
	$s    = pr_email_settings();
	$opts = array( '' => __( '— none —', 'product-reviews' ) );
	foreach ( $s['transports'] as $id => $cfg ) { $opts[ $id ] = ( $cfg['label'] ?? $id ) . " ({$id})"; }
	$purposes = array(
		'confirm'    => __( 'Subscription confirmation (double opt-in)', 'product-reviews' ),
		'price_drop' => __( 'Price-drop alert', 'product-reviews' ),
		'unsub'      => __( 'Unsubscribe confirmation', 'product-reviews' ),
		'test'       => __( 'Admin test email', 'product-reviews' ),
	);
	echo '<form method="post">';
	wp_nonce_field( 'pr_alerts_admin' );
	echo '<input type="hidden" name="pr_alerts_action" value="save_routing">';
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Email purpose', 'product-reviews' ) . '</th><th>' . esc_html__( 'Primary transport', 'product-reviews' ) . '</th><th>' . esc_html__( 'Fallback (optional)', 'product-reviews' ) . '</th></tr></thead><tbody>';
	foreach ( $purposes as $p => $label ) {
		$cur = $s['routing'][ $p ] ?? array( 'primary' => 'wpmail', 'fallback' => '' );
		echo '<tr><td>' . esc_html( $label ) . '</td><td>';
		echo '<select name="routing[' . esc_attr( $p ) . '][primary]">';
		foreach ( $opts as $k => $l ) {
			if ( '' === $k ) { continue; }
			printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $cur['primary'], $k, false ), esc_html( $l ) );
		}
		echo '</select></td><td>';
		echo '<select name="routing[' . esc_attr( $p ) . '][fallback]">';
		foreach ( $opts as $k => $l ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $cur['fallback'], $k, false ), esc_html( $l ) );
		}
		echo '</select></td></tr>';
	}
	echo '</tbody></table>';
	submit_button( __( 'Save routing', 'product-reviews' ) );
	echo '</form>';
}

function pr_alerts_admin_tab_settings() {
	settings_errors( 'pr_alerts' );
	$s = pr_email_settings();
	echo '<form method="post">';
	wp_nonce_field( 'pr_alerts_admin' );
	echo '<input type="hidden" name="pr_alerts_action" value="save_settings">';
	?>
	<table class="form-table">
		<tr><th><?php esc_html_e( 'Alerts enabled', 'product-reviews' ); ?></th><td><label><input type="checkbox" name="enabled" value="1" <?php checked( $s['enabled'] ); ?>/> <?php esc_html_e( 'Send alert emails', 'product-reviews' ); ?></label></td></tr>
		<tr><th><?php esc_html_e( 'Minimum drop %', 'product-reviews' ); ?></th><td><input type="number" min="1" max="95" name="min_drop_percent" value="<?php echo esc_attr( $s['min_drop_percent'] ); ?>" /> <p class="description"><?php esc_html_e( 'Only notify when the price drops at least this much.', 'product-reviews' ); ?></p></td></tr>
		<tr><th><?php esc_html_e( 'Cooldown (hours)', 'product-reviews' ); ?></th><td><input type="number" min="1" max="720" name="cooldown_hours" value="<?php echo esc_attr( $s['cooldown_hours'] ); ?>" /> <p class="description"><?php esc_html_e( 'Wait at least this long between notifications for the same subscriber.', 'product-reviews' ); ?></p></td></tr>
		<tr><th><?php esc_html_e( 'From name', 'product-reviews' ); ?></th><td><input type="text" name="from_name" value="<?php echo esc_attr( $s['from_name'] ); ?>" style="width:340px" /></td></tr>
		<tr><th><?php esc_html_e( 'From email', 'product-reviews' ); ?></th><td><input type="email" name="from_email" value="<?php echo esc_attr( $s['from_email'] ); ?>" style="width:340px" /></td></tr>
	</table>
	<?php
	submit_button( __( 'Save settings', 'product-reviews' ) );
	echo '</form>';
}

function pr_alerts_admin_tab_subscribers() {
	settings_errors( 'pr_alerts' );
	global $wpdb;
	$table = pr_alerts_table();
	$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 200" );
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	$conf  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status='confirmed'" );

	echo '<p>' . sprintf( esc_html__( '%1$d total · %2$d confirmed · showing latest %3$d', 'product-reviews' ), $total, $conf, count( $rows ) ) . ' &middot; <a href="' . esc_url( add_query_arg( array( 'pr_alerts_export' => 1 ), pr_alerts_admin_url( array( 'tab' => 'subscribers' ) ) ) ) . '">' . esc_html__( 'Export CSV', 'product-reviews' ) . '</a></p>';
	echo '<table class="widefat striped"><thead><tr><th>Email</th><th>Product</th><th>Status</th><th>Price</th><th>Last notified</th><th>Created</th><th></th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr>';
		echo '<td>' . esc_html( $r->email ) . '</td>';
		echo '<td>' . esc_html( $r->product_name ) . '</td>';
		echo '<td>' . esc_html( $r->status ) . '</td>';
		echo '<td>' . esc_html( $r->current_price ? pr_alerts_format_price( $r->current_price, $r->currency ) : '—' ) . '</td>';
		echo '<td>' . esc_html( $r->last_notified_at ?: '—' ) . '</td>';
		echo '<td>' . esc_html( $r->created_at ) . '</td>';
		echo '<td><form method="post" onsubmit="return confirm(\'Delete?\')" style="margin:0">';
		wp_nonce_field( 'pr_alerts_admin' );
		echo '<input type="hidden" name="pr_alerts_action" value="delete_sub"><input type="hidden" name="sub_id" value="' . (int) $r->id . '">';
		submit_button( '×', 'delete small', '', false );
		echo '</form></td>';
		echo '</tr>';
	}
	echo '</tbody></table>';
}

add_action( 'admin_init', function () {
	if ( empty( $_GET['pr_alerts_export'] ) || ! current_user_can( 'manage_options' ) ) { return; }
	global $wpdb;
	$rows = $wpdb->get_results( 'SELECT email, product_slug, product_name, status, current_price, currency, created_at FROM ' . pr_alerts_table() );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename=pr-alert-subscribers.csv' );
	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array( 'email', 'product_slug', 'product_name', 'status', 'current_price', 'currency', 'created_at' ) );
	foreach ( $rows as $r ) { fputcsv( $out, (array) $r ); }
	fclose( $out );
	exit;
} );

function pr_alerts_admin_tab_logs() {
	$rows = get_option( 'pr_email_log', array() );
	echo '<p>' . esc_html__( 'Last 100 send attempts across all transports.', 'product-reviews' ) . '</p>';
	echo '<table class="widefat striped"><thead><tr><th>Time</th><th>Transport</th><th>Type</th><th>Purpose</th><th>To</th><th>Status</th><th>Error</th></tr></thead><tbody>';
	if ( ! $rows ) {
		echo '<tr><td colspan="7">' . esc_html__( 'No emails sent yet.', 'product-reviews' ) . '</td></tr>';
	} else {
		foreach ( $rows as $r ) {
			echo '<tr>';
			echo '<td>' . esc_html( gmdate( 'Y-m-d H:i:s', $r['t'] ) ) . ' UTC</td>';
			echo '<td><code>' . esc_html( $r['tid'] ) . '</code></td>';
			echo '<td>' . esc_html( $r['type'] ) . '</td>';
			echo '<td>' . esc_html( $r['use'] ) . '</td>';
			echo '<td>' . esc_html( $r['to'] ) . '</td>';
			echo $r['ok'] ? '<td style="color:#059669">✓</td>' : '<td style="color:#dc2626">✗</td>';
			echo '<td>' . esc_html( $r['err'] ) . '</td>';
			echo '</tr>';
		}
	}
	echo '</tbody></table>';
}
