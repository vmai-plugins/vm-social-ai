<?php
/**
 * Google Gemini image provider (Imagen).
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Accesses Google's Imagen models via the Generative Language API.
 */
class VMSAI_Image_Gemini implements VMSAI_Image_Provider {

	const BASE = 'https://generativelanguage.googleapis.com/v1beta';

	/**
	 * Provider slug.
	 */
	public function slug() {
		return 'gemini';
	}

	/**
	 * Provider label.
	 */
	public function label() {
		return 'Google Gemini (Imagen)';
	}

	/**
	 * Configured when the shared Gemini key exists.
	 */
	public function is_configured() {
		return '' !== VMSAI_Settings::credential( 'gemini_key' );
	}

	/**
	 * Create an image.
	 */
	public function create( $prompt, array $args = array() ) {
		$key   = VMSAI_Settings::credential( 'gemini_key' );
		$model = ( VMSAI_Settings::get( 'image_model' )['gemini'] ?? '' ) ?: self::default_model();

		// Auto-migration: Google retired the generic 'imagen-3' ID.
		if ( 'imagen-3' === $model ) {
			$model = 'imagen-3.0-generate-001';
		}

		// Handle region-specific model ID variations.
		// We try the user-selected model first, then known stable versions.
		// 'imagen-3' (generic) was retired by Google in August 2024.
		$models_to_try = array_unique( array_filter( array(
			$model,
			'imagen-3.0-generate-001',
			'imagen-3.0-fast-generate-001',
			'imagen-3.0-capability-001',
		) ) );

		$last_error = '';

		foreach ( $models_to_try as $current_model ) {
			// Some model IDs already include the models/ prefix if synced from live_models.
			$model_path = ( strpos( $current_model, 'models/' ) === 0 ) ? $current_model : 'models/' . $current_model;
			$url        = self::BASE . '/' . $model_path . ':predict';

			// Handle aspect ratio mapping for Imagen 3
			$ar     = '1:1';
			$target = ( ( $args['width'] ?? 1080 ) / max( 1, ( $args['height'] ?? 1080 ) ) );

			if ( $target > 1.4 ) {
				$ar = '16:9';
			} elseif ( $target > 1.1 ) {
				$ar = '4:3';
			} elseif ( $target < 0.65 ) {
				$ar = '9:16';
			} elseif ( $target < 0.85 ) {
				$ar = '3:4';
			}

			$response = VMSAI_Http::post(
				$url . '?key=' . $key,
				array(
					'json'    => array(
						'instances'  => array(
							array( 'prompt' => $prompt ),
						),
						'parameters' => array(
							'sampleCount' => 1,
							'aspectRatio' => $ar,
						),
					),
					'scope'   => 'engine.image.gemini',
					'retries' => 0,
				)
			);

			if ( $response['ok'] ) {
				$b64 = $response['json']['predictions'][0]['bytesBase64Encoded'] ?? '';
				if ( $b64 ) {
					$binary = base64_decode( $b64, true );
					if ( $binary ) {
						return array(
							'ok'            => true,
							'binary'        => $binary,
							'url'           => '',
							'mime'          => 'image/png',
							'credit'        => 'Generated via Google Imagen',
							'error'         => '',
						);
					}
				}
			}

			$last_error = $response['error'];

			// Fallback: Try generateContent if predict fails (common for some accounts or multimodal models)
			if ( strpos( $last_error, '404' ) !== false || strpos( $last_error, '400' ) !== false || strpos( $last_error, 'predict' ) !== false ) {
				$fallback_url = self::BASE . '/' . $model_path . ':generateContent?key=' . $key;
				$fallback_res = VMSAI_Http::post(
					$fallback_url,
					array(
						'json'  => array(
							'contents' => array(
								array(
									'parts' => array(
										array( 'text' => $prompt ),
									),
								),
							),
						),
						'scope' => 'engine.image.gemini.fallback',
					)
				);

				if ( ! empty( $fallback_res['json']['candidates'][0]['content']['parts'][0]['inlineData']['data'] ) ) {
					$b64 = $fallback_res['json']['candidates'][0]['content']['parts'][0]['inlineData']['data'];
					return array(
						'ok'            => true,
						'binary'        => base64_decode( $b64 ),
						'url'           => '',
						'mime'          => 'image/png',
						'credit'        => 'Generated via Google Imagen (Fallback)',
						'error'         => '',
					);
				}

				// If generateContent also fails with something other than "not found", keep that error.
				if ( ! $fallback_res['ok'] && strpos( $fallback_res['error'], '404' ) === false ) {
					$last_error = $fallback_res['error'];
				}
			}
		}

		return $this->fail( $last_error ?: __( 'Gemini returned no usable image.', 'vm-social-ai-pro' ) );
	}

	private function fail( $error ) {
		return array( 'ok' => false, 'binary' => '', 'url' => '', 'mime' => '', 'credit' => '', 'error' => $error );
	}

	/**
	 * A model that's actually live today. Google periodically retires
	 * Imagen model IDs (this is exactly what happened to the hardcoded
	 * "imagen-3" this used to default to), so ask the real /models endpoint
	 * rather than trust a string baked into this file.
	 *
	 * @return string
	 */
	private static function default_model() {
		$models = self::live_models();

		if ( ! empty( $models[0]['id'] ) ) {
			return (string) $models[0]['id'];
		}

		// Absolute last resort: Google retired 'imagen-3'.
		// We use the full versioned string which is the current stable production ID.
		return 'imagen-3.0-generate-001';
	}

	/**
	 * Image-capable models from Google's live catalogue — anything whose
	 * supported methods include 'predict' (Imagen's generation call),
	 * mirroring how the text provider filters for 'generateContent'.
	 *
	 * @return array
	 */
	private static function live_models() {
		if ( '' === VMSAI_Settings::credential( 'gemini_key' ) ) {
			return array();
		}

		$key = VMSAI_Settings::credential( 'gemini_key' );

		$response = VMSAI_Http::get(
			self::BASE . '/models?key=' . $key,
			array(
				'scope'   => 'engine.image.gemini',
				'retries' => 0,
				'timeout' => 25,
			)
		);

		if ( ! $response['ok'] ) {
			VMSAI_Logger::error( 'engine.image.gemini', 'Model sync failed: ' . $response['error'] );
			return array();
		}

		$models = array();

		foreach ( (array) ( $response['json']['models'] ?? array() ) as $model ) {
			$methods = (array) ( $model['supportedGenerationMethods'] ?? array() );

			if ( ! in_array( 'predict', $methods, true ) ) {
				continue;
			}

			$id = str_replace( 'models/', '', (string) ( $model['name'] ?? '' ) );

			if ( ! $id ) {
				continue;
			}

			$models[] = array(
				'id'    => $id,
				'label' => (string) ( $model['displayName'] ?? $id ),
				'free'  => ( false !== strpos( $id, 'fast' ) ) || ( false !== strpos( $id, '3.0' ) )
			);
		}

		return $models;
	}

	/**
	 * Model list for the Engines dropdown.
	 */
	public function list_models() {
		return self::live_models();
	}
}
