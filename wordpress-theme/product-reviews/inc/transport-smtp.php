<?php
/**
 * Generic SMTP transport. Reuses WordPress's bundled PHPMailer via the
 * phpmailer_init hook scoped to a single send — no plugin required.
 *
 * Each SMTP transport entry stores: host, port, encryption (tls|ssl|none),
 * username, password (encrypted), from_name, from_email, preset (informational).
 *
 * Provider presets (host/port/encryption/username-hint) are exposed via
 * pr_smtp_presets() so the admin UI can autofill the form.
 *
 * @package ProductReviews
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function pr_smtp_presets() {
	return array(
		'brevo'    => array( 'label' => 'Brevo (Sendinblue) — 300/day free',     'host' => 'smtp-relay.brevo.com', 'port' => 587, 'encryption' => 'tls', 'username_hint' => 'Your Brevo login email' ),
		'sendgrid' => array( 'label' => 'SendGrid — 100/day free',               'host' => 'smtp.sendgrid.net',    'port' => 587, 'encryption' => 'tls', 'username_hint' => 'apikey' ),
		'mailgun'  => array( 'label' => 'Mailgun — 100/day free after trial',    'host' => 'smtp.mailgun.org',     'port' => 587, 'encryption' => 'tls', 'username_hint' => 'postmaster@yourdomain' ),
		'smtp2go'  => array( 'label' => 'SMTP2GO — 1,000/month free',            'host' => 'mail.smtp2go.com',     'port' => 587, 'encryption' => 'tls', 'username_hint' => 'SMTP username you created' ),
		'gmail'    => array( 'label' => 'Gmail — ~500/day, app password',        'host' => 'smtp.gmail.com',       'port' => 587, 'encryption' => 'tls', 'username_hint' => 'you@gmail.com' ),
		'zoho'     => array( 'label' => 'Zoho Mail — 200/day free',              'host' => 'smtp.zoho.com',        'port' => 587, 'encryption' => 'tls', 'username_hint' => 'you@zoho.com' ),
		'mailjet'  => array( 'label' => 'Mailjet — 200/day free',                'host' => 'in-v3.mailjet.com',    'port' => 587, 'encryption' => 'tls', 'username_hint' => 'API key' ),
		'custom'   => array( 'label' => 'Custom SMTP',                            'host' => '',                      'port' => 587, 'encryption' => 'tls', 'username_hint' => '' ),
	);
}

add_filter( 'pr_email_transports', function( $reg ) {
	$reg['smtp'] = array(
		'label' => 'SMTP',
		'send'  => 'pr_smtp_send',
	);
	return $reg;
} );

function pr_smtp_send( $cfg, $msg ) {
	$host = isset( $cfg['host'] ) ? trim( $cfg['host'] ) : '';
	if ( '' === $host ) { return array( 'success' => false, 'error' => 'smtp_host_missing' ); }

	$from_email = ! empty( $cfg['from_email'] ) ? $cfg['from_email'] : $msg['from_email'];
	$from_name  = ! empty( $cfg['from_name'] )  ? $cfg['from_name']  : $msg['from_name'];
	$port       = isset( $cfg['port'] ) ? (int) $cfg['port'] : 587;
	$enc        = isset( $cfg['encryption'] ) ? $cfg['encryption'] : 'tls';
	$user       = isset( $cfg['username'] ) ? $cfg['username'] : '';
	$pass       = isset( $cfg['password'] ) ? pr_decrypt( $cfg['password'] ) : '';

	$configure = function( $phpmailer ) use ( $host, $port, $enc, $user, $pass, $from_email, $from_name ) {
		$phpmailer->isSMTP();
		$phpmailer->Host       = $host;
		$phpmailer->Port       = $port;
		$phpmailer->SMTPAuth   = ! empty( $user );
		$phpmailer->Username   = $user;
		$phpmailer->Password   = $pass;
		$phpmailer->SMTPSecure = ( 'none' === $enc ) ? '' : $enc; // tls|ssl|''
		$phpmailer->SMTPAutoTLS = ( 'none' !== $enc );
		$phpmailer->setFrom( $from_email, $from_name, false );
	};
	add_action( 'phpmailer_init', $configure, 99 );

	$headers = array(
		'Content-Type: text/html; charset=UTF-8',
		sprintf( 'From: %s <%s>', $from_name, $from_email ),
	);
	$err = '';
	$capture = function( $e ) use ( &$err ) {
		$err = is_wp_error( $e ) ? $e->get_error_message() : (string) $e;
	};
	add_action( 'wp_mail_failed', $capture );

	$ok = wp_mail( $msg['to'], $msg['subject'], $msg['html'], $headers );

	remove_action( 'phpmailer_init', $configure, 99 );
	remove_action( 'wp_mail_failed', $capture );

	return $ok
		? array( 'success' => true )
		: array( 'success' => false, 'error' => $err ? $err : 'smtp_send_failed' );
}
