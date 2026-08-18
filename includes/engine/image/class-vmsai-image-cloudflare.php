<?php
/**
 * Cloudflare Workers AI image provider (SDXL).
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Generates images via Cloudflare Workers AI.
 * Uses Stable Diffusion XL Lightning for high speed and free tier compatibility.
 */
class VMSAI_Image_Cloudflare implements VMSAI_Image_Provider {

	/**
	 * Provider slug.
	 */
	public function slug() {
		return 'cloudflare';
	}

	/**
	 * Provider label.
	 */
	public function label() {
		return 'Cloudflare Workers AI';
	}

	/**
	 * Configured when an account ID and API token exist.
	 */
	public function is_configured() {
		$account_id = VMSAI_Settings::credential( 'r2_account_id' );
		$token      = VMSAI_Settings::credential( 'cf_token' );
		return '' !== $account_id && '' !== $token;
	}

	/**
	 * Create an image.
	 */
	public function create( $prompt, array $args = array() ) {
		$account_id = VMSAI_Settings::credential( 'r2_account_id' );
		$token      = VMSAI_Settings::credential( 'cf_token' );

		// Using SDXL Lightning for the best speed/quality balance in the free tier.
		$model = '@cf/bytedance/stable-diffusion-xl-lightning';
		$url   = "https://api.cloudflare.com/client/v4/accounts/{$account_id}/ai/run/{$model}";

		$response = VMSAI_Http::post( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
			),
			'json'    => array(
				'prompt' => $prompt,
				'num_steps' => 4, // Optimized for Lightning model
			),
			'timeout' => 90,
			'scope'   => 'engine.image.cloudflare'
		) );

		if ( ! $response['ok'] ) {
			return $this->fail( $response['error'] );
		}

		$binary = $response['body'];

		if ( ! $binary || strlen( $binary ) < 5000 ) {
			return $this->fail( __( 'Cloudflare returned an invalid image payload.', 'vm-social-ai-pro' ) );
		}

		return array(
			'ok'     => true,
			'binary' => $binary,
			'url'    => '',
			'mime'   => 'image/png',
			'credit' => 'Generated via Cloudflare Workers AI',
			'error'  => '',
		);
	}

	private function fail( $error ) {
		return array( 'ok' => false, 'binary' => '', 'url' => '', 'mime' => '', 'credit' => '', 'error' => $error );
	}

	/**
	 * Model list.
	 */
	public function list_models() {
		return array(
			array( 'id' => 'stable-diffusion-xl-lightning', 'label' => 'SDXL Lightning (Fastest)' ),
			array( 'id' => 'stable-diffusion-xl-base-1.0', 'label' => 'SDXL Base 1.0' ),
		);
	}
}
