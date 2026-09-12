<?php
/**
 * Bluesky (AT Protocol) channel.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

class VMSAI_Channel_Bluesky extends VMSAI_Channel {

	const API = 'https://bsky.social/xrpc';

	public function slug() {
		return 'bluesky';
	}

	public function label() {
		return 'Bluesky';
	}

	public function credential_fields() {
		return array(
			'bsky_handle'   => __( 'Bluesky Handle (e.g. user.bsky.social)', 'vm-social-ai-pro' ),
			'bsky_password' => __( 'App Password', 'vm-social-ai-pro' ),
		);
	}

	private function get_session() {
		$handle = VMSAI_Settings::credential( 'bsky_handle' );
		$pass   = VMSAI_Settings::credential( 'bsky_password' );

		if ( ! $handle || ! $pass ) return false;

		$res = VMSAI_Http::post( self::API . '/com.atproto.server.createSession', array(
			'json' => array( 'identifier' => $handle, 'password' => $pass )
		) );

		return $res['ok'] ? $res['json'] : false;
	}

	public function test_connection() {
		$session = $this->get_session();
		if ( ! $session ) return $this->fail( __( 'Bluesky authentication failed. Check handle and app password.', 'vm-social-ai-pro' ) );
		return array( 'ok' => true, 'message' => sprintf( __( 'Connected to Bluesky as %s.', 'vm-social-ai-pro' ), $session['handle'] ) );
	}

	public function publish( array $post ) {
		$session = $this->get_session();
		if ( ! $session ) return $this->fail( __( 'Bluesky session failed.', 'vm-social-ai-pro' ) );

		$token = $session['accessJwt'];
		$did   = $session['did'];

		$text = mb_substr( $this->caption( $post, true, true ), 0, 300 );

		$record = array(
			'text'      => $text,
			'createdAt' => gmdate( 'Y-m-d\\TH:i:s\\Z' ),
			'$type'     => 'app.bsky.feed.post',
		);

		// Only attach images if we have a usable one — otherwise emit text-only.
		$image = $this->media_url( $post );
		if ( $image ) {
			// Register the image blob, fetch the handle, attach it.
			$img_res = VMSAI_Http::post( self::API . '/com.atproto.repo.uploadBlob', array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					// ATProto rejects blobs without an exact mime type.
					'Content-Type'  => 'image/jpeg',
				),
				'body'    => file_get_contents( $this->media_path( $post ) ),
				'scope'   => 'channel.bluesky',
				'timeout' => 30,
			) );
			if ( $img_res['ok'] && ! empty( $img_res['json']['blob']['uri'] ) ) {
				$record['embed'] = array(
					'$type'       => 'app.bsky.embed.images',
					'images'      => array( array( 'image' => $img_res['json']['blob']['uri'] ) ),
				);
			}
		}

		$res = VMSAI_Http::post( self::API . '/com.atproto.repo.createRecord', array(
			'headers' => array( 'Authorization' => 'Bearer ' . $token ),
			'json'    => array(
				'repo'       => $did,
				'collection' => 'app.bsky.feed.post',
				'record'     => $record,
			),
			'scope'   => 'channel.bluesky',
		) );

		if ( ! $res['ok'] ) return $this->fail( $res['error'] );

		return $this->ok( $res['json']['uri'], 'https://bsky.app/' );
	}
}
