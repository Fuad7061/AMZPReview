<?php
/**
 * Branding — single source of truth for the site's identity.
 *
 * Every template, admin label, schema block, and email reads from these
 * helpers. To rebrand the site, change values in Appearance → Customize →
 * Brand. Nothing here is hardcoded to "YadFood" or any other name.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Primary site brand name. Defaults to the WP site title.
 */
function pr_brand() {
	$value = trim( (string) get_theme_mod( 'pr_brand_name', '' ) );
	if ( $value === '' ) {
		$value = (string) get_bloginfo( 'name' );
	}
	return $value;
}

/**
 * Short brand name (≤ 24 chars) for tight headers / mobile. Falls back to pr_brand().
 */
function pr_short_name() {
	$value = trim( (string) get_theme_mod( 'pr_brand_short', '' ) );
	if ( $value === '' ) {
		$value = pr_brand();
	}
	return mb_substr( $value, 0, 24 );
}

/**
 * Tagline. Falls back to WP "description" blog option.
 */
function pr_tagline() {
	$value = trim( (string) get_theme_mod( 'pr_brand_tagline', '' ) );
	if ( $value === '' ) {
		$value = (string) get_bloginfo( 'description' );
	}
	return $value;
}

/**
 * Logo URL. Falls back to the WP custom-logo if set.
 */
function pr_logo_url() {
	$value = trim( (string) get_theme_mod( 'pr_brand_logo', '' ) );
	if ( $value !== '' ) {
		return $value;
	}
	$logo_id = get_theme_mod( 'custom_logo' );
	if ( $logo_id ) {
		$src = wp_get_attachment_image_src( $logo_id, 'full' );
		if ( $src ) {
			return $src[0];
		}
	}
	return '';
}

/**
 * Dark-mode logo URL (optional).
 */
function pr_logo_dark_url() {
	$value = trim( (string) get_theme_mod( 'pr_brand_logo_dark', '' ) );
	return $value !== '' ? $value : pr_logo_url();
}

/**
 * Admin dashboard label (the top-level menu item under "YadFood HQ" before).
 */
function pr_admin_label() {
	$value = trim( (string) get_theme_mod( 'pr_admin_label', '' ) );
	if ( $value === '' ) {
		$value = pr_short_name();
	}
	return $value;
}

/**
 * Email "From" name for system notifications.
 */
function pr_email_from() {
	$value = trim( (string) get_theme_mod( 'pr_email_from_name', '' ) );
	return $value !== '' ? $value : pr_brand();
}

/**
 * Footer copyright text. Supports {year} and {brand} placeholders.
 */
function pr_footer_copyright() {
	$tpl = (string) get_theme_mod(
		'pr_footer_copyright',
		'© {year} {brand}. All rights reserved.'
	);
	return strtr( $tpl, array(
		'{year}'  => gmdate( 'Y' ),
		'{brand}' => pr_brand(),
	) );
}

/**
 * Brand color tokens (used by Customizer + enqueue.php to inject CSS vars).
 */
function pr_brand_color_primary() {
	return (string) get_theme_mod( 'pr_brand_color_primary', '#ff6b35' );
}
function pr_brand_color_secondary() {
	return (string) get_theme_mod( 'pr_brand_color_secondary', '#1a1a2e' );
}
