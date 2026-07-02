<?php
/**
 * Review-quality signals: testing methodology block, hands-on evidence,
 * and pros/cons enrichment for Product schema (positiveNotes / negativeNotes).
 *
 * Defensive: every render checks for data and returns nothing when empty,
 * so missing fields never break the existing single-review design.
 *
 * @package YadFoodReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ------------------------------------------------------------------ */
/* Per-post meta: testing methodology, hours tested, units tested.    */
/* ------------------------------------------------------------------ */
function pr_rq_register_meta() {
	$str = array( '_pr_methodology', '_pr_test_summary', '_pr_test_location' );
	foreach ( $str as $k ) {
		register_post_meta( 'review', $k, array(
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'string',
			'auth_callback' => function () { return current_user_can( 'edit_posts' ); },
		) );
	}
	$num = array( '_pr_hours_tested', '_pr_units_tested' );
	foreach ( $num as $k ) {
		register_post_meta( 'review', $k, array(
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'number',
			'auth_callback' => function () { return current_user_can( 'edit_posts' ); },
		) );
	}
}
add_action( 'init', 'pr_rq_register_meta' );

/* ------------------------------------------------------------------ */
/* Admin meta box for the methodology fields (side, low priority).    */
/* ------------------------------------------------------------------ */
function pr_rq_meta_box() {
	add_meta_box(
		'pr_review_quality',
		__( 'Testing & Methodology', 'product-reviews' ),
		'pr_rq_render_box',
		'review',
		'side',
		'low'
	);
}
add_action( 'add_meta_boxes', 'pr_rq_meta_box' );

function pr_rq_render_box( $post ) {
	wp_nonce_field( 'pr_rq_save', 'pr_rq_nonce' );
	$m = function( $k ) use ( $post ) { return esc_attr( get_post_meta( $post->ID, $k, true ) ); };
	?>
	<p><label>Methodology (1–3 sentences)<br>
		<textarea name="_pr_methodology" rows="3" style="width:100%;"><?php echo $m( '_pr_methodology' ); ?></textarea>
	</label></p>
	<p><label>Test summary (what we measured)<br>
		<textarea name="_pr_test_summary" rows="2" style="width:100%;"><?php echo $m( '_pr_test_summary' ); ?></textarea>
	</label></p>
	<p><label>Test location<br>
		<input type="text" name="_pr_test_location" value="<?php echo $m( '_pr_test_location' ); ?>" style="width:100%;">
	</label></p>
	<p><label>Hours tested
		<input type="number" step="0.5" min="0" name="_pr_hours_tested" value="<?php echo $m( '_pr_hours_tested' ); ?>" style="width:100%;">
	</label></p>
	<p><label>Units tested
		<input type="number" step="1" min="0" name="_pr_units_tested" value="<?php echo $m( '_pr_units_tested' ); ?>" style="width:100%;">
	</label></p>
	<?php
}

function pr_rq_save( $post_id ) {
	if ( ! isset( $_POST['pr_rq_nonce'] ) || ! wp_verify_nonce( $_POST['pr_rq_nonce'], 'pr_rq_save' ) ) return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;

	$map = array(
		'_pr_methodology'   => 'sanitize_textarea_field',
		'_pr_test_summary'  => 'sanitize_textarea_field',
		'_pr_test_location' => 'sanitize_text_field',
		'_pr_hours_tested'  => 'floatval',
		'_pr_units_tested'  => 'intval',
	);
	foreach ( $map as $key => $fn ) {
		if ( isset( $_POST[ $key ] ) && $_POST[ $key ] !== '' ) {
			update_post_meta( $post_id, $key, call_user_func( $fn, wp_unslash( $_POST[ $key ] ) ) );
		} else {
			delete_post_meta( $post_id, $key );
		}
	}
}
add_action( 'save_post_review', 'pr_rq_save' );

/* ------------------------------------------------------------------ */
/* Render: "How we tested" block, injected on single reviews above    */
/* the_content. Returns nothing if no methodology data is present.    */
/* ------------------------------------------------------------------ */
function pr_rq_has_data( $post_id ) {
	foreach ( array( '_pr_methodology', '_pr_test_summary', '_pr_test_location', '_pr_hours_tested', '_pr_units_tested' ) as $k ) {
		$v = get_post_meta( $post_id, $k, true );
		if ( $v !== '' && $v !== null && $v !== false && $v !== '0' ) return true;
	}
	return false;
}

function pr_rq_render( $post_id ) {
	if ( ! pr_rq_has_data( $post_id ) ) return '';
	$method   = get_post_meta( $post_id, '_pr_methodology', true );
	$summary  = get_post_meta( $post_id, '_pr_test_summary', true );
	$location = get_post_meta( $post_id, '_pr_test_location', true );
	$hours    = (float) get_post_meta( $post_id, '_pr_hours_tested', true );
	$units    = (int)   get_post_meta( $post_id, '_pr_units_tested', true );

	$stats = array();
	if ( $hours > 0 )            $stats[] = sprintf( _n( '%s hour tested', '%s hours tested', $hours, 'product-reviews' ), number_format_i18n( $hours, ( $hours == (int) $hours ? 0 : 1 ) ) );
	if ( $units > 0 )            $stats[] = sprintf( _n( '%d unit evaluated', '%d units evaluated', $units, 'product-reviews' ), $units );
	if ( $location )             $stats[] = esc_html( $location );

	ob_start(); ?>
	<aside class="pr-methodology" aria-label="<?php esc_attr_e( 'How we tested', 'product-reviews' ); ?>">
		<h2 class="pr-methodology__title"><?php esc_html_e( 'How we tested', 'product-reviews' ); ?></h2>
		<?php if ( $method ) : ?>
			<p class="pr-methodology__lede"><?php echo esc_html( $method ); ?></p>
		<?php endif; ?>
		<?php if ( $summary ) : ?>
			<p class="pr-methodology__summary"><?php echo esc_html( $summary ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $stats ) ) : ?>
			<ul class="pr-methodology__stats">
				<?php foreach ( $stats as $s ) : ?><li><?php echo $s; // already-escaped ?></li><?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</aside>
	<?php
	return ob_get_clean();
}

/* Hook into the_content on single reviews, after the cluster spoke chip (prio 9). */
function pr_rq_inject_content( $content ) {
	if ( ! is_singular( 'review' ) || ! in_the_loop() || ! is_main_query() ) return $content;
	$block = pr_rq_render( get_the_ID() );
	if ( ! $block ) return $content;
	return $block . $content;
}
add_filter( 'the_content', 'pr_rq_inject_content', 11 );

/* ------------------------------------------------------------------ */
/* CSS — inline, only when needed.                                    */
/* ------------------------------------------------------------------ */
function pr_rq_styles() {
	if ( ! is_singular( 'review' ) ) return;
	if ( ! pr_rq_has_data( get_queried_object_id() ) ) return;
	?>
	<style id="pr-rq-css">
		.pr-methodology{border:1px solid var(--yf-border,#e5e7eb);background:var(--yf-surface,#fafafa);border-radius:12px;padding:18px 20px;margin:18px 0 24px;}
		.pr-methodology__title{margin:0 0 8px;font-size:1.05rem;font-weight:700;}
		.pr-methodology__lede{margin:0 0 6px;}
		.pr-methodology__summary{margin:0 0 10px;color:var(--yf-muted,#555);}
		.pr-methodology__stats{display:flex;flex-wrap:wrap;gap:8px;list-style:none;padding:0;margin:6px 0 0;}
		.pr-methodology__stats li{background:var(--yf-chip,#eef2ff);color:var(--yf-chip-fg,#1f2937);font-size:.82rem;font-weight:600;padding:4px 10px;border-radius:999px;}
	</style>
	<?php
}
add_action( 'wp_head', 'pr_rq_styles', 72 );

/* ------------------------------------------------------------------ */
/* Schema enrichment: add positiveNotes / negativeNotes (ItemList)    */
/* to every Product node already emitted by inc/schema.php, derived   */
/* from each product's pros/cons. Runs at prio 80 (after eeat=60).    */
/* ------------------------------------------------------------------ */
function pr_rq_pros_cons_jsonld() {
	if ( ! is_singular( 'review' ) ) return;
	$post_id  = get_queried_object_id();
	$products = function_exists( 'yadfood_get_products' ) ? yadfood_get_products( $post_id ) : array();
	if ( empty( $products ) ) return;

	$nodes = array();
	foreach ( $products as $p ) {
		$name = isset( $p['name'] ) ? $p['name'] : ( isset( $p['title'] ) ? $p['title'] : '' );
		if ( ! $name ) continue;
		$pros = isset( $p['pros'] ) && is_array( $p['pros'] ) ? array_values( array_filter( array_map( 'strval', $p['pros'] ) ) ) : array();
		$cons = isset( $p['cons'] ) && is_array( $p['cons'] ) ? array_values( array_filter( array_map( 'strval', $p['cons'] ) ) ) : array();
		if ( empty( $pros ) && empty( $cons ) ) continue;

		$node = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Product',
			'name'     => $name,
		);
		$to_itemlist = function( $arr ) {
			$items = array();
			$i = 1;
			foreach ( $arr as $t ) {
				$items[] = array( '@type' => 'ListItem', 'position' => $i++, 'name' => wp_strip_all_tags( $t ) );
			}
			return array( '@type' => 'ItemList', 'itemListElement' => $items );
		};
		if ( ! empty( $pros ) ) $node['positiveNotes'] = $to_itemlist( $pros );
		if ( ! empty( $cons ) ) $node['negativeNotes'] = $to_itemlist( $cons );
		$nodes[] = $node;
	}
	if ( empty( $nodes ) ) return;
	echo "\n<script type=\"application/ld+json\">" . wp_json_encode( count( $nodes ) === 1 ? $nodes[0] : $nodes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}
add_action( 'wp_head', 'pr_rq_pros_cons_jsonld', 80 );
