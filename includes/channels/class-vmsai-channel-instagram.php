<?php
/**
 * Instagram channel.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Publishes to an Instagram Business or Creator account through the Graph
 * API. Instagram requires a two-step flow: create a media container, wait
 * for it to finish processing, then publish it.
 */
class VMSAI_Channel_Instagram extends VMSAI_Channel {

	const GRAPH = 'https://graph.facebook.com/v21.0';

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug() {
		return 'instagram';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return 'Instagram';
	}

	/**
	 * Required credentials.
	 *
	 * @return array
	 */
	public function credential_fields() {
		return array(
			'ig_user_id'    => __( 'Instagram Business account ID', 'vm-social-ai-pro' ),
			'fb_page_token' => __( 'Page access token (shared with Facebook)', 'vm-social-ai-pro' ),
		);
	}

	/**
	 * Test the Instagram connection by fetching the business profile name.
	 *
	 * @return array
	 */
	public function test_connection() {
		$user_id = VMSAI_Settings::credential( 'ig_user_id' );
		$token   = VMSAI_Settings::credential( 'fb_page_token' );

		if ( ! $user_id || ! $token ) {
			return $this->fail( __( 'Missing Instagram Account ID or Token.', 'vm-social-ai-pro' ) );
		}

		// First test the User/Page permissions.
		$response = VMSAI_Http::get(
			self::GRAPH . '/me/permissions?access_token=' . $token,
			array( 'scope' => 'channel.instagram', 'retries' => 0 )
		);

		if ( ! $response['ok'] ) {
			return $this->fail( $response['error'] );
		}

		$perms = array();
		foreach ( (array) ( $response['json']['data'] ?? array() ) as $p ) {
			if ( is_array( $p ) && 'granted' === ( $p['status'] ?? '' ) ) {
				$perms[] = $p['permission'] ?? '';
			}
		}

		$required = array( 'instagram_basic', 'instagram_content_publish' );
		$missing  = array_diff( $required, array_filter( $perms ) );

		if ( $missing ) {
			/* translators: %s: list of missing permissions */
			return $this->fail( sprintf( __( 'Token is valid, but missing Instagram permissions: %s', 'vm-social-ai-pro' ), implode( ', ', $missing ) ) );
		}

		// Now verify the account ID.
		$verify = VMSAI_Http::get(
			self::GRAPH . '/' . rawurlencode( $user_id ) . '?fields=username&access_token=' . $token,
			array( 'scope' => 'channel.instagram', 'retries' => 0 )
		);

		if ( ! $verify['ok'] ) {
			return $this->fail( __( 'The Instagram Account ID is invalid or not accessible by this token.', 'vm-social-ai-pro' ) );
		}

		$json     = (array) ( $verify['json'] ?? array() );
		$username = (string) ( $json['username'] ?? 'user' );

		return array( 'ok' => true, 'message' => sprintf( /* translators: %s: username */ __( 'Connected to Instagram as @%s.', 'vm-social-ai-pro' ), $username ) );
	}

	/**
	 * Publish a post.
	 *
	 * @param array $post Queue row.
	 * @return array
	 */
	public function publish( array $post ) {
		// Ensure the script doesn't time out while waiting for Instagram's container processing.
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 );
		}

		$user_id = VMSAI_Settings::credential( 'ig_user_id' );
		$token   = VMSAI_Settings::credential( 'fb_page_token' );

		// CAROUSEL LOGIC: Check for child slides.
		global $wpdb;
		$slides = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM " . VMSAI_Install::table('queue') . " WHERE parent_id = %d ORDER BY id ASC",
			(int) $post['id']
		), ARRAY_A );

		if ( ! empty($slides) ) {
			return $this->publish_carousel( $post, $slides, $user_id, $token );
		}

		// REELS SUPPORT: Produce and upload video if format is short.
		if ( 'short' === $post['format'] ) {
			$video_file = vmsai()->video_engine()->produce( $post );
			if ( $video_file ) {
				return $this->publish_video( $post, $video_file, $user_id, $token );
			}
		}

		$image   = $this->media_url( $post );
		if ( ! $image ) {
			return $this->fail( __( 'Instagram will not accept a post without media.', 'vm-social-ai-pro' ) );
		}

		// Instagram cannot render clickable links in captions, so the link
		// is dropped and the profile link carries the traffic instead.
		$caption = $this->caption( $post, false, true );

		$container = VMSAI_Http::post(
			self::GRAPH . '/' . rawurlencode( $user_id ) . '/media',
			array(
				'json'  => array(
					'image_url'    => $image,
					'caption'      => mb_substr( $caption, 0, 2200 ),
					'access_token' => $token,
				),
				'scope' => 'channel.instagram',
			)
		);

		if ( ! $container['ok'] ) {
			$msg = $container['error'];
			if ( strpos( $msg, 'publish_actions' ) !== false || strpos( $msg, '(#200)' ) !== false || strpos( $msg, 'Permission denied' ) !== false ) {
				$msg = __( 'Instagram Error: You are likely using a User Access Token instead of a PAGE Access Token. Go to the Meta Graph Explorer, select your App, select your PAGE (not yourself), and ensure these scopes are granted: instagram_basic, instagram_content_publish, pages_read_engagement.', 'vm-social-ai-pro' );
			}
			return $this->fail( $msg );
		}

		$creation_id = (string) ( $container['json']['id'] ?? '' );

		if ( ! $creation_id ) {
			return $this->fail( __( 'Instagram returned no media container id.', 'vm-social-ai-pro' ) );
		}

		if ( ! $this->await_container( $creation_id, $token ) ) {
			return $this->fail( __( 'The Instagram media container never finished processing.', 'vm-social-ai-pro' ) );
		}

		$published = VMSAI_Http::post(
			self::GRAPH . '/' . rawurlencode( $user_id ) . '/media_publish',
			array(
				'json'  => array( 'creation_id' => $creation_id, 'access_token' => $token ),
				'scope' => 'channel.instagram',
			)
		);

		if ( ! $published['ok'] ) {
			return $this->fail( $published['error'] );
		}

		$media_id = (string) ( $published['json']['id'] ?? '' );

		return $this->ok( $media_id, $this->permalink( $media_id, $token ) );
	}

	/**
	 * Handle native Instagram carousels.
	 */
	private function publish_carousel( $post, $slides, $user_id, $token ) {
		$children = array();

		// Pro Fix: Include the parent post as the first slide.
		$all_slides = array_merge( array( $post ), $slides );

		// 1. Create items for each slide
		foreach ( $all_slides as $slide ) {
			$url = $this->media_url( $slide );
			if ( ! $url ) continue;

			$res = VMSAI_Http::post( self::GRAPH . '/' . rawurlencode( $user_id ) . '/media', array(
				'json' => array(
					'image_url' => $url,
					'is_carousel_item' => true,
					'access_token' => $token
				)
			) );

			if ( $res['ok'] && ! empty($res['json']['id']) ) {
				$children[] = $res['json']['id'];
			}
		}

		if ( count($children) < 2 ) {
			return $this->fail( __( 'Not enough valid slides for a carousel.', 'vm-social-ai-pro' ) );
		}

		// 2. Create the carousel container
		$container = VMSAI_Http::post( self::GRAPH . '/' . rawurlencode( $user_id ) . '/media', array(
			'json' => array(
				'media_type' => 'CAROUSEL',
				'children' => $children,
				'caption' => $this->caption( $post, false, true ),
				'access_token' => $token
			)
		) );

		if ( ! $container['ok'] ) return $this->fail( $container['error'] );

		$creation_id = $container['json']['id'];
		if ( ! $this->await_container( $creation_id, $token ) ) {
			return $this->fail( __( 'Carousel container processing failed.', 'vm-social-ai-pro' ) );
		}

		// 3. Publish
		$published = VMSAI_Http::post( self::GRAPH . '/' . rawurlencode( $user_id ) . '/media_publish', array(
			'json' => array( 'creation_id' => $creation_id, 'access_token' => $token )
		) );

		if ( ! $published['ok'] ) return $this->fail( $published['error'] );

		$media_id = $published['json']['id'];

		// Mark child slides as published
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "UPDATE " . VMSAI_Install::table('queue') . " SET status = 'published', published_at = UTC_TIMESTAMP() WHERE parent_id = %d", (int) $post['id'] ) );

		return $this->ok( $media_id, $this->permalink( $media_id, $token ) );
	}

	/**
	 * Publish a video (Reel).
	 */
	private function publish_video( $post, $file, $user_id, $token ) {
		$filename = basename( $file );
		$url      = '';

		// To publish to Instagram, the video MUST be on a public URL.
		// If remote storage is on, offload it there.
		if ( 'off' !== VMSAI_Settings::get( 'remote_storage' ) ) {
			$res = VMSAI_Storage::upload( $file, $filename, 'video/mp4' );
			if ( ! is_wp_error( $res ) ) {
				$url = $res;
			}
		}

		// Fallback to local URL if remote upload failed or is off.
		if ( ! $url ) {
			$uploads = wp_upload_dir();
			$url = trailingslashit( $uploads['baseurl'] ) . $filename;
		}

		$container = VMSAI_Http::post(
			self::GRAPH . '/' . rawurlencode( $user_id ) . '/media',
			array(
				'json'  => array(
					'media_type'   => 'REELS',
					'video_url'    => $url,
					'caption'      => mb_substr( $this->caption( $post, false, true ), 0, 2200 ),
					'access_token' => $token,
				),
				'scope' => 'channel.instagram',
				'timeout' => 60,
			)
		);

		@unlink( $file );

		if ( ! $container['ok'] ) {
			return $this->fail( $container['error'] );
		}

		$creation_id = (string) ( $container['json']['id'] ?? '' );
		if ( ! $creation_id || ! $this->await_container( $creation_id, $token ) ) {
			return $this->fail( __( 'The Reel container processing failed or timed out.', 'vm-social-ai-pro' ) );
		}

		$published = VMSAI_Http::post(
			self::GRAPH . '/' . rawurlencode( $user_id ) . '/media_publish',
			array(
				'json'  => array( 'creation_id' => $creation_id, 'access_token' => $token ),
				'scope' => 'channel.instagram',
			)
		);

		if ( ! $published['ok'] ) {
			return $this->fail( $published['error'] );
		}

		$media_id = (string) ( $published['json']['id'] ?? '' );
		return $this->ok( $media_id, $this->permalink( $media_id, $token ) );
	}

	/**
	 * Poll the container until Instagram reports it ready.
	 *
	 * @param string $creation_id Container id.
	 * @param string $token       Access token.
	 * @return bool
	 */
	private function await_container( $creation_id, $token ) {
		for ( $attempt = 0; $attempt < 12; $attempt++ ) {
			sleep( 4 );

			$status = VMSAI_Http::get(
				self::GRAPH . '/' . rawurlencode( $creation_id ) . '?' . http_build_query(
					array( 'fields' => 'status_code', 'access_token' => $token )
				),
				array( 'scope' => 'channel.instagram', 'retries' => 0, 'timeout' => 20 )
			);

			$code = (string) ( $status['json']['status_code'] ?? '' );

			if ( 'FINISHED' === $code ) {
				return true;
			}
			if ( 'ERROR' === $code || 'EXPIRED' === $code ) {
				return false;
			}
		}

		return false;
	}

	/**
	 * Resolve the public permalink.
	 *
	 * @param string $media_id Media id.
	 * @param string $token    Access token.
	 * @return string
	 */
	private function permalink( $media_id, $token ) {
		if ( ! $media_id ) {
			return '';
		}

		$response = VMSAI_Http::get(
			self::GRAPH . '/' . rawurlencode( $media_id ) . '?' . http_build_query(
				array( 'fields' => 'permalink', 'access_token' => $token )
			),
			array( 'scope' => 'channel.instagram', 'retries' => 0, 'timeout' => 20 )
		);

		return (string) ( $response['json']['permalink'] ?? '' );
	}

	/**
	 * Read media insights.
	 *
	 * @param array $post Queue row.
	 * @return array
	 */
	public function post_comment( $remote_id, $text ) {
		$text = trim( (string) $text );

		if ( '' === $text || '' === (string) $remote_id ) {
			return false;
		}

		$response = VMSAI_Http::post(
			self::GRAPH . '/' . rawurlencode( $remote_id ) . '/comments',
			array(
				'json'  => array(
					'message'      => $text,
					'access_token' => VMSAI_Settings::credential( 'fb_page_token' ),
				),
				'scope' => 'channel.instagram',
			)
		);

		if ( ! $response['ok'] ) {
			VMSAI_Logger::warn( 'channel.instagram', 'First comment failed: ' . $response['error'] );
			return false;
		}

		return true;
	}

	/**
	 * Read media insights.
	 *
	 * @param array $post Queue row.
	 * @return array
	 */
	public function fetch_metrics( array $post ) {
		$empty = array( 'impressions' => 0, 'reach' => 0, 'engagements' => 0, 'clicks' => 0 );

		if ( empty( $post['remote_id'] ) ) {
			return $empty;
		}

		$response = VMSAI_Http::get(
			self::GRAPH . '/' . rawurlencode( $post['remote_id'] ) . '/insights?' . http_build_query(
				array(
					'metric'       => 'impressions,reach,saved,likes,comments,shares',
					'access_token' => VMSAI_Settings::credential( 'fb_page_token' ),
				)
			),
			array( 'scope' => 'channel.instagram', 'retries' => 0, 'timeout' => 25 )
		);

		if ( ! $response['ok'] ) {
			return $empty;
		}

		$engagement = 0;
		$metrics    = (array) ( $response['json']['data'] ?? array() );

		foreach ( $metrics as $metric ) {
			if ( ! is_array( $metric ) ) {
				continue;
			}

			$name   = (string) ( $metric['name'] ?? '' );
			$values = (array) ( $metric['values'] ?? array() );
			$value  = (int) ( $values[0]['value'] ?? 0 );

			if ( 'impressions' === $name ) {
				$empty['impressions'] = $value;
			} elseif ( 'reach' === $name ) {
				$empty['reach'] = $value;
			} else {
				$engagement += $value;
			}
		}

		$empty['engagements'] = $engagement;

		return $empty;
	}

	/**
	 * Pull recent media comments.
	 *
	 * @param int $limit Max comments.
	 * @return array
	 */
	public function fetch_comments( $limit = 10 ) {
		$user_id = VMSAI_Settings::credential( 'ig_user_id' );
		$token   = VMSAI_Settings::credential( 'fb_page_token' );

		$response = VMSAI_Http::get(
			self::GRAPH . '/' . rawurlencode( $user_id ) . '/media?' . http_build_query(
				array(
					'fields'       => 'comments.limit(' . (int) $limit . '){id,text,username,timestamp}',
					'access_token' => $token,
				)
			),
			array( 'scope' => 'channel.instagram', 'retries' => 0, 'timeout' => 25 )
		);

		if ( ! $response['ok'] ) {
			return array();
		}

		$out   = array();
		$media = (array) ( $response['json']['data'] ?? array() );

		foreach ( $media as $item ) {
			if ( ! is_array( $item ) || empty( $item['comments']['data'] ) ) {
				continue;
			}

			foreach ( (array) $item['comments']['data'] as $comment ) {
				if ( ! is_array( $comment ) ) {
					continue;
				}

				$out[] = array(
					'id'      => $comment['id'],
					'author'  => $comment['username'] ?? 'Instagram User',
					'text'    => $comment['text'] ?? '',
					'time'    => $comment['timestamp'] ?? '',
					'channel' => 'instagram',
				);
			}
		}

		return array_slice( $out, 0, $limit );
	}
}
