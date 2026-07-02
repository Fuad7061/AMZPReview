<?php
/**
 * PR_Source_Manager — driver registry, chain, and failover.
 *
 * Public entry point: PR_Source_Manager::search( $kw, $page, $count ).
 * Tries drivers in the configured order; on WP_Error falls through and
 * logs the swap. Per-driver health stored in transients for the admin UI.
 *
 * @package ProductReviews
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PR_Source_Manager {

	/** @var PR_Source[] */
	private static $drivers = null;

	public static function drivers(): array {
		if ( self::$drivers === null ) {
			self::$drivers = array(
				'lambda'   => new PR_Source_Lambda(),
				'scrape'   => new PR_Source_Scrape(),
				'creators' => new PR_Source_Creators(),
				'paapi'    => new PR_Source_PAAPI(),
				'fallback' => new PR_Source_Fallback(),
			);
		}
		return self::$drivers;
	}

	public static function get( string $id ) {
		$d = self::drivers();
		return $d[ $id ] ?? null;
	}

	/** Driver order from settings; defaults to lambda → scrape → creators → paapi → fallback. */
	public static function chain(): array {
		$raw = (string) get_option( 'pr_source_chain', 'lambda,scrape,creators,paapi,fallback' );
		$ids = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
		$d   = self::drivers();
		$out = array();
		foreach ( $ids as $id ) {
			if ( isset( $d[ $id ] ) ) { $out[] = $d[ $id ]; }
		}
		$has_fallback = false;
		foreach ( $out as $driver ) {
			if ( $driver->id() === 'fallback' ) {
				$has_fallback = true;
				break;
			}
		}
		if ( ! $has_fallback && isset( $d['fallback'] ) ) {
			$out[] = $d['fallback'];
		}
		return $out ?: array( $d['lambda'], $d['fallback'] );
	}

	public static function search( string $keyword, int $page = 1, int $count = 10 ) {
		$last_error = null;
		foreach ( self::chain() as $driver ) {
			if ( ! $driver->is_configured() ) {
				continue;
			}
			$started = microtime( true );
			$res     = $driver->search( $keyword, $page, $count );
			$ms      = (int) ( ( microtime( true ) - $started ) * 1000 );

			self::record( $driver->id(), is_wp_error( $res ), $ms, is_wp_error( $res ) ? $res->get_error_message() : '' );

			if ( ! is_wp_error( $res ) && ! empty( $res ) ) {
				return $res;
			}
			$last_error = $res;
		}
		return $last_error instanceof WP_Error
			? $last_error
			: new WP_Error( 'pr_no_driver', __( 'No product-data driver returned results.', 'product-reviews' ) );
	}

	/**
	 * Look up a list of ASINs across the driver chain. Returns one canonical
	 * row per ASIN that any driver could resolve. Used by the monitor loop
	 * to refresh facts (price/rating/availability) for published reviews.
	 *
	 * @param string[] $asins
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	public static function lookup( array $asins ) {
		$asins = array_values( array_unique( array_filter( array_map( 'strtoupper', $asins ) ) ) );
		if ( empty( $asins ) ) { return array(); }
		$out = array();
		$last_error = null;
		foreach ( self::chain() as $driver ) {
			if ( ! $driver->is_configured() ) { continue; }
			foreach ( $asins as $asin ) {
				if ( isset( $out[ $asin ] ) ) { continue; }
				$started = microtime( true );
				$res     = $driver->get_item( $asin );
				$ms      = (int) ( ( microtime( true ) - $started ) * 1000 );
				self::record( $driver->id(), is_wp_error( $res ), $ms, is_wp_error( $res ) ? $res->get_error_message() : '' );
				if ( ! is_wp_error( $res ) && ! empty( $res ) ) {
					$row = is_array( $res ) && isset( $res[0] ) ? $res[0] : $res;
					if ( is_array( $row ) ) {
						$row['asin']   = $row['asin'] ?? $asin;
						$row['source'] = $row['source'] ?? $driver->id();
						$out[ $asin ]  = $row;
					}
				} else {
					$last_error = $res instanceof WP_Error ? $res : $last_error;
				}
			}
			if ( count( $out ) === count( $asins ) ) { break; }
		}
		if ( empty( $out ) && $last_error ) { return $last_error; }
		return array_values( $out );
	}

	public static function record( string $id, bool $error, int $latency_ms, string $message = '' ): void {
		$key   = 'pr_source_health_' . $id;
		$stats = get_transient( $key );
		if ( ! is_array( $stats ) ) {
			$stats = array( 'ok' => 0, 'err' => 0, 'last_ms' => 0, 'last_msg' => '', 'last_ts' => 0 );
		}
		$stats[ $error ? 'err' : 'ok' ]++;
		$stats['last_ms']  = $latency_ms;
		$stats['last_msg'] = $message;
		$stats['last_ts']  = time();
		set_transient( $key, $stats, HOUR_IN_SECONDS );
	}

	public static function health( string $id ): array {
		$s = get_transient( 'pr_source_health_' . $id );
		return is_array( $s ) ? $s : array( 'ok' => 0, 'err' => 0, 'last_ms' => 0, 'last_msg' => '', 'last_ts' => 0 );
	}
}
