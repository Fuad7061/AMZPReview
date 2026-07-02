<?php
/**
 * HowTo schema + setup-steps consolidation.
 *
 * Stores ordered setup/usage steps per post and emits HowTo JSON-LD plus an
 * accessible visible block. Steps live in the `_pr_howto` meta as an array
 * of { name, text, image } items, with an optional `_pr_howto_title` and
 * `_pr_howto_time` (ISO 8601 duration like PT10M).
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read normalized HowTo data for a post.
 */
function pr_howto_data( $post_id ) {
	$raw = get_post_meta( $post_id, '_pr_howto', true );
	if ( ! is_array( $raw ) ) {
		$raw = array();
	}
	$steps = array();
	foreach ( $raw as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$name = isset( $row['name'] ) ? trim( wp_strip_all_tags( (string) $row['name'] ) ) : '';
		$text = isset( $row['text'] ) ? trim( wp_strip_all_tags( (string) $row['text'] ) ) : '';
		$image = isset( $row['image'] ) ? esc_url_raw( (string) $row['image'] ) : '';
		if ( $name === '' && $text === '' ) {
			continue;
		}
		$steps[] = array(
			'name'  => $name !== '' ? $name : wp_trim_words( $text, 8, '…' ),
			'text'  => $text,
			'image' => $image,
		);
	}
	return array(
		'title' => trim( (string) get_post_meta( $post_id, '_pr_howto_title', true ) ),
		'time'  => trim( (string) get_post_meta( $post_id, '_pr_howto_time', true ) ),
		'steps' => $steps,
	);
}

/**
 * Render accessible HowTo HTML block.
 */
function pr_howto_render_html( $post_id ) {
	$data = pr_howto_data( $post_id );
	if ( empty( $data['steps'] ) ) {
		return '';
	}
	$title = $data['title'] !== '' ? $data['title'] : __( 'How to set it up', 'product-reviews' );

	$html  = '<section class="pr-howto" aria-labelledby="pr-howto-title-' . (int) $post_id . '">';
	$html .= '<h2 id="pr-howto-title-' . (int) $post_id . '" class="pr-howto-title">' . esc_html( $title ) . '</h2>';
	if ( $data['time'] !== '' ) {
		$html .= '<p class="pr-howto-time"><span class="pr-howto-time-label">' . esc_html__( 'Total time:', 'product-reviews' ) . '</span> <time datetime="' . esc_attr( $data['time'] ) . '">' . esc_html( pr_howto_format_duration( $data['time'] ) ) . '</time></p>';
	}
	$html .= '<ol class="pr-howto-steps">';
	foreach ( $data['steps'] as $i => $step ) {
		$html .= '<li class="pr-howto-step">';
		$html .= '<h3 class="pr-howto-step-name"><span class="pr-howto-step-num" aria-hidden="true">' . ( $i + 1 ) . '.</span> ' . esc_html( $step['name'] ) . '</h3>';
		if ( $step['image'] !== '' ) {
			$html .= '<img class="pr-howto-step-image" src="' . esc_url( $step['image'] ) . '" alt="" loading="lazy" />';
		}
		if ( $step['text'] !== '' ) {
			$html .= '<p class="pr-howto-step-text">' . esc_html( $step['text'] ) . '</p>';
		}
		$html .= '</li>';
	}
	$html .= '</ol></section>';
	return $html;
}

/**
 * Best-effort ISO 8601 duration → human string. PT10M → "10 min".
 */
function pr_howto_format_duration( $iso ) {
	if ( ! preg_match( '/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $iso, $m ) ) {
		return $iso;
	}
	$out = array();
	if ( ! empty( $m[1] ) ) {
		$out[] = sprintf( _n( '%d hour', '%d hours', (int) $m[1], 'product-reviews' ), (int) $m[1] );
	}
	if ( ! empty( $m[2] ) ) {
		$out[] = sprintf( _n( '%d minute', '%d minutes', (int) $m[2], 'product-reviews' ), (int) $m[2] );
	}
	if ( ! empty( $m[3] ) ) {
		$out[] = sprintf( _n( '%d second', '%d seconds', (int) $m[3], 'product-reviews' ), (int) $m[3] );
	}
	return $out ? implode( ' ', $out ) : $iso;
}

/**
 * Shortcode wrapper.
 */
function pr_howto_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'pr_howto' );
	$post_id = (int) $atts['id'];
	if ( ! $post_id ) {
		$post_id = (int) get_the_ID();
	}
	return pr_howto_render_html( $post_id );
}
add_shortcode( 'pr_howto', 'pr_howto_shortcode' );

/**
 * Auto-inject the HowTo block into singular review/post/page content.
 * Runs at the_content priority 16 (after FAQ at 14).
 */
function pr_howto_inject_content( $content ) {
	if ( ! is_singular( array( 'review', 'post', 'page' ) ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	if ( ! apply_filters( 'pr_howto_auto_inject', true, get_the_ID() ) ) {
		return $content;
	}
	$block = pr_howto_render_html( get_the_ID() );
	if ( $block === '' ) {
		return $content;
	}
	return $content . $block;
}
add_filter( 'the_content', 'pr_howto_inject_content', 16 );

/**
 * Scoped styles.
 */
function pr_howto_styles() {
	if ( ! is_singular() ) {
		return;
	}
	$css = '.pr-howto{margin:2rem 0;padding:1.25rem 1.5rem;border:1px solid var(--pr-border,#e5e7eb);border-radius:.75rem;background:var(--pr-surface,#fafafa)}.pr-howto-title{margin:0 0 .5rem;font-size:1.25rem;font-weight:700}.pr-howto-time{margin:.25rem 0 1rem;color:var(--pr-muted,#6b7280);font-size:.9rem}.pr-howto-time-label{font-weight:600;color:inherit}.pr-howto-steps{list-style:none;counter-reset:pr-howto;padding:0;margin:0;display:grid;gap:1rem}.pr-howto-step{padding:1rem 1.1rem;border:1px solid var(--pr-border,#e5e7eb);border-radius:.5rem;background:var(--pr-bg,#fff)}.pr-howto-step-name{margin:0 0 .35rem;font-size:1.02rem;font-weight:600;line-height:1.35}.pr-howto-step-num{display:inline-block;margin-right:.25rem;color:var(--pr-accent,#2563eb);font-weight:700}.pr-howto-step-image{display:block;max-width:100%;height:auto;margin:.5rem 0;border-radius:.375rem}.pr-howto-step-text{margin:0;color:inherit;line-height:1.55}';
	echo "<style id=\"pr-howto-css\">" . $css . "</style>\n";
}
add_action( 'wp_head', 'pr_howto_styles', 94 );

/**
 * Emit HowTo JSON-LD for singular views with steps.
 */
if ( ! function_exists( 'pr_howto_jsonld' ) ) {
function pr_howto_jsonld() {
	if ( ! is_singular() ) {
		return;
	}
	$post_id = get_queried_object_id();
	if ( ! $post_id ) {
		return;
	}
	$data = pr_howto_data( $post_id );
	if ( empty( $data['steps'] ) ) {
		return;
	}
	$title = $data['title'] !== '' ? $data['title'] : sprintf( __( 'How to set up %s', 'product-reviews' ), get_the_title( $post_id ) );

	$steps = array();
	foreach ( $data['steps'] as $i => $step ) {
		$node = array(
			'@type'    => 'HowToStep',
			'position' => $i + 1,
			'name'     => $step['name'],
			'text'     => $step['text'] !== '' ? $step['text'] : $step['name'],
			'url'      => get_permalink( $post_id ) . '#pr-howto-step-' . ( $i + 1 ),
		);
		if ( $step['image'] !== '' ) {
			$node['image'] = $step['image'];
		}
		$steps[] = $node;
	}

	$ld = array(
		'@context' => 'https://schema.org',
		'@type'    => 'HowTo',
		'@id'      => get_permalink( $post_id ) . '#howto',
		'name'     => $title,
		'step'     => $steps,
	);
	if ( $data['time'] !== '' ) {
		$ld['totalTime'] = $data['time'];
	}
	$thumb = get_the_post_thumbnail_url( $post_id, 'pr-hero' );
	if ( $thumb ) {
		$ld['image'] = $thumb;
	}

	echo "<script type=\"application/ld+json\">" . wp_json_encode( $ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}
}
add_action( 'wp_head', 'pr_howto_jsonld', 84 );

/**
 * Admin meta box for editing HowTo steps.
 */
function pr_howto_add_meta_box() {
	foreach ( array( 'review', 'post', 'page' ) as $type ) {
		add_meta_box( 'pr_howto', __( 'HowTo Steps', 'product-reviews' ), 'pr_howto_meta_box', $type, 'normal', 'default' );
	}
}
add_action( 'add_meta_boxes', 'pr_howto_add_meta_box' );

function pr_howto_meta_box( $post ) {
	wp_nonce_field( 'pr_howto_save', 'pr_howto_nonce' );
	$data = pr_howto_data( $post->ID );
	?>
	<p>
		<label><strong><?php esc_html_e( 'Title (optional)', 'product-reviews' ); ?></strong></label><br />
		<input type="text" name="pr_howto_title" value="<?php echo esc_attr( $data['title'] ); ?>" class="widefat" />
	</p>
	<p>
		<label><strong><?php esc_html_e( 'Total time (ISO 8601, e.g. PT10M)', 'product-reviews' ); ?></strong></label><br />
		<input type="text" name="pr_howto_time" value="<?php echo esc_attr( $data['time'] ); ?>" class="regular-text" placeholder="PT10M" />
	</p>
	<table class="widefat pr-howto-rows" id="pr-howto-rows">
		<thead><tr>
			<th style="width:24%"><?php esc_html_e( 'Step name', 'product-reviews' ); ?></th>
			<th><?php esc_html_e( 'Step text', 'product-reviews' ); ?></th>
			<th style="width:24%"><?php esc_html_e( 'Image URL (optional)', 'product-reviews' ); ?></th>
			<th style="width:60px"></th>
		</tr></thead>
		<tbody>
		<?php
		$rows = ! empty( $data['steps'] ) ? $data['steps'] : array( array( 'name' => '', 'text' => '', 'image' => '' ) );
		foreach ( $rows as $i => $row ) :
			?>
			<tr>
				<td><input type="text" name="pr_howto[<?php echo (int) $i; ?>][name]" value="<?php echo esc_attr( $row['name'] ); ?>" class="widefat" /></td>
				<td><textarea name="pr_howto[<?php echo (int) $i; ?>][text]" rows="2" class="widefat"><?php echo esc_textarea( $row['text'] ); ?></textarea></td>
				<td><input type="url" name="pr_howto[<?php echo (int) $i; ?>][image]" value="<?php echo esc_attr( $row['image'] ); ?>" class="widefat" /></td>
				<td><button type="button" class="button-link pr-howto-del" aria-label="<?php esc_attr_e( 'Remove step', 'product-reviews' ); ?>">&times;</button></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<p><button type="button" class="button" id="pr-howto-add"><?php esc_html_e( 'Add step', 'product-reviews' ); ?></button></p>
	<script>
	(function(){
		var tbody = document.querySelector('#pr-howto-rows tbody');
		if (!tbody) return;
		document.getElementById('pr-howto-add').addEventListener('click', function(){
			var i = tbody.querySelectorAll('tr').length;
			var tr = document.createElement('tr');
			tr.innerHTML = '<td><input type="text" name="pr_howto[' + i + '][name]" class="widefat" /></td>' +
				'<td><textarea name="pr_howto[' + i + '][text]" rows="2" class="widefat"></textarea></td>' +
				'<td><input type="url" name="pr_howto[' + i + '][image]" class="widefat" /></td>' +
				'<td><button type="button" class="button-link pr-howto-del">&times;</button></td>';
			tbody.appendChild(tr);
		});
		tbody.addEventListener('click', function(e){
			if (e.target && e.target.classList.contains('pr-howto-del')) {
				var row = e.target.closest('tr');
				if (row) row.parentNode.removeChild(row);
			}
		});
	})();
	</script>
	<?php
}

if ( ! function_exists( 'pr_howto_save' ) ) {
function pr_howto_save( $post_id ) {
	if ( ! isset( $_POST['pr_howto_nonce'] ) || ! wp_verify_nonce( $_POST['pr_howto_nonce'], 'pr_howto_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$title = isset( $_POST['pr_howto_title'] ) ? sanitize_text_field( wp_unslash( $_POST['pr_howto_title'] ) ) : '';
	$time  = isset( $_POST['pr_howto_time'] ) ? sanitize_text_field( wp_unslash( $_POST['pr_howto_time'] ) ) : '';
	update_post_meta( $post_id, '_pr_howto_title', $title );
	update_post_meta( $post_id, '_pr_howto_time', $time );

	$rows = isset( $_POST['pr_howto'] ) && is_array( $_POST['pr_howto'] ) ? wp_unslash( $_POST['pr_howto'] ) : array();
	$clean = array();
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$name = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
		$text = isset( $row['text'] ) ? sanitize_textarea_field( $row['text'] ) : '';
		$image = isset( $row['image'] ) ? esc_url_raw( $row['image'] ) : '';
		if ( $name === '' && $text === '' ) {
			continue;
		}
		$clean[] = array( 'name' => $name, 'text' => $text, 'image' => $image );
	}
	update_post_meta( $post_id, '_pr_howto', $clean );
}
}
add_action( 'save_post', 'pr_howto_save' );
