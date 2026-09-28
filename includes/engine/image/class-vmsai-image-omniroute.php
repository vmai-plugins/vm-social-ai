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
		$saved_m  = VMSAI_Settings::get( 'image_model', array() );
		$model    = $args['model'] ?? ( $saved_m['omniroute'] ?? 'aihorde/stable_diffusion' );

		if ( empty( $model ) || 'flux' === $model || 'nvidia/black-forest-labs/flux.1-schnell' === $model ) {
			$model = 'aihorde/stable_diffusion';
		}

		$payload = array(
			'model'  => $model,
			'prompt' => $prompt,
			'n'      => 1,
			'size'   => $this->nearest_size( $args['width'] ?? 1024, $args['height'] ?? 1024 ),
		);

		$response = VMSAI_Http::post(
			rtrim( $base_url, '/' ) . '/images/generations',
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $key ),
				'json'    => $payload,
				'scope'   => 'engine.image.omniroute',
				'timeout' => 45,
				'retries' => 1,
			)
		);

		if ( ! $response['ok'] ) {
			return $this->fail( $response['error'] );
		}

		$binary = '';
		$b64 = $response['json']['data'][0]['b64_json'] ?? $response['json']['data'][0]['b64'] ?? '';
		if ( $b64 ) {
			$binary = base64_decode( $b64, true ) ?: '';
		}

		// Some proxies might return a URL instead of b64
		if ( empty( $binary ) ) {
			$url = $response['json']['data'][0]['url'] ?? $response['json']['url'] ?? '';
			if ( $url ) {
				$binary = VMSAI_Http::fetch_binary( $url, 45 ) ?: '';
			}
		}

		if ( empty( $binary ) ) {
			return $this->fail( __( 'OmniRoute returned no usable image.', 'vm-social-ai-pro' ) );
		}

		// Accurate MIME detection from binary magic bytes
		$mime = 'image/png';
		if ( 0 === strncmp( $binary, 'RIFF', 4 ) && 'WEBP' === substr( $binary, 8, 4 ) ) {
			$mime = 'image/webp';
		} elseif ( 0 === strncmp( $binary, "\xFF\xD8\xFF", 3 ) ) {
			$mime = 'image/jpeg';
		} elseif ( 0 === strncmp( $binary, "\x89PNG", 4 ) ) {
			$mime = 'image/png';
		}

		return array(
			'ok'     => true,
			'binary' => $binary,
			'url'    => '',
			'mime'   => $mime,
			'credit' => 'Generated via OmniRoute (' . $model . ')',
			'error'  => '',
		);
	}

	private function fail( $error ) {
		return array( 'ok' => false, 'binary' => '', 'url' => '', 'mime' => '', 'credit' => '', 'error' => $error );
	}

	private function nearest_size( $w, $h ) {
		$ratio = $w / max( 1, $h );
		if ( $ratio > 1.5 ) {
			return '768x512';
		}
		if ( $ratio < 0.6 ) {
			return '512x768';
		}
		return '512x512';
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
		$keywords = array( 'flux', 'sdxl', 'image', 'diffusion', 'dall', 'midjourney', 'imagen' );

		foreach ( $response['json']['data'] as $model ) {
			$id = $model['id'] ?? '';
			if ( ! $id ) continue;

			// Skip video models
			if ( str_contains( $id, 'video' ) || str_contains( $id, 'veo' ) ) continue;

			$match = false;
			foreach ( $keywords as $kw ) {
				if ( false !== stripos( $id, $kw ) ) {
					$match = true;
					break;
				}
			}

			if ( $match ) {
				$models[] = array(
					'id'    => $id,
					'label' => $model['name'] ?? $id,
				);
			}
		}

		return $models ?: $this->default_models();
	}

	private function default_models() {
		return array(
			array( 'id' => 'aihorde/stable_diffusion', 'label' => 'AI Horde Stable Diffusion (Verified Working)' ),
			array( 'id' => 'aihorde/Flux.1-Schnell fp8 (Compact)', 'label' => 'AI Horde FLUX.1 Schnell' ),
			array( 'id' => 'aihorde/AlbedoBase XL (SDXL)', 'label' => 'AI Horde AlbedoBase XL' ),
			array( 'id' => 'aihorde/SDXL 1.0', 'label' => 'AI Horde SDXL 1.0' ),
		);
	}
}
