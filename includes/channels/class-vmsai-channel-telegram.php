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
				'caption' => self::to_telegram_html( $caption, 1024 ),
				'parse_mode' => 'HTML'
			);
		} else {
			$method = '/sendMessage';
			$payload = array(
				'chat_id' => $chat_id,
				'text'    => self::to_telegram_html( $caption, 4096 ),
				'parse_mode' => 'HTML'
			);
		}

		$res = VMSAI_Http::post( self::API . $token . $method, array( 'json' => $payload ) );

		if ( ! $res['ok'] ) return $this->fail( $res['error'] );

		return $this->ok( $res['json']['result']['message_id'], 'https://t.me/' . ltrim($chat_id, '@') );
	}

	/**
	 * Escape a caption for parse_mode=HTML and convert Markdown bold.
	 *
	 * Convert first (pairing **open** **close**), then truncate, so the
	 * length cap cannot slice a tag in half. Emoji are preserved.
	 *
	 * @param string $text  Raw caption.
	 * @param int    $limit Max characters after conversion.
	 * @return string
	 */
	private static function to_telegram_html( $text, $limit = 4096 ) {
		$text = (string) $text;
		if ( '' === trim( $text ) ) {
			return '';
		}
		$escaped = htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
		// Pair **open** **close**; an odd trailing ** is dropped.
		$parts = explode( '**', $escaped );
		$out   = array_shift( $parts );
		foreach ( $parts as $i => $chunk ) {
			$out .= ( 0 === $i % 2 ) ? '<b>' . $chunk : $chunk . '</b>';
		}
		// Remove a dangling opener left by an odd count.
		$count = substr_count( $out, '<b>' ) - substr_count( $out, '</b>' );
		if ( $count > 0 ) {
			$pos = strrpos( $out, '<b>' );
			if ( false !== $pos ) {
				$out = substr_replace( $out, '', $pos, 3 );
			}
		}
		if ( $limit > 0 && mb_strlen( $out ) > $limit ) {
			$out = mb_substr( $out, 0, $limit );
			// Truncation must not leave a half-written tag.
			$open  = substr_count( $out, '<b>' );
			$close = substr_count( $out, '</b>' );
			if ( $open > $close ) {
				$out .= str_repeat( '</b>', $open - $close );
				if ( mb_strlen( $out ) > $limit ) {
					$out = mb_substr( $out, 0, $limit );
				}
			}
			$lt = strrpos( $out, '<' );
			if ( false !== $lt && false === strpos( $out, '>', $lt ) ) {
				$out = substr( $out, 0, $lt );
			}
		}
		return $out;
	}
}
