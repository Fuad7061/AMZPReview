<?php
/**
 * Autopilot — facts store + change detection.
 *
 * Persists structured per-product facts (price, rating, review_count, title,
 * image, bullets, brand …) to {prefix}pr_facts and diffs new snapshots
 * against the latest stored values, logging each material change to
 * {prefix}pr_change_log.
 *
 * Material change rules (defaults, filterable via `pr_facts_material`):
 *   - price          → diff > 1% or > $1
 *   - rating         → diff >= 0.1 stars
 *   - review_count   → diff > 10% or > 25
 *   - availability   → any change
 *   - title / image  → any change
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PR_Facts {

	const FACT_KEYS = array(
		'title', 'brand', 'image', 'url', 'price', 'currency',
		'rating', 'review_count', 'availability', 'bullets', 'asin',
	);

	/**
	 * Write a normalized product snapshot for an article. Returns the
	 * list of changed fields detected vs the latest prior snapshot.
	 *
	 * @param int                  $article_id Review CPT post id (0 = orphan / discovery-only).
	 * @param array<string,mixed>  $product    Normalized product dict.
	 * @param string               $driver     Source driver id (lambda/scrape/…).
	 * @return string[] List of changed field names.
	 */
	public static function record_snapshot( int $article_id, array $product, string $driver = 'lambda' ): array {
		global $wpdb;
		$asin = isset( $product['asin'] ) ? PR_Dedup::normalize( (string) $product['asin'] ) : '';
		if ( $asin === '' ) { return array(); }

		$prior = self::latest_for_asin( $article_id, $asin );
		$now   = current_time( 'mysql', true );
		$changed = array();

		foreach ( self::FACT_KEYS as $key ) {
			if ( ! array_key_exists( $key, $product ) ) { continue; }
			$value = self::serialize_value( $product[ $key ] );
			$prev  = $prior[ $key ] ?? null;

			if ( self::is_material_change( $key, $prev, $value ) ) {
				$changed[] = $key;
				if ( $article_id ) {
					$wpdb->insert( pr_table( 'change_log' ), array(
						'article_id' => $article_id,
						'asin'       => $asin,
						'field'      => $key,
						'old_value'  => $prev,
						'new_value'  => $value,
						'ts'         => $now,
					) );
				}
			}

			$wpdb->insert( pr_table( 'facts' ), array(
				'article_id'    => $article_id,
				'asin'          => $asin,
				'fact_key'      => $key,
				'fact_type'     => self::type_for( $key ),
				'value'         => $value,
				'source_driver' => $driver,
				'source_url'    => isset( $product['url'] ) ? (string) $product['url'] : null,
				'confidence'    => 0.90,
				'checked_at'    => $now,
			) );
		}
		return $changed;
	}

	/** Latest stored value per fact_key for (article_id, asin). */
	public static function latest_for_asin( int $article_id, string $asin ): array {
		global $wpdb;
		$tbl = pr_table( 'facts' );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT f1.fact_key, f1.value
			   FROM {$tbl} f1
			   JOIN ( SELECT fact_key, MAX(id) AS mid FROM {$tbl}
			          WHERE article_id = %d AND asin = %s GROUP BY fact_key ) f2
			     ON f1.id = f2.mid",
			$article_id, $asin
		), ARRAY_A );
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[ $r['fact_key'] ] = $r['value'];
		}
		return $out;
	}

	private static function serialize_value( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return (string) wp_json_encode( $value );
		}
		if ( is_bool( $value ) ) { return $value ? '1' : '0'; }
		return (string) $value;
	}

	private static function type_for( string $key ): string {
		return in_array( $key, array( 'price', 'rating', 'review_count' ), true ) ? 'number'
			: ( $key === 'bullets' ? 'json' : 'string' );
	}

	private static function is_material_change( string $key, $prev, $next ): bool {
		if ( $prev === null ) { return true; }
		if ( (string) $prev === (string) $next ) { return false; }

		switch ( $key ) {
			case 'price':
				$a = (float) $prev; $b = (float) $next;
				$delta = abs( $a - $b );
				$pct   = $a > 0 ? $delta / $a : 1.0;
				return ( $delta > 1.0 ) || ( $pct > 0.01 );
			case 'rating':
				return abs( (float) $prev - (float) $next ) >= 0.1;
			case 'review_count':
				$a = (int) $prev; $b = (int) $next;
				$delta = abs( $a - $b );
				$pct   = $a > 0 ? $delta / $a : 1.0;
				return ( $delta > 25 ) || ( $pct > 0.10 );
			default:
				return apply_filters( 'pr_facts_material', true, $key, $prev, $next );
		}
	}

	/**
	 * Did anything material change between this snapshot list and what we
	 * already have on file for the same article? Used by the monitor loop
	 * to decide whether to enqueue an UPDATE job.
	 *
	 * @param int   $article_id
	 * @param array $products Normalized product list.
	 * @return array<string,string[]> Map of asin → list of changed fields.
	 */
	public static function detect_changes( int $article_id, array $products ): array {
		$out = array();
		foreach ( $products as $p ) {
			$asin = isset( $p['asin'] ) ? PR_Dedup::normalize( (string) $p['asin'] ) : '';
			if ( $asin === '' ) { continue; }
			$prior = self::latest_for_asin( $article_id, $asin );
			$diffs = array();
			foreach ( self::FACT_KEYS as $key ) {
				if ( ! array_key_exists( $key, $p ) ) { continue; }
				if ( self::is_material_change( $key, $prior[ $key ] ?? null, self::serialize_value( $p[ $key ] ) ) ) {
					$diffs[] = $key;
				}
			}
			if ( ! empty( $diffs ) ) { $out[ $asin ] = $diffs; }
		}
		return $out;
	}
}
