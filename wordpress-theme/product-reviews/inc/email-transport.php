<?php
/**
 * Email transport dispatcher.
 *
 * Single entry point pr_send_email() routes mail through whichever transport
 * the admin mapped to that purpose. Adapters self-register via the
 * 'pr_email_transports' filter so adding providers is a one-file change.
 *
 * Also provides AES-256-CBC at-rest crypto (keyed off AUTH_KEY) for storing
 * SMTP passwords / API keys, plus a rolling 100-row send log.
 *
 * @package ProductReviews
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ---------------------------------------------------------------------------
 * Crypto helpers — used by every adapter that persists secrets.
 * ------------------------------------------------------------------------- */
if ( ! function_exists( 'pr_crypt_key' ) ) {
function pr_crypt_key() {
	$k = defined( 'AUTH_KEY' ) ? AUTH_KEY : ( defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'pr-fallback-key' );
	return substr( hash( 'sha256', $k, true ), 0, 32 );
}
}
if ( ! function_exists( 'pr_encrypt' ) ) {
function pr_encrypt( $plain ) {
	if ( '' === (string) $plain ) { return ''; }
	if ( ! function_exists( 'openssl_encrypt' ) ) { return (string) $plain; }
	$iv  = random_bytes( 16 );
	$enc = openssl_encrypt( (string) $plain, 'aes-256-cbc', pr_crypt_key(), OPENSSL_RAW_DATA, $iv );
	return 'enc:' . base64_encode( $iv . $enc );
}
}
if ( ! function_exists( 'pr_decrypt' ) ) {
function pr_decrypt( $stored ) {
	if ( ! is_string( $stored ) || 0 !== strpos( $stored, 'enc:' ) ) { return (string) $stored; }
	if ( ! function_exists( 'openssl_decrypt' ) ) { return ''; }
	$raw = base64_decode( substr( $stored, 4 ), true );
	if ( ! $raw || strlen( $raw ) < 17 ) { return ''; }
	$iv  = substr( $raw, 0, 16 );
	$ct  = substr( $raw, 16 );
	$out = openssl_decrypt( $ct, 'aes-256-cbc', pr_crypt_key(), OPENSSL_RAW_DATA, $iv );
	return false === $out ? '' : $out;
}
}
function pr_mask_secret( $s ) {
	$s = (string) $s;
	if ( '' === $s ) { return ''; }
	$len = strlen( $s );
	if ( $len <= 4 ) { return str_repeat( '•', $len ); }
	return str_repeat( '•', max( 4, $len - 4 ) ) . substr( $s, -4 );
}

/* ---------------------------------------------------------------------------
 * Settings access.
 * ------------------------------------------------------------------------- */
function pr_email_settings() {
	$defaults = array(
		'enabled'           => 1,
		'min_drop_percent'  => 5,
		'cooldown_hours'    => 72,
		'from_name'         => get_bloginfo( 'name' ),
		'from_email'        => get_option( 'admin_email' ),
		'routing'           => array(
			'confirm'    => array( 'primary' => 'wpmail',    'fallback' => '' ),
			'price_drop' => array( 'primary' => 'wpmail',    'fallback' => '' ),
			'unsub'      => array( 'primary' => 'wpmail',    'fallback' => '' ),
			'test'       => array( 'primary' => 'wpmail',    'fallback' => '' ),
		),
		'transports'        => array(
			// id => array('type'=>'smtp|mailchimp|wpmail', 'label'=>..., ...creds)
			'wpmail' => array( 'type' => 'wpmail', 'label' => 'WordPress default (wp_mail)' ),
		),
	);
	$opt = get_option( 'pr_email_settings', array() );
	$opt = is_array( $opt ) ? $opt : array();
	return wp_parse_args( $opt, $defaults );
}
function pr_email_settings_save( $patch ) {
	$cur = pr_email_settings();
	$new = array_replace_recursive( $cur, (array) $patch );
	update_option( 'pr_email_settings', $new, false );
	return $new;
}

/* ---------------------------------------------------------------------------
 * Transport registry. Adapters add themselves via this filter.
 *
 * Each entry: array(
 *   'send'  => callable( $config, array $msg ): array{success:bool,error?:string},
 *   'label' => 'Human label',
 * )
 * ------------------------------------------------------------------------- */
function pr_email_transport_registry() {
	static $reg = null;
	if ( null !== $reg ) { return $reg; }
	$reg = apply_filters( 'pr_email_transports', array() );
	return $reg;
}

/* ---------------------------------------------------------------------------
 * Public sender. $purpose ∈ confirm|price_drop|unsub|test.
 * ------------------------------------------------------------------------- */
function pr_send_email( $to, $subject, $html, $text = '', $purpose = 'confirm', $extra = array() ) {
	$settings  = pr_email_settings();
	if ( empty( $settings['enabled'] ) ) {
		return array( 'success' => false, 'error' => 'alerts_disabled' );
	}
	$route     = isset( $settings['routing'][ $purpose ] ) ? $settings['routing'][ $purpose ] : array();
	$transports = pr_email_transport_registry();
	$tries     = array_filter( array(
		isset( $route['primary'] )  ? $route['primary']  : '',
		isset( $route['fallback'] ) ? $route['fallback'] : '',
	) );
	if ( empty( $tries ) ) { $tries = array( 'wpmail' ); }

	$msg = array(
		'to'         => $to,
		'subject'    => $subject,
		'html'       => $html,
		'text'       => $text ? $text : wp_strip_all_tags( $html ),
		'from_name'  => $settings['from_name'],
		'from_email' => $settings['from_email'],
		'extra'      => (array) $extra,
		'purpose'    => $purpose,
	);

	$last = array( 'success' => false, 'error' => 'no_transport' );
	foreach ( $tries as $tid ) {
		$cfg = isset( $settings['transports'][ $tid ] ) ? $settings['transports'][ $tid ] : null;
		if ( ! $cfg ) { continue; }
		$type = isset( $cfg['type'] ) ? $cfg['type'] : '';
		if ( ! isset( $transports[ $type ] ) ) { continue; }
		$res  = call_user_func( $transports[ $type ]['send'], $cfg, $msg );
		pr_email_log( $tid, $type, $purpose, $to, $res );
		if ( ! empty( $res['success'] ) ) { return $res; }
		$last = $res;
	}
	return $last;
}

/* ---------------------------------------------------------------------------
 * Rolling log (last 100 sends).
 * ------------------------------------------------------------------------- */
function pr_email_log( $tid, $type, $purpose, $to, $res ) {
	$rows = get_option( 'pr_email_log', array() );
	$rows = is_array( $rows ) ? $rows : array();
	array_unshift( $rows, array(
		't'    => time(),
		'tid'  => $tid,
		'type' => $type,
		'use'  => $purpose,
		'to'   => pr_mask_email( $to ),
		'ok'   => ! empty( $res['success'] ) ? 1 : 0,
		'err'  => isset( $res['error'] ) ? substr( (string) $res['error'], 0, 240 ) : '',
	) );
	$rows = array_slice( $rows, 0, 100 );
	update_option( 'pr_email_log', $rows, false );
}
function pr_mask_email( $e ) {
	$e = (string) $e;
	$at = strpos( $e, '@' );
	if ( false === $at || $at < 2 ) { return $e; }
	return substr( $e, 0, 2 ) . str_repeat( '•', max( 1, $at - 2 ) ) . substr( $e, $at );
}

/* ---------------------------------------------------------------------------
 * WordPress default transport (always registered).
 * ------------------------------------------------------------------------- */
add_filter( 'pr_email_transports', function( $reg ) {
	$reg['wpmail'] = array(
		'label' => 'WordPress default (wp_mail)',
		'send'  => function( $cfg, $msg ) {
			$headers = array(
				'Content-Type: text/html; charset=UTF-8',
				sprintf( 'From: %s <%s>', $msg['from_name'], $msg['from_email'] ),
			);
			$ok = wp_mail( $msg['to'], $msg['subject'], $msg['html'], $headers );
			return $ok
				? array( 'success' => true )
				: array( 'success' => false, 'error' => 'wp_mail_failed' );
		},
	);
	return $reg;
} );
