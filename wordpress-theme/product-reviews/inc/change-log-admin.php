<?php
/**
 * Admin UI: Change Log dashboard + per-post change history meta box +
 * Monitor settings (renders inside the Autopilot page via action hook).
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ============================================================
 * Submenu: Change Log dashboard
 * ============================================================ */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=review',
		__( 'Change Log', 'product-reviews' ),
		__( 'Change Log', 'product-reviews' ),
		'manage_options',
		'pr-change-log',
		'pr_render_change_log_page'
	);
} );

function pr_render_change_log_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	// Manual monitor trigger.
	if ( isset( $_POST['pr_run_monitor_nonce'] ) && wp_verify_nonce( $_POST['pr_run_monitor_nonce'], 'pr_run_monitor' ) ) {
		$stats = pr_monitor_run();
		printf(
			'<div class="notice notice-success"><p>%s</p></div>',
			esc_html( sprintf(
				/* translators: 1: posts checked, 2: posts with material changes, 3: update jobs queued */
				__( 'Monitor run: checked %1$d posts, %2$d with changes, %3$d update jobs queued.', 'product-reviews' ),
				(int) $stats['checked'], (int) $stats['changed'], (int) $stats['jobs']
			) )
		);
	}

	$field = isset( $_GET['field'] ) ? sanitize_text_field( $_GET['field'] ) : null;
	$rows  = pr_changelog_recent( 200, $field ?: null );
	$last  = (string) get_option( 'pr_monitor_last_run', '' );
	$stats = (array)  get_option( 'pr_monitor_last_stats', array() );
	$next  = wp_next_scheduled( 'pr_monitor_tick' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Product Reviews — Change Log', 'product-reviews' ); ?></h1>
		<p class="description">
			<?php esc_html_e( 'Material price / rating / availability changes detected by the monitor. The monitor refreshes published reviews on a schedule and queues update jobs when something material shifts.', 'product-reviews' ); ?>
		</p>

		<div class="card" style="max-width:680px">
			<h2><?php esc_html_e( 'Monitor', 'product-reviews' ); ?></h2>
			<p>
				<strong><?php esc_html_e( 'Last run:', 'product-reviews' ); ?></strong>
				<?php echo $last ? esc_html( $last ) : '<em>' . esc_html__( 'never', 'product-reviews' ) . '</em>'; ?><br>
				<strong><?php esc_html_e( 'Last stats:', 'product-reviews' ); ?></strong>
				<?php echo $stats
					? esc_html( sprintf( 'checked=%d, changed=%d, jobs=%d', (int) ( $stats['checked'] ?? 0 ), (int) ( $stats['changed'] ?? 0 ), (int) ( $stats['jobs'] ?? 0 ) ) )
					: '—'; ?><br>
				<strong><?php esc_html_e( 'Next run:', 'product-reviews' ); ?></strong>
				<?php echo $next ? esc_html( gmdate( 'Y-m-d H:i:s', $next ) ) . ' UTC' : '—'; ?>
			</p>
			<form method="post">
				<?php wp_nonce_field( 'pr_run_monitor', 'pr_run_monitor_nonce' ); ?>
				<?php submit_button( __( 'Run monitor now', 'product-reviews' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>

		<h2 style="margin-top:24px"><?php esc_html_e( 'Recent changes', 'product-reviews' ); ?></h2>
		<p>
			<?php $base = remove_query_arg( 'field' ); ?>
			<a class="button button-small <?php echo $field ? '' : 'button-primary'; ?>" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'All', 'product-reviews' ); ?></a>
			<?php foreach ( array( 'price', 'rating', 'review_count', 'availability', 'title', 'image' ) as $f ) :
				$href = add_query_arg( 'field', $f );
				$cls  = $field === $f ? 'button-primary' : '';
				?>
				<a class="button button-small <?php echo esc_attr( $cls ); ?>" href="<?php echo esc_url( $href ); ?>"><?php echo esc_html( $f ); ?></a>
			<?php endforeach; ?>
		</p>

		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'When', 'product-reviews' ); ?></th>
					<th><?php esc_html_e( 'Post', 'product-reviews' ); ?></th>
					<th><?php esc_html_e( 'ASIN', 'product-reviews' ); ?></th>
					<th><?php esc_html_e( 'Field', 'product-reviews' ); ?></th>
					<th><?php esc_html_e( 'Old', 'product-reviews' ); ?></th>
					<th><?php esc_html_e( 'New', 'product-reviews' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="6"><em><?php esc_html_e( 'No changes recorded yet.', 'product-reviews' ); ?></em></td></tr>
			<?php else : foreach ( $rows as $r ) :
				$title = get_the_title( (int) $r['article_id'] );
				$edit  = get_edit_post_link( (int) $r['article_id'] );
				?>
				<tr>
					<td><?php echo esc_html( $r['ts'] ); ?></td>
					<td>
						<?php if ( $edit ) : ?>
							<a href="<?php echo esc_url( $edit ); ?>">#<?php echo (int) $r['article_id']; ?> <?php echo esc_html( $title ); ?></a>
						<?php else : ?>
							#<?php echo (int) $r['article_id']; ?>
						<?php endif; ?>
					</td>
					<td><code><?php echo esc_html( (string) $r['asin'] ); ?></code></td>
					<td><strong><?php echo esc_html( (string) $r['field'] ); ?></strong></td>
					<td><code><?php echo esc_html( pr_changelog_truncate( (string) $r['old_value'] ) ); ?></code></td>
					<td><code><?php echo esc_html( pr_changelog_truncate( (string) $r['new_value'] ) ); ?></code></td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}

function pr_changelog_truncate( string $s, int $max = 60 ): string {
	$s = trim( $s );
	if ( mb_strlen( $s ) <= $max ) { return $s; }
	return mb_substr( $s, 0, $max - 1 ) . '…';
}

/* ============================================================
 * Per-post meta box: Change history
 * ============================================================ */
add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'pr_change_history',
		__( 'Change history', 'product-reviews' ),
		'pr_render_change_history_metabox',
		'review',
		'side',
		'default'
	);
} );

function pr_render_change_history_metabox( $post ): void {
	$rows = pr_changelog_for_post( (int) $post->ID, 25 );
	$last = (string) get_post_meta( (int) $post->ID, '_pr_monitor_last', true );
	echo '<p><strong>' . esc_html__( 'Last monitor check:', 'product-reviews' ) . '</strong> ';
	echo $last ? esc_html( $last ) : '<em>' . esc_html__( 'never', 'product-reviews' ) . '</em>';
	echo '</p>';
	if ( empty( $rows ) ) {
		echo '<p><em>' . esc_html__( 'No material changes recorded.', 'product-reviews' ) . '</em></p>';
		return;
	}
	echo '<ul style="margin:0;max-height:320px;overflow:auto">';
	foreach ( $rows as $r ) {
		printf(
			'<li style="border-top:1px solid #eee;padding:6px 0"><code>%s</code> · <strong>%s</strong><br><small>%s → %s</small><br><small style="color:#888">%s · %s</small></li>',
			esc_html( (string) $r['asin'] ),
			esc_html( (string) $r['field'] ),
			esc_html( pr_changelog_truncate( (string) $r['old_value'], 40 ) ),
			esc_html( pr_changelog_truncate( (string) $r['new_value'], 40 ) ),
			esc_html( $r['ts'] ),
			esc_html__( 'UTC', 'product-reviews' )
		);
	}
	echo '</ul>';
}

/* ============================================================
 * Monitor settings — append to Autopilot page settings form.
 * ============================================================ */
add_action( 'pr_autopilot_settings_extra', 'pr_render_monitor_settings' );

function pr_render_monitor_settings(): void {
	$o = (array) get_option( 'pr_monitor', array() );
	$interval = $o['interval']         ?? PR_MONITOR_DEFAULTS['interval'];
	$per_tick = $o['posts_per_tick']   ?? PR_MONITOR_DEFAULTS['posts_per_tick'];
	$min_age  = $o['min_age_hours']    ?? PR_MONITOR_DEFAULTS['min_age_hours'];
	$cooldown = $o['cooldown_hours']   ?? PR_MONITOR_DEFAULTS['cooldown_hours'];
	$choices = array(
		'pr_1hour' => __( 'Every hour',  'product-reviews' ),
		'pr_6hour' => __( 'Every 6 hours','product-reviews' ),
		'daily'    => __( 'Daily',       'product-reviews' ),
		'weekly'   => __( 'Weekly',      'product-reviews' ),
	);
	?>
	<h2 style="margin-top:24px"><?php esc_html_e( 'Monitor', 'product-reviews' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="pr_monitor_interval"><?php esc_html_e( 'Monitor schedule', 'product-reviews' ); ?></label></th>
			<td>
				<select id="pr_monitor_interval" name="pr_monitor_interval">
					<?php foreach ( $choices as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $interval, $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'How often to re-check published reviews for material changes.', 'product-reviews' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="pr_monitor_per_tick"><?php esc_html_e( 'Posts per run', 'product-reviews' ); ?></label></th>
			<td><input type="number" min="1" max="100" id="pr_monitor_per_tick" name="pr_monitor_per_tick" value="<?php echo esc_attr( (int) $per_tick ); ?>" class="small-text"></td>
		</tr>
		<tr>
			<th><label for="pr_monitor_min_age"><?php esc_html_e( 'Skip posts younger than (hours)', 'product-reviews' ); ?></label></th>
			<td><input type="number" min="0" max="720" id="pr_monitor_min_age" name="pr_monitor_min_age" value="<?php echo esc_attr( (int) $min_age ); ?>" class="small-text"></td>
		</tr>
		<tr>
			<th><label for="pr_monitor_cooldown"><?php esc_html_e( 'Per-post cooldown (hours)', 'product-reviews' ); ?></label></th>
			<td><input type="number" min="1" max="720" id="pr_monitor_cooldown" name="pr_monitor_cooldown" value="<?php echo esc_attr( (int) $cooldown ); ?>" class="small-text"></td>
		</tr>
	</table>
	<?php
}

/** Save monitor settings when the Autopilot settings form is submitted. */
add_action( 'pr_autopilot_settings_save', function () {
	$valid = array( 'pr_1hour', 'pr_6hour', 'daily', 'weekly' );
	$interval = sanitize_text_field( $_POST['pr_monitor_interval'] ?? PR_MONITOR_DEFAULTS['interval'] );
	if ( ! in_array( $interval, $valid, true ) ) { $interval = PR_MONITOR_DEFAULTS['interval']; }
	update_option( 'pr_monitor', array(
		'interval'       => $interval,
		'posts_per_tick' => max( 1, min( 100, (int) ( $_POST['pr_monitor_per_tick'] ?? PR_MONITOR_DEFAULTS['posts_per_tick'] ) ) ),
		'min_age_hours'  => max( 0, min( 720, (int) ( $_POST['pr_monitor_min_age']  ?? PR_MONITOR_DEFAULTS['min_age_hours'] ) ) ),
		'cooldown_hours' => max( 1, min( 720, (int) ( $_POST['pr_monitor_cooldown'] ?? PR_MONITOR_DEFAULTS['cooldown_hours'] ) ) ),
	), false );
} );
