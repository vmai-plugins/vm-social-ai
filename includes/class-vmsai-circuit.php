<?php
/**
 * Per-provider circuit breaker.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Trips a provider offline after repeated failures so the fallback chain
 * stops paying the timeout cost on every single request.
 */
class VMSAI_Circuit {

	const OPTION = 'vmsai_circuit_state';

	/**
	 * Whether a provider is currently allowed to be called.
	 *
	 * @param string $provider Provider slug.
	 * @return bool
	 */
	public static function is_open( $provider ) {
		$state = self::state();

		if ( empty( $state[ $provider ]['tripped_at'] ) ) {
			return true;
		}

		$cooldown = (int) VMSAI_Settings::get( 'circuit_cooldown', 1800 );
		if ( ( time() - (int) $state[ $provider ]['tripped_at'] ) > $cooldown ) {
			self::reset( $provider );
			return true;
		}

		return false;
	}

	/**
	 * Record a successful call.
	 *
	 * @param string $provider Provider slug.
	 * @return void
	 */
	public static function success( $provider ) {
		$state              = self::state();
		$state[ $provider ] = array(
			'failures'   => 0,
			'tripped_at' => 0,
			'last_ok'    => time(),
			'last_error' => '',
		);
		self::save( $state );
	}

	/**
	 * Record a failed call and trip the breaker if the threshold is crossed.
	 *
	 * @param string $provider Provider slug.
	 * @param string $error    Error message.
	 * @param float  $weight   How much this failure counts toward the trip
	 *                         threshold. A rate limit (429/408) isn't a sign
	 *                         the provider is broken the way a dead key or a
	 *                         500 is — callers should pass a lower weight
	 *                         (e.g. 0.5) for those so a burst of legitimate
	 *                         rate limiting doesn't take a healthy provider
	 *                         offline as fast as real failures would.
	 * @return void
	 */
	public static function failure( $provider, $error = '', $weight = 1.0 ) {
		$state       = self::state();
		$entry       = $state[ $provider ] ?? array( 'failures' => 0, 'tripped_at' => 0, 'last_ok' => 0 );
		$threshold   = max( 2, (int) VMSAI_Settings::get( 'circuit_threshold', 5 ) );
		$was_tripped = ! empty( $entry['tripped_at'] );

		// PRO TERMINAL DETECTION: If the session has expired or the key is invalid,
		// trip the circuit immediately (weight = threshold) to stop wasting requests.
		if ( stripos($error, 'expired') !== false || stripos($error, 'invalid_key') !== false || stripos($error, 'access token') !== false ) {
			$weight = $threshold;
		}

		$entry['failures']   = (float) $entry['failures'] + max( 0.1, (float) $weight );
		$entry['last_error'] = substr( (string) $error, 0, 300 );

		if ( $entry['failures'] >= $threshold ) {
			$entry['tripped_at'] = time();
			VMSAI_Logger::error( 'circuit', sprintf( 'Provider %s tripped after %d failures.', $provider, $entry['failures'] ), array( 'error' => $entry['last_error'] ) );

			// Only worth an email at the moment it actually goes offline,
			// not on every failure that happens while it's already benched.
			if ( ! $was_tripped ) {
				VMSAI_Alerts::provider_tripped( $provider, $entry['last_error'] );
			}
		}

		$state[ $provider ] = $entry;
		self::save( $state );
	}

	/**
	 * Clear a provider's breaker.
	 *
	 * @param string $provider Provider slug.
	 * @return void
	 */
	public static function reset( $provider ) {
		$state = self::state();
		unset( $state[ $provider ] );
		self::save( $state );
	}

	/**
	 * Full health snapshot for the dashboard.
	 *
	 * @return array
	 */
	public static function state() {
		$state = get_option( self::OPTION, array() );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * Persist state.
	 *
	 * @param array $state State array.
	 * @return void
	 */
	private static function save( array $state ) {
		update_option( self::OPTION, $state, false );
	}
}
