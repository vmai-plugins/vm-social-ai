<?php
/**
 * AI Puffer image provider.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Uses AI Puffer's own image endpoint so image generation follows whatever
 * model the AIPKit install is already licensed for.
 */
class VMSAI_Image_Aipuffer implements VMSAI_Image_Provider {

	/**
	 * Provider slug.
	 *
	 * @return string
	 */
	public function slug() {
		return 'aipuffer';
	}

	/**
	 * Provider label.
	 *
	 * @return string
	 */
	public function label() {
		return 'AI Puffer Images';
	}

	/**
	 * Configured when the shared AI Puffer key exists.
	 *
	 * @return bool
	 */
	public function is_configured() {
		if ( '' !== VMSAI_Settings::credential( 'aipuffer_key' ) ) {
			return true;
		}

		// create() already knows how to pull the key straight out of an AIPKit
		// install on this same site, but this gate ran first and returned
		// false — so the chain dropped AI Puffer before that code could ever
		// execute, and image generation fell through to weaker providers even
		// though a working multi-model backend was sitting right here. The
		// text provider has always detected this; the image one did not.
		return '' !== self::local_key();
	}

	/**
	 * The public API key of an AIPKit install on this same site, if any.
	 *
	 * @return string
	 */
	private static function local_key() {
		$opts = get_option( 'aipkit_options' );
		if ( is_array( $opts ) && ! empty( $opts['api_keys']['public_api_key'] ) ) {
			return (string) $opts['api_keys']['public_api_key'];
		}

		$legacy = get_option( 'wpaicg_options' );
		if ( is_array( $legacy ) && ! empty( $legacy['rest_api_key'] ) ) {
			return (string) $legacy['rest_api_key'];
		}

		return '';
	}

	/**
	 * Create an image.
	 *
	 * @param string $prompt Visual prompt.
	 * @param array  $args   Options.
	 * @return array
	 */
	public function create( $prompt, array $args = array() ) {
		$site = VMSAI_Settings::credential( 'aipuffer_site' );
		$is_local = ( ! $site || strpos( home_url(), (string) $site ) !== false );
		$site = $site ? untrailingslashit( $site ) : untrailingslashit( home_url() );
		$key  = VMSAI_Settings::credential( 'aipuffer_key' );

		// AUTO-DETECT LOCAL KEY (If missing in settings)
		if ( $is_local && empty( $key ) ) {
			$key = self::local_key();
		}

		$width  = (int) ( $args['width'] ?? 1080 );
		$height = (int) ( $args['height'] ?? 1350 );
		$model  = VMSAI_Settings::get( 'image_model' )['aipuffer'] ?? '';

		$payload = array(
			'prompt'          => $prompt,
			'size'            => self::nearest_supported_size( $width, $height ),
			'n'               => 1,
			'response_format' => 'url',
			'quality'         => 'hd',
			'style'           => 'vivid',
		);

		if ( $model ) {
			$payload['model'] = $model;
		}

		if ( $is_local ) {
			$request = new WP_REST_Request( 'POST', '/aipkit/v1/images/generate' );
			$request->set_header( 'Authorization', 'Bearer ' . $key );
			$request->set_body_params( $payload );

			try {
				$response = rest_do_request( $request );
			} catch ( \Throwable $e ) {
				VMSAI_Logger::error( 'engine.image.aipuffer', 'Local REST dispatch crashed.', array( 'message' => $e->getMessage() ) );
				return $this->fail( __( "AI Puffer's backend plugin crashed.", 'vm-social-ai-pro' ) );
			}

			if ( $response->is_error() ) {
				$data = $response->get_data();
				// If AIPKit returns "Invalid or missing API Key", it might be because the key
				// we detected or sent isn't what it expects.
				$msg = $data['message'] ?? 'Local AI Puffer call failed.';
				VMSAI_Logger::error( 'engine.image.aipuffer', 'Local REST Fail.', array( 'status' => $response->get_status(), 'data' => $data ) );
				return $this->fail( $msg );
			}

			$json = $response->get_data();
		} else {
			$response = VMSAI_Http::post(
				$site . '/wp-json/aipkit/v1/images/generate',
				array(
					'headers' => array( 'Authorization' => 'Bearer ' . $key ),
					'json'    => $payload,
					'scope'   => 'engine.image.aipuffer',
					'retries' => 1,
				)
			);

			if ( ! $response['ok'] ) {
				$msg = $response['error'];
				if ( strpos( $msg, 'Incorrect API key' ) !== false || strpos( $msg, '401' ) !== false ) {
					$msg = __( 'AI Puffer has an invalid OpenAI key saved. Fix the key on your AI Power settings page.', 'vm-social-ai-pro' );
				} elseif ( strpos( $msg, 'insufficient_quota' ) !== false || strpos( $msg, '429' ) !== false ) {
					$msg = __( 'AI Puffer (OpenAI) has run out of credits. Recharge your OpenAI account billing.', 'vm-social-ai-pro' );
				}
				return $this->fail( $msg );
			}

			$json = $response['json'];
		}

		$url  = $json['url'] ?? $json['image_url'] ?? $json['data'][0]['url'] ?? $json['images'][0]['url'] ?? '';
		$b64  = $json['b64_json'] ?? $json['data'][0]['b64_json'] ?? $json['images'][0]['b64_json'] ?? '';

		if ( $b64 ) {
			// Strip data:image/png;base64, prefix if present
			if ( preg_match( '/^data:image\/(\w+);base64,/', $b64 ) ) {
				$b64 = substr( $b64, strpos( $b64, ',' ) + 1 );
			}
			$binary = base64_decode( $b64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
			if ( $binary ) {
				return array( 'ok' => true, 'binary' => $binary, 'url' => '', 'mime' => 'image/png', 'credit' => '', 'error' => '' );
			}
		}

		if ( $url ) {
			$binary = VMSAI_Http::fetch_binary( $url );
			if ( $binary ) {
				return array( 'ok' => true, 'binary' => $binary, 'url' => $url, 'mime' => 'image/png', 'credit' => '', 'error' => '' );
			}
		}

		return $this->fail( __( 'AI Puffer returned no usable image.', 'vm-social-ai-pro' ) );
	}

	/**
	 * Failure shape.
	 *
	 * @param string $error Message.
	 * @return array
	 */
	private function fail( $error ) {
		return array( 'ok' => false, 'binary' => '', 'url' => '', 'mime' => '', 'credit' => '', 'error' => $error );
	}

	/**
	 * AIPKit's /images/generate validates 'size' against a fixed enum, not
	 * arbitrary pixel dimensions. Pick whichever allowed size has the
	 * closest aspect ratio to the channel's actual canvas.
	 *
	 * @param int $width  Requested width.
	 * @param int $height Requested height.
	 * @return string
	 */
	private static function nearest_supported_size( $width, $height ) {
		$allowed = array( '256x256', '512x512', '1024x1024', '1792x1024', '1024x1792', '1536x1024', '1024x1536', '1024x768', '768x1024' );
		$target  = $width / max( 1, $height );

		$best      = '1024x1024';
		$best_diff = null;

		foreach ( $allowed as $size ) {
			list( $w, $h ) = array_map( 'intval', explode( 'x', $size ) );
			$diff = abs( ( $w / $h ) - $target );

			if ( null === $best_diff || $diff < $best_diff ) {
				$best_diff = $diff;
				$best      = $size;
			}
		}

		return $best;
	}

	/**
	 * AIPKit exposes no REST route for listing image models.
	 * If local, we reach into its classes to find what's available.
	 *
	 * @return array
	 */
	public function list_models() {
		if ( ! $this->is_configured() ) return array();

		$models = array();

		// LOCAL SYNC: If on same site, try to reach into the backend classes directly.
		if ( ( ! VMSAI_Settings::credential( 'aipuffer_site' ) || strpos( home_url(), VMSAI_Settings::credential( 'aipuffer_site' ) ) !== false ) ) {
			if ( class_exists( '\WPAICG\AIPKit_Providers' ) ) {
				$types = array( 'OpenAI', 'GoogleImage', 'xAIImage' );
				foreach ( $types as $t ) {
					// Some are methods, some are catalog entries.
					$list = array();
					if ( 'OpenAI' === $t ) $list = \WPAICG\AIPKit_Providers::get_openai_image_models();
					else $list = \WPAICG\AIPKit_Providers::get_model_list( $t );

					if ( is_array( $list ) ) {
						foreach ( $list as $m ) {
							$id = is_array( $m ) ? ( $m['id'] ?? '' ) : (string) $m;
							if ( $id ) {
								$models[] = array(
									'id'    => $id,
									'label' => 'AIP: ' . ( is_array( $m ) ? ( $m['name'] ?? $id ) : $id )
								);
							}
						}
					}
				}
			}
		}

		return $models;
	}
}
