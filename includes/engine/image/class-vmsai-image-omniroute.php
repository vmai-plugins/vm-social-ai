<?php
/**
 * OmniRoute image provider.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Accesses OmniRoute AI Gateway for image generation.
 */
class VMSAI_Image_OmniRoute implements VMSAI_Image_Provider {

	/**
	 * Provider slug.
	 */
	public function slug() {
		return 'omniroute';
	}

	/**
	 * Provider label.
	 */
	public function label() {
		return 'OmniRoute';
	}

	/**
	 * Configured when an API key and URL exist.
	 */
	public function is_configured() {
		return '' !== VMSAI_Settings::credential( 'omniroute_key' ) && '' !== VMSAI_Settings::credential( 'omniroute_url' );
	}

	/**
	 * Create an image.
	 */
	public function create( $prompt, array $args = array() ) {
		$key      = VMSAI_Settings::credential( 'omniroute_key' );
		$base_url = VMSAI_Settings::credential( 'omniroute_url' );
		$model    = VMSAI_Settings::get( 'image_model' )['omniroute'] ?? 'flux';

		$payload = array(
			'model'           => $model,
			'prompt'          => $prompt,
			'n'               => 1,
			'size'            => $this->nearest_size( $args['width'] ?? 1024, $args['height'] ?? 1024 ),
			'response_format' => 'b64_json',
		);

		$response = VMSAI_Http::post(
			rtrim( $base_url, '/' ) . '/images/generations',
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $key ),
				'json'    => $payload,
				'scope'   => 'engine.image.omniroute',
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
				return array( 'ok' => true, 'binary' => $binary, 'url' => '', 'mime' => 'image/png', 'credit' => 'Generated via OmniRoute', 'error' => '' );
			}
		}

		// Some proxies might return a URL instead of b64
		$url = $response['json']['data'][0]['url'] ?? '';
		if ( $url ) {
			$binary = VMSAI_Http::fetch_binary( $url );
			if ( $binary ) {
				return array( 'ok' => true, 'binary' => $binary, 'url' => '', 'mime' => 'image/png', 'credit' => 'Generated via OmniRoute', 'error' => '' );
			}
		}

		return $this->fail( __( 'OmniRoute returned no usable image.', 'vm-social-ai-pro' ) );
	}

	private function fail( $error ) {
		return array( 'ok' => false, 'binary' => '', 'url' => '', 'mime' => '', 'credit' => '', 'error' => $error );
	}

	private function nearest_size( $w, $h ) {
		$ratio = $w / max( 1, $h );
		if ( $ratio > 1.5 ) {
			return '1792x1024';
		}
		if ( $ratio < 0.6 ) {
			return '1024x1792';
		}
		return '1024x1024';
	}

	public function list_models() {
		$key      = VMSAI_Settings::credential( 'omniroute_key' );
		$base_url = VMSAI_Settings::credential( 'omniroute_url' );

		if ( ! $key || ! $base_url ) {
			return $this->default_models();
		}

		$response = VMSAI_Http::get(
			rtrim( $base_url, '/' ) . '/models',
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $key ),
				'scope'   => 'engine.image.omniroute.models',
				'timeout' => 10,
			)
		);

		if ( ! $response['ok'] || empty( $response['json']['data'] ) ) {
			return $this->default_models();
		}

		$models = array();
		foreach ( $response['json']['data'] as $model ) {
			// Basic heuristic: if it mentions image/flux/sdxl, it's likely an image model.
			// Or just include everything and let the user decide.
			$id = $model['id'] ?? '';
			if ( ! $id ) continue;

			// Filter for image models if possible, but many gateways don't label them clearly.
			$models[] = array(
				'id'    => $id,
				'label' => $model['name'] ?? $id,
			);
		}

		return $models ?: $this->default_models();
	}

	private function default_models() {
		return array(
			array( 'id' => 'flux', 'label' => 'FLUX' ),
			array( 'id' => 'stable-diffusion-xl', 'label' => 'SDXL' ),
			array( 'id' => 'dall-e-3', 'label' => 'DALL-E 3' ),
		);
	}
}
