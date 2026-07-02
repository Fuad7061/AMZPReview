<?php
/**
 * Mailchimp transport. "Sending" a confirmation = upserting the contact as
 * pending (Mailchimp sends its built-in opt-in email). "Sending" a price drop
 * = updating merge fields + adding a tag that a Customer Journey listens for.
 *
 * Transport cfg: api_key (encrypted), audience_id, tag_drop ('price-drop-fired').
 *
 * @package ProductReviews
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once PR_THEME_DIR . '/inc/mailchimp-client.php';

add_filter( 'pr_email_transports', function( $reg ) {
	$reg['mailchimp'] = array(
		'label' => 'Mailchimp (API + Journey)',
		'send'  => 'pr_mc_send',
	);
	return $reg;
} );

function pr_mc_send( $cfg, $msg ) {
	$api  = isset( $cfg['api_key'] ) ? pr_decrypt( $cfg['api_key'] ) : '';
	$list = isset( $cfg['audience_id'] ) ? $cfg['audience_id'] : '';
	if ( '' === $api || '' === $list ) { return array( 'success' => false, 'error' => 'mailchimp_not_configured' ); }

	$email = is_array( $msg['to'] ) ? reset( $msg['to'] ) : $msg['to'];
	$hash  = md5( strtolower( $email ) );
	$ex    = isset( $msg['extra'] ) ? $msg['extra'] : array();
	$merge = array();
	foreach ( array( 'PRODUCT', 'PSLUG', 'PRICE', 'CURRENCY', 'OLD_PRICE', 'NEW_PRICE', 'DROP_PCT', 'BUY_URL' ) as $k ) {
		if ( isset( $ex[ $k ] ) ) { $merge[ $k ] = (string) $ex[ $k ]; }
	}

	switch ( $msg['purpose'] ) {
		case 'confirm':
			$body = array(
				'email_address' => $email,
				'status_if_new' => 'pending', // double opt-in via Mailchimp
				'status'        => 'pending',
				'merge_fields'  => $merge,
				'tags'          => array( 'price-alert' ),
			);
			$r = pr_mc_request( $api, 'PUT', "lists/{$list}/members/{$hash}", $body );
			break;

		case 'price_drop':
			$tag = ! empty( $cfg['tag_drop'] ) ? $cfg['tag_drop'] : 'price-drop-fired';
			$r1  = pr_mc_request( $api, 'PATCH', "lists/{$list}/members/{$hash}", array( 'merge_fields' => $merge ) );
			if ( is_wp_error( $r1 ) ) { $r = $r1; break; }
			$r = pr_mc_request( $api, 'POST', "lists/{$list}/members/{$hash}/tags", array(
				'tags' => array( array( 'name' => $tag, 'status' => 'active' ) ),
			) );
			break;

		case 'unsub':
			$r = pr_mc_request( $api, 'PATCH', "lists/{$list}/members/{$hash}", array( 'status' => 'unsubscribed' ) );
			break;

		default:
			// 'test' or anything else — just upsert as subscribed (no email triggered).
			$r = pr_mc_request( $api, 'PUT', "lists/{$list}/members/{$hash}", array(
				'email_address' => $email,
				'status_if_new' => 'subscribed',
				'status'        => 'subscribed',
			) );
	}

	if ( is_wp_error( $r ) ) {
		return array( 'success' => false, 'error' => $r->get_error_message() );
	}
	return array( 'success' => true );
}
