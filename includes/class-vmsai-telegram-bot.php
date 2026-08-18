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
				'permission_callback' => '__return_true', // Telegram validates via Token in URL or payload
			) );
		} );
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
	 * Handle incoming messages from Telegram.
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
		$chat_id = $msg['chat']['id'];
		$text    = $msg['text'] ?? '';

		// 2. Command: /start (Secure the bot)
		if ( strpos( $text, '/start' ) === 0 ) {
			$pass = substr( $text, 7 );
			if ( $pass === substr( wp_hash( home_url() ), 0, 8 ) ) {
				VMSAI_Settings::update( array( 'telegram_owner_id' => $chat_id ) );
				$this->reply( $chat_id, "🤝 <b>Command Center Connected!</b>\nYou are now the authorized owner of this Social AI instance." );
			} else {
				$this->reply( $chat_id, "❌ <b>Authorization Failed.</b>\nPlease use the secret link provided in your Social AI Settings." );
			}
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		// Security: Only process if owner matches
		if ( (int) $chat_id !== (int) VMSAI_Settings::get( 'telegram_owner_id' ) ) {
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
		$chat_id = $query['message']['chat']['id'];
		$data    = $query['data']; // Format: action:id
		$parts   = explode( ':', $data );
		$action  = $parts[0];
		$id      = (int) $parts[1];

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
