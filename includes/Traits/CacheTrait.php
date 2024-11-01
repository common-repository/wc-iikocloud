<?php

namespace WPWC\iikoCloud\Traits;

defined( 'ABSPATH' ) || exit;

use WPWC\iikoCloud\Logs;

trait CacheTrait {

	/**
	 * Divides goods into chunks of 200 pieces and saves them in transients.
	 *
	 * @param  array  $data
	 * @param  string  $type
	 * @param  int  $length
	 *
	 * @return false|void
	 */
	protected function set_cache_by_chunks( array $data, string $type, int $length = 200 ) {

		if ( empty( $data ) ) {
			return false;
		}

		$chunks = array_chunk( $data, $length, true );

		$i = 1;
		foreach ( $chunks as $chunk ) {

			delete_transient( "wc_iikocloud_{$type}_$i" );

			if ( false === set_transient( WC_IIKOCLOUD_PREFIX . "{$type}_$i", $chunk, self::TRANSIENT_EXPIRATION ) ) {
				Logs::add_error( sprintf( esc_html__( 'Cannot set chunk #%s with transient of type - %s', 'wc-iikocloud' ), $i, $type ) );

				return false;
			}

			$i ++;
		}

		set_transient( WC_IIKOCLOUD_PREFIX . "{$type}_count", $i, self::TRANSIENT_EXPIRATION );
	}

	/**
	 * Get and check transient.
	 *
	 * @param  string  $transient  Transient name. Expected to not be SQL-escaped.
	 * @param  string  $title  Transient name for error log.
	 *
	 * @return false|mixed Transient. If an error occurs it returns false and log the error.
	 */
	protected function get_cache( string $transient, string $title ) {

		$transient = get_transient( $transient );
		$message   = sprintf( esc_html__( 'Error while getting iiko %s from the cache. Get the nomenclature one more time.', 'wc-iikocloud' ), $title );

		if ( false === $transient ) {
			Logs::add_error( $message );
			Logs::add_wc_log( $message, 'get-cache', 'error' );

			return false;
		}

		return $transient;
	}

	/**
	 * Get dishes chunks from transients and combine them.
	 *
	 * @param $type
	 *
	 * @return array|mixed
	 */
	protected function get_cache_by_chunks( $type ) {

		$chunks       = [];
		$chunks_count = get_transient( WC_IIKOCLOUD_PREFIX . "{$type}_count" );

		for ( $i = 1; $i <= $chunks_count; $i ++ ) {
			$current_chunk = get_transient( WC_IIKOCLOUD_PREFIX . "{$type}_$i" );

			if ( empty( $current_chunk ) ) {
				continue;
			}

			$chunks += $current_chunk;
		}

		return $chunks;
	}
}
