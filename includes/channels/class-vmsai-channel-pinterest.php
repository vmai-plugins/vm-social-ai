<?php
/**
 * Pinterest channel.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

class VMSAI_Channel_Pinterest extends VMSAI_Channel {

	const API = 'https://api.pinterest.com/v5';

	public function slug() {
		return 'pinterest';
	}

	public function label() {
		return 'Pinterest';
	}

	public function credential_fields() {
		return array(
			'pinterest_token'    => __( 'Pinterest Access Token', 'vm-social-ai-pro' ),
			'pinterest_board_id' => __( 'Default Board ID', 'vm-social-ai-pro' ),
		);
	}

	public function test_connection() {
		$token = VMSAI_Settings::credential( 'pinterest_token' );
		if ( ! $token ) return $this->fail( __( 'Missing Pinterest Token.', 'vm-social-ai-pro' ) );

		$res = VMSAI_Http::get( self::API . '/user_account', array(
			'headers' => array( 'Authorization' => 'Bearer ' . $token ),
			'scope'   => 'channel.pinterest'
		) );

		if ( ! $res['ok'] ) return $this->fail( $res['error'] );

		return array( 'ok' => true, 'message' => sprintf( __( 'Connected as %s.', 'vm-social-ai-pro' ), $res['json']['username'] ?? 'User' ) );
	}

	public function publish( array $post ) {
		$token    = VMSAI_Settings::credential( 'pinterest_token' );
		$board_id = VMSAI_Settings::credential( 'pinterest_board_id' );
		$image    = $this->media_url( $post );

		if ( ! $image ) return $this->fail( __( 'Pinterest requires an image.', 'vm-social-ai-pro' ) );

		$payload = array(
			'title'       => mb_substr( $post['title'], 0, 100 ),
			'description' => mb_substr( $this->caption( $post, false, true ), 0, 500 ),
			'link'        => $post['link'] ?: home_url('/'),
			'board_id'    => $board_id,
			'media_source' => array(
				'source_type'  => 'image_url',
				'url'          => $image
			)
		);

		$res = VMSAI_Http::post( self::API . '/pins', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json'
			),
			'json' => $payload,
			'scope' => 'channel.pinterest'
		) );

		if ( ! $res['ok'] ) return $this->fail( $res['error'] );

		return $this->ok( $res['json']['id'], 'https://www.pinterest.com/pin/' . $res['json']['id'] );
	}
}
