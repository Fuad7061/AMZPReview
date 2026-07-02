<?php
/**
 * Author / Reviewer E-E-A-T bylines.
 *
 * Adds a compact author card to single reviews and emits Person JSON-LD for
 * the post author plus an optional reviewer. Everything degrades to an empty
 * string when data is missing so the surrounding layout never breaks.
 *
 * Data sources (all optional):
 *   - WP user: display_name, user_email, description, profile picture
 *   - WP user meta: pr_author_title, pr_author_credentials, pr_author_sameas
 *     (sameas = newline-separated URLs)
 *   - Post meta: _pr_reviewer_id (WP user ID of an expert reviewer)
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collect normalized author data for a user ID.
 *
 * @return array{name:string,title:string,bio:string,url:string,avatar:string,credentials:string,sameas:array}|null
 */
function pr_author_data( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return null;
	}
	$u = get_userdata( $user_id );
	if ( ! $u ) {
		return null;
	}
	$sameas_raw = (string) get_user_meta( $user_id, 'pr_author_sameas', true );
	$sameas     = array();
	if ( $sameas_raw ) {
		foreach ( preg_split( '/\r\n|\r|\n/', $sameas_raw ) as $line ) {
			$line = trim( $line );
			if ( $line && filter_var( $line, FILTER_VALIDATE_URL ) ) {
				$sameas[] = $line;
			}
		}
	}
	return array(
		'name'        => $u->display_name ? $u->display_name : $u->user_login,
		'title'       => (string) ( get_user_meta( $user_id, 'pr_author_title', true ) ?: get_user_meta( $user_id, 'pr_job_title', true ) ),
		'bio'         => (string) get_user_meta( $user_id, 'description', true ),
		'url'         => get_author_posts_url( $user_id ),
		'avatar'      => (string) ( get_user_meta( $user_id, 'pr_headshot', true ) ?: get_avatar_url( $user_id, array( 'size' => 96 ) ) ),
		'credentials' => (string) ( get_user_meta( $user_id, 'pr_author_credentials', true ) ?: get_user_meta( $user_id, 'pr_credentials', true ) ),
		'sameas'      => $sameas,
	);
}

/**
 * Render the author card. Returns empty string when no author/bio is present
 * so the template's wrapper collapses cleanly.
 */
if ( ! function_exists( 'pr_render_author_card' ) ) {
function pr_render_author_card( $post_id ) {
	$author_id   = (int) get_post_field( 'post_author', $post_id );
	$author      = pr_author_data( $author_id );
	$reviewer_id = (int) get_post_meta( $post_id, '_pr_reviewer_id', true );
	$reviewer    = $reviewer_id && $reviewer_id !== $author_id ? pr_author_data( $reviewer_id ) : null;

	if ( ! $author || ( empty( $author['bio'] ) && empty( $author['title'] ) && ! $reviewer ) ) {
		return '';
	}

	ob_start();
	?>
	<aside class="pr-author-card" aria-label="About the author">
		<?php if ( ! empty( $author['avatar'] ) ) : ?>
			<img class="pr-author-card__avatar" src="<?php echo esc_url( $author['avatar'] ); ?>" alt="" width="56" height="56" loading="lazy" decoding="async" />
		<?php endif; ?>
		<div class="pr-author-card__body">
			<p class="pr-author-card__line">
				<span class="pr-author-card__label">Written by</span>
				<a class="pr-author-card__name" href="<?php echo esc_url( $author['url'] ); ?>" rel="author"><?php echo esc_html( $author['name'] ); ?></a>
				<?php if ( $author['title'] ) : ?>
					<span class="pr-author-card__title">· <?php echo esc_html( $author['title'] ); ?></span>
				<?php endif; ?>
			</p>
			<?php if ( $reviewer ) : ?>
				<p class="pr-author-card__line pr-author-card__line--reviewer">
					<span class="pr-author-card__label">Reviewed by</span>
					<a class="pr-author-card__name" href="<?php echo esc_url( $reviewer['url'] ); ?>"><?php echo esc_html( $reviewer['name'] ); ?></a>
					<?php if ( $reviewer['title'] ) : ?>
						<span class="pr-author-card__title">· <?php echo esc_html( $reviewer['title'] ); ?></span>
					<?php endif; ?>
				</p>
			<?php endif; ?>
			<?php if ( $author['bio'] ) : ?>
				<p class="pr-author-card__bio"><?php echo esc_html( wp_trim_words( $author['bio'], 40, '…' ) ); ?></p>
			<?php endif; ?>
			<?php if ( $author['credentials'] ) : ?>
				<p class="pr-author-card__credentials"><?php echo esc_html( $author['credentials'] ); ?></p>
			<?php endif; ?>
		</div>
	</aside>
	<?php
	return (string) ob_get_clean();
}
}

/**
 * Minimal styling. Inlined so the card never appears unstyled and the file
 * stays self-contained for users who don't run the full main.css build.
 */
function pr_author_card_styles() {
	if ( ! is_singular( 'review' ) ) {
		return;
	}
	echo "<style id='pr-author-card-css'>"
		. ".pr-author-card{display:flex;gap:14px;align-items:flex-start;margin:18px 0;padding:14px 16px;border:1px solid var(--pr-border,#e5e7eb);border-radius:12px;background:var(--pr-surface,#fafafa)}"
		. ".pr-author-card__avatar{width:56px;height:56px;border-radius:50%;flex:0 0 auto;object-fit:cover}"
		. ".pr-author-card__body{font-size:14px;line-height:1.5;color:var(--pr-text,#222)}"
		. ".pr-author-card__line{margin:0 0 2px}"
		. ".pr-author-card__label{color:var(--pr-muted,#6b7280);margin-right:4px}"
		. ".pr-author-card__name{font-weight:600;text-decoration:none;color:inherit;border-bottom:1px dotted currentColor}"
		. ".pr-author-card__title{color:var(--pr-muted,#6b7280)}"
		. ".pr-author-card__bio{margin:6px 0 0;color:var(--pr-muted,#4b5563)}"
		. ".pr-author-card__credentials{margin:4px 0 0;font-size:12px;color:var(--pr-muted,#6b7280)}"
		. "</style>";
}
add_action( 'wp_head', 'pr_author_card_styles', 70 );

/**
 * Expose the author profile fields in WP's user edit screen.
 */
function pr_author_user_fields( $user ) {
	if ( ! current_user_can( 'edit_user', $user->ID ) ) {
		return;
	}
	$title       = (string) get_user_meta( $user->ID, 'pr_author_title', true );
	$credentials = (string) get_user_meta( $user->ID, 'pr_author_credentials', true );
	$sameas      = (string) get_user_meta( $user->ID, 'pr_author_sameas', true );
	?>
	<h2><?php esc_html_e( 'E-E-A-T profile', 'product-reviews' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="pr_author_title"><?php esc_html_e( 'Title / role', 'product-reviews' ); ?></label></th>
			<td><input type="text" id="pr_author_title" name="pr_author_title" value="<?php echo esc_attr( $title ); ?>" class="regular-text" placeholder="Senior Reviews Editor" /></td>
		</tr>
		<tr>
			<th><label for="pr_author_credentials"><?php esc_html_e( 'Credentials', 'product-reviews' ); ?></label></th>
			<td><input type="text" id="pr_author_credentials" name="pr_author_credentials" value="<?php echo esc_attr( $credentials ); ?>" class="regular-text" placeholder="12 years testing kitchen gear; published in Wired, NYT Wirecutter" /></td>
		</tr>
		<tr>
			<th><label for="pr_author_sameas"><?php esc_html_e( 'Profile URLs (sameAs)', 'product-reviews' ); ?></label></th>
			<td>
				<textarea id="pr_author_sameas" name="pr_author_sameas" rows="4" class="large-text code" placeholder="https://twitter.com/handle&#10;https://linkedin.com/in/handle"><?php echo esc_textarea( $sameas ); ?></textarea>
				<p class="description"><?php esc_html_e( 'One URL per line. Emitted as sameAs in Person schema.', 'product-reviews' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'pr_author_user_fields' );
add_action( 'edit_user_profile', 'pr_author_user_fields' );

function pr_author_user_save( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}
	foreach ( array( 'pr_author_title', 'pr_author_credentials', 'pr_author_sameas' ) as $k ) {
		if ( isset( $_POST[ $k ] ) ) {
			update_user_meta( $user_id, $k, sanitize_textarea_field( wp_unslash( $_POST[ $k ] ) ) );
		}
	}
}
add_action( 'personal_options_update', 'pr_author_user_save' );
add_action( 'edit_user_profile_update', 'pr_author_user_save' );

/**
 * Reviewer picker on the review post — a single select of users who can edit posts.
 */
function pr_reviewer_metabox() {
	add_meta_box( 'pr_reviewer', __( 'Expert reviewer', 'product-reviews' ), 'pr_reviewer_metabox_render', 'review', 'side', 'default' );
}
add_action( 'add_meta_boxes', 'pr_reviewer_metabox' );

function pr_reviewer_metabox_render( $post ) {
	wp_nonce_field( 'pr_reviewer_save', 'pr_reviewer_nonce' );
	$current = (int) get_post_meta( $post->ID, '_pr_reviewer_id', true );
	$users   = get_users( array( 'capability' => 'edit_posts', 'number' => 200, 'orderby' => 'display_name' ) );
	echo '<select name="pr_reviewer_id" style="width:100%"><option value="">' . esc_html__( '— none —', 'product-reviews' ) . '</option>';
	foreach ( $users as $u ) {
		printf(
			'<option value="%d"%s>%s</option>',
			(int) $u->ID,
			selected( $current, (int) $u->ID, false ),
			esc_html( $u->display_name )
		);
	}
	echo '</select>';
	echo '<p class="description">' . esc_html__( 'Optional second byline shown as "Reviewed by".', 'product-reviews' ) . '</p>';
}

function pr_reviewer_save( $post_id ) {
	if ( ! isset( $_POST['pr_reviewer_nonce'] ) || ! wp_verify_nonce( $_POST['pr_reviewer_nonce'], 'pr_reviewer_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$val = isset( $_POST['pr_reviewer_id'] ) ? (int) $_POST['pr_reviewer_id'] : 0;
	if ( $val > 0 ) {
		update_post_meta( $post_id, '_pr_reviewer_id', $val );
	} else {
		delete_post_meta( $post_id, '_pr_reviewer_id' );
	}
}
add_action( 'save_post_review', 'pr_reviewer_save' );

/**
 * Person JSON-LD for author + reviewer. Runs at wp_head priority 65 — after
 * core schema (50) and Organization (60), before any closing tags.
 */
function pr_author_person_jsonld() {
	if ( ! is_singular( 'review' ) ) {
		return;
	}
	$post_id  = get_queried_object_id();
	$nodes    = array();
	$author   = pr_author_data( (int) get_post_field( 'post_author', $post_id ) );
	$reviewer = pr_author_data( (int) get_post_meta( $post_id, '_pr_reviewer_id', true ) );

	foreach ( array( $author, $reviewer ) as $p ) {
		if ( ! $p ) {
			continue;
		}
		$node = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Person',
			'name'     => $p['name'],
			'url'      => $p['url'],
		);
		if ( $p['avatar'] )      { $node['image']       = $p['avatar']; }
		if ( $p['title'] )       { $node['jobTitle']    = $p['title']; }
		if ( $p['bio'] )         { $node['description'] = wp_strip_all_tags( $p['bio'] ); }
		if ( $p['credentials'] ) { $node['knowsAbout']  = $p['credentials']; }
		if ( ! empty( $p['sameas'] ) ) { $node['sameAs'] = array_values( $p['sameas'] ); }
		$nodes[] = $node;
	}

	if ( empty( $nodes ) ) {
		return;
	}
	foreach ( $nodes as $n ) {
		echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $n, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
	}
}
add_action( 'wp_head', 'pr_author_person_jsonld', 65 );
