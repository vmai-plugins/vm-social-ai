<?php
/**
 * Mobile Command Center — Telegram Bot Control.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

class VMSAI_Telegram_Bot {

	const BASE_URL = 'https://api.telegram.org/bot';

	/**
	 * Register the bot's REST endpoint.
	 */
	public function register() {
		add_action( 'rest_api_init', function() {
			register_rest_route( 'vm-social-ai/v1', '/telegram/webhook', array(
				'methods'  => 'POST',
				'callback' => array( $this, 'handle_webhook' ),
				// The claim in the old comment here ("Telegram validates via
				// Token in URL or payload") was never actually true — nothing
				// checked Telegram's secret_token header, so anyone who found
				// this URL (printed in plain text on the Channels admin page)
				// could POST forged updates directly. verify_request() below
				// checks it when a secret is configured (see channels.php for
				// the header to set on Telegram's own setWebhook call); it
				// stays permissive when none is set so existing webhooks set
				// up before this fix don't break.
				'permission_callback' => array( $this, 'verify_request' ),
			) );
		} );
	}

	/**
	 * Verify the request actually came from Telegram, when a webhook
	 * secret has been configured.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function verify_request( WP_REST_Request $request ) {
		$secret = VMSAI_Settings::credential( 'telegram_webhook_secret' );
		if ( ! $secret ) {
			return true;
		}
		$sent = (string) $request->get_header( 'X-Telegram-Bot-Api-Secret-Token' );
		return hash_equals( (string) $secret, $sent );
	}

	/**
	 * The webhook secret Telegram is expected to echo back on every update
	 * via the X-Telegram-Bot-Api-Secret-Token header (set as the
	 * secret_token parameter on Telegram's own setWebhook call — see the
	 * Channels admin screen). Generated and persisted once on first use.
	 *
	 * @return string
	 */
	public static function webhook_secret() {
		$secret = VMSAI_Settings::credential( 'telegram_webhook_secret' );
		if ( ! $secret ) {
			$secret = wp_generate_password( 32, false, false );
			VMSAI_Settings::update_credentials( array( 'telegram_webhook_secret' => $secret ) );
		}
		return $secret;
	}

	/**
	 * Send an alert to the owner.
	 */
	public static function alert( $message, $keyboard = array() ) {
		$token = VMSAI_Settings::credential( 'telegram_bot_token' );
		$chat_id = VMSAI_Settings::get( 'telegram_owner_id' );

		if ( ! $token || ! $chat_id ) return;

		$payload = array(
			'chat_id'    => $chat_id,
			'text'       => $message,
			'parse_mode' => 'HTML',
		);

		if ( ! empty( $keyboard ) ) {
			$payload['reply_markup'] = array( 'inline_keyboard' => $keyboard );
		}

		VMSAI_Http::post( self::BASE_URL . $token . '/sendMessage', array( 'json' => $payload ) );
	}

	/**
	 * Handle incoming messages from Telegram. Rejects anything not from the
	 * paired owner chat or not signed for the configured bot.
	 */
	public function handle_webhook( WP_REST_Request $request ) {
		$data = $request->get_json_params();
		if ( empty( $data['message'] ) && empty( $data['callback_query'] ) ) return new WP_REST_Response( array( 'ok' => true ), 200 );

		$token = VMSAI_Settings::credential( 'telegram_bot_token' );

		// 1. Handle Button Clicks
		if ( ! empty( $data['callback_query'] ) ) {
			return $this->handle_callback( $data['callback_query'] );
		}

		$msg     = $data['message'];
		$chat_id = isset( $msg['chat']['id'] ) ? (int) $msg['chat']['id'] : 0;
		$text    = isset( $msg['text'] ) ? (string) $msg['text'] : '';

		// 2. Command: /start (Secure the bot)
		if ( strpos( $text, '/start' ) === 0 ) {
			// The pairing secret is a fixed, never-rotating 8-hex-char value
			// (32 bits) — throttle guesses instead of allowing unlimited
			// attempts against it.
			$attempts_key = 'vmsai_tg_pair_attempts';
			$attempts     = (int) get_transient( $attempts_key );
			if ( $attempts >= 10 ) {
				return new WP_REST_Response( array( 'ok' => true ), 200 );
			}

			$pass = substr( $text, 7 );
			if ( $pass === substr( wp_hash( home_url() ), 0, 8 ) ) {
				delete_transient( $attempts_key );
				VMSAI_Settings::update( array( 'telegram_owner_id' => $chat_id ) );
				$this->reply( $chat_id, "🤝 <b>Command Center Connected!</b>\nYou are now the authorized owner of this Social AI instance." );
			} else {
				set_transient( $attempts_key, $attempts + 1, HOUR_IN_SECONDS );
				$this->reply( $chat_id, "❌ <b>Authorization Failed.</b>\nPlease use the secret link provided in your Social AI Settings." );
			}
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		// Security: Only process if an owner is actually paired and matches.
		// A bare chat_id !== check without the <= 0 guard let a chat_id of 0
		// (an omitted/forged chat.id) satisfy the comparison whenever
		// telegram_owner_id was still unset — a forged request could then
		// run commands like /write before anyone ever paired the bot. This
		// mirrors the guard handle_callback() already has below.
		$owner = (int) VMSAI_Settings::get( 'telegram_owner_id' );
		if ( $owner <= 0 || $chat_id !== $owner ) {
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		// 3. Command: /write (Storyteller)
		if ( strpos( $text, '/write' ) === 0 ) {
			$topic = trim( substr( $text, 7 ) );
			if ( ! $topic ) return $this->reply( $chat_id, "Please provide a topic. e.g. /write Our new office opening." );

			$this->reply( $chat_id, "✍️ <b>The Storyteller is drafting...</b>" );

			// Inject into immediate plan
			VMSAI_Research::inject( $topic );

			// Run the tick to compose it
			$scheduler = new VMSAI_Scheduler();
			$scheduler->tick();

			$this->reply( $chat_id, "✅ <b>Drafted!</b> Check your War Room or wait for the approval alert." );
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	private function handle_callback( $query ) {
		$chat_id = isset( $query['message']['chat']['id'] ) ? (int) $query['message']['chat']['id'] : 0;

		// Security: callbacks change post state — require the paired owner,
		// exactly like the message path. Reject forged/foreign chats.
		$owner = (int) VMSAI_Settings::get( 'telegram_owner_id' );
		if ( $owner <= 0 || $chat_id !== $owner ) {
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		$data    = isset( $query['data'] ) ? (string) $query['data'] : ''; // Format: action:id
		$parts   = explode( ':', $data );
		$action  = isset( $parts[0] ) ? sanitize_key( $parts[0] ) : '';
		$id      = isset( $parts[1] ) ? (int) $parts[1] : 0;

		if ( $id <= 0 || ! in_array( $action, array( 'approve', 'reject' ), true ) ) {
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		global $wpdb;
		$table = VMSAI_Install::table( 'queue' );

		if ( 'approve' === $action ) {
			$wpdb->update( $table, array( 'status' => 'approved', 'attempts' => 0 ), array( 'id' => $id ) );
			$this->reply( $chat_id, "🚀 <b>Post #{$id} Approved!</b>" );
		} elseif ( 'reject' === $action ) {
			$wpdb->update( $table, array( 'status' => 'draft' ), array( 'id' => $id ) );
			$this->reply( $chat_id, "✍️ <b>Post #{$id} sent back to drafts.</b>" );
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	private function reply( $chat_id, $text ) {
		$token = VMSAI_Settings::credential( 'telegram_bot_token' );
		VMSAI_Http::post( self::BASE_URL . $token . '/sendMessage', array(
			'json' => array( 'chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML' )
		) );
	}
}
