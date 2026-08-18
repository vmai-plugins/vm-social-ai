<?php
/**
 * Usage and billing tracker.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tracks every AI interaction (tokens, costs, modality) to provide a
 * transparency dashboard for the user.
 */
class VMSAI_Usage {

	/**
	 * Check if the current plan allows more generations today.
	 *
	 * @return array{ok:bool,message:string}
	 */
	public static function check_allowance() {
		$limits = VMSAI_License::limits();

		global $wpdb;
		$table = VMSAI_Install::table( 'usage' );

		// Count generations in the last 24 hours.
		// A 'post' might involve multiple generations (text + image), so we count
		// unique 'generation' events for text specifically as the anchor for a finished post.
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table` WHERE usage_type = 'generation' AND modality = 'text' AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)" );

		if ( $count >= $limits['posts_per_day'] ) {
			return array(
				'ok'      => false,
				'message' => sprintf(
					__( "Daily limit reached. Your %s plan allows %d generations per day. Upgrade to unlock more.", 'vm-social-ai-pro' ),
					$limits['label'],
					$limits['posts_per_day']
				)
			);
		}

		return array( 'ok' => true, 'message' => '' );
	}

	/**
	 * Record a usage event.
	 *
	 * @param array $args {
	 *     @type string $provider   Provider slug.
	 *     @type string $model      Model name.
	 *     @type string $modality   text|image.
	 *     @type string $usage_type generation|sync|audit.
	 *     @type int    $tokens_in  Input tokens (or prompts).
	 *     @type int    $tokens_out Output tokens (or images).
	 *     @type float  $cost       Optional explicit cost.
	 * }
	 * @return void
	 */
	public static function record( array $args ) {
		global $wpdb;

		$data = wp_parse_args( $args, array(
			'provider'   => 'unknown',
			'model'      => 'unknown',
			'modality'   => 'text',
			'usage_type' => 'generation',
			'tokens_in'  => 0,
			'tokens_out' => 0,
			'cost'       => 0,
		) );

		// If cost isn't provided, try to estimate it.
		if ( 0.0 === (float) $data['cost'] ) {
			$data['cost'] = self::estimate_cost( $data );
		}

		$wpdb->insert(
			VMSAI_Install::table( 'usage' ),
			array(
				'provider'   => sanitize_key( $data['provider'] ),
				'model'      => sanitize_text_field( $data['model'] ),
				'modality'   => sanitize_key( $data['modality'] ),
				'usage_type' => sanitize_key( $data['usage_type'] ),
				'tokens_in'  => (int) $data['tokens_in'],
				'tokens_out' => (int) $data['tokens_out'],
				'cost'       => (float) $data['cost'],
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%f', '%s' )
		);
	}

	/**
	 * Rough cost estimation for transparency when the API doesn't return it.
	 * Rates are USD per 1k tokens (text) or per image.
	 */
	private static function estimate_cost( $data ) {
		$model = strtolower( $data['model'] );

		if ( 'image' === $data['modality'] ) {
			if ( strpos( $model, 'dall-e-3' ) !== false ) return 0.040;
			if ( strpos( $model, 'dall-e-2' ) !== false ) return 0.020;
			return 0.000; // Pollinations/Local are free.
		}

		// Text rates (per 1k tokens combined).
		$rates = array(
			'gpt-4o'       => 0.010,
			'gpt-4o-mini'  => 0.001,
			'gemini-1.5-p' => 0.005,
			'gemini-1.5-f' => 0.001,
			'claude-3-5'   => 0.009,
		);

		foreach ( $rates as $slug => $rate ) {
			if ( false !== strpos( $model, $slug ) ) {
				$total_tokens = $data['tokens_in'] + $data['tokens_out'];
				return ( $total_tokens / 1000 ) * $rate;
			}
		}

		return 0.000;
	}

	/**
	 * Summarize usage for a period.
	 */
	public static function stats( $days = 30 ) {
		global $wpdb;
		$table = VMSAI_Install::table( 'usage' );

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT
				SUM(tokens_in) as total_in,
				SUM(tokens_out) as total_out,
				SUM(cost) as total_cost,
				COUNT(*) as total_calls
			 FROM `$table` WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)",
			(int) $days
		), ARRAY_A );

		return array(
			'total_in'    => (int) ( $row['total_in'] ?? 0 ),
			'total_out'   => (int) ( $row['total_out'] ?? 0 ),
			'total_cost'  => (float) ( $row['total_cost'] ?? 0 ),
			'total_calls' => (int) ( $row['total_calls'] ?? 0 ),
		);
	}

	/**
	 * Usage breakdown by model/provider.
	 */
	public static function breakdown( $days = 30 ) {
		global $wpdb;
		$table = VMSAI_Install::table( 'usage' );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT provider, model, modality, SUM(tokens_in + tokens_out) as tokens, SUM(cost) as cost, COUNT(*) as calls
			 FROM `$table` WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
			 GROUP BY provider, model, modality ORDER BY cost DESC",
			(int) $days
		), ARRAY_A );
	}
}
