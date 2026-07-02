<?php
/**
 * AI article generator — BYOK for OpenAI / Gemini / Anthropic.
 *
 * Flow:
 *   1. Normalize the keyword.
 *   2. Fetch live products from Amazon PA-API.
 *   3. Ask the LLM to produce TL;DR, intro, per-product "why we picked it",
 *      pros, cons, buyer's guide, FAQ, and category in one structured call.
 *   4. Create / update a `review` post (saved as draft for review).
 *
 * @package YadFoodReviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generate a full review article from a raw keyword.
 *
 * @param string $raw_keyword
 * @param int    $count
 * @param string $status      'draft' or 'publish'
 * @return int|WP_Error Post ID on success.
 */
function yadfood_generate_review( $raw_keyword, $count = 10, $status = 'draft' ) {
	$keyword = yadfood_normalize_query( $raw_keyword );
	if ( '' === $keyword ) {
		return new WP_Error( 'empty_keyword', __( 'Keyword is empty after normalization.', 'yadfood-reviews' ) );
	}

	// 1) Live product data.
	$products = yadfood_amazon_search( $keyword, $count );
	if ( is_wp_error( $products ) ) {
		return $products;
	}
	if ( empty( $products ) ) {
		return new WP_Error( 'no_products', __( 'No Amazon products found for this keyword.', 'yadfood-reviews' ) );
	}

	// 2) Ask the LLM for editorial copy.
	$ai = yadfood_ai_complete_structured( $keyword, $products );
	if ( is_wp_error( $ai ) ) {
		return $ai;
	}

	// 3) Merge AI editorial into the product list.
	$merged = array();
	foreach ( $products as $i => $p ) {
		$ai_p = isset( $ai['products'][ $i ] ) ? $ai['products'][ $i ] : array();
		$merged[] = array_merge( $p, array(
			'why'      => isset( $ai_p['why'] ) ? $ai_p['why'] : '',
			'pros'     => isset( $ai_p['pros'] ) ? array_values( (array) $ai_p['pros'] ) : array(),
			'cons'     => isset( $ai_p['cons'] ) ? array_values( (array) $ai_p['cons'] ) : array(),
			'badge'    => isset( $ai_p['badge'] ) ? $ai_p['badge'] : '',
		) );
	}

	$title = isset( $ai['title'] ) && $ai['title'] ? $ai['title'] : yadfood_make_title( $keyword, count( $merged ) );

	// 4) Create / update post.
	$post_id = wp_insert_post( array(
		'post_type'    => 'review',
		'post_status'  => $status,
		'post_title'   => $title,
		'post_content' => isset( $ai['intro'] ) ? $ai['intro'] : '',
		'post_excerpt' => isset( $ai['tldr'] ) ? $ai['tldr'] : '',
	), true );

	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	update_post_meta( $post_id, '_yadfood_keyword',  $keyword );
	update_post_meta( $post_id, '_yadfood_tldr',     isset( $ai['tldr'] ) ? $ai['tldr'] : '' );
	update_post_meta( $post_id, '_yadfood_intro',    isset( $ai['intro'] ) ? $ai['intro'] : '' );
	update_post_meta( $post_id, '_yadfood_products', $merged );
	update_post_meta( $post_id, '_yadfood_faqs',     isset( $ai['faqs'] ) ? $ai['faqs'] : array() );
	update_post_meta( $post_id, '_yadfood_buyers',   isset( $ai['buyers'] ) ? $ai['buyers'] : array() );

	// Assign to a category if AI suggested one.
	if ( ! empty( $ai['category'] ) ) {
		$term = term_exists( $ai['category'], 'review_category' );
		if ( ! $term ) {
			$term = wp_insert_term( $ai['category'], 'review_category' );
		}
		if ( ! is_wp_error( $term ) ) {
			wp_set_post_terms( $post_id, array( (int) $term['term_id'] ), 'review_category', false );
		}
	}

	// Featured image: download top product image if no thumbnail set.
	if ( ! has_post_thumbnail( $post_id ) && ! empty( $merged[0]['image'] ) ) {
		yadfood_set_thumbnail_from_url( $post_id, $merged[0]['image'] );
	}

	return $post_id;
}

/**
 * Single structured LLM call.
 *
 * @return array|WP_Error
 */
function yadfood_ai_complete_structured( $keyword, $products ) {
	$settings = yadfood_ai_settings();
	if ( empty( $settings['api_key'] ) ) {
		return new WP_Error( 'no_api_key', __( 'No AI API key set. Configure it under Appearance → Customize → AI Article Generation.', 'yadfood-reviews' ) );
	}

	// Slim product list for the prompt.
	$slim = array();
	foreach ( $products as $i => $p ) {
		$slim[] = array(
			'rank'     => $i + 1,
			'title'    => $p['title'],
			'brand'    => $p['brand'],
			'price'    => $p['price'],
			'rating'   => $p['rating'],
			'features' => array_slice( (array) $p['features'], 0, 6 ),
		);
	}

	$system = "You are a senior editor at an Amazon affiliate review site. Write factual, helpful, conversion-friendly copy. Never invent specs not present in the product list. Be concise, neutral, and useful. Output STRICT JSON only — no markdown, no commentary.";

	$schema_hint = '{
		"title": "string — punchy article title, includes year",
		"category": "string — short category slug like \"coffee-grinder\" or \"running-shoes\"",
		"tldr": "string — 2–3 sentence summary naming the #1 pick",
		"intro": "string — 80–140 word intro paragraph, plain text",
		"buyers": ["string — 4–7 buying-guide bullets, one tip per item"],
		"faqs": [{"q": "string", "a": "string"}],
		"products": [{"why": "60–90 words why this product is a top pick", "pros": ["string","string","string"], "cons": ["string","string"], "badge": "editors_choice|best_value|premium|budget|\"\""}]
	}';

	$user = "Keyword: {$keyword}\n\nProducts (in rank order, do not reorder):\n"
		. wp_json_encode( $slim, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		. "\n\nReturn a single JSON object matching this shape (omit comments):\n" . $schema_hint;

	switch ( $settings['provider'] ) {
		case 'gemini':
			$text = yadfood_call_gemini( $settings, $system, $user );
			break;
		case 'anthropic':
			$text = yadfood_call_anthropic( $settings, $system, $user );
			break;
		case 'openai':
		default:
			$text = yadfood_call_openai( $settings, $system, $user );
	}
	if ( is_wp_error( $text ) ) {
		return $text;
	}

	// Strip code fences if any.
	$text = preg_replace( '/^```(?:json)?\s*/m', '', trim( $text ) );
	$text = preg_replace( '/\s*```\s*$/m', '', $text );

	$json = json_decode( $text, true );
	if ( ! is_array( $json ) ) {
		return new WP_Error( 'bad_json', __( 'AI returned non-JSON content.', 'yadfood-reviews' ), $text );
	}
	return $json;
}

/* ---------------- Provider adapters ---------------- */

function yadfood_call_openai( $settings, $system, $user ) {
	$res = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
		'headers' => array(
			'Authorization' => 'Bearer ' . $settings['api_key'],
			'Content-Type'  => 'application/json',
		),
		'timeout' => 120,
		'body'    => wp_json_encode( array(
			'model'           => $settings['model'] ?: 'gpt-4o-mini',
			'response_format' => array( 'type' => 'json_object' ),
			'messages'        => array(
				array( 'role' => 'system', 'content' => $system ),
				array( 'role' => 'user',   'content' => $user ),
			),
		) ),
	) );
	if ( is_wp_error( $res ) ) { return $res; }
	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( isset( $body['error']['message'] ) ) {
		return new WP_Error( 'openai_error', $body['error']['message'] );
	}
	return isset( $body['choices'][0]['message']['content'] ) ? $body['choices'][0]['message']['content'] : '';
}

function yadfood_call_gemini( $settings, $system, $user ) {
	$model = $settings['model'] ?: 'gemini-2.5-flash';
	$url   = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . rawurlencode( $settings['api_key'] );
	$res   = wp_remote_post( $url, array(
		'headers' => array( 'Content-Type' => 'application/json' ),
		'timeout' => 120,
		'body'    => wp_json_encode( array(
			'systemInstruction' => array( 'parts' => array( array( 'text' => $system ) ) ),
			'contents'          => array( array( 'role' => 'user', 'parts' => array( array( 'text' => $user ) ) ) ),
			'generationConfig'  => array( 'responseMimeType' => 'application/json' ),
		) ),
	) );
	if ( is_wp_error( $res ) ) { return $res; }
	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( isset( $body['error']['message'] ) ) {
		return new WP_Error( 'gemini_error', $body['error']['message'] );
	}
	return isset( $body['candidates'][0]['content']['parts'][0]['text'] ) ? $body['candidates'][0]['content']['parts'][0]['text'] : '';
}

function yadfood_call_anthropic( $settings, $system, $user ) {
	$res = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
		'headers' => array(
			'x-api-key'        => $settings['api_key'],
			'anthropic-version' => '2023-06-01',
			'Content-Type'     => 'application/json',
		),
		'timeout' => 120,
		'body'    => wp_json_encode( array(
			'model'      => $settings['model'] ?: 'claude-3-5-sonnet-latest',
			'max_tokens' => 4000,
			'system'     => $system,
			'messages'   => array( array( 'role' => 'user', 'content' => $user ) ),
		) ),
	) );
	if ( is_wp_error( $res ) ) { return $res; }
	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( isset( $body['error']['message'] ) ) {
		return new WP_Error( 'anthropic_error', $body['error']['message'] );
	}
	return isset( $body['content'][0]['text'] ) ? $body['content'][0]['text'] : '';
}

/* ---------------- Featured image sideload ---------------- */

function yadfood_set_thumbnail_from_url( $post_id, $url ) {
	if ( ! function_exists( 'media_sideload_image' ) ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
	$id = media_sideload_image( $url, $post_id, null, 'id' );
	if ( ! is_wp_error( $id ) ) {
		set_post_thumbnail( $post_id, $id );
	}
}
