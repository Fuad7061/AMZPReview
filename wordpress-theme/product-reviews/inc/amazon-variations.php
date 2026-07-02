<?php
/**
 * Amazon variations + bundles.
 *
 * Stores lightweight variation/bundle data inside the existing `_yadfood_products`
 * post meta so we don't add a new table.
 *
 * Per-product entries support:
 *   'variations' => [
 *       ['asin' => 'B0...', 'label' => 'Twin', 'price' => 199.99],
 *       ['asin' => 'B0...', 'label' => 'Queen', 'price' => 349.99],
 *   ],
 *   'bundle' => [
 *       ['asin' => 'B0...', 'title' => 'Mattress topper', 'price' => 59.99, 'image' => 'https://m.media-amazon.com/...'],
 *       ...
 *   ],
 *
 * Variations render as a <select>; selecting one swaps the CTA href + price
 * client-side (no AJAX). Bundles render as a "Frequently bought together"
 * mini-list using remote images (no sideloading).
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Variation picker markup. Empty string if no variations.
 *
 * @param array  $product Single entry from _yadfood_products.
 * @param string $slug    Parent review slug (for UTM campaign).
 */
function pr_render_variations( array $product, string $slug = '' ): string {
	$vars = isset( $product['variations'] ) && is_array( $product['variations'] ) ? $product['variations'] : array();
	if ( count( $vars ) < 2 ) { return ''; }

	$options = array();
	foreach ( $vars as $v ) {
		$asin  = isset( $v['asin'] ) ? strtoupper( substr( (string) $v['asin'], 0, 16 ) ) : '';
		$label = isset( $v['label'] ) ? (string) $v['label'] : $asin;
		if ( $asin === '' ) { continue; }
		$url   = function_exists( 'yadfood_amazon_url' ) ? yadfood_amazon_url( $asin, $slug ) : '#';
		$price = isset( $v['price'] ) && is_numeric( $v['price'] )
			? ( function_exists( 'pr_format_price_localized' ) ? pr_format_price_localized( $v['price'] ) : ( '$' . number_format( (float) $v['price'], 2 ) ) )
			: '';
		$options[] = sprintf(
			'<option value="%s" data-asin="%s" data-price="%s">%s%s</option>',
			esc_url( $url ),
			esc_attr( $asin ),
			esc_attr( $price ),
			esc_html( $label ),
			$price !== '' ? ' — ' . esc_html( $price ) : ''
		);
	}
	if ( ! $options ) { return ''; }

	return '<label class="pr-variations"><span class="pr-variations__lbl">' . esc_html__( 'Variant', 'product-reviews' ) . '</span>'
		. '<select class="pr-variations__select" data-pr-variations>' . implode( '', $options ) . '</select></label>';
}

/**
 * Bundle list ("Frequently bought together"). Empty string if no items.
 *
 * @param array  $product Single entry from _yadfood_products.
 * @param string $slug    Parent review slug.
 */
function pr_render_bundle( array $product, string $slug = '' ): string {
	$items = isset( $product['bundle'] ) && is_array( $product['bundle'] ) ? $product['bundle'] : array();
	if ( ! $items ) { return ''; }

	$total = 0.0;
	$rows  = array();
	foreach ( $items as $it ) {
		$asin = isset( $it['asin'] ) ? strtoupper( substr( (string) $it['asin'], 0, 16 ) ) : '';
		if ( $asin === '' ) { continue; }
		$title = isset( $it['title'] ) ? (string) $it['title'] : $asin;
		$img   = isset( $it['image'] ) ? (string) $it['image'] : '';
		$price = isset( $it['price'] ) && is_numeric( $it['price'] ) ? (float) $it['price'] : 0.0;
		$total += $price;
		$url   = function_exists( 'yadfood_amazon_url' ) ? yadfood_amazon_url( $asin, $slug ) : '#';
		$price_fmt = $price > 0
			? ( function_exists( 'pr_format_price_localized' ) ? pr_format_price_localized( $price ) : ( '$' . number_format( $price, 2 ) ) )
			: '';

		$img_html = '';
		if ( $img !== '' ) {
			$img_html = function_exists( 'pr_render_remote_image' )
				? pr_render_remote_image( $img, $title, array( 'class' => 'pr-bundle__img' ) )
				: '<img src="' . esc_url( $img ) . '" alt="' . esc_attr( $title ) . '" loading="lazy" decoding="async">';
		}

		$rows[] = '<li class="pr-bundle__item">'
			. '<a href="' . esc_url( $url ) . '" target="_blank" rel="nofollow sponsored noopener" data-asin="' . esc_attr( $asin ) . '" data-slug="' . esc_attr( $slug ) . '">'
			. $img_html
			. '<span class="pr-bundle__title">' . esc_html( $title ) . '</span>'
			. ( $price_fmt !== '' ? '<span class="pr-bundle__price">' . esc_html( $price_fmt ) . '</span>' : '' )
			. '</a></li>';
	}
	if ( ! $rows ) { return ''; }

	$total_html = '';
	if ( $total > 0 ) {
		$total_fmt = function_exists( 'pr_format_price_localized' ) ? pr_format_price_localized( $total ) : ( '$' . number_format( $total, 2 ) );
		$total_html = '<div class="pr-bundle__total">' . esc_html__( 'Bundle total:', 'product-reviews' ) . ' <strong>' . esc_html( $total_fmt ) . '</strong></div>';
	}

	return '<div class="pr-bundle"><h4 class="pr-bundle__title-h">' . esc_html__( 'Frequently bought together', 'product-reviews' ) . '</h4>'
		. '<ul class="pr-bundle__list">' . implode( '', $rows ) . '</ul>'
		. $total_html . '</div>';
}

/**
 * Tiny inline JS for variation picker. Enqueued once per page.
 */
function pr_variations_inline_js(): void {
	static $done = false;
	if ( $done ) { return; }
	$done = true;
	?>
	<script>
	(function(){
		document.addEventListener('change', function(e){
			var sel = e.target.closest('[data-pr-variations]');
			if(!sel) return;
			var opt = sel.options[sel.selectedIndex];
			var card = sel.closest('.yf-product');
			if(!card) return;
			var cta = card.querySelector('.yf-cta');
			if(cta){ cta.href = opt.value; cta.setAttribute('data-asin', opt.dataset.asin||''); }
			var price = card.querySelector('.yf-product__price');
			if(price && opt.dataset.price){ price.textContent = opt.dataset.price; }
		}, false);
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'pr_variations_inline_js', 99 );
