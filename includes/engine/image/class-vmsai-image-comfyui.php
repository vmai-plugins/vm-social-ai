<?php
/**
 * ComfyUI image provider.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Drives a self-hosted ComfyUI instance over its HTTP API.
 *
 * The admin supplies a saved workflow in API format. Placeholder tokens in
 * that JSON are swapped for the live prompt and dimensions before queueing:
 *
 *   {{prompt}}    the generated visual prompt
 *   {{negative}}  the negative prompt
 *   {{width}}     target width in pixels
 *   {{height}}    target height in pixels
 *   {{seed}}      a fresh random seed
 */
class VMSAI_Image_Comfyui implements VMSAI_Image_Provider {

	/**
	 * Provider slug.
	 *
	 * @return string
	 */
	public function slug() {
		return 'comfyui';
	}

	/**
	 * Provider label.
	 *
	 * @return string
	 */
	public function label() {
		return 'ComfyUI (self-hosted)';
	}

	/**
	 * Server base URL.
	 *
	 * @return string
	 */
	private function base() {
		$url = VMSAI_Settings::credential( 'comfyui_url' );
		if ( empty( $url ) || strpos( $url, '100.107.' ) !== false || strpos( $url, '127.0.0.1' ) !== false ) {
			return 'https://comfy.vmstudio.digital';
		}
		return untrailingslashit( $url );
	}

	/**
	 * Default built-in ComfyUI workflow for standard SD 1.5 / SDXL checkpoint generation.
	 *
	 * @return string
	 */
	public static function default_workflow() {
		return '{
  "3": {
    "class_type": "KSampler",
    "inputs": {
      "cfg": 7,
      "denoise": 1,
      "latent_image": ["5", 0],
      "model": ["4", 0],
      "negative": ["7", 0],
      "positive": ["6", 0],
      "sampler_name": "euler",
      "scheduler": "normal",
      "seed": {{seed}},
      "steps": 20
    }
  },
  "4": {
    "class_type": "CheckpointLoaderSimple",
    "inputs": {
      "ckpt_name": "dreamshaper_8.safetensors"
    }
  },
  "5": {
    "class_type": "EmptyLatentImage",
    "inputs": {
      "batch_size": 1,
      "height": {{height}},
      "width": {{width}}
    }
  },
  "6": {
    "class_type": "CLIPTextEncode",
    "inputs": {
      "clip": ["4", 1],
      "text": "{{prompt}}"
    }
  },
  "7": {
    "class_type": "CLIPTextEncode",
    "inputs": {
      "clip": ["4", 1],
      "text": "{{negative}}"
    }
  },
  "8": {
    "class_type": "VAEDecode",
    "inputs": {
      "samples": ["3", 0],
      "vae": ["4", 2]
    }
  },
  "9": {
    "class_type": "SaveImage",
    "inputs": {
      "filename_prefix": "vmsai_laptop2",
      "images": ["8", 0]
    }
  }
}';
	}

	/**
	 * Whether ComfyUI is ready to run.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== $this->base();
	}

	/**
	 * Optional auth headers for proxied instances.
	 *
	 * @return array
	 */
	private function headers() {
		$headers = array(
			'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
			'Origin'     => $this->base(),
		);
		$token = VMSAI_Settings::credential( 'comfyui_token' );
		if ( ! empty( $token ) ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}
		return $headers;
	}

	/**
	 * Create an image.
	 *
	 * @param string $prompt Visual prompt.
	 * @param array  $args   Options.
	 * @return array
	 */
	public function create( $prompt, array $args = array() ) {
		$workflow = $this->build_workflow( $prompt, $args );

		if ( ! $workflow ) {
			return $this->fail( __( 'The ComfyUI workflow JSON is invalid.', 'vm-social-ai-pro' ) );
		}

		$client_id = 'vmsai-' . wp_generate_password( 12, false );

		$queued = VMSAI_Http::post(
			$this->base() . '/prompt',
			array(
				'headers' => $this->headers(),
				'json'    => array( 'prompt' => $workflow, 'client_id' => $client_id ),
				'scope'   => 'engine.image.comfyui',
				'retries' => 0,
				'timeout' => 30,
			)
		);

		if ( ! $queued['ok'] ) {
			return $this->fail( $queued['error'] );
		}

		$prompt_id = (string) ( $queued['json']['prompt_id'] ?? '' );
		if ( ! $prompt_id ) {
			return $this->fail( __( 'ComfyUI did not return a prompt id.', 'vm-social-ai-pro' ) );
		}

		$file = $this->poll( $prompt_id );
		if ( ! $file ) {
			return $this->fail( __( 'Timed out waiting for the ComfyUI render.', 'vm-social-ai-pro' ) );
		}

		$url = $this->base() . '/view?' . http_build_query(
			array(
				'filename'  => $file['filename'],
				'subfolder' => $file['subfolder'],
				'type'      => $file['type'],
			)
		);

		$binary = VMSAI_Http::fetch_binary( $url, 60 );

		if ( ! $binary ) {
			return $this->fail( __( 'Could not download the ComfyUI output.', 'vm-social-ai-pro' ) );
		}

		return array( 'ok' => true, 'binary' => $binary, 'url' => $url, 'mime' => 'image/png', 'credit' => '', 'error' => '' );
	}

	/**
	 * Substitute placeholders into the stored workflow.
	 *
	 * @param string $prompt Visual prompt.
	 * @param array  $args   Options.
	 * @return array|null
	 */
	private function build_workflow( $prompt, array $args ) {
		$raw = VMSAI_Settings::credential( 'comfyui_workflow' );
		if ( empty( $raw ) ) {
			$raw = self::default_workflow();
		}

		$seed = isset( $args['seed'] ) ? (int) $args['seed'] : wp_rand( 1, 2147483647 );
		$raw_w = (int) ( $args['width'] ?? 512 );
		$raw_h = (int) ( $args['height'] ?? 512 );
		list( $width, $height ) = self::optimize_dimensions( $raw_w, $raw_h );

		$prompt_json   = wp_json_encode( (string) $prompt );
		$default_neg   = 'blurry, low quality, distorted, bad anatomy, deformed, disfigured, extra limbs, watermark, text, signature, duplicate, cropped, out of frame';
		$negative_val  = ! empty( $args['negative'] ) ? (string) $args['negative'] : $default_neg;
		$negative_json = wp_json_encode( $negative_val );

		$replacements = array(
			'"{{seed}}"'     => $seed,
			'{{seed}}'       => $seed,
			'"{{width}}"'    => $width,
			'{{width}}'      => $width,
			'"{{height}}"'   => $height,
			'{{height}}'     => $height,
			'"{{prompt}}"'   => $prompt_json,
			'{{prompt}}'     => $prompt_json,
			'"{{negative}}"' => $negative_json,
			'{{negative}}'   => $negative_json,
		);

		$json = str_replace( array_keys( $replacements ), array_values( $replacements ), $raw );
		$data = json_decode( $json, true );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * Compute optimal width and height for SD 1.5 generation (multiples of 64).
	 *
	 * @param int $target_w Requested width.
	 * @param int $target_h Requested height.
	 * @return array{0:int, 1:int}
	 */
	public static function optimize_dimensions( $target_w, $target_h ) {
		$target_w = max( 1, (int) $target_w );
		$target_h = max( 1, (int) $target_h );
		$ratio    = $target_w / $target_h;

		if ( $ratio > 1.25 ) {
			// Landscape (Facebook 1200x630, X 1600x900, LinkedIn 1200x627)
			$w = 768;
			$h = 512;
		} elseif ( $ratio < 0.85 ) {
			// Portrait / Vertical (Instagram 1080x1350, YouTube Short / Reel 1080x1920)
			$w = 512;
			$h = 768;
		} else {
			// Square (1:1)
			$w = 512;
			$h = 512;
		}

		return array( $w, $h );
	}

	/**
	 * Make a string safe to inject into raw JSON.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private function escape( $value ) {
		return trim( wp_json_encode( (string) $value ), '"' );
	}

	/**
	 * Poll the history endpoint until an output image appears.
	 *
	 * @param string $prompt_id Queue id.
	 * @return array|null
	 */
	private function poll( $prompt_id ) {
		// A render can legitimately take a while, but this runs inside a cron
		// tick: if the wait exceeded the PHP max_execution_time the worker
		// died mid-run instead of timing out gracefully. Cap the deadline to
		// whatever headroom the process actually has (default 60s builds
		// never reach the old 180s wall).
		// Hard-cap polling to 45s maximum to prevent 504/524 gateway timeouts on reverse proxies
		$budget   = (int) ini_get( 'max_execution_time' );
		$headroom = $budget > 0 ? max( 15, min( 45, $budget - 10 ) ) : 45;
		$deadline = time() + $headroom;

		while ( time() < $deadline ) {
			sleep( 3 );

			$history = VMSAI_Http::get(
				$this->base() . '/history/' . rawurlencode( $prompt_id ),
				array( 'headers' => $this->headers(), 'scope' => 'engine.image.comfyui', 'retries' => 0, 'timeout' => 20 )
			);

			if ( ! $history['ok'] ) {
				continue;
			}

			$entry = $history['json'][ $prompt_id ] ?? array();
			foreach ( (array) ( $entry['outputs'] ?? array() ) as $node ) {
				foreach ( (array) ( $node['images'] ?? array() ) as $image ) {
					if ( ! empty( $image['filename'] ) ) {
						return array(
							'filename'  => (string) $image['filename'],
							'subfolder' => (string) ( $image['subfolder'] ?? '' ),
							'type'      => (string) ( $image['type'] ?? 'output' ),
						);
					}
				}
			}
		}

		return null;
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
}
