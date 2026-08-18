<?php
/**
 * OpenAI DALL-E image provider.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Accesses OpenAI's DALL-E models. Also supports custom base URLs
 * for Puter.js bridges or other OpenAI-compatible services.
 */
class VMSAI_Image_OpenAI implements VMSAI_Image_Provider {

	/**
	 * Provider slug.
	 */
	public function slug() {
		return 'openai';
	}

	/**
	 * Provider label.
	 */
	public function label() {
		return 'OpenAI (DALL-E)';
	}

	/**
	 * Configured when an API key exists.
	 */
	public function is_configured() {
		return '' !== VMSAI_Settings::credential( 'openai_key' );
	}

	/**
	 * Create an image.
	 */
	public function create( $prompt, array $args = array() ) {
		$key      = VMSAI_Settings::credential( 'openai_key' );
		$base_url = VMSAI_Settings::credential( 'openai_image_url' ) ?: 'https://api.openai.com/v1';
		$model    = VMSAI_Settings::get( 'image_model' )['openai'] ?? 'dall-e-3';

		$payload = array(
			'model'           => $model,
			'prompt'          => $prompt,
			'n'               => 1,
			'size'            => $this->nearest_size( $args['width'] ?? 1024, $args['height'] ?? 1024 ),
			'response_format' => 'b64_json',
		);

		// DALL-E 3 specific settings
		if ( 'dall-e-3' === $model ) {
			$payload['quality'] = 'standard';
			$payload['style']   = 'vivid';
		}

		$response = VMSAI_Http::post(
			rtrim( $base_url, '/' ) . '/images/generations',
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $key ),
				'json'    => $payload,
				'scope'   => 'engine.image.openai',
				'retries' => 1,
			)
		);

		if ( ! $response['ok'] ) {
			return $this->fail( $response['error'] );
		}

		$b64 = $response['json']['data'][0]['b64_json'] ?? '';

		if ( $b64 ) {
			$binary = base64_decode( $b64, true );
			if ( $binary ) {
				return array( 'ok' => true, 'binary' => $binary, 'url' => '', 'mime' => 'image/png', 'credit' => 'Generated via OpenAI', 'error' => '' );
			}
		}

		return $this->fail( __( 'OpenAI returned no usable image.', 'vm-social-ai-pro' ) );
	}

	private function fail( $error ) {
		return array( 'ok' => false, 'binary' => '', 'url' => '', 'mime' => '', 'credit' => '', 'error' => $error );
	}

	private function nearest_size( $w, $h ) {
		$ratio = $w / max( 1, $h );
		if ( $ratio > 1.5 ) return '1792x1024';
		if ( $ratio < 0.6 ) return '1024x1792';
		return '1024x1024';
	}

	public function list_models() {
		return array(
			array( 'id' => 'dall-e-3', 'label' => 'DALL-E 3 (High quality)' ),
			array( 'id' => 'dall-e-2', 'label' => 'DALL-E 2 (Fast/Cheap)' ),
		);
	}
}
