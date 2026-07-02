<?php
/**
 * Pros / Cons consolidation.
 *
 * Reads `_pr_pros` and `_pr_cons` post meta (newline- or array-encoded),
 * renders an accessible two-column block, exposes a [pr_pros_cons]
 * shortcode, auto-injects into the_content, and emits positiveNotes /
 * negativeNotes ItemList nodes for the page Review schema via the
 * `pr_review_schema_extra` filter (graceful no-op if filter isn't used).
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalize a pros/cons meta value into a clean string[].
 */
function pr_proscons_items( $post_id, $key ) {
	$raw = get_post_meta( $post_id, $key, true );
	if ( empty( $raw ) ) {
		return array();
	}
	if ( is_string( $raw ) ) {
		$raw = preg_split( '/\r\n|\r|\n/', $raw );
	}
	if ( ! is_array( $raw ) ) {
		return array();
	}
	$out = array();
	foreach ( $raw as $item ) {
		$item = is_scalar( $item ) ? trim( (string) $item ) : '';
		if ( $item !== '' ) {
			$out[] = $item;
		}
	}
	return $out;
}

function pr_proscons_pros( $post_id ) { return pr_proscons_items( $post_id, '_pr_pros' ); }
function pr_proscons_cons( $post_id ) { return pr_proscons_items( $post_id, '_pr_cons' ); }

/**
 * Render the visible pros/cons block.
 */
function pr_proscons_render_html( $post_id = 0 ) {
	$post_id = $post_id ?: get_the_ID();
	if ( ! $post_id ) {
		return '';
	}
	$pros = pr_proscons_pros( $post_id );
	$cons = pr_proscons_cons( $post_id );
	if ( empty( $pros ) && empty( $cons ) ) {
		return '';
	}
	ob_start();
	?>
	<section class="pr-proscons" aria-label="<?php esc_attr_e( 'Pros and cons', 'product-reviews' ); ?>">
		<?php if ( ! empty( $pros ) ) : ?>
		<div class="pr-proscons-col pr-proscons-pros">
			<h3 class="pr-proscons-title"><?php esc_html_e( 'Pros', 'product-reviews' ); ?></h3>
			<ul>
				<?php foreach ( $pros as $p ) : ?>
				<li><?php echo esc_html( $p ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php endif; ?>
		<?php if ( ! empty( $cons ) ) : ?>
		<div class="pr-proscons-col pr-proscons-cons">
			<h3 class="pr-proscons-title"><?php esc_html_e( 'Cons', 'product-reviews' ); ?></h3>
			<ul>
				<?php foreach ( $cons as $c ) : ?>
				<li><?php echo esc_html( $c ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php endif; ?>
	</section>
	<?php
	return (string) ob_get_clean();
}

function pr_proscons_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'pr_pros_cons' );
	return pr_proscons_render_html( (int) $atts['id'] ?: get_the_ID() );
}
add_shortcode( 'pr_pros_cons', 'pr_proscons_shortcode' );

/**
 * Auto-inject before main content on singular reviews/posts/pages.
 */
function pr_proscons_inject_content( $content ) {
	if ( ! is_singular( array( 'review', 'post', 'page' ) ) || ! is_main_query() || ! in_the_loop() ) {
		return $content;
	}
	if ( ! apply_filters( 'pr_proscons_auto_inject', true, get_the_ID() ) ) {
		return $content;
	}
	$html = pr_proscons_render_html( get_the_ID() );
	return $html ? $html . $content : $content;
}
add_filter( 'the_content', 'pr_proscons_inject_content', 12 );

/**
 * Scoped CSS.
 */
function pr_proscons_styles() {
	if ( ! is_singular() ) {
		return;
	}
	?>
	<style id="pr-proscons-css">
	.pr-proscons{display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));margin:1.25rem 0;}
	.pr-proscons-col{border:1px solid rgba(0,0,0,.08);border-radius:.5rem;padding:1rem;}
	.pr-proscons-pros{background:rgba(34,197,94,.06);}
	.pr-proscons-cons{background:rgba(239,68,68,.06);}
	.pr-proscons-title{margin:0 0 .5rem;font-size:1rem;font-weight:600;}
	.pr-proscons ul{margin:0;padding-left:1.1rem;}
	.pr-proscons li{margin:.25rem 0;line-height:1.45;}
	</style>
	<?php
}
add_action( 'wp_head', 'pr_proscons_styles', 95 );

/**
 * Extend Review JSON-LD with positiveNotes / negativeNotes ItemList nodes.
 * Wired via filter so it integrates with whichever schema module emits Review.
 */
function pr_proscons_schema_extra( $node, $post_id ) {
	if ( ! is_array( $node ) ) {
		return $node;
	}
	$pros = pr_proscons_pros( $post_id );
	$cons = pr_proscons_cons( $post_id );
	$to_list = function ( $items ) {
		$elements = array();
		$pos      = 1;
		foreach ( $items as $name ) {
			$elements[] = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'name'     => $name,
			);
		}
		return array(
			'@type'           => 'ItemList',
			'itemListElement' => $elements,
		);
	};
	if ( ! empty( $pros ) ) {
		$node['positiveNotes'] = $to_list( $pros );
	}
	if ( ! empty( $cons ) ) {
		$node['negativeNotes'] = $to_list( $cons );
	}
	return $node;
}
add_filter( 'pr_review_schema_extra', 'pr_proscons_schema_extra', 10, 2 );

/**
 * Admin meta box for editing pros/cons (one per line).
 */
function pr_proscons_register_metabox() {
	foreach ( array( 'review', 'post', 'page' ) as $pt ) {
		add_meta_box(
			'pr_pros_cons',
			__( 'Pros & Cons', 'product-reviews' ),
			'pr_proscons_metabox',
			$pt,
			'normal',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', 'pr_proscons_register_metabox' );

function pr_proscons_metabox( $post ) {
	wp_nonce_field( 'pr_proscons_save', 'pr_proscons_nonce' );
	$pros = implode( "\n", pr_proscons_pros( $post->ID ) );
	$cons = implode( "\n", pr_proscons_cons( $post->ID ) );
	?>
	<p><label for="pr_pros_input"><strong><?php esc_html_e( 'Pros (one per line)', 'product-reviews' ); ?></strong></label></p>
	<textarea id="pr_pros_input" name="pr_pros_input" rows="5" style="width:100%"><?php echo esc_textarea( $pros ); ?></textarea>
	<p><label for="pr_cons_input"><strong><?php esc_html_e( 'Cons (one per line)', 'product-reviews' ); ?></strong></label></p>
	<textarea id="pr_cons_input" name="pr_cons_input" rows="5" style="width:100%"><?php echo esc_textarea( $cons ); ?></textarea>
	<?php
}

function pr_proscons_save( $post_id ) {
	if ( ! isset( $_POST['pr_proscons_nonce'] ) || ! wp_verify_nonce( $_POST['pr_proscons_nonce'], 'pr_proscons_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	foreach ( array( '_pr_pros' => 'pr_pros_input', '_pr_cons' => 'pr_cons_input' ) as $meta => $field ) {
		$val = isset( $_POST[ $field ] ) ? (string) wp_unslash( $_POST[ $field ] ) : '';
		$lines = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $val ) ), 'strlen' ) );
		if ( empty( $lines ) ) {
			delete_post_meta( $post_id, $meta );
		} else {
			update_post_meta( $post_id, $meta, array_map( 'sanitize_text_field', $lines ) );
		}
	}
}
add_action( 'save_post', 'pr_proscons_save' );
