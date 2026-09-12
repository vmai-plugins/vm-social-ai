<?php
/**
 * Threads channel.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

class VMSAI_Channel_Threads extends VMSAI_Channel {

	const GRAPH = 'https://graph.threads.net/v1.0';

	public function slug() {
		return 'threads';
	}

	public function label() {
		return 'Threads';
	}

	public function credential_fields() {
		return array(
			'threads_user_id' => __( 'Threads User ID', 'vm-social-ai-pro' ),
			'threads_token'   => __( 'Threads Access Token', 'vm-social-ai-pro' ),
		);
	}

	public function test_connection() {
		$id    = VMSAI_Settings::credential( 'threads_user_id' );
		$token = VMSAI_Settings::credential( 'threads_token' );
		if ( ! $id || ! $token ) return $this->fail( __( 'Missing Threads ID or Token.', 'vm-social-ai-pro' ) );

		$res = VMSAI_Http::get( self::GRAPH . '/me?fields=username&access_token=' . $token, array( 'scope' => 'channel.threads' ) );
		if ( ! $res['ok'] ) return $this->fail( $res['error'] );

		return array( 'ok' => true, 'message' => sprintf( __( 'Connected to Threads as @%s.', 'vm-social-ai-pro' ), $res['json']['username'] ?? 'user' ) );
	}

	public function publish( array $post ) {
		$id    = VMSAI_Settings::credential( 'threads_user_id' );
		$token = VMSAI_Settings::credential( 'threads_token' );
		$image = $this->media_url( $post );

		// Step 1: Create Media Container
		$payload = array(
			'media_type' => $image ? 'IMAGE' : 'TEXT',
			'text'       => $this->caption( $post, true, true ),
			'access_token' => $token
		);
		if ( $image ) $payload['image_url'] = $image;

		$container = VMSAI_Http::post( self::GRAPH . '/' . rawurlencode($id) . '/threads', array( 'json' => $payload ) );
		if ( ! $container['ok'] ) return $this->fail( $container['error'] );

		$creation_id = $container['json']['id'];

		// Poll the container instead of a blind sleep(): Threads media
		// processing can take longer than 5s (a fixed sleep both blocked
		// cron AND still raced the publish), and 'FINISHED' is known
		// immediately for text-only posts.
		if ( $image ) {
			$state    = '';
			$deadline = time() + 25;
			while ( time() < $deadline ) {
				sleep( 3 );
				$status = VMSAI_Http::get(
					self::GRAPH . '/' . rawurlencode( $id ) . '/' . rawurlencode( $creation_id ) . '?' . http_build_query( array( 'fields' => 'status', 'access_token' => $token ) ),
					array( 'scope' => 'channel.threads' )
				);
				if ( $status['ok'] ) {
					$state = (string) ( $status['json']['status'] ?? '' );
					if ( 'FINISHED' === $state || 'ERROR' === $state ) {
						break;
					}
				}
			}
			if ( 'ERROR' === $state ) {
				return $this->fail( __( 'Threads failed to process the media container.', 'vm-social-ai-pro' ) );
			}
		}

		// Step 2: Publish
		$published = VMSAI_Http::post( self::GRAPH . '/' . rawurlencode($id) . '/threads_publish', array(
			'json' => array( 'creation_id' => $creation_id, 'access_token' => $token )
		) );

		if ( ! $published['ok'] ) return $this->fail( $published['error'] );

		return $this->ok( $published['json']['id'], 'https://www.threads.net/' );
	}
}
