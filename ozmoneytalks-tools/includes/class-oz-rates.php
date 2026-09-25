<?php
/**
 * AUD→INR reference rate from Frankfurter (European Central Bank data).
 *
 * One request fetches ~45 days of daily rates; the latest entry is today's rate.
 * Results are cached for an hour, and the last good result is kept so the tools
 * still work if the API is down.
 */

defined( 'ABSPATH' ) || exit;

class Oz_Tools_Rates {

	const CACHE_KEY = 'oz_tools_rate_cache';
	const LAST_GOOD = 'oz_tools_rate_last_good';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route( 'oz-tools/v1', '/rate', array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => function () {
				$data = self::get();
				if ( ! $data ) {
					return new WP_Error( 'oz_rate_unavailable', 'Exchange rate unavailable right now.', array( 'status' => 503 ) );
				}
				return rest_ensure_response( $data );
			},
		) );
	}

	/**
	 * @param bool $force Skip the one-hour cache (used by the daily alert job).
	 * @return array|null { rate, date, history: [[date, rate], ...], stale }
	 */
	public static function get( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( $cached ) {
				return $cached;
			}
		}

		$fresh = self::fetch();
		if ( $fresh ) {
			set_transient( self::CACHE_KEY, $fresh, HOUR_IN_SECONDS );
			update_option( self::LAST_GOOD, $fresh, false );
			return $fresh;
		}

		$last = get_option( self::LAST_GOOD );
		if ( $last ) {
			$last['stale'] = true;
			// Don't hammer the API while it's down.
			set_transient( self::CACHE_KEY, $last, 10 * MINUTE_IN_SECONDS );
			return $last;
		}
		return null;
	}

	private static function fetch() {
		$api   = untrailingslashit( oz_tools_config()['fx_api'] );
		$start = gmdate( 'Y-m-d', time() - 45 * DAY_IN_SECONDS );
		$url   = add_query_arg( array( 'base' => 'AUD', 'symbols' => 'INR' ), $api . '/' . $start . '..' );

		$response = wp_remote_get( $url, array( 'timeout' => 8 ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['rates'] ) || ! is_array( $body['rates'] ) ) {
			return null;
		}

		$history = array();
		foreach ( $body['rates'] as $date => $rates ) {
			if ( isset( $rates['INR'] ) && is_numeric( $rates['INR'] ) ) {
				$history[] = array( (string) $date, round( (float) $rates['INR'], 4 ) );
			}
		}
		if ( ! $history ) {
			return null;
		}
		usort( $history, function ( $a, $b ) {
			return strcmp( $a[0], $b[0] );
		} );
		$latest = end( $history );

		return array(
			'rate'    => $latest[1],
			'date'    => $latest[0],
			'history' => array_slice( $history, -30 ),
			'stale'   => false,
		);
	}
}
