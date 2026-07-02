<?php
/**
 * Theme Customizer — exposes Amazon tag, AI provider, and Amazon PA-API keys.
 *
 * @package YadFoodReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function yadfood_customize_register( $wp_customize ) {
	/* ----- Affiliate settings ----- */
	$wp_customize->add_section( 'yadfood_affiliate', array(
		'title'    => __( 'Affiliate Settings', 'yadfood-reviews' ),
		'priority' => 30,
	) );

	$wp_customize->add_setting( 'yadfood_amazon_tag', array(
		'default'           => 'YOUR-TAG-20',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'yadfood_amazon_tag', array(
		'label'       => __( 'Amazon Associates Tag', 'yadfood-reviews' ),
		'section'     => 'yadfood_affiliate',
		'description' => __( 'Example: yadfood-20. Applied to every affiliate link.', 'yadfood-reviews' ),
		'type'        => 'text',
	) );

	$wp_customize->add_setting( 'yadfood_disclosure', array(
		'default'           => __( 'As an Amazon Associate, yadfood.com earns from qualifying purchases. Prices and availability are accurate as of the time shown and are subject to change.', 'yadfood-reviews' ),
		'sanitize_callback' => 'wp_kses_post',
	) );
	$wp_customize->add_control( 'yadfood_disclosure', array(
		'label'   => __( 'FTC Disclosure Text', 'yadfood-reviews' ),
		'section' => 'yadfood_affiliate',
		'type'    => 'textarea',
	) );

	/* ----- AI generation settings ----- */
	$wp_customize->add_section( 'yadfood_ai', array(
		'title'    => __( 'AI Article Generation', 'yadfood-reviews' ),
		'priority' => 35,
	) );

	$wp_customize->add_setting( 'yadfood_ai_provider', array(
		'default'           => 'openai',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'yadfood_ai_provider', array(
		'label'   => __( 'AI Provider', 'yadfood-reviews' ),
		'section' => 'yadfood_ai',
		'type'    => 'select',
		'choices' => array(
			'openai'    => 'OpenAI (GPT)',
			'gemini'    => 'Google Gemini',
			'anthropic' => 'Anthropic (Claude)',
		),
	) );

	$wp_customize->add_setting( 'yadfood_ai_model', array(
		'default'           => 'gpt-4o-mini',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'yadfood_ai_model', array(
		'label'       => __( 'Model name', 'yadfood-reviews' ),
		'section'     => 'yadfood_ai',
		'description' => __( 'e.g. gpt-4o-mini, gemini-2.5-flash, claude-3-5-sonnet-latest.', 'yadfood-reviews' ),
		'type'        => 'text',
	) );

	$wp_customize->add_setting( 'yadfood_ai_api_key', array(
		'default'           => '',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'yadfood_ai_api_key', array(
		'label'       => __( 'AI API Key', 'yadfood-reviews' ),
		'section'     => 'yadfood_ai',
		'description' => __( 'Your own provider API key. Stored in WordPress options. Never exposed to the browser.', 'yadfood-reviews' ),
		'type'        => 'password',
	) );

	$wp_customize->add_setting( 'yadfood_cron_enabled', array(
		'default'           => '0',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'yadfood_cron_enabled', array(
		'label'       => __( 'Auto-generate scheduled articles', 'yadfood-reviews' ),
		'section'     => 'yadfood_ai',
		'description' => __( 'When enabled, the keyword queue (under Reviews → Auto Queue) generates one article daily.', 'yadfood-reviews' ),
		'type'        => 'checkbox',
	) );

	/* ----- Amazon PA-API (live product data) ----- */
	$wp_customize->add_section( 'yadfood_paapi', array(
		'title'    => __( 'Amazon Product API', 'yadfood-reviews' ),
		'priority' => 40,
	) );

	$wp_customize->add_setting( 'yadfood_paapi_access_key', array(
		'default'           => '',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'yadfood_paapi_access_key', array(
		'label'       => __( 'PA-API Access Key', 'yadfood-reviews' ),
		'section'     => 'yadfood_paapi',
		'description' => __( 'From Amazon Associates Central → Tools → PA-API.', 'yadfood-reviews' ),
		'type'        => 'text',
	) );

	$wp_customize->add_setting( 'yadfood_paapi_secret_key', array(
		'default'           => '',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'yadfood_paapi_secret_key', array(
		'label'   => __( 'PA-API Secret Key', 'yadfood-reviews' ),
		'section' => 'yadfood_paapi',
		'type'    => 'password',
	) );

	$wp_customize->add_setting( 'yadfood_paapi_region', array(
		'default'           => 'us-east-1',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'yadfood_paapi_region', array(
		'label'   => __( 'PA-API Region', 'yadfood-reviews' ),
		'section' => 'yadfood_paapi',
		'type'    => 'text',
	) );

	$wp_customize->add_setting( 'yadfood_paapi_marketplace', array(
		'default'           => 'www.amazon.com',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'yadfood_paapi_marketplace', array(
		'label'   => __( 'Marketplace host', 'yadfood-reviews' ),
		'section' => 'yadfood_paapi',
		'type'    => 'text',
	) );

	/* ----- Mirror Customizer values into options so helpers can read them. ----- */
}
add_action( 'customize_register', 'yadfood_customize_register' );

/**
 * Sync theme_mod-style settings into get_option() since we read them via get_option().
 */
function yadfood_sync_customizer_to_options() {
	$keys = array(
		'yadfood_amazon_tag', 'yadfood_disclosure',
		'yadfood_ai_provider', 'yadfood_ai_model', 'yadfood_ai_api_key', 'yadfood_cron_enabled',
		'yadfood_paapi_access_key', 'yadfood_paapi_secret_key', 'yadfood_paapi_region', 'yadfood_paapi_marketplace',
	);
	foreach ( $keys as $k ) {
		$v = get_option( $k, null );
		if ( null === $v ) {
			$default = ( 'yadfood_amazon_tag' === $k ) ? 'YOUR-TAG-20' : '';
			update_option( $k, $default );
		}
	}
}
add_action( 'after_switch_theme', 'yadfood_sync_customizer_to_options' );

/* ============================================================
 * Brand panel — single source of truth for site identity.
 * Every label / footer / email / schema reads from pr_brand().
 * ============================================================ */
function pr_customize_register_brand( $wp_customize ) {
	$wp_customize->add_section( 'pr_brand', array(
		'title'       => __( 'Brand', 'product-reviews' ),
		'priority'    => 20,
		'description' => __( 'Site identity used in headers, footers, emails, schema, and the admin dashboard. Leave a field empty to fall back to the WordPress site title / tagline.', 'product-reviews' ),
	) );

	$fields = array(
		'pr_brand_name'          => array( 'label' => __( 'Brand name', 'product-reviews' ),         'type' => 'text',     'default' => '' ),
		'pr_brand_short'         => array( 'label' => __( 'Short name (≤ 24 chars)', 'product-reviews' ), 'type' => 'text', 'default' => '' ),
		'pr_brand_tagline'       => array( 'label' => __( 'Tagline', 'product-reviews' ),            'type' => 'text',     'default' => '' ),
		'pr_brand_logo'          => array( 'label' => __( 'Logo URL (light)', 'product-reviews' ),   'type' => 'url',      'default' => '' ),
		'pr_brand_logo_dark'     => array( 'label' => __( 'Logo URL (dark mode)', 'product-reviews' ),'type' => 'url',     'default' => '' ),
		'pr_brand_color_primary' => array( 'label' => __( 'Primary color', 'product-reviews' ),      'type' => 'color',    'default' => '#ff6b35' ),
		'pr_brand_color_secondary' => array( 'label' => __( 'Secondary color', 'product-reviews' ),  'type' => 'color',    'default' => '#1a1a2e' ),
		'pr_admin_label'         => array( 'label' => __( 'Admin dashboard label', 'product-reviews' ),'type' => 'text',   'default' => '' ),
		'pr_email_from_name'     => array( 'label' => __( 'Email "From" name', 'product-reviews' ),  'type' => 'text',     'default' => '' ),
		'pr_footer_copyright'    => array( 'label' => __( 'Footer copyright template', 'product-reviews' ), 'type' => 'text', 'default' => '© {year} {brand}. All rights reserved.', 'description' => __( 'Placeholders: {year}, {brand}', 'product-reviews' ) ),
	);

	foreach ( $fields as $id => $cfg ) {
		$wp_customize->add_setting( $id, array(
			'default'           => $cfg['default'],
			'sanitize_callback' => $cfg['type'] === 'color' ? 'sanitize_hex_color' : ( $cfg['type'] === 'url' ? 'esc_url_raw' : 'sanitize_text_field' ),
			'transport'         => 'refresh',
		) );

		if ( $cfg['type'] === 'color' ) {
			$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $id, array(
				'label'   => $cfg['label'],
				'section' => 'pr_brand',
			) ) );
		} else {
			$wp_customize->add_control( $id, array(
				'label'       => $cfg['label'],
				'description' => isset( $cfg['description'] ) ? $cfg['description'] : '',
				'section'     => 'pr_brand',
				'type'        => $cfg['type'] === 'url' ? 'url' : 'text',
			) );
		}
	}
}
add_action( 'customize_register', 'pr_customize_register_brand' );

/* ============================================================
 * Feature toggles + Price display + Social (entity graph).
 * Mirrors the React FEATURES flags so operators can enable /
 * disable modules without editing PHP.
 * ============================================================ */
function pr_customize_register_features( $wp_customize ) {
	$wp_customize->add_section( 'pr_features', array(
		'title'       => __( 'Feature Toggles', 'product-reviews' ),
		'priority'    => 25,
		'description' => __( 'Enable or disable optional modules. All are safe to toggle — templates fail gracefully.', 'product-reviews' ),
	) );

	$toggles = array(
		'pr_feat_deals_box'      => array( 'label' => 'Live deals box',              'default' => 1 ),
		'pr_feat_exit_intent'    => array( 'label' => 'Exit-intent deal popup',      'default' => 1 ),
		'pr_feat_mobile_cta'     => array( 'label' => 'Mobile sticky "View Deal"',   'default' => 1 ),
		'pr_feat_email_alerts'   => array( 'label' => 'Price-drop email alerts',     'default' => 1 ),
		'pr_feat_faq_schema'     => array( 'label' => 'FAQ / HowTo schema',          'default' => 1 ),
		'pr_feat_price_history'  => array( 'label' => 'Price history sparkline',     'default' => 1 ),
		'pr_feat_related'        => array( 'label' => 'Related / more options grid', 'default' => 1 ),
		'pr_feat_final_verdict'  => array( 'label' => 'Final verdict block',         'default' => 1 ),
		'pr_feat_toc'            => array( 'label' => 'Table of contents',           'default' => 1 ),
	);
	foreach ( $toggles as $id => $cfg ) {
		$wp_customize->add_setting( $id, array(
			'default'           => $cfg['default'],
			'sanitize_callback' => 'absint',
		) );
		$wp_customize->add_control( $id, array(
			'label'   => __( $cfg['label'], 'product-reviews' ),
			'section' => 'pr_features',
			'type'    => 'checkbox',
		) );
	}

	/* ----- Price display safety (matches React PriceVisibilityToggle) ----- */
	$wp_customize->add_section( 'pr_price_display', array(
		'title'       => __( 'Price Display Safety', 'product-reviews' ),
		'priority'    => 27,
		'description' => __( 'Amazon policy discourages showing exact prices that can go stale. Symbols ($, $$, $$$, $$$$) show a range without misleading buyers.', 'product-reviews' ),
	) );
	$wp_customize->add_setting( 'pr_hide_prices', array( 'default' => 1, 'sanitize_callback' => 'absint' ) );
	$wp_customize->add_control( 'pr_hide_prices', array(
		'label'       => __( 'Hide exact prices site-wide (show $ symbols)', 'product-reviews' ),
		'section'     => 'pr_price_display',
		'type'        => 'checkbox',
		'description' => __( 'Recommended by Amazon Associates Operating Agreement.', 'product-reviews' ),
	) );
	$wp_customize->add_setting( 'pr_show_deal_prices', array( 'default' => 1, 'sanitize_callback' => 'absint' ) );
	$wp_customize->add_control( 'pr_show_deal_prices', array(
		'label'       => __( 'Show exact price inside "Deal" boxes', 'product-reviews' ),
		'section'     => 'pr_price_display',
		'type'        => 'checkbox',
		'description' => __( 'Deal urgency reads better with a real price. Countdown implies it is time-sensitive.', 'product-reviews' ),
	) );

	/* ----- Social / entity graph ----- */
	$wp_customize->add_section( 'pr_social', array(
		'title'    => __( 'Social Profiles (sameAs)', 'product-reviews' ),
		'priority' => 28,
		'description' => __( 'Used in Organization JSON-LD to build the knowledge graph.', 'product-reviews' ),
	) );
	foreach ( array(
		'pr_social_twitter'   => 'X / Twitter URL',
		'pr_social_facebook'  => 'Facebook URL',
		'pr_social_instagram' => 'Instagram URL',
		'pr_social_youtube'   => 'YouTube URL',
		'pr_social_pinterest' => 'Pinterest URL',
		'pr_social_linkedin'  => 'LinkedIn URL',
	) as $id => $label ) {
		$wp_customize->add_setting( $id, array( 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
		$wp_customize->add_control( $id, array(
			'label'   => __( $label, 'product-reviews' ),
			'section' => 'pr_social',
			'type'    => 'url',
		) );
	}
}
add_action( 'customize_register', 'pr_customize_register_features' );

/**
 * Public helper — check a feature flag from anywhere.
 * Templates should wrap optional sections with: if ( pr_feature( 'deals_box' ) ) { ... }
 */
function pr_feature( string $key ): bool {
	return (bool) get_theme_mod( 'pr_feat_' . $key, 1 );
}

/**
 * Public helper — should we show the exact price at this surface?
 * $context = 'default' | 'deal'
 */
function pr_show_price( string $context = 'default' ): bool {
	$hide = (bool) get_theme_mod( 'pr_hide_prices', 1 );
	if ( ! $hide ) return true;
	if ( $context === 'deal' ) return (bool) get_theme_mod( 'pr_show_deal_prices', 1 );
	return false;
}

/**
 * Public helper — convert an exact price to a symbol tier.
 */
function pr_price_symbol( $price ): string {
	$p = (float) $price;
	if ( $p <= 0 )   return '';
	if ( $p < 15 )   return '$';
	if ( $p < 50 )   return '$$';
	if ( $p < 150 )  return '$$$';
	return '$$$$';
}

/**
 * Public helper — render either the exact price or its symbol tier.
 */
function pr_price_display( $price, string $context = 'default' ): string {
	if ( pr_show_price( $context ) ) {
		return '$' . number_format( (float) $price, 2 );
	}
	return pr_price_symbol( $price );
}

