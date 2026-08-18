<?php
/**
 * Telegram channel.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

class VMSAI_Channel_Telegram extends VMSAI_Channel {

	const API = 'https://api.telegram.org/bot';

	public function slug() {
		return 'telegram';
	}

	public function label() {
		return 'Telegram Channel';
	}

	public function credential_fields() {
		return array(
			'telegram_bot_token' => __( 'Bot API Token', 'vm-social-ai-pro' ),
			'telegram_chat_id'   => __( 'Channel/Chat ID (e.g. @mychannel)', 'vm-social-ai-pro' ),
		);
	}

	public function test_connection() {
		$token = VMSAI_Settings::credential( 'telegram_bot_token' );
		if ( ! $token ) return $this->fail( __( 'Missing Bot Token.', 'vm-social-ai-pro' ) );

		$res = VMSAI_Http::get( self::API . $token . '/getMe', array( 'scope' => 'channel.telegram' ) );
		if ( ! $res['ok'] ) return $this->fail( $res['error'] );

		return array( 'ok' => true, 'message' => sprintf( __( 'Connected as @%s.', 'vm-social-ai-pro' ), $res['json']['result']['username'] ?? 'Bot' ) );
	}

	public function publish( array $post ) {
		$token   = VMSAI_Settings::credential( 'telegram_bot_token' );
		$chat_id = VMSAI_Settings::credential( 'telegram_chat_id' );
		$image   = $this->media_url( $post );
		$caption = $this->caption( $post, true, true );

		if ( $image ) {
			$method = '/sendPhoto';
			$payload = array(
				'chat_id' => $chat_id,
				'photo'   => $image,
				'caption' => mb_substr( $caption, 0, 1024 ),
				'parse_mode' => 'HTML'
			);
		} else {
			$method = '/sendMessage';
			$payload = array(
				'chat_id' => $chat_id,
				'text'    => mb_substr( $caption, 0, 4096 ),
				'parse_mode' => 'HTML'
			);
		}

		// Convert basic markdown/unicode bold to simple HTML for Telegram
		$payload['text'] = !empty($payload['text']) ? str_replace( array('**', '🚀'), array('<b>', ''), $payload['text'] ) : '';
		if(isset($payload['caption'])) $payload['caption'] = str_replace( array('**', '🚀'), array('<b>', ''), $payload['caption'] );

		$res = VMSAI_Http::post( self::API . $token . $method, array( 'json' => $payload ) );

		if ( ! $res['ok'] ) return $this->fail( $res['error'] );

		return $this->ok( $res['json']['result']['message_id'], 'https://t.me/' . ltrim($chat_id, '@') );
	}
}
