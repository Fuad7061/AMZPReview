<?php
/**
 * Editorial trust signals — Milestone 18.
 *
 * Lightweight, cron-free. Adds:
 *  - Author profile fields (credentials, bio, headshot URL, profile links).
 *  - pr_render_author_card() — byline + bio + "Tested by" badge.
 *  - Person + Review schema enrichment via filter (author.knowsAbout, jobTitle,
 *    sameAs, plus dateModified / reviewedBy on existing review JSON-LD if any).
 *  - Auto methodology link injection into the disclosure block.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ------------------------------------------------------------------ *
 * 1. Extra user profile fields (credentials, jobTitle, profile links)
 * ------------------------------------------------------------------ */
add_action( 'show_user_profile', 'pr_author_extra_fields', 20 );
add_action( 'edit_user_profile', 'pr_author_extra_fields', 20 );
function pr_author_extra_fields( $user ): void {
	$fields = array(
		'pr_job_title'   => array( 'Job title',          'e.g. Senior Editor, Sleep Specialist' ),
		'pr_credentials' => array( 'Credentials',        'Comma-separated, e.g. CPT, MSc Nutrition' ),
		'pr_expertise'   => array( 'Areas of expertise', 'Comma-separated, e.g. mattresses, kitchen tools' ),
		'pr_headshot'    => array( 'Headshot URL',       'https://…/photo.jpg' ),
		'pr_tested_by'   => array( 'Tested by line',     'Defaults to display name. Shown on review pages.' ),
		'pr_profile_x'   => array( 'X / Twitter URL',    '' ),
		'pr_profile_li'  => array( 'LinkedIn URL',       '' ),
		'pr_profile_ig'  => array( 'Instagram URL',      '' ),
	);
	echo '<h2>Editorial profile</h2><table class="form-table">';
	foreach ( $fields as $key => $meta ) {
		$val = esc_attr( (string) get_user_meta( $user->ID, $key, true ) );
		printf(
			'<tr><th><label for="%1$s">%2$s</label></th><td><input type="text" name="%1$s" id="%1$s" value="%3$s" class="regular-text"><p class="description">%4$s</p></td></tr>',
			esc_attr( $key ), esc_html( $meta[0] ), $val, esc_html( $meta[1] )
		);
	}
	echo '</table>';
}
add_action( 'personal_options_update', 'pr_author_save_fields' );
add_action( 'edit_user_profile_update', 'pr_author_save_fields' );
function pr_author_save_fields( $user_id ): void {
	if ( ! current_user_can( 'edit_user', $user_id ) ) { return; }
	foreach ( array( 'pr_job_title','pr_credentials','pr_expertise','pr_headshot','pr_tested_by','pr_profile_x','pr_profile_li','pr_profile_ig' ) as $k ) {
		if ( isset( $_POST[ $k ] ) ) {
			update_user_meta( $user_id, $k, sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) );
		}
	}
}

/* ------------------------------------------------------------------ *
 * 2. Render the author card (byline + "Tested by" badge + bio link)
 * ------------------------------------------------------------------ */
if ( ! function_exists( 'pr_render_author_card' ) ) {
function pr_render_author_card( int $post_id ): string {
	$uid = (int) get_post_field( 'post_author', $post_id );
	if ( ! $uid ) { return ''; }
	$name        = get_the_author_meta( 'display_name', $uid );
	$job         = (string) get_user_meta( $uid, 'pr_job_title', true );
	$creds       = (string) get_user_meta( $uid, 'pr_credentials', true );
	$expertise   = (string) get_user_meta( $uid, 'pr_expertise', true );
	$headshot    = (string) get_user_meta( $uid, 'pr_headshot', true );
	$tested_by   = (string) get_user_meta( $uid, 'pr_tested_by', true );
	$bio         = (string) get_the_author_meta( 'description', $uid );
	$author_url  = get_author_posts_url( $uid );
	$tested_line = $tested_by !== '' ? $tested_by : $name;

	$avatar = $headshot
		? '<img src="' . esc_url( $headshot ) . '" alt="" class="pr-author__avatar" loading="lazy" decoding="async" width="56" height="56">'
		: get_avatar( $uid, 56, '', '', array( 'class' => 'pr-author__avatar' ) );

	$tested = '<span class="pr-tested" title="Hands-on testing by the author"><svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path fill="currentColor" d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/></svg> Tested by ' . esc_html( $tested_line ) . '</span>';

	$method_url = function_exists( 'pr_methodology_url' ) ? pr_methodology_url() : home_url( '/methodology/' );

	$html  = '<aside class="pr-author" itemscope itemtype="https://schema.org/Person">';
	$html .= '<div class="pr-author__head">' . $avatar;
	$html .= '<div class="pr-author__id">';
	$html .= '<a class="pr-author__name" href="' . esc_url( $author_url ) . '" itemprop="url"><span itemprop="name">' . esc_html( $name ) . '</span></a>';
	if ( $job !== '' )   { $html .= '<span class="pr-author__job" itemprop="jobTitle">' . esc_html( $job ) . '</span>'; }
	if ( $creds !== '' ) { $html .= '<span class="pr-author__creds">' . esc_html( $creds ) . '</span>'; }
	$html .= '</div></div>';
	$html .= '<div class="pr-author__signals">' . $tested;
	$html .= ' · <a class="pr-author__method" href="' . esc_url( $method_url ) . '">How we test</a>';
	$html .= '</div>';
	if ( $bio !== '' ) {
		$html .= '<p class="pr-author__bio" itemprop="description">' . esc_html( wp_trim_words( $bio, 50, '…' ) ) . '</p>';
	}
	if ( $expertise !== '' ) {
		$html .= '<p class="pr-author__expertise"><strong>Specialty:</strong> ' . esc_html( $expertise ) . '</p>';
	}
	$html .= '</aside>';
	return $html;
}
}

/* ------------------------------------------------------------------ *
 * 3. JSON-LD enrichment — augment Review/Article with reviewedBy + author
 * ------------------------------------------------------------------ */
add_filter( 'pr_schema_review', 'pr_schema_trust_enrich', 20, 2 );
add_filter( 'pr_schema_article', 'pr_schema_trust_enrich', 20, 2 );
function pr_schema_trust_enrich( $schema, $post_id ) {
	if ( ! is_array( $schema ) ) { return $schema; }
	$uid = (int) get_post_field( 'post_author', $post_id );
	if ( ! $uid ) { return $schema; }
	$person = array(
		'@type' => 'Person',
		'name'  => get_the_author_meta( 'display_name', $uid ),
		'url'   => get_author_posts_url( $uid ),
	);
	$job  = (string) get_user_meta( $uid, 'pr_job_title', true );
	$exp  = (string) get_user_meta( $uid, 'pr_expertise', true );
	$head = (string) get_user_meta( $uid, 'pr_headshot', true );
	$same = array_filter( array(
		(string) get_user_meta( $uid, 'pr_profile_x', true ),
		(string) get_user_meta( $uid, 'pr_profile_li', true ),
		(string) get_user_meta( $uid, 'pr_profile_ig', true ),
	) );
	if ( $job !== '' )  { $person['jobTitle']  = $job; }
	if ( $exp !== '' )  { $person['knowsAbout'] = array_map( 'trim', explode( ',', $exp ) ); }
	if ( $head !== '' ) { $person['image']     = $head; }
	if ( ! empty( $same ) ) { $person['sameAs'] = array_values( $same ); }

	$schema['author']     = $person;
	$schema['reviewedBy'] = $person;
	$schema['dateModified'] = get_the_modified_date( DATE_W3C, $post_id );
	return $schema;
}

/* ------------------------------------------------------------------ *
 * 4. Append methodology link to the disclosure block automatically
 * ------------------------------------------------------------------ */
add_filter( 'option_yadfood_disclosure', function ( $value ) {
	if ( ! is_string( $value ) || $value === '' ) { return $value; }
	if ( stripos( $value, 'methodology' ) !== false || stripos( $value, '/methodology' ) !== false ) {
		return $value;
	}
	$url = function_exists( 'pr_methodology_url' ) ? pr_methodology_url() : home_url( '/methodology/' );
	return rtrim( $value ) . ' <a class="pr-method-link" href="' . esc_url( $url ) . '">See our methodology →</a>';
} );

/* ------------------------------------------------------------------ *
 * 5. Styles
 * ------------------------------------------------------------------ */
add_action( 'wp_head', function () {
	?>
	<style id="pr-trust-css">
	.pr-author{margin:1.25rem 0;padding:1rem 1.1rem;border:1px solid var(--pr-border,#e5e7eb);border-radius:.75rem;background:var(--pr-surface,#fff)}
	.pr-author__head{display:flex;align-items:center;gap:.85rem}
	.pr-author__avatar{width:56px;height:56px;border-radius:50%;object-fit:cover;flex:0 0 auto}
	.pr-author__id{display:flex;flex-direction:column;gap:.1rem;min-width:0}
	.pr-author__name{font-weight:700;color:inherit;text-decoration:none}
	.pr-author__name:hover{text-decoration:underline}
	.pr-author__job{font-size:.85rem;color:var(--pr-muted,#6b7280)}
	.pr-author__creds{font-size:.75rem;color:var(--pr-muted,#6b7280);text-transform:uppercase;letter-spacing:.04em}
	.pr-author__signals{margin-top:.65rem;font-size:.85rem;color:var(--pr-muted,#6b7280);display:flex;flex-wrap:wrap;gap:.4rem;align-items:center}
	.pr-author__method{color:inherit;text-decoration:underline;text-underline-offset:2px}
	.pr-author__bio{margin:.7rem 0 0;font-size:.92rem;line-height:1.55;color:var(--pr-text,#374151)}
	.pr-author__expertise{margin:.4rem 0 0;font-size:.85rem;color:var(--pr-muted,#6b7280)}
	.pr-tested{display:inline-flex;align-items:center;gap:.3rem;background:#dcfce7;color:#14532d;border:1px solid #86efac;padding:.2rem .55rem;border-radius:999px;font-weight:600;font-size:.78rem}
	.pr-method-link{margin-left:.35rem;font-weight:600;text-decoration:underline}
	</style>
	<?php
} );
