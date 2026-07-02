<?php
/**
 * Performance + remote-image pipeline.
 *
 * Product images are NEVER sideloaded into the WordPress media library.
 * They are served directly from Amazon's CDN to save hosting storage.
 * This module makes those remote images fast:
 *   - preconnect / dns-prefetch to image CDNs
 *   - native lazy-loading + async decoding
 *   - responsive srcset/sizes generated from Amazon's `_SL{n}_` URL pattern
 *   - async/defer for non-critical scripts
 *   - inline critical CSS for above-the-fold layout
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hosts we ship product/hero images from. Used for preconnect + recognising
 * which URLs are safe to rewrite into an Amazon srcset.
 */
function pr_remote_image_hosts() {
	return array(
		'm.media-amazon.com',
		'images-na.ssl-images-amazon.com',
		'images-eu.ssl-images-amazon.com',
		'images-fe.ssl-images-amazon.com',
		'images-cn.ssl-images-amazon.com',
	);
}

/**
 * Add resource hints for the image CDNs we use.
 */
function pr_resource_hints( $urls, $type ) {
	if ( 'preconnect' === $type ) {
		foreach ( pr_remote_image_hosts() as $host ) {
			$urls[] = array(
				'href'        => 'https://' . $host,
				'crossorigin' => 'anonymous',
			);
		}
	}
	if ( 'dns-prefetch' === $type ) {
		foreach ( pr_remote_image_hosts() as $host ) {
			$urls[] = '//' . $host;
		}
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'pr_resource_hints', 10, 2 );

/**
 * Detect Amazon CDN image URLs (m.media-amazon.com / *.ssl-images-amazon.com).
 */
function pr_is_amazon_image_url( $url ) {
	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( ! $host ) {
		return false;
	}
	return in_array( $host, pr_remote_image_hosts(), true );
}

/**
 * Rewrite an Amazon image URL to a given target width using the `_SL{n}_`
 * or `_AC_SL{n}_` token Amazon honours on its CDN. Returns the original URL
 * if the host or filename doesn't match the expected pattern.
 *
 * Example: .../I/71abc._AC_SL1500_.jpg  →  width 800 →  .../I/71abc._AC_SL800_.jpg
 *          .../I/71abc.jpg              →  width 800 →  .../I/71abc._SL800_.jpg
 */
function pr_amazon_image_at_width( $url, $width ) {
	if ( ! pr_is_amazon_image_url( $url ) ) {
		return $url;
	}
	$width = (int) $width;
	if ( $width <= 0 ) {
		return $url;
	}

	$parts = wp_parse_url( $url );
	if ( empty( $parts['path'] ) ) {
		return $url;
	}

	$path = $parts['path'];
	$dir  = '';
	$file = $path;
	$pos  = strrpos( $path, '/' );
	if ( false !== $pos ) {
		$dir  = substr( $path, 0, $pos + 1 );
		$file = substr( $path, $pos + 1 );
	}

	$dot = strrpos( $file, '.' );
	if ( false === $dot ) {
		return $url;
	}
	$base = substr( $file, 0, $dot );
	$ext  = substr( $file, $dot ); // includes leading dot

	// Strip any existing size tokens like ._SL1500_, ._AC_SL1500_, ._SX300_, ._SY300_, ._UX385_.
	$base = preg_replace( '/\._(AC_)?(SL|SX|SY|UX|UY|UL|CR[0-9,]+)[0-9]+_/i', '', $base );
	$base = preg_replace( '/\._AC_/', '', $base );

	$new_file = $base . '._AC_SL' . $width . '_' . $ext;
	$new_path = $dir . $new_file;

	$rebuilt = ( isset( $parts['scheme'] ) ? $parts['scheme'] : 'https' ) . '://' . $parts['host'] . $new_path;
	if ( ! empty( $parts['query'] ) ) {
		$rebuilt .= '?' . $parts['query'];
	}
	return $rebuilt;
}

/**
 * Build a srcset string for an Amazon image across common breakpoints.
 *
 * @param string $url    Original Amazon CDN URL.
 * @param array  $widths Widths (px) to include.
 * @return string srcset value, or empty string if not an Amazon URL.
 */
function pr_amazon_image_srcset( $url, $widths = array( 240, 480, 800, 1200, 1600 ) ) {
	if ( ! pr_is_amazon_image_url( $url ) ) {
		return '';
	}
	$set = array();
	foreach ( $widths as $w ) {
		$set[] = pr_amazon_image_at_width( $url, $w ) . ' ' . (int) $w . 'w';
	}
	return implode( ', ', $set );
}

/**
 * Render an <img> for a remote product image with srcset/sizes/lazy/async.
 *
 * @param string $url      Source URL.
 * @param string $alt      Alt text.
 * @param array  $args     class, sizes, eager (bool), width, height.
 */
function pr_render_remote_image( $url, $alt = '', $args = array() ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}

	$defaults = array(
		'class'  => '',
		'sizes'  => '(max-width: 600px) 90vw, (max-width: 1024px) 45vw, 480px',
		'eager'  => false,
		'width'  => 0,
		'height' => 0,
	);
	$args = wp_parse_args( $args, $defaults );

	$srcset = pr_amazon_image_srcset( $url );
	$loading = $args['eager'] ? 'eager' : 'lazy';
	$fetch   = $args['eager'] ? 'high' : 'low';

	$attrs  = 'src="' . esc_url( pr_is_amazon_image_url( $url ) ? pr_amazon_image_at_width( $url, 800 ) : $url ) . '"';
	$attrs .= ' alt="' . esc_attr( $alt ) . '"';
	$attrs .= ' loading="' . esc_attr( $loading ) . '"';
	$attrs .= ' decoding="async"';
	$attrs .= ' fetchpriority="' . esc_attr( $fetch ) . '"';
	$attrs .= ' referrerpolicy="no-referrer-when-downgrade"';
	if ( $srcset ) {
		$attrs .= ' srcset="' . esc_attr( $srcset ) . '"';
		$attrs .= ' sizes="' . esc_attr( $args['sizes'] ) . '"';
	}
	if ( $args['width'] )  $attrs .= ' width="' . (int) $args['width'] . '"';
	if ( $args['height'] ) $attrs .= ' height="' . (int) $args['height'] . '"';
	if ( $args['class'] )  $attrs .= ' class="' . esc_attr( $args['class'] ) . '"';

	return '<img ' . $attrs . '>';
}

/**
 * Defer non-critical scripts. Keep jQuery and admin scripts untouched.
 */
function pr_defer_scripts( $tag, $handle ) {
	if ( is_admin() ) {
		return $tag;
	}
	$skip = array( 'jquery', 'jquery-core', 'jquery-migrate', 'wp-polyfill' );
	if ( in_array( $handle, $skip, true ) ) {
		return $tag;
	}
	if ( false !== strpos( $tag, ' defer' ) || false !== strpos( $tag, ' async' ) ) {
		return $tag;
	}
	return preg_replace( '/<script /', '<script defer ', $tag, 1 );
}
add_filter( 'script_loader_tag', 'pr_defer_scripts', 10, 2 );

/**
 * Inline a tiny critical-CSS payload for above-the-fold layout. The full
 * stylesheet still loads via the normal enqueue so unstyled flash is bounded.
 */
function pr_inline_critical_css() {
	if ( is_admin() ) {
		return;
	}
	$css = '
		.yf-hero{min-height:320px;background:var(--pr-bg,#fff);}
		.yf-product__img{aspect-ratio:4/3;background:#f4f4f5;overflow:hidden;}
		.yf-product__img img{width:100%;height:100%;object-fit:cover;display:block;}
		img[loading="lazy"]{background:#f4f4f5;}
	';
	echo "<style id=\"pr-critical-css\">" . wp_strip_all_tags( $css ) . "</style>\n"; // phpcs:ignore
}
add_action( 'wp_head', 'pr_inline_critical_css', 1 );

/**
 * Make the post-thumbnail HTML output (used by archives) follow the same
 * lazy/async/fetchpriority defaults, even for media-library images.
 */
function pr_thumbnail_attrs( $attr ) {
	$attr['loading']  = isset( $attr['loading'] ) ? $attr['loading'] : 'lazy';
	$attr['decoding'] = 'async';
	if ( ! isset( $attr['fetchpriority'] ) ) {
		$attr['fetchpriority'] = 'low';
	}
	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'pr_thumbnail_attrs', 10, 1 );
