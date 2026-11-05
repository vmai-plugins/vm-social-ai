<?php
/**
 * License and subscription manager.
 * Sovereign Free & Unlimited Edition.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles feature gating and subscription status.
 * All features, engines, and channels are 100% unlocked and free.
 */
class VMSAI_License {

	/**
	 * Current plan slug. Free and sovereign without paid restrictions.
	 *
	 * @return string elite
	 */
	public static function plan() {
		return 'elite';
	}

	/**
	 * Check if the active plan is at least a certain level.
	 *
	 * @param string $min_plan free|pro|elite.
	 * @return bool
	 */
	public static function at_least( $min_plan ) {
		return true;
	}

	/**
	 * Gate a feature based on the plan.
	 * All features permanently unlocked and free.
	 *
	 * @param string $feature Feature slug.
	 * @return bool
	 */
	public static function has_feature( $feature ) {
		return true;
	}

	/**
	 * Get usage limits for the current plan.
	 * Unlimited channels, posts, and campaigns.
	 */
	public static function limits() {
		return array(
			'channels'      => 9999,
			'posts_per_day' => 9999,
			'label'         => 'Sovereign Edition (Free & Unlimited)',
		);
	}

	/**
	 * Verify a license key with the remote server. Always active.
	 *
	 * @return true
	 */
	public static function verify( $key = '' ) {
		return true;
	}
}
