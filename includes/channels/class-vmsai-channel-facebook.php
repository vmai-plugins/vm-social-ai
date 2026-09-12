<?php
/**
 * Facebook Page channel.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Publishes to a Facebook Page via the Graph API using a long-lived Page
 * access token.
 */
class VMSAI_Channel_Facebook extends VMSAI_Channel {

	const GRAPH = 'https://graph.facebook.com/v21.0';

	/**
	 * Slug.
	 *
	 * @return string
	 */
	public function slug() {
		return 'facebook';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label() {
		return 'Facebook Page';
	}

	/**
	 * Required credentials.
	 *
	 * @return array
	 */
	public function credential_fields() {
		return array(
			'fb_page_id'    => __( 'Page ID', 'vm-social-ai-pro' ),
			'fb_page_token' => __( 'Page access token (long-lived)', 'vm-social-ai-pro' ),
		);
	}

	/**
	 * Test the Page token.
	 *
	 * @return array
	 */
	public function test_connection() {
		$id    = VMSAI_Settings::credential( 'fb_page_id' );
		$token = VMSAI_Settings::credential( 'fb_page_token' );

		if ( ! $id || ! $token ) {
			return $this->fail( __( 'Missing Page ID or Token.', 'vm-social-ai-pro' ) );
		}

		// 1. Query the configured Page ID directly (not /me). System User
		// tokens from Business Manager resolve /me to the System User's own
		// identity, not the Page, which produced false "USER token" positives
		// even for perfectly valid Page-scoped tokens. Asking for the Page by
		// its ID works for classic Page tokens, System User tokens, and any
		// User token that happens to have access to the page.
		$me = VMSAI_Http::get(
			self::GRAPH . '/' . rawurlencode( $id ) . '?fields=id,name,category&access_token=' . $token,
			array( 'scope' => 'channel.facebook', 'retries' => 0 )
		);

		if ( ! $me['ok'] ) {
			// A "nonexisting field (category)" error here means the ID isn't
			// a Page at all (e.g. a User ID was pasted into the Page ID box).
			if ( false !== strpos( $me['error'], 'nonexisting field (category)' ) ) {
				return $this->fail( __( 'The Page ID does not belong to a Facebook Page. Double check you copied the Page ID, not a personal/User ID.', 'vm-social-ai-pro' ) );
			}
			return $this->fail( __( 'Invalid Token or Page ID: ', 'vm-social-ai-pro' ) . $me['error'] );
		}

		$is_page = isset( $me['json']['category'] );

		// 2. Check permissions
		$response = VMSAI_Http::get(
			self::GRAPH . '/me/permissions?access_token=' . $token,
			array( 'scope' => 'channel.facebook', 'retries' => 0 )
		);

		$perms = array();
		if ( $response['ok'] ) {
			foreach ( (array) ( $response['json']['data'] ?? array() ) as $p ) {
				if ( is_array( $p ) && 'granted' === ( $p['status'] ?? '' ) ) {
					$perms[] = $p['permission'] ?? '';
				}
			}
		}

		$required = array( 'pages_manage_posts', 'pages_read_engagement', 'pages_show_list' );
		$missing  = array_diff( $required, array_filter( $perms ) );

		if ( $missing ) {
			/* translators: %s: list of missing permissions */
			return $this->fail( sprintf( __( 'Permissions missing: %s. Please re-generate the token in Graph Explorer and check these boxes.', 'vm-social-ai-pro' ), implode( ', ', $missing ) ) );
		}

		if ( ! $is_page ) {
			return $this->fail( __( 'This token cannot access this Page. Re-generate a token with pages_manage_posts, pages_read_engagement, and pages_show_list for this specific Page.', 'vm-social-ai-pro' ) );
		}

		return array( 'ok' => true, 'message' => sprintf( /* translators: %s: page name */ __( 'Successfully connected to "%s".', 'vm-social-ai-pro' ), $me['json']['name'] ) );
	}

	/**
	 * Publish a post.
	 *
	 * @param array $post Queue row.
	 * @return array
	 */
	public function publish( array $post ) {
		$page_id = VMSAI_Settings::credential( 'fb_page_id' );
		$token   = VMSAI_Settings::credential( 'fb_page_token' );

		// CAROUSEL/MULTI-PHOTO LOGIC
		global $wpdb;
		$slides = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM " . VMSAI_Install::table('queue') . " WHERE parent_id = %d ORDER BY id ASC",
			(int) $post['id']
		), ARRAY_A );

		if ( ! empty($slides) ) {
			return $this->publish_multi_photo( $post, $slides, $page_id, $token );
		}

		// REELS SUPPORT: Produce and upload video if format is short.
		if ( 'short' === $post['format'] ) {
			$video_file = vmsai()->video_engine()->produce( $post );
			if ( $video_file ) {
				return $this->publish_video( $post, $video_file, $page_id, $token );
			}
		}

		$image   = $this->media_url( $post );
		$message = $this->caption( $post );

		if ( $image ) {
			$response = VMSAI_Http::post(
				self::GRAPH . '/' . rawurlencode( $page_id ) . '/photos',
				array(
					'json'  => array(
						'url'          => $image,
						'caption'      => $message,
						'access_token' => $token,
					),
					'scope' => 'channel.facebook',
				)
			);
		} else {
			$response = VMSAI_Http::post(
				self::GRAPH . '/' . rawurlencode( $page_id ) . '/feed',
				array(
					'json'  => array(
						'message'      => $message,
						'link'         => $post['link'] ?: null,
						'access_token' => $token,
					),
					'scope' => 'channel.facebook',
				)
			);
		}

		if ( ! $response['ok'] ) {
			$msg = $response['error'];
			// Detect the specific legacy permission error and provide a direct fix.
			if ( strpos( $msg, 'publish_actions' ) !== false || strpos( $msg, '(#200)' ) !== false ) {
				$msg = __( 'Facebook Permission Error: You are using a User Access Token or an app without "pages_manage_posts". FIX: 1. Go to Channels tab. 2. Use the "Test Connection" probe. 3. Follow the instructions to get a "Permanent Page Access Token".', 'vm-social-ai-pro' );
			}
			return $this->fail( $msg );
		}

		$id = (string) ( $response['json']['post_id'] ?? $response['json']['id'] ?? '' );

		if ( ! $id ) {
			return $this->fail( __( 'Facebook accepted the request but returned no post id.', 'vm-social-ai-pro' ) );
		}

		return $this->ok( $id, 'https://www.facebook.com/' . $id );
	}

	/**
	 * Publish a video (Reel).
	 */
	private function publish_video( $post, $file, $page_id, $token ) {
		if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
			return $this->fail( __( 'Reel video file is missing or unreadable.', 'vm-social-ai-pro' ) );
		}

		// Phase 1: start a Reel upload session.
		$res = VMSAI_Http::post( self::GRAPH . '/' . rawurlencode( $page_id ) . '/video_reels', array(
			'json' => array(
				'upload_phase' => 'start',
				'access_token' => $token,
			),
			'scope' => 'channel.facebook',
		) );

		if ( ! $res['ok'] || empty( $res['json']['video_id'] ) || empty( $res['json']['upload_url'] ) ) {
			return $this->fail( __( 'Failed to start Reel upload session.', 'vm-social-ai-pro' ) );
		}

		$video_id   = $res['json']['video_id'];
		$upload_url = $res['json']['upload_url'];

		// Phase 2: stream the binary to the rupload endpoint. Facebook's Reel
		// protocol is a raw PUT with offset/file_size headers — NOT a form
		// post (the old code discarded the session and posted the binary as
		// a multipart field on /videos, which corrupted the upload).
		$upload = VMSAI_Http::send_file(
			'PUT',
			$upload_url,
			$file,
			array(
				'Authorization' => 'OAuth ' . $token,
				'file_url'      => $upload_url,
				'offset'        => '0',
				'file_size'     => (string) filesize( $file ),
			),
			'channel.facebook',
			600
		);

		if ( ! $upload['ok'] ) {
			return $this->fail( $upload['error'] ?: __( 'Reel binary upload failed.', 'vm-social-ai-pro' ) );
		}

		// The binary is delivered; the local file is no longer needed even if
		// the finish call below fails (the session can be finished by hand).
		@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		// Phase 3: finish the session with the description.
		$finish = VMSAI_Http::post( self::GRAPH . '/' . rawurlencode( $page_id ) . '/video_reels', array(
			'json' => array(
				'upload_phase' => 'finish',
				'video_id'     => $video_id,
				'description'  => $this->caption( $post ),
				'access_token' => $token,
			),
			'scope' => 'channel.facebook',
		) );

		if ( ! $finish['ok'] ) {
			return $this->fail( $finish['error'] ?: __( 'Failed to finish Reel upload.', 'vm-social-ai-pro' ) );
		}

		return $this->ok( $video_id, 'https://www.facebook.com/' . $video_id );
	}

	/**
	 * Publish multiple photos as a single post.
	 */
	private function publish_multi_photo( $post, $slides, $page_id, $token ) {
		$attached = array();

		// Pro Fix: Include the parent post as the first photo.
		$all_slides = array_merge( array( $post ), $slides );

		foreach ( $all_slides as $slide ) {
			$url = $this->media_url( $slide );
			if ( ! $url ) continue;

			$res = VMSAI_Http::post( self::GRAPH . '/' . rawurlencode( $page_id ) . '/photos', array(
				'json' => array(
					'url' => $url,
					'published' => false,
					'access_token' => $token
				)
			) );

			if ( $res['ok'] && ! empty($res['json']['id']) ) {
				$attached[] = array( 'media_fbid' => $res['json']['id'] );
			}
		}

		if ( empty($attached) ) return $this->fail( __( 'No valid photos found for multi-photo post.', 'vm-social-ai-pro' ) );

		$response = VMSAI_Http::post( self::GRAPH . '/' . rawurlencode( $page_id ) . '/feed', array(
			'json' => array(
				'message' => $this->caption( $post ),
				'attached_media' => $attached,
				'access_token' => $token
			)
		) );

		if ( ! $response['ok'] ) return $this->fail( $response['error'] );

		$id = $response['json']['id'];

		// Mark children as published
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "UPDATE " . VMSAI_Install::table('queue') . " SET status = 'published', published_at = UTC_TIMESTAMP() WHERE parent_id = %d", (int) $post['id'] ) );

		return $this->ok( $id, 'https://www.facebook.com/' . $id );
	}

	/**
	 * Leave the first comment on a Page post.
	 *
	 * @param string $remote_id Page post id.
	 * @param string $text      Comment body.
	 * @return bool
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
				'scope' => 'channel.facebook',
			)
		);

		if ( ! $response['ok'] ) {
			VMSAI_Logger::warn( 'channel.facebook', 'First comment failed: ' . $response['error'] );
			return false;
		}

		return true;
	}

	/**
	 * Read Page post insights.
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
					'metric'       => 'post_impressions,post_impressions_unique,post_engaged_users,post_clicks',
					'access_token' => VMSAI_Settings::credential( 'fb_page_token' ),
				)
			),
			array( 'scope' => 'channel.facebook', 'retries' => 0, 'timeout' => 25 )
		);

		if ( ! $response['ok'] ) {
			return $empty;
		}

		$map = array(
			'post_impressions'        => 'impressions',
			'post_impressions_unique' => 'reach',
			'post_engaged_users'      => 'engagements',
			'post_clicks'             => 'clicks',
		);

		$metrics = (array) ( $response['json']['data'] ?? array() );

		foreach ( $metrics as $metric ) {
			if ( ! is_array( $metric ) ) {
				continue;
			}

			$name   = (string) ( $metric['name'] ?? '' );
			$values = (array) ( $metric['values'] ?? array() );

			if ( isset( $map[ $name ] ) ) {
				$empty[ $map[ $name ] ] = (int) ( $values[0]['value'] ?? 0 );
			}
		}

		return $empty;
	}

	/**
	 * Pull recent Page comments.
	 *
	 * @param int $limit Max comments.
	 * @return array
	 */
	public function fetch_comments( $limit = 10 ) {
		$page_id = VMSAI_Settings::credential( 'fb_page_id' );
		$token   = VMSAI_Settings::credential( 'fb_page_token' );

		$response = VMSAI_Http::get(
			self::GRAPH . '/' . rawurlencode( $page_id ) . '/published_posts?' . http_build_query(
				array(
					'fields'       => 'comments.limit(' . (int) $limit . '){id,message,from,created_time}',
					'access_token' => $token,
				)
			),
			array( 'scope' => 'channel.facebook', 'retries' => 0, 'timeout' => 25 )
		);

		if ( ! $response['ok'] ) {
			return array();
		}

		$out = array();
		$posts = (array) ( $response['json']['data'] ?? array() );

		foreach ( $posts as $post ) {
			if ( ! is_array( $post ) || empty( $post['comments']['data'] ) ) {
				continue;
			}

			foreach ( (array) $post['comments']['data'] as $comment ) {
				if ( ! is_array( $comment ) ) {
					continue;
				}

				$out[] = array(
					'id'      => $comment['id'],
					'author'  => $comment['from']['name'] ?? 'Facebook User',
					'text'    => $comment['message'] ?? '',
					'time'    => $comment['created_time'] ?? '',
					'channel' => 'facebook',
				);
			}
		}

		return array_slice( $out, 0, $limit );
	}
}
