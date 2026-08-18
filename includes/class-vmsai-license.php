<?php
/**
 * License and subscription manager.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles feature gating and subscription status.
 */
class VMSAI_License {

	/**
	 * Current plan slug.
	 *
	 * @return string free|pro|elite
	 */
	public static function plan() {
		$license = get_option( 'vmsai_license', array( 'plan' => 'free' ) );
		return $license['plan'] ?? 'free';
	}

	/**
	 * Check if the active plan is at least a certain level.
	 *
	 * @param string $min_plan free|pro|elite.
	 * @return bool
	 */
	public static function at_least( $min_plan ) {
		$tiers = array( 'free' => 0, 'pro' => 1, 'elite' => 2 );
		$current_weight = $tiers[ self::plan() ] ?? 0;
		$target_weight  = $tiers[ $min_plan ] ?? 0;

		return $current_weight >= $target_weight;
	}

	/**
	 * Gate a feature based on the plan.
	 *
	 * @param string $feature Feature slug.
	 * @return bool
	 */
	public static function has_feature( $feature ) {
		$map = array(
			// PRO Features
			'news_jacking'        => 'pro',
			'viral_rescoring'     => 'pro',
			'strategy_hub'        => 'pro',
			'ab_testing'          => 'pro',
			'unlimited_campaigns' => 'pro',
			'carousel'            => 'pro',

			// ELITE Features
			'video_engine'        => 'elite',
			'voice_lab'           => 'elite',
			'executive_reporting' => 'elite',
			'production_pipeline' => 'elite',
		);

		$required = $map[ $feature ] ?? 'free';

		return self::at_least( $required );
	}

	/**
	 * Get usage limits for the current plan.
	 */
	public static function limits() {
		$plan = self::plan();

		if ( 'elite' === $plan ) {
			return array( 'channels' => 99, 'posts_per_day' => 100, 'label' => 'Elite' );
		}

		if ( 'pro' === $plan ) {
			return array( 'channels' => 10, 'posts_per_day' => 10, 'label' => 'Pro' );
		}

		return array( 'channels' => 1, 'posts_per_day' => 2, 'label' => 'Free' );
	}

	/**
	 * Verify a license key with the remote server.
	 *
	 * @return true|string True on success, error message string on failure.
	 */
	public static function verify( $key ) {
		$key = strtoupper( trim( $key ) );

		if ( ! $key ) {
			update_option( 'vmsai_license', array( 'plan' => 'free', 'status' => 'inactive' ) );
			return true;
		}

		// TEST KEYS for local development
		if ( 'VM-PRO-TEST' === $key ) {
			update_option( 'vmsai_license', array( 'plan' => 'pro', 'status' => 'active', 'key' => $key ) );
			return true;
		}

		if ( 'VM-ELITE-TEST' === $key ) {
			update_option( 'vmsai_license', array( 'plan' => 'elite', 'status' => 'active', 'key' => $key ) );
			return true;
		}

		// LOCAL BRIDGE: If the License Manager is on the SAME site, talk to its REST endpoint via internal request.
		if ( class_exists( 'VM_Licence_Manager' ) ) {
			$request = new WP_REST_Request( 'POST', '/vm-licence-manager/v1/verify' );
			$request->set_param( 'key', $key );
			$request->set_param( 'site_url', home_url() );
			$request->set_param( 'product', 'vm-social-ai-pro' );

			$response_obj = rest_do_request( $request );
			$status       = $response_obj->get_status();
			$res_data     = $response_obj->get_data();

			$response = array(
				'ok'    => ( $status >= 200 && $status < 300 ),
				'json'  => $res_data,
				'error' => ( $status >= 400 ) ? ( $res_data['message'] ?? 'Internal verification failed.' ) : ''
			);
		} else {
			// REAL REMOTE VERIFICATION (Production)
			$response = VMSAI_Http::post( 'https://vmstudio.digital/wp-json/vm-licence-manager/v1/verify', array(
				'json' => array(
					'key'      => $key,
					'site_url' => home_url(),
					'product'  => 'vm-social-ai-pro'
				),
				'timeout' => 15,
			) );
		}

		if ( $response['ok'] && ! empty( $response['json']['plan'] ) ) {
			update_option( 'vmsai_license', array(
				'plan'   => sanitize_key( $response['json']['plan'] ),
				'status' => 'active',
				'key'    => $key,
				'expiry' => $response['json']['expiry'] ?? '',
			) );
			return true;
		}

		$error = $response['error'] ?: 'Verification failed.';
		VMSAI_Logger::error( 'license', 'License verification failed.', array( 'error' => $error ) );
		return $error;
	}
}
