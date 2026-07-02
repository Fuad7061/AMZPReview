<?php
/**
 * Meta fields for the review CPT.
 *
 * Stores the data the AI generator and templates need:
 *   _yadfood_products  array of product cards (rank, asin, price, image, pros/cons, etc.)
 *   _yadfood_tldr      short TL;DR string
 *   _yadfood_faqs      array of { q, a }
 *   _yadfood_keyword   normalized search query that produced this article
 *   _yadfood_intro     editorial intro block
 *   _yadfood_buyers    buyer's guide bullets
 *
 * Uses ACF if available for a nicer admin UI; falls back to a native meta box.
 *
 * @package YadFoodReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------------------------------------------------------------- */
/* Register meta with REST API so the AI generator can read/write it */
/* ---------------------------------------------------------------- */
function yadfood_register_meta() {
	$keys = array( '_yadfood_keyword', '_yadfood_tldr', '_yadfood_intro' );
	foreach ( $keys as $k ) {
		register_post_meta( 'review', $k, array(
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'string',
			'auth_callback' => function () { return current_user_can( 'edit_posts' ); },
		) );
	}
	$array_keys = array( '_yadfood_products', '_yadfood_faqs', '_yadfood_buyers' );
	foreach ( $array_keys as $k ) {
		register_post_meta( 'review', $k, array(
			'show_in_rest'  => array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ) ),
			'single'        => true,
			'type'          => 'array',
			'auth_callback' => function () { return current_user_can( 'edit_posts' ); },
		) );
	}
}
add_action( 'init', 'yadfood_register_meta' );

/* ---------------------------------------------------------------- */
/* Native fallback meta box (works even without ACF)                */
/* ---------------------------------------------------------------- */
function yadfood_add_meta_boxes() {
	add_meta_box(
		'yadfood_review_data',
		__( 'Review Data', 'yadfood-reviews' ),
		'yadfood_render_review_meta_box',
		'review',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'yadfood_add_meta_boxes' );

function yadfood_render_review_meta_box( $post ) {
	wp_nonce_field( 'yadfood_save_meta', 'yadfood_meta_nonce' );
	$keyword  = yadfood_meta( $post->ID, '_yadfood_keyword' );
	$tldr     = yadfood_meta( $post->ID, '_yadfood_tldr' );
	$intro    = yadfood_meta( $post->ID, '_yadfood_intro' );
	$products = yadfood_get_products( $post->ID );
	$faqs     = yadfood_get_faqs( $post->ID );
	$buyers   = get_post_meta( $post->ID, '_yadfood_buyers', true );
	if ( ! is_array( $buyers ) ) { $buyers = array(); }
	?>
	<style>
		.yf-mb label { display:block; font-weight:600; margin-top:14px; }
		.yf-mb input[type=text], .yf-mb textarea { width:100%; }
		.yf-mb textarea { min-height: 70px; }
		.yf-mb .yf-row { background:#f6f7f7; padding:10px; border:1px solid #ddd; margin:8px 0; border-radius:4px; }
		.yf-mb .yf-actions { margin-top: 12px; }
		.yf-mb button.button { margin-right:6px; }
	</style>
	<div class="yf-mb">
		<p class="description">
			<?php esc_html_e( 'These fields are filled automatically when you click "Generate with AI" in the Auto Generator. You can edit any field by hand here.', 'yadfood-reviews' ); ?>
		</p>

		<label><?php esc_html_e( 'Search Keyword (normalized)', 'yadfood-reviews' ); ?></label>
		<input type="text" name="yadfood_keyword" value="<?php echo esc_attr( $keyword ); ?>">

		<label><?php esc_html_e( 'TL;DR', 'yadfood-reviews' ); ?></label>
		<textarea name="yadfood_tldr"><?php echo esc_textarea( $tldr ); ?></textarea>

		<label><?php esc_html_e( 'Intro paragraph', 'yadfood-reviews' ); ?></label>
		<textarea name="yadfood_intro"><?php echo esc_textarea( $intro ); ?></textarea>

		<label><?php esc_html_e( 'Buyer\'s Guide bullets (JSON array of strings)', 'yadfood-reviews' ); ?></label>
		<textarea name="yadfood_buyers"><?php echo esc_textarea( wp_json_encode( $buyers, JSON_PRETTY_PRINT ) ); ?></textarea>

		<label><?php esc_html_e( 'Products (JSON array)', 'yadfood-reviews' ); ?></label>
		<textarea name="yadfood_products" style="min-height:200px;font-family:monospace;font-size:12px;"><?php echo esc_textarea( wp_json_encode( $products, JSON_PRETTY_PRINT ) ); ?></textarea>

		<label><?php esc_html_e( 'FAQs (JSON array of {q,a})', 'yadfood-reviews' ); ?></label>
		<textarea name="yadfood_faqs" style="min-height:120px;font-family:monospace;font-size:12px;"><?php echo esc_textarea( wp_json_encode( $faqs, JSON_PRETTY_PRINT ) ); ?></textarea>
	</div>
	<?php
}

function yadfood_save_meta( $post_id ) {
	if ( ! isset( $_POST['yadfood_meta_nonce'] ) || ! wp_verify_nonce( $_POST['yadfood_meta_nonce'], 'yadfood_save_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$plain = array( 'yadfood_keyword', 'yadfood_tldr', 'yadfood_intro' );
	foreach ( $plain as $key ) {
		if ( isset( $_POST[ $key ] ) ) {
			update_post_meta( $post_id, '_' . $key, wp_kses_post( wp_unslash( $_POST[ $key ] ) ) );
		}
	}

	$json_keys = array( 'yadfood_products', 'yadfood_faqs', 'yadfood_buyers' );
	foreach ( $json_keys as $key ) {
		if ( isset( $_POST[ $key ] ) ) {
			$raw     = wp_unslash( $_POST[ $key ] );
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				update_post_meta( $post_id, '_' . $key, $decoded );
			}
		}
	}
}
add_action( 'save_post_review', 'yadfood_save_meta' );
