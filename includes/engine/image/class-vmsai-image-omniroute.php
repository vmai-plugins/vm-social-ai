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
	 * Create an image, failing over cleanly across healthy OmniRoute image models.
	 */
	public function create( $prompt, array $args = array() ) {
		$key      = VMSAI_Settings::credential( 'omniroute_key' );
		$base_url = VMSAI_Settings::credential( 'omniroute_url' );
		$saved_m  = VMSAI_Settings::get( 'image_model', array() );
		$primary  = $args['model'] ?? ( $saved_m['omniroute'] ?? 'antigravity/gemini-3.1-flash-image' );

		if ( empty( $primary ) || 'flux' === $primary || 'aihorde/stable_diffusion' === $primary ) {
			$primary = 'nvidia/black-forest-labs/flux.1-schnell';
		}

		// Clean architectural/editorial prompt enhancement without negative trigger tokens that confuse Flux.
		$enhanced_prompt = $this->enhance_prompt( $prompt );
		$candidates      = array_values( array_unique( array(
			$primary,
			'nvidia/black-forest-labs/flux.1-schnell',
			'gemini/gemini-3.1-flash-image-preview',
			'antigravity/gemini-3.1-flash-image',
		) ) );

		$last_error = __( 'OmniRoute returned no usable image.', 'vm-social-ai-pro' );
		foreach ( $candidates as $model ) {
			$payload = array(
				'model'  => $model,
				'prompt' => $enhanced_prompt,
				'n'      => 1,
				'size'   => $this->nearest_size( $args['width'] ?? 1024, $args['height'] ?? 1024 ),
			);

			$response = VMSAI_Http::post(
				rtrim( $base_url, '/' ) . '/images/generations',
				array(
					'headers' => array( 'Authorization' => 'Bearer ' . $key ),
					'json'    => $payload,
					'scope'   => 'engine.image.omniroute',
					'timeout' => 30,
					'retries' => 0,
				)
			);

			if ( ! $response['ok'] ) {
				$last_error = $response['error'] ?: $last_error;
				continue;
			}

			$binary = '';
			$b64 = $response['json']['data'][0]['b64_json'] ?? $response['json']['data'][0]['b64'] ?? '';
			if ( $b64 ) {
				$binary = base64_decode( $b64, true ) ?: '';
			}

			// Some proxies might return a URL instead of b64.
			if ( empty( $binary ) ) {
				$url = $response['json']['data'][0]['url'] ?? $response['json']['url'] ?? '';
				if ( $url ) {
					$binary = VMSAI_Http::fetch_binary( $url, 30 ) ?: '';
				}
			}

			if ( empty( $binary ) ) {
				continue;
			}

			// Accurate MIME detection from binary magic bytes.
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

		return $this->fail( $last_error );
	}

	/**
	 * Enhances the prompt with authentic architectural photography cues and strips diffusion trigger phrases.
	 */
	private function enhance_prompt( $prompt ) {
		$clean = trim( (string) $prompt );
		$clean = preg_replace( '/^Style:\s*PHOTO-REALISTIC VISUAL BRIEF\.\s*/i', '', $clean );
		// Strip "NO fashion models / runway clothing / caucasian model" phrases because Flux draws words even after "NO" or "--no".
		$clean = preg_replace( '/\bStrictly\s+NO\s+[^.!?\n]+[.!?]?/i', '', $clean );
		$clean = preg_replace( '/--no\s+.*$/is', '', $clean );
		$clean = trim( preg_replace( '/\s+/', ' ', $clean ) );

		$quality_guardrail = 'Authentic Indian travel & heritage documentary photography, sacred stone architecture, tranquil river ghats, golden hour natural light, 8k resolution, sharp focus, clean landscape frame';

		return $quality_guardrail . '. ' . $clean;
	}

	private function fail( $error ) {
		return array( 'ok' => false, 'binary' => '', 'url' => '', 'mime' => '', 'credit' => '', 'error' => $error );
	}

	private function nearest_size( $w, $h ) {
		$ratio = $w / max( 1, $h );
		if ( $ratio > 1.4 ) {
			return '1200x630';
		}
		if ( $ratio < 0.7 ) {
			return '1080x1350';
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

		$models   = array();
		$keywords = array( 'flux', 'sdxl', 'image', 'diffusion', 'dall', 'midjourney', 'imagen' );

		foreach ( $response['json']['data'] as $model ) {
			$id = $model['id'] ?? '';
			if ( ! $id ) continue;

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
			array( 'id' => 'antigravity/gemini-3.1-flash-image', 'label' => 'Google Imagen 3.1 (OmniRoute - Recommended)' ),
			array( 'id' => 'nvidia/black-forest-labs/flux.1-dev', 'label' => 'FLUX.1 Dev (NVIDIA NIM)' ),
			array( 'id' => 'pol/tongyi-mai/z-image-turbo', 'label' => 'Z-Image Turbo HD' ),
			array( 'id' => 'codex/gpt-image-2.5-flare', 'label' => 'GPT Image 2.5 Flare' ),
		);
	}
}
