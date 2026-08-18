<?php
/**
 * OpenRouter image provider.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * OpenRouter proxies image-capable models from several vendors behind one
 * key, which makes it a useful second opinion when the primary generator is
 * rate limited. Images come back on the chat completions endpoint as an
 * `images` array on the message, not from a dedicated image route.
 */
class VMSAI_Image_OpenRouter implements VMSAI_Image_Provider {

	const BASE = 'https://openrouter.ai/api/v1/chat/completions';

	/**
	 * Machine slug.
	 *
	 * @return string
	 */
	public function slug() {
		return 'openrouter';
	}

	/**
	 * Human label.
	 *
	 * @return string
	 */
	public function label() {
		return 'OpenRouter (image models)';
	}

	/**
	 * Shares the text provider's key.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== VMSAI_Settings::credential( 'openrouter_key' );
	}

	/**
	 * The configured image model, falling back to a free one.
	 *
	 * @return string
	 */
	private function model() {
		$models = (array) VMSAI_Settings::get( 'image_model', array() );
		$chosen = trim( (string) ( $models['openrouter'] ?? '' ) );

		if ( '' !== $chosen ) {
			return $chosen;
		}

		$synced = VMSAI_Model_Sync::models( 'openrouter', 'image' );

		if ( ! empty( $synced[0]['model_id'] ) ) {
			return (string) $synced[0]['model_id'];
		}

		return 'google/gemini-2.5-flash-image-preview';
	}

	/**
	 * Produce an image.
	 *
	 * @param string $prompt Visual prompt.
	 * @param array  $args   Keys: width, height.
	 * @return array
	 */
	public function create( $prompt, array $args = array() ) {
		if ( ! $this->is_configured() ) {
			return $this->fail( __( 'No OpenRouter key set.', 'vm-social-ai-pro' ) );
		}

		$width  = (int) ( $args['width'] ?? 1080 );
		$height = (int) ( $args['height'] ?? 1350 );

		// Aspect ratio has to be described in the prompt: this endpoint takes
		// no width/height parameters.
		$sized = $prompt . sprintf( '. Compose for a %d x %d canvas (%s).', $width, $height, $this->ratio_label( $width, $height ) );

		$response = VMSAI_Http::post(
			self::BASE,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . VMSAI_Settings::credential( 'openrouter_key' ),
					'Content-Type'  => 'application/json',
					'HTTP-Referer'  => home_url( '/' ),
					'X-Title'       => 'VM Social AI',
				),
				'json'    => array(
					'model'      => $this->model(),
					'modalities' => array( 'image', 'text' ),
					'messages'   => array(
						array( 'role' => 'user', 'content' => $sized ),
					),
				),
				'scope'   => 'image.openrouter',
				'timeout' => 120,
			)
		);

		if ( empty( $response['ok'] ) ) {
			return $this->fail( (string) $response['error'] );
		}

		$image = $this->first_image( (array) ( $response['json'] ?? array() ) );

		if ( '' === $image ) {
			return $this->fail( __( 'OpenRouter returned no image — the selected model may be text-only.', 'vm-social-ai-pro' ) );
		}

		// Images arrive as a data: URI on the message.
		if ( 0 === strpos( $image, 'data:' ) ) {
			$parts = explode( ',', $image, 2 );
			$mime  = 'image/png';

			if ( preg_match( '#data:([a-z/+-]+);#i', $parts[0], $m ) ) {
				$mime = strtolower( $m[1] );
			}

			$binary = base64_decode( $parts[1] ?? '', true ); // phpcs:ignore

			if ( false === $binary || strlen( $binary ) < 1000 ) {
				return $this->fail( __( 'OpenRouter returned an unreadable image payload.', 'vm-social-ai-pro' ) );
			}

			return array(
				'ok'     => true,
				'binary' => $binary,
				'url'    => '',
				'mime'   => $mime,
				'credit' => '',
				'error'  => '',
				'model'  => $this->model(),
			);
		}

		$fetched = VMSAI_Http::get( $image, array( 'scope' => 'image.openrouter', 'timeout' => 60 ) );

		if ( empty( $fetched['ok'] ) ) {
			return $this->fail( (string) $fetched['error'] );
		}

		return array(
			'ok'     => true,
			'binary' => (string) $fetched['body'],
			'url'    => $image,
			'mime'   => 'image/png',
			'credit' => '',
			'error'  => '',
			'model'  => $this->model(),
		);
	}

	/**
	 * Dig the first image out of a chat completion response.
	 *
	 * @param array $json Decoded body.
	 * @return string data: URI or URL, empty when none.
	 */
	private function first_image( array $json ) {
		$message = $json['choices'][0]['message'] ?? array();

		foreach ( (array) ( $message['images'] ?? array() ) as $entry ) {
			if ( is_string( $entry ) && '' !== $entry ) {
				return $entry;
			}
			// The common shape is { type: image_url, image_url: { url: ... } }.
			$url = $entry['image_url']['url'] ?? ( $entry['url'] ?? '' );
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		return '';
	}

	/**
	 * Describe the canvas in words the model can act on.
	 *
	 * @param int $width  Pixels.
	 * @param int $height Pixels.
	 * @return string
	 */
	private function ratio_label( $width, $height ) {
		if ( $height > $width ) {
			return 'vertical portrait orientation';
		}
		if ( $width > $height ) {
			return 'horizontal landscape orientation';
		}

		return 'square orientation';
	}

	/**
	 * Models offered for the Engines tab.
	 *
	 * @return array
	 */
	public function list_models() {
		return array(
			array( 'id' => 'google/gemini-2.5-flash-image-preview', 'label' => 'Gemini 2.5 Flash Image' ),
			array( 'id' => 'google/gemini-2.0-flash-exp:free', 'label' => 'Gemini 2.0 Flash Exp (free)' ),
		);
	}

	/**
	 * Failure shape.
	 *
	 * @param string $error Message.
	 * @return array
	 */
	private function fail( $error ) {
		return array( 'ok' => false, 'binary' => '', 'url' => '', 'mime' => '', 'credit' => '', 'error' => (string) $error );
	}
}
