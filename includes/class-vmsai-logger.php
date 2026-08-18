<?php
/**
 * Structured logging.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Writes engine and dispatch events to the vmsai_logs table.
 */
class VMSAI_Logger {

	/**
	 * Severity ranking used for threshold filtering.
	 *
	 * @var array<string,int>
	 */
	private static $ranks = array(
		'debug' => 10,
		'info'  => 20,
		'warn'  => 30,
		'error' => 40,
	);

	/**
	 * Write a log line.
	 *
	 * @param string $level   debug|info|warn|error.
	 * @param string $scope   Subsystem, e.g. "engine.text".
	 * @param string $message Human readable message.
	 * @param array  $context Extra data, JSON encoded.
	 * @return void
	 */
	public static function log( $level, $scope, $message, array $context = array() ) {
		$threshold = self::$ranks[ VMSAI_Settings::get( 'log_level', 'info' ) ] ?? 20;
		$rank      = self::$ranks[ $level ] ?? 20;

		// Force error/warn levels to always log for easier debugging
		if ( $rank < $threshold && $rank < 30 ) {
			return;
		}

		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			VMSAI_Install::table( 'logs' ),
			array(
				'level'      => $level,
				'scope'      => substr( $scope, 0, 40 ),
				'message'    => substr( (string) $message, 0, 1000 ), // Increased limit
				'context'    => $context ? substr( wp_json_encode( self::redact( $context ) ), 0, 4000 ) : null, // Increased limit
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/** @param string $s Scope. @param string $m Message. @param array $c Context. @return void */
	public static function debug( $s, $m, array $c = array() ) {
		self::log( 'debug', $s, $m, $c );
	}

	/** @param string $s Scope. @param string $m Message. @param array $c Context. @return void */
	public static function info( $s, $m, array $c = array() ) {
		self::log( 'info', $s, $m, $c );
	}

	/** @param string $s Scope. @param string $m Message. @param array $c Context. @return void */
	public static function warn( $s, $m, array $c = array() ) {
		self::log( 'warn', $s, $m, $c );
	}

	/** @param string $s Scope. @param string $m Message. @param array $c Context. @return void */
	public static function error( $s, $m, array $c = array() ) {
		self::log( 'error', $s, $m, $c );
	}

	/**
	 * Strip anything that looks like a secret before persisting.
	 *
	 * @param array $context Context array.
	 * @return array
	 */
	private static function redact( array $context ) {
		$sensitive = array( 'key', 'token', 'secret', 'password', 'authorization', 'bearer' );

		foreach ( $context as $k => $v ) {
			if ( is_array( $v ) ) {
				$context[ $k ] = self::redact( $v );
				continue;
			}
			foreach ( $sensitive as $needle ) {
				if ( false !== stripos( (string) $k, $needle ) ) {
					$context[ $k ] = '[redacted]';
					break;
				}
			}
		}

		return $context;
	}

	/**
	 * Fetch recent lines for the admin log view.
	 *
	 * @param int    $limit Row count.
	 * @param string $level Optional level filter.
	 * @return array
	 */
	public static function recent( $limit = 200, $level = '' ) {
		global $wpdb;
		$table = VMSAI_Install::table( 'logs' );
		$limit = max( 1, min( 1000, (int) $limit ) );

		if ( $level ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$table` WHERE level = %s ORDER BY id DESC LIMIT %d", $level, $limit ), ARRAY_A ); // phpcs:ignore
		}
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `$table` ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A ); // phpcs:ignore
	}

	/**
	 * Delete logs older than the retention window.
	 *
	 * @return void
	 */
	public static function prune() {
		global $wpdb;
		$table = VMSAI_Install::table( 'logs' );
		$days  = max( 7, (int) VMSAI_Settings::get( 'retention_days', 120 ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM `$table` WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)", $days ) ); // phpcs:ignore
	}
}
