<?php
/**
 * Media schema enrichment — VideoObject + ImageObject hardening.
 *
 * Emits JSON-LD for any embedded YouTube/Vimeo videos in the post content
 * and a clean ImageObject node for the featured image. All output is
 * conditional — missing data simply produces no node, never broken markup.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collect video URLs from post content (oEmbed lines + iframes).
 *
 * @param int $post_id Post ID.
 * @return array<int,array{provider:string,id:string,url:string,embed:string}>
 */
function pr_media_collect_videos( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return array();
	}
	$content = (string) $post->post_content;
	$found   = array();

	// YouTube.
	if ( preg_match_all( '#(?:youtube\.com/(?:watch\?v=|embed/|v/)|youtu\.be/)([A-Za-z0-9_-]{6,})#i', $content, $m ) ) {
		foreach ( $m[1] as $id ) {
			$found[ 'yt_' . $id ] = array(
				'provider' => 'youtube',
				'id'       => $id,
				'url'      => 'https://www.youtube.com/watch?v=' . $id,
				'embed'    => 'https://www.youtube.com/embed/' . $id,
			);
		}
	}
	// Vimeo.
	if ( preg_match_all( '#vimeo\.com/(?:video/)?(\d{5,})#i', $content, $m ) ) {
		foreach ( $m[1] as $id ) {
			$found[ 'vm_' . $id ] = array(
				'provider' => 'vimeo',
				'id'       => $id,
				'url'      => 'https://vimeo.com/' . $id,
				'embed'    => 'https://player.vimeo.com/video/' . $id,
			);
		}
	}
	return array_values( $found );
}

/**
 * Best-effort YouTube thumbnail URL.
 */
function pr_media_yt_thumb( $id ) {
	return 'https://i.ytimg.com/vi/' . rawurlencode( $id ) . '/hqdefault.jpg';
}

/**
 * Emit VideoObject JSON-LD for embedded videos on single reviews.
 */
function pr_media_video_jsonld() {
	if ( ! is_singular( 'review' ) ) {
		return;
	}
	$post_id = get_queried_object_id();
	$videos  = pr_media_collect_videos( $post_id );
	if ( empty( $videos ) ) {
		return;
	}

	$title = get_the_title( $post_id );
	$date  = get_the_date( 'c', $post_id );
	$nodes = array();
	foreach ( $videos as $v ) {
		$thumb = '';
		if ( 'youtube' === $v['provider'] ) {
			$thumb = pr_media_yt_thumb( $v['id'] );
		}
		$node = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'VideoObject',
			'name'        => $title,
			'description' => wp_strip_all_tags( get_the_excerpt( $post_id ) ),
			'uploadDate'  => $date,
			'contentUrl'  => $v['url'],
			'embedUrl'    => $v['embed'],
		);
		if ( $thumb ) {
			$node['thumbnailUrl'] = $thumb;
		}
		$nodes[] = $node;
	}

	foreach ( $nodes as $n ) {
		echo '<script type="application/ld+json">' . wp_json_encode( $n, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
	}
}
add_action( 'wp_head', 'pr_media_video_jsonld', 58 );

/**
 * Emit a hardened ImageObject node for the featured image on single reviews.
 */
function pr_media_image_jsonld() {
	if ( ! is_singular( 'review' ) ) {
		return;
	}
	$post_id = get_queried_object_id();
	$thumb   = get_post_thumbnail_id( $post_id );
	if ( ! $thumb ) {
		return;
	}
	$src = wp_get_attachment_image_src( $thumb, 'full' );
	if ( empty( $src[0] ) ) {
		return;
	}
	$alt  = trim( (string) get_post_meta( $thumb, '_wp_attachment_image_alt', true ) );
	$cap  = trim( (string) wp_get_attachment_caption( $thumb ) );
	$node = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'ImageObject',
		'contentUrl'  => $src[0],
		'url'         => $src[0],
		'width'       => isset( $src[1] ) ? (int) $src[1] : null,
		'height'      => isset( $src[2] ) ? (int) $src[2] : null,
		'caption'     => $cap ? $cap : ( $alt ? $alt : get_the_title( $post_id ) ),
		'representativeOfPage' => true,
	);
	$node = array_filter(
		$node,
		static function ( $v ) {
			return null !== $v && '' !== $v;
		}
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}
add_action( 'wp_head', 'pr_media_image_jsonld', 59 );
