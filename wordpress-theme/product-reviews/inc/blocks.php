<?php
/**
 * Gutenberg blocks — Phase 1 stubs.
 *
 * Three blocks ship with the theme:
 *   yadfood/top-products    — renders top N from a review post
 *   yadfood/comparison      — renders a comparison table
 *   yadfood/pros-cons       — pros/cons two-column block
 *
 * Phase 1 = server-rendered blocks (no React build step required).
 *
 * @package YadFoodReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function yadfood_register_blocks() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	register_block_type( 'yadfood/top-products', array(
		'api_version'     => 3,
		'title'           => __( 'YadFood — Top Products', 'yadfood-reviews' ),
		'category'        => 'widgets',
		'icon'            => 'star-filled',
		'attributes'      => array(
			'reviewId' => array( 'type' => 'number', 'default' => 0 ),
			'count'    => array( 'type' => 'number', 'default' => 5 ),
		),
		'render_callback' => 'yadfood_render_top_products_block',
	) );

	register_block_type( 'yadfood/comparison', array(
		'api_version'     => 3,
		'title'           => __( 'YadFood — Comparison Table', 'yadfood-reviews' ),
		'category'        => 'widgets',
		'icon'            => 'editor-table',
		'attributes'      => array( 'reviewId' => array( 'type' => 'number', 'default' => 0 ) ),
		'render_callback' => 'yadfood_render_comparison_block',
	) );

	register_block_type( 'yadfood/pros-cons', array(
		'api_version'     => 3,
		'title'           => __( 'YadFood — Pros / Cons', 'yadfood-reviews' ),
		'category'        => 'widgets',
		'icon'            => 'list-view',
		'attributes'      => array(
			'pros' => array( 'type' => 'array', 'default' => array() ),
			'cons' => array( 'type' => 'array', 'default' => array() ),
		),
		'render_callback' => 'yadfood_render_pros_cons_block',
	) );
}
add_action( 'init', 'yadfood_register_blocks' );

function yadfood_render_top_products_block( $atts ) {
	$id    = (int) ( $atts['reviewId'] ?? 0 );
	$count = max( 1, min( 10, (int) ( $atts['count'] ?? 5 ) ) );
	if ( ! $id ) {
		return '<p><em>' . esc_html__( 'Select a review post in the block sidebar.', 'yadfood-reviews' ) . '</em></p>';
	}
	$products = array_slice( yadfood_get_products( $id ), 0, $count );
	if ( empty( $products ) ) {
		return '';
	}
	ob_start();
	echo '<div class="yf-block yf-block--top">';
	foreach ( $products as $p ) {
		set_query_var( 'yf_product', $p );
		set_query_var( 'yf_post_id', $id );
		get_template_part( 'template-parts/product-card' );
	}
	echo '</div>';
	return ob_get_clean();
}

function yadfood_render_comparison_block( $atts ) {
	$id = (int) ( $atts['reviewId'] ?? 0 );
	if ( ! $id ) {
		return '<p><em>' . esc_html__( 'Select a review post.', 'yadfood-reviews' ) . '</em></p>';
	}
	$products = yadfood_get_products( $id );
	if ( empty( $products ) ) { return ''; }
	ob_start();
	set_query_var( 'yf_products', $products );
	set_query_var( 'yf_post_id', $id );
	get_template_part( 'template-parts/comparison-table' );
	return ob_get_clean();
}

function yadfood_render_pros_cons_block( $atts ) {
	$pros = isset( $atts['pros'] ) ? (array) $atts['pros'] : array();
	$cons = isset( $atts['cons'] ) ? (array) $atts['cons'] : array();
	ob_start();
	?>
	<div class="yf-proscons">
		<div class="yf-proscons__col yf-proscons__col--pros">
			<h4>Pros</h4>
			<ul><?php foreach ( $pros as $p ) : ?><li><?php echo esc_html( $p ); ?></li><?php endforeach; ?></ul>
		</div>
		<div class="yf-proscons__col yf-proscons__col--cons">
			<h4>Cons</h4>
			<ul><?php foreach ( $cons as $c ) : ?><li><?php echo esc_html( $c ); ?></li><?php endforeach; ?></ul>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
