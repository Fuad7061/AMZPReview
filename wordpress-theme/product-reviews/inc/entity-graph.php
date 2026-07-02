<?php
/**
 * Entity / Organization knowledge graph.
 *
 * Emits Organization + WebSite JSON-LD with sameAs links so search engines
 * can connect the site to its canonical entity profiles (Wikipedia,
 * Wikidata, social, etc.). Designed to be design-safe (head-only output).
 *
 * Settings stored in option 'pr_entity_graph':
 *   - org_name       (string)
 *   - org_legal_name (string)
 *   - org_logo       (URL)
 *   - org_founded    (YYYY)
 *   - org_email      (string)
 *   - org_phone      (string)
 *   - same_as        (array of URLs)
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return saved entity-graph settings with sane defaults.
 *
 * @return array
 */
function pr_entity_settings() {
	$defaults = array(
		'org_name'       => get_bloginfo( 'name' ),
		'org_legal_name' => '',
		'org_logo'       => '',
		'org_founded'    => '',
		'org_email'      => get_bloginfo( 'admin_email' ),
		'org_phone'      => '',
		'same_as'        => array(),
	);
	$saved = get_option( 'pr_entity_graph', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	$out = array_merge( $defaults, $saved );

	if ( ! is_array( $out['same_as'] ) ) {
		$out['same_as'] = array();
	}
	$out['same_as'] = array_values( array_filter( array_map( 'esc_url_raw', $out['same_as'] ) ) );

	return $out;
}

/**
 * Emit Organization + WebSite JSON-LD with sameAs links.
 *
 * Output is head-only (no DOM changes). Runs at wp_head priority 57.
 */
function pr_entity_jsonld() {
	$s = pr_entity_settings();

	$site_url = home_url( '/' );
	$org_id   = trailingslashit( $site_url ) . '#organization';
	$site_id  = trailingslashit( $site_url ) . '#website';

	$org = array(
		'@type' => 'Organization',
		'@id'   => $org_id,
		'name'  => $s['org_name'],
		'url'   => $site_url,
	);
	if ( ! empty( $s['org_legal_name'] ) ) {
		$org['legalName'] = $s['org_legal_name'];
	}
	if ( ! empty( $s['org_founded'] ) ) {
		$org['foundingDate'] = $s['org_founded'];
	}
	if ( ! empty( $s['org_logo'] ) ) {
		$org['logo'] = array(
			'@type' => 'ImageObject',
			'url'   => esc_url_raw( $s['org_logo'] ),
		);
	}
	$contact = array();
	if ( ! empty( $s['org_email'] ) ) {
		$contact['email'] = sanitize_email( $s['org_email'] );
	}
	if ( ! empty( $s['org_phone'] ) ) {
		$contact['telephone'] = preg_replace( '/[^0-9+\-\s().]/', '', $s['org_phone'] );
	}
	if ( ! empty( $contact ) ) {
		$contact['@type']       = 'ContactPoint';
		$contact['contactType'] = 'customer support';
		$org['contactPoint']    = array( $contact );
	}
	if ( ! empty( $s['same_as'] ) ) {
		$org['sameAs'] = $s['same_as'];
	}

	$site = array(
		'@type'     => 'WebSite',
		'@id'       => $site_id,
		'url'       => $site_url,
		'name'      => get_bloginfo( 'name' ),
		'publisher' => array( '@id' => $org_id ),
	);

	$search_url = home_url( '/?s={search_term_string}' );
	$site['potentialAction'] = array(
		'@type'       => 'SearchAction',
		'target'      => array(
			'@type'       => 'EntryPoint',
			'urlTemplate' => $search_url,
		),
		'query-input' => 'required name=search_term_string',
	);

	$graph = array(
		'@context' => 'https://schema.org',
		'@graph'   => array( $org, $site ),
	);

	echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}
add_action( 'wp_head', 'pr_entity_jsonld', 57 );

/**
 * Settings page: Appearance → Entity Graph.
 */
function pr_entity_admin_menu() {
	add_theme_page(
		__( 'Entity Graph', 'product-reviews' ),
		__( 'Entity Graph', 'product-reviews' ),
		'manage_options',
		'pr-entity-graph',
		'pr_entity_admin_page'
	);
}
add_action( 'admin_menu', 'pr_entity_admin_menu' );

function pr_entity_admin_register() {
	register_setting( 'pr_entity_graph_group', 'pr_entity_graph', array(
		'type'              => 'array',
		'sanitize_callback' => 'pr_entity_sanitize',
		'default'           => array(),
	) );
}
add_action( 'admin_init', 'pr_entity_admin_register' );

function pr_entity_sanitize( $in ) {
	$out = array();
	$out['org_name']       = isset( $in['org_name'] ) ? sanitize_text_field( $in['org_name'] ) : '';
	$out['org_legal_name'] = isset( $in['org_legal_name'] ) ? sanitize_text_field( $in['org_legal_name'] ) : '';
	$out['org_logo']       = isset( $in['org_logo'] ) ? esc_url_raw( $in['org_logo'] ) : '';
	$out['org_founded']    = isset( $in['org_founded'] ) ? preg_replace( '/[^0-9\-]/', '', $in['org_founded'] ) : '';
	$out['org_email']      = isset( $in['org_email'] ) ? sanitize_email( $in['org_email'] ) : '';
	$out['org_phone']      = isset( $in['org_phone'] ) ? sanitize_text_field( $in['org_phone'] ) : '';

	$same_as = array();
	if ( isset( $in['same_as'] ) ) {
		$raw = is_array( $in['same_as'] ) ? implode( "\n", $in['same_as'] ) : (string) $in['same_as'];
		foreach ( preg_split( '/\r?\n/', $raw ) as $line ) {
			$line = trim( $line );
			if ( $line !== '' ) {
				$url = esc_url_raw( $line );
				if ( $url ) {
					$same_as[] = $url;
				}
			}
		}
	}
	$out['same_as'] = array_values( array_unique( $same_as ) );

	return $out;
}

function pr_entity_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s = pr_entity_settings();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Entity Graph (Organization + sameAs)', 'product-reviews' ); ?></h1>
		<p><?php esc_html_e( 'These values are emitted as Organization + WebSite JSON-LD in the page head. Add canonical profile URLs (Wikipedia, Wikidata, LinkedIn, Twitter/X, Facebook, etc.) to sameAs — one per line.', 'product-reviews' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'pr_entity_graph_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="pr_eg_name"><?php esc_html_e( 'Organization name', 'product-reviews' ); ?></label></th>
					<td><input id="pr_eg_name" type="text" class="regular-text" name="pr_entity_graph[org_name]" value="<?php echo esc_attr( $s['org_name'] ); ?>"></td>
				</tr>
				<tr>
					<th><label for="pr_eg_legal"><?php esc_html_e( 'Legal name', 'product-reviews' ); ?></label></th>
					<td><input id="pr_eg_legal" type="text" class="regular-text" name="pr_entity_graph[org_legal_name]" value="<?php echo esc_attr( $s['org_legal_name'] ); ?>"></td>
				</tr>
				<tr>
					<th><label for="pr_eg_logo"><?php esc_html_e( 'Logo URL', 'product-reviews' ); ?></label></th>
					<td><input id="pr_eg_logo" type="url" class="regular-text" name="pr_entity_graph[org_logo]" value="<?php echo esc_attr( $s['org_logo'] ); ?>"></td>
				</tr>
				<tr>
					<th><label for="pr_eg_founded"><?php esc_html_e( 'Founding year', 'product-reviews' ); ?></label></th>
					<td><input id="pr_eg_founded" type="text" class="small-text" name="pr_entity_graph[org_founded]" value="<?php echo esc_attr( $s['org_founded'] ); ?>" placeholder="2020"></td>
				</tr>
				<tr>
					<th><label for="pr_eg_email"><?php esc_html_e( 'Contact email', 'product-reviews' ); ?></label></th>
					<td><input id="pr_eg_email" type="email" class="regular-text" name="pr_entity_graph[org_email]" value="<?php echo esc_attr( $s['org_email'] ); ?>"></td>
				</tr>
				<tr>
					<th><label for="pr_eg_phone"><?php esc_html_e( 'Contact phone', 'product-reviews' ); ?></label></th>
					<td><input id="pr_eg_phone" type="text" class="regular-text" name="pr_entity_graph[org_phone]" value="<?php echo esc_attr( $s['org_phone'] ); ?>"></td>
				</tr>
				<tr>
					<th><label for="pr_eg_sameas"><?php esc_html_e( 'sameAs URLs (one per line)', 'product-reviews' ); ?></label></th>
					<td><textarea id="pr_eg_sameas" name="pr_entity_graph[same_as]" rows="8" class="large-text code" placeholder="https://www.wikidata.org/wiki/Q...&#10;https://twitter.com/yourhandle&#10;https://www.linkedin.com/company/..."><?php echo esc_textarea( implode( "\n", $s['same_as'] ) ); ?></textarea></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
