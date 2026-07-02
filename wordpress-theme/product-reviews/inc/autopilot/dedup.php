<?php
/**
 * Autopilot — ASIN dedup engine.
 *
 * Wraps the {prefix}pr_seen_asins table so every product seen by any driver
 * is recorded exactly once and can be looked up cheaply during discovery.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PR_Dedup {

	/** Has this ASIN ever been ingested? */
	public static function is_seen( string $asin ): bool {
		return (bool) self::row( $asin );
	}

	/** Full row for an ASIN (or null). */
	public static function row( string $asin ): ?array {
		global $wpdb;
		$asin = self::normalize( $asin );
		if ( $asin === '' ) { return null; }
		$tbl  = pr_table( 'seen_asins' );
		$row  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl} WHERE asin = %s", $asin ), ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Record an ASIN as seen. Updates last_seen if it already exists.
	 *
	 * @param string   $asin
	 * @param int|null $category_id    review_category term id (optional).
	 * @param int|null $review_post_id Review CPT post id (optional — set once written).
	 */
	public static function mark_seen( string $asin, ?int $category_id = null, ?int $review_post_id = null ): void {
		global $wpdb;
		$asin = self::normalize( $asin );
		if ( $asin === '' ) { return; }
		$now  = current_time( 'mysql', true );
		$tbl  = pr_table( 'seen_asins' );
		$row  = self::row( $asin );
		if ( $row ) {
			$update = array( 'last_seen' => $now );
			if ( $category_id    && empty( $row['category_id'] ) )    { $update['category_id']    = $category_id; }
			if ( $review_post_id && empty( $row['review_post_id'] ) ) { $update['review_post_id'] = $review_post_id; }
			$wpdb->update( $tbl, $update, array( 'asin' => $asin ) );
			return;
		}
		$wpdb->insert( $tbl, array(
			'asin'           => $asin,
			'first_seen'     => $now,
			'last_seen'      => $now,
			'category_id'    => $category_id,
			'review_post_id' => $review_post_id,
		) );
	}

	/** Attach an existing seen ASIN to a freshly created review CPT post. */
	public static function attach_post( string $asin, int $review_post_id ): void {
		global $wpdb;
		$asin = self::normalize( $asin );
		if ( $asin === '' || ! $review_post_id ) { return; }
		$wpdb->update( pr_table( 'seen_asins' ),
			array( 'review_post_id' => $review_post_id, 'last_seen' => current_time( 'mysql', true ) ),
			array( 'asin' => $asin )
		);
	}

	/**
	 * Partition a list of ASINs into [novel, duplicates].
	 * Duplicates carry their existing review_post_id when known.
	 *
	 * @return array{novel:string[], duplicates:array<string,int>}
	 */
	public static function partition( array $asins ): array {
		global $wpdb;
		$asins = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'normalize' ), $asins ) ) ) );
		if ( empty( $asins ) ) {
			return array( 'novel' => array(), 'duplicates' => array() );
		}
		$placeholders = implode( ',', array_fill( 0, count( $asins ), '%s' ) );
		$tbl  = pr_table( 'seen_asins' );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT asin, review_post_id FROM {$tbl} WHERE asin IN ({$placeholders})",
			$asins
		), ARRAY_A );
		$dupes = array();
		foreach ( (array) $rows as $r ) {
			$dupes[ $r['asin'] ] = (int) $r['review_post_id'];
		}
		$novel = array_values( array_diff( $asins, array_keys( $dupes ) ) );
		return array( 'novel' => $novel, 'duplicates' => $dupes );
	}

	/** ASIN normalizer: trim, uppercase, strip non-alphanumeric. */
	public static function normalize( string $asin ): string {
		$asin = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $asin ) );
		return ( strlen( $asin ) >= 8 && strlen( $asin ) <= 14 ) ? $asin : '';
	}
}
