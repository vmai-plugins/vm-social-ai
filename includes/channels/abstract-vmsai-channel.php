<?php
/**
 * Channel base class.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shared behaviour for every publishing destination.
 */
abstract class VMSAI_Channel {

	/**
	 * Machine slug.
	 *
	 * @return string
	 */
	abstract public function slug();

	/**
	 * Human label.
	 *
	 * @return string
	 */
	abstract public function label();

	/**
	 * Credential fields this channel needs, as key => label.
	 *
	 * @return array<string,string>
	 */
	abstract public function credential_fields();

	/**
	 * Send a composed post.
	 *
	 * @param array $post Queue row.
	 * @return array{ok:bool,remote_id:string,permalink:string,error:string}
	 */
	abstract public function publish( array $post );

	/**
	 * Whether every required credential is present.
	 *
	 * @return bool
	 */
	public function is_connected() {
		foreach ( array_keys( $this->credential_fields() ) as $field ) {
			if ( '' === VMSAI_Settings::credential( $field ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Pull performance numbers for a published post. Channels that cannot
	 * report override this.
	 *
	 * @param array $post Queue row.
	 * @return array{impressions:int,reach:int,engagements:int,clicks:int}
	 */
	public function fetch_metrics( array $post ) {
		return array( 'impressions' => 0, 'reach' => 0, 'engagements' => 0, 'clicks' => 0 );
	}

	/**
	 * Pull recent comments for the channel.
	 *
	 * @param int $limit Max comments to fetch.
	 * @return array List of comment arrays.
	 */
	public function fetch_comments( $limit = 10 ) {
		return array();
	}

	/**
	 * Leave a comment on a post this channel just published — used for the
	 * "first comment" pattern: hashtags out of the Instagram caption, and
	 * links out of the LinkedIn body (LinkedIn suppresses reach on posts
	 * with outbound links). Channels that support it override this.
	 *
	 * @param string $remote_id Network post id returned by publish().
	 * @param string $text      Comment body.
	 * @return bool True if the comment was posted.
	 */
	public function post_comment( $remote_id, $text ) {
		return false;
	}

	/**
	 * Verify that the credentials actually work by talking to the API.
	 *
	 * @return array{ok:bool,message:string}
	 */
	public function test_connection() {
		if ( ! $this->is_connected() ) {
			return array( 'ok' => false, 'message' => __( 'Missing credentials.', 'vm-social-ai-pro' ) );
		}
		return array( 'ok' => true, 'message' => __( 'Credentials present.', 'vm-social-ai-pro' ) );
	}

	/**
	 * Assemble the full published caption from its parts.
	 *
	 * @param array $post          Queue row.
	 * @param bool  $include_link  Append the tracked link.
	 * @param bool  $include_tags  Append hashtags.
	 * @return string
	 */
	protected function caption( array $post, $include_link = true, $include_tags = true ) {
		$parts = array( trim( (string) $post['body'] ) );

		$cta = trim( (string) $post['cta'] );
		if ( $cta && false === stripos( $post['body'], $cta ) ) {
			$parts[] = $cta;
		}

		// Anything already carried by the first comment must not be repeated
		// in the caption itself.
		$first_comment = trim( (string) ( $post['first_comment'] ?? '' ) );

		if ( $include_link && ! empty( $post['link'] ) && false === strpos( $first_comment, (string) $post['link'] ) ) {
			$parts[] = $post['link'];
		}

		if ( $include_tags && ! empty( $post['hashtags'] ) && false === strpos( $first_comment, (string) $post['hashtags'] ) ) {
			$parts[] = $post['hashtags'];
		}

		return implode( "\n\n", array_filter( $parts ) );
	}

	/**
	 * Publicly reachable URL for the post image.
	 *
	 * @param array $post Queue row.
	 * @return string
	 */
	protected function media_url( array $post ) {
		$url = '';
		if ( ! empty( $post['media_url'] ) ) {
			$url = (string) $post['media_url'];
		} elseif ( ! empty( $post['media_id'] ) ) {
			$url = (string) wp_get_attachment_url( (int) $post['media_id'] );
		}

		if ( $url ) {
			$host = wp_parse_url( $url, PHP_URL_HOST );
			if ( $host && ( 'localhost' === $host || false !== strpos( $host, '.local' ) || preg_match( '/^(127\.|10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/', $host ) ) ) {
				VMSAI_Logger::warn( 'channel.' . $this->slug(), 'Public networks cannot reach local URLs. Use a tunnel (like Ngrok or LocalWP Live Link) for testing media publishing.', array( 'url' => $url ) );
			}
		}

		return $url;
	}

	/**
	 * Absolute path to the post image on disk.
	 *
	 * @param array $post Queue row.
	 * @return string
	 */
	protected function media_path( array $post ) {
		if ( empty( $post['media_id'] ) ) {
			return '';
		}
		$path = get_attached_file( (int) $post['media_id'] );
		return $path && file_exists( $path ) ? $path : '';
	}

	/**
	 * Success shape.
	 *
	 * @param string $remote_id Network post id.
	 * @param string $permalink Public URL.
	 * @return array
	 */
	protected function ok( $remote_id, $permalink = '' ) {
		return array( 'ok' => true, 'remote_id' => (string) $remote_id, 'permalink' => (string) $permalink, 'error' => '' );
	}

	/**
	 * Failure shape.
	 *
	 * @param string $error Message.
	 * @return array
	 */
	protected function fail( $error ) {
		return array( 'ok' => false, 'remote_id' => '', 'permalink' => '', 'message' => (string) $error, 'error' => (string) $error );
	}
}
