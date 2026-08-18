<?php
/**
 * Proactive failure alerting.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sends a plain admin email the moment something goes from "one failed
 * request" to "this needs a human" — a provider circuit tripping, or a
 * queued post giving up after exhausting its retries. Rate-limited per
 * subject so a bad afternoon doesn't turn into an inbox flood.
 */
class VMSAI_Alerts {

	/**
	 * Minimum gap between two emails about the same subject.
	 */
	const COOLDOWN = 6 * HOUR_IN_SECONDS;

	/**
	 * A provider (text/image engine or channel) just tripped its circuit
	 * breaker — i.e. failed enough consecutive times that the chain has
	 * benched it for the cooldown window.
	 *
	 * @param string $provider Circuit key, e.g. 'text:gemini'.
	 * @param string $error    Last error message.
	 * @return void
	 */
	public static function provider_tripped( $provider, $error ) {
		self::notify(
			'trip:' . $provider,
			sprintf( /* translators: %s: provider key */ __( '[VM Social AI] %s stopped working', 'vm-social-ai-pro' ), $provider ),
			sprintf(
				/* translators: 1: provider key, 2: error message */
				__( "%1\$s failed enough times in a row that VM Social AI has benched it for a while. The rest of your chain will keep working, but this link needs attention.\n\nLast error:\n%2\$s\n\nCheck it under Engines or Channels in VM Social AI.", 'vm-social-ai-pro' ),
				$provider,
				$error
			)
		);
	}

	/**
	 * A queued post exhausted its retries and was retired as permanently
	 * failed — nothing will pick it up again without manual action.
	 *
	 * @param int    $queue_id Queue row id.
	 * @param string $channel  Channel slug.
	 * @param string $error    Last error message.
	 * @return void
	 */
	public static function post_retired( $queue_id, $channel, $error ) {
		self::notify(
			'retired:' . $channel,
			sprintf( /* translators: %s: channel name */ __( '[VM Social AI] A %s post gave up after repeated failures', 'vm-social-ai-pro' ), ucfirst( $channel ) ),
			sprintf(
				/* translators: 1: queue id, 2: channel, 3: error message */
				__( "Post #%1\$d for %2\$s failed on every retry and has been marked failed — it will not be tried again automatically.\n\nLast error:\n%3\$s\n\nReview it in the Queue tab.", 'vm-social-ai-pro' ),
				$queue_id,
				$channel,
				$error
			)
		);
	}

	/**
	 * Send once per subject per cooldown window.
	 *
	 * @param string $subject_key Dedupe key.
	 * @param string $subject     Email subject.
	 * @param string $body        Email body.
	 * @return void
	 */
	private static function notify( $subject_key, $subject, $body ) {
		if ( ! VMSAI_Settings::get( 'alert_on_failure', 1 ) ) {
			return;
		}

		$lock_key = 'vmsai_alert_' . md5( $subject_key );
		if ( get_transient( $lock_key ) ) {
			return;
		}
		set_transient( $lock_key, 1, self::COOLDOWN );

		$to = VMSAI_Settings::get( 'alert_email', '' ) ?: get_option( 'admin_email' );

		if ( ! $to ) {
			return;
		}

		wp_mail( $to, $subject, $body );
	}
}
