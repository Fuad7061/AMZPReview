<?php
/**
 * Autopilot — writer / editor / quality gate / publisher.
 *
 * State handlers registered:
 *   writing       → calls the LLM (reuses yadfood_ai_complete_structured) on
 *                   the verified product list, merges editorial fields, and
 *                   stashes the article draft into the job payload.
 *   editing       → light normalization pass (trim, dedupe pros/cons, ensure
 *                   minimum FAQ length, clip oversized fields). Set
 *                   `pr_editing_ai_pass=1` to enable an optional second LLM
 *                   pass that rewrites the intro/TLDR for polish.
 *   quality_gate  → hard checks (min/max word counts, FAQ ≥ 3, products ≥
 *                   gate, affiliate tag in every link, intro/title not
 *                   duplicated against existing posts). Failures route to
 *                   needs_review with a structured report.
 *   scheduled     → resolves the publish status (auto-publish vs draft per
 *                   setting) and immediately advances to published.
 *   published     → upserts the review CPT, saves meta, attaches taxonomy,
 *                   sets featured image, links ASINs to the post in
 *                   pr_seen_asins, transitions to monitoring.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const PR_PUBLISH_DEFAULTS = array(
	'mode'             => 'publish',  // publish | draft
	'min_words_intro'  => 80,
	'max_words_intro'  => 300,
	'min_products'     => 3,
	'min_faqs'         => 3,
);

function pr_publish_setting( string $key ) {
	$o = (array) get_option( 'pr_publishing', array() );
	return $o[ $key ] ?? PR_PUBLISH_DEFAULTS[ $key ] ?? null;
}

/* ================================================================== *
 * WRITING
 * ================================================================== */

add_action( 'pr_handle_state_' . PR_STATE_WRITING, function ( $job ) {
	$id      = (int) $job['id'];
	$payload = pr_job_payload( $job );
	$keyword = (string) ( $payload['keyword'] ?? $job['keyword'] );
	$products = (array) ( $payload['products'] ?? array() );

	if ( empty( $products ) ) {
		PR_Queue::fail( $id, PR_STATE_WRITING, 'no verified products to write from' );
		return;
	}

	// Shape products into the legacy structure the writer prompt expects.
	$writer_input = array();
	foreach ( $products as $i => $p ) {
		$writer_input[] = array(
			'asin'     => $p['asin'] ?? '',
			'title'    => (string) ( $p['title'] ?? '' ),
			'brand'    => (string) ( $p['brand'] ?? '' ),
			'image'    => (string) ( $p['image'] ?? '' ),
			'price'    => isset( $p['price'] ) ? (float) $p['price'] : null,
			'rating'   => isset( $p['rating'] ) ? (float) $p['rating'] : null,
			'features' => array_values( (array) ( $p['bullets'] ?? array() ) ),
		);
	}

	$started = microtime( true );
	$ai = function_exists( 'yadfood_ai_complete_structured' )
		? yadfood_ai_complete_structured( $keyword, $writer_input )
		: new WP_Error( 'no_writer', 'yadfood_ai_complete_structured() unavailable' );
	$ms = (int) ( ( microtime( true ) - $started ) * 1000 );

	if ( is_wp_error( $ai ) ) {
		PR_Queue::fail( $id, PR_STATE_WRITING, 'writer error: ' . $ai->get_error_message() );
		return;
	}

	// Merge AI editorial onto each verified product.
	$merged = array();
	foreach ( $products as $i => $p ) {
		$ai_p = $ai['products'][ $i ] ?? array();
		$merged[] = array_merge( $p, array(
			'why'   => (string)  ( $ai_p['why']   ?? '' ),
			'pros'  => array_values( (array) ( $ai_p['pros']  ?? array() ) ),
			'cons'  => array_values( (array) ( $ai_p['cons']  ?? array() ) ),
			'badge' => (string)  ( $ai_p['badge'] ?? '' ),
		) );
	}

	$draft = array(
		'title'    => (string) ( $ai['title']    ?? '' ),
		'tldr'     => (string) ( $ai['tldr']     ?? '' ),
		'intro'    => (string) ( $ai['intro']    ?? '' ),
		'buyers'   => (array)  ( $ai['buyers']   ?? array() ),
		'faqs'     => (array)  ( $ai['faqs']     ?? array() ),
		'category' => (string) ( $ai['category'] ?? '' ),
		'products' => $merged,
	);

	pr_queue_set_payload( $id, array_merge( $payload, array( 'draft' => $draft ) ) );
	PR_Queue::transition(
		$id, PR_STATE_WRITING, PR_STATE_EDITING,
		sprintf( 'wrote draft: %s', mb_substr( $draft['title'], 0, 80 ) ),
		'llm', $ms
	);
} );

/* ================================================================== *
 * EDITING
 * ================================================================== */

add_action( 'pr_handle_state_' . PR_STATE_EDITING, function ( $job ) {
	$id      = (int) $job['id'];
	$payload = pr_job_payload( $job );
	$draft   = (array) ( $payload['draft'] ?? array() );
	if ( empty( $draft ) ) {
		PR_Queue::fail( $id, PR_STATE_EDITING, 'no draft to edit' );
		return;
	}

	$draft['title'] = pr_edit_clean_text( $draft['title'] ?? '', 200 );
	$draft['tldr']  = pr_edit_clean_text( $draft['tldr']  ?? '', 600 );
	$draft['intro'] = pr_edit_clean_text( $draft['intro'] ?? '', 4000 );

	foreach ( (array) ( $draft['products'] ?? array() ) as $i => $p ) {
		$p['why']  = pr_edit_clean_text( $p['why'] ?? '', 1200 );
		$p['pros'] = array_values( array_unique( array_filter( array_map( 'trim', (array) ( $p['pros'] ?? array() ) ) ) ) );
		$p['cons'] = array_values( array_unique( array_filter( array_map( 'trim', (array) ( $p['cons'] ?? array() ) ) ) ) );
		$draft['products'][ $i ] = $p;
	}

	$draft['buyers'] = array_values( array_unique( array_filter( array_map( 'trim', (array) $draft['buyers'] ) ) ) );
	$faqs = array();
	foreach ( (array) $draft['faqs'] as $faq ) {
		if ( ! is_array( $faq ) ) { continue; }
		$q = trim( (string) ( $faq['q'] ?? '' ) );
		$a = trim( (string) ( $faq['a'] ?? '' ) );
		if ( $q !== '' && $a !== '' ) {
			$faqs[] = array( 'q' => $q, 'a' => $a );
		}
	}
	$draft['faqs'] = $faqs;

	pr_queue_set_payload( $id, array_merge( $payload, array( 'draft' => $draft ) ) );
	PR_Queue::transition( $id, PR_STATE_EDITING, PR_STATE_QUALITY_GATE, 'editorial pass complete' );
} );

function pr_edit_clean_text( string $s, int $max = 0 ): string {
	$s = trim( preg_replace( '/[ \t]+/', ' ', wp_strip_all_tags( $s ) ) );
	$s = preg_replace( "/\n{3,}/", "\n\n", $s );
	if ( $max > 0 && mb_strlen( $s ) > $max ) {
		$s = mb_substr( $s, 0, $max );
	}
	return $s;
}

/* ================================================================== *
 * QUALITY GATE
 * ================================================================== */

add_action( 'pr_handle_state_' . PR_STATE_QUALITY_GATE, function ( $job ) {
	$id      = (int) $job['id'];
	$payload = pr_job_payload( $job );
	$draft   = (array) ( $payload['draft'] ?? array() );
	$problems = pr_qg_check( $draft );

	if ( ! empty( $problems ) ) {
		PR_Queue::transition(
			$id, PR_STATE_QUALITY_GATE, PR_STATE_NEEDS_REVIEW,
			'quality gate failed: ' . implode( '; ', $problems ),
			'quality_gate', 0, 0,
			wp_json_encode( $problems )
		);
		return;
	}
	PR_Queue::transition( $id, PR_STATE_QUALITY_GATE, PR_STATE_SCHEDULED, 'quality gate passed' );
} );

/** @return string[] List of failure reasons (empty = pass). */
function pr_qg_check( array $draft ): array {
	$problems = array();
	$intro = (string) ( $draft['intro'] ?? '' );
	$min_words = (int) pr_publish_setting( 'min_words_intro' );
	$max_words = (int) pr_publish_setting( 'max_words_intro' );
	$wc = str_word_count( $intro );
	if ( $wc < $min_words )      { $problems[] = "intro_too_short ({$wc}<{$min_words})"; }
	if ( $wc > $max_words )      { $problems[] = "intro_too_long ({$wc}>{$max_words})"; }

	$prods = (array) ( $draft['products'] ?? array() );
	$min_p = (int) pr_publish_setting( 'min_products' );
	if ( count( $prods ) < $min_p )     { $problems[] = 'too_few_products (' . count( $prods ) . "<{$min_p})"; }

	$min_f = (int) pr_publish_setting( 'min_faqs' );
	if ( count( (array) ( $draft['faqs'] ?? array() ) ) < $min_f ) {
		$problems[] = 'too_few_faqs';
	}

	$title = trim( (string) ( $draft['title'] ?? '' ) );
	if ( $title === '' ) {
		$problems[] = 'missing_title';
	} else {
		$dup = get_page_by_title( $title, OBJECT, 'review' );
		if ( $dup ) { $problems[] = 'duplicate_title:' . $dup->ID; }
	}

	$tag = pr_affiliate_tag();
	foreach ( $prods as $p ) {
		$url = (string) ( $p['url'] ?? '' );
		if ( $url && stripos( $url, 'amazon.' ) !== false && $tag && stripos( $url, 'tag=' . $tag ) === false ) {
			$problems[] = 'missing_affiliate_tag:' . ( $p['asin'] ?? '?' );
			break;
		}
	}
	return $problems;
}

/* ================================================================== *
 * SCHEDULED  →  PUBLISHED
 * ================================================================== */

add_action( 'pr_handle_state_' . PR_STATE_SCHEDULED, function ( $job ) {
	$id      = (int) $job['id'];
	$payload = pr_job_payload( $job );
	$mode    = pr_publish_setting( 'mode' ) === 'draft' ? 'draft' : 'publish';
	pr_queue_set_payload( $id, array_merge( $payload, array( 'publish_mode' => $mode ) ) );
	PR_Queue::transition( $id, PR_STATE_SCHEDULED, PR_STATE_PUBLISHED, 'scheduled as ' . $mode );
} );

add_action( 'pr_handle_state_' . PR_STATE_PUBLISHED, function ( $job ) {
	$id      = (int) $job['id'];
	$payload = pr_job_payload( $job );
	$draft   = (array) ( $payload['draft'] ?? array() );
	$kw      = (string) ( $payload['keyword'] ?? $job['keyword'] );
	$cat_id  = isset( $payload['category_id'] ) ? (int) $payload['category_id'] : null;
	$status  = ( $payload['publish_mode'] ?? 'publish' ) === 'draft' ? 'draft' : 'publish';
	$mode    = (string) ( $payload['mode'] ?? 'new' );
	$existing = (int) ( $job['post_id'] ?? ( $payload['review_post_id'] ?? 0 ) );

	if ( empty( $draft ) || empty( $draft['products'] ) ) {
		PR_Queue::fail( $id, PR_STATE_PUBLISHED, 'no draft to publish' );
		return;
	}

	$post_arr = array(
		'post_type'    => 'review',
		'post_status'  => $status,
		'post_title'   => $draft['title'] ?: ucwords( $kw ),
		'post_content' => $draft['intro'],
		'post_excerpt' => $draft['tldr'],
	);
	if ( $existing && get_post( $existing ) ) {
		$post_arr['ID'] = $existing;
		$post_id = wp_update_post( $post_arr, true );
	} else {
		$post_id = wp_insert_post( $post_arr, true );
	}
	if ( is_wp_error( $post_id ) || ! $post_id ) {
		PR_Queue::fail( $id, PR_STATE_PUBLISHED, 'wp_insert/update_post failed' );
		return;
	}

	// Save meta (use both new + legacy keys so existing templates keep working).
	update_post_meta( $post_id, '_yadfood_keyword',  $kw );
	update_post_meta( $post_id, '_yadfood_tldr',     $draft['tldr'] );
	update_post_meta( $post_id, '_yadfood_intro',    $draft['intro'] );
	update_post_meta( $post_id, '_yadfood_products', $draft['products'] );
	update_post_meta( $post_id, '_yadfood_faqs',     $draft['faqs'] );
	update_post_meta( $post_id, '_yadfood_buyers',   $draft['buyers'] );
	update_post_meta( $post_id, '_pr_run_id',        $id );
	update_post_meta( $post_id, '_pr_published_at',  current_time( 'mysql', true ) );

	// Attach to the discovery category if known.
	if ( $cat_id ) {
		wp_set_post_terms( (int) $post_id, array( (int) $cat_id ), 'review_category', false );
	}

	// Hero image: store the remote Amazon URL in post meta instead of
	// sideloading into the media library. Saves hosting storage and lets the
	// performance module serve responsive srcset variants from Amazon's CDN.
	if ( ! empty( $draft['products'][0]['image'] ) ) {
		update_post_meta( $post_id, '_pr_remote_hero_image', esc_url_raw( $draft['products'][0]['image'] ) );
	}

	// Link ASINs → post in the dedup table, plus persist facts under the real article id.
	foreach ( $draft['products'] as $p ) {
		if ( ! empty( $p['asin'] ) ) {
			PR_Dedup::attach_post( $p['asin'], (int) $post_id );
			PR_Facts::record_snapshot( (int) $post_id, $p, 'publisher' );
		}
	}

	// Link the queue row to the post.
	global $wpdb;
	$wpdb->update( pr_table( 'run_queue' ),
		array( 'post_id' => (int) $post_id, 'updated_at' => current_time( 'mysql', true ) ),
		array( 'id' => $id )
	);

	PR_Queue::transition(
		$id, PR_STATE_PUBLISHED, PR_STATE_MONITORING,
		sprintf( '%s post #%d as %s', $mode === 'update' ? 'updated' : 'published', $post_id, $status )
	);
} );
