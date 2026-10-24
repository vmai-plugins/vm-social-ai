<?php
/**
 * Multimodal Vision Engine for VM Social AI.
 *
 * Inspects image uploads, reads text/objects via Multimodal AI (Gemini / OpenAI),
 * and generates engaging social captions, viral hooks, and accessibility Alt text.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

class VMSAI_Vision {

	/**
	 * Inspect an image and return factual description.
	 *
	 * @param string|int $image_input Attachment ID, local path, or base64 data URI.
	 * @param string     $context     Extra brand or post context.
	 * @return string
	 */
	public static function describe( $image_input, $context = '' ) {
		$instruction = 'Describe the visible subject and setting of this image in one concise, factual sentence (max 25 words). No preamble.';
		if ( ! empty( $context ) ) {
			$instruction .= ' Brand Context: ' . $context;
		}

		$res = self::analyze( $image_input, $instruction, 150 );
		return $res['ok'] ? trim( (string) $res['text'] ) : '';
	}

	/**
	 * Analyze an image and generate 3 viral social hooks and hashtags.
	 *
	 * @param string|int $image_input Attachment ID, local path, or URL.
	 * @param string     $channel     Target channel (instagram, facebook, x, etc.).
	 * @return array{ok:bool, description:string, hooks:array<string>, hashtags:array<string>, error?:string}
	 */
	public static function generate_social_hooks( $image_input, $channel = 'instagram' ) {
		$instruction = "Act as an elite social media director for {$channel}. Analyze this image:\n"
			. "1. Identify the key visual focal point, lighting, and vibe.\n"
			. "2. Provide 3 punchy, high-engagement viral caption hooks tailored to this visual.\n"
			. "3. Provide 8 trending, highly relevant hashtags.\n\n"
			. "Format response as strict JSON with keys: 'visual_summary', 'hooks' (array of 3 strings), 'hashtags' (array of 8 strings).";

		$res = self::analyze( $image_input, $instruction, 500, true );
		if ( ! $res['ok'] ) {
			return [
				'ok'    => false,
				'error' => $res['error'] ?? 'Vision analysis failed',
			];
		}

		$json = json_decode( $res['text'], true );
		if ( ! is_array( $json ) ) {
			// Extract json from markdown block if present
			if ( preg_match( '/\{.*\}/s', $res['text'], $m ) ) {
				$json = json_decode( $m[0], true );
			}
		}

		return [
			'ok'             => true,
			'visual_summary' => $json['visual_summary'] ?? $res['text'],
			'hooks'          => $json['hooks'] ?? [],
			'hashtags'       => $json['hashtags'] ?? [],
		];
	}

	/**
	 * Low-level multimodal vision caller.
	 *
	 * @param string|int $image_input
	 * @param string     $instruction
	 * @param int        $max_tokens
	 * @param bool       $json_mode
	 * @return array{ok:bool, text:string, provider:string, error?:string}
	 */
	public static function analyze( $image_input, $instruction, $max_tokens = 300, $json_mode = false ) {
		$b64 = '';
		$mime = 'image/jpeg';

		// Resolve input
		if ( is_numeric( $image_input ) && (int) $image_input > 0 ) {
			$file = get_attached_file( (int) $image_input );
			if ( $file && file_exists( $file ) ) {
				$image_input = $file;
			}
		}

		if ( is_string( $image_input ) && 0 === strpos( $image_input, 'data:' ) ) {
			if ( preg_match( '/^data:([^;]+);base64,(.+)$/', $image_input, $m ) ) {
				$mime = $m[1];
				$b64  = $m[2];
			}
		} elseif ( is_string( $image_input ) && filter_var( $image_input, FILTER_VALIDATE_URL ) ) {
			$resp = wp_remote_get( $image_input, [ 'timeout' => 20, 'sslverify' => false ] );
			if ( ! is_wp_error( $resp ) ) {
				$body = wp_remote_retrieve_body( $resp );
				$hdr  = wp_remote_retrieve_header( $resp, 'content-type' );
				$mime = $hdr ? (string) $hdr : 'image/jpeg';
				$b64  = base64_encode( $body );
			}
		} elseif ( is_string( $image_input ) && file_exists( $image_input ) && is_readable( $image_input ) ) {
			$mime = wp_check_filetype( $image_input )['type'] ?: 'image/jpeg';
			$b64  = base64_encode( (string) file_get_contents( $image_input ) );
		}

		if ( empty( $b64 ) ) {
			return [ 'ok' => false, 'error' => 'Unreadable image file or empty payload.' ];
		}

		// Use configured Google Gemini key from Social AI or suite
		$vmsai_s = get_option( 'vmsai_settings', [] );
		$key = $vmsai_s['text_key']['gemini'] ?? '';
		if ( empty( $key ) && class_exists( 'VMSB_Settings' ) ) {
			$key = VMSB_Settings::get( 'gemini_key' );
		}
		if ( empty( $key ) && class_exists( 'AILG_Secrets' ) ) {
			$key = AILG_Secrets::get( 'ailg_google_key' );
		}

		if ( empty( $key ) ) {
			return [ 'ok' => false, 'error' => 'No active Gemini Vision API key configured.' ];
		}

		$model = $vmsai_s['text_model']['gemini'] ?? 'gemini-3.5-flash';
		$url   = add_query_arg( 'key', rawurlencode( $key ), "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent" );

		$gen_config = [
			'temperature'     => 0.4,
			'maxOutputTokens' => (int) $max_tokens,
		];
		if ( $json_mode ) {
			$gen_config['responseMimeType'] = 'application/json';
		}

		$body = [
			'contents' => [
				[
					'parts' => [
						[ 'text' => $instruction ],
						[
							'inline_data' => [
								'mime_type' => $mime,
								'data'      => $b64,
							],
						],
					],
				],
			],
			'generationConfig' => $gen_config,
		];

		$resp = wp_remote_post( $url, [
			'timeout'   => 45,
			'sslverify' => false,
			'headers'   => [ 'Content-Type' => 'application/json' ],
			'body'      => wp_json_encode( $body ),
		] );

		if ( is_wp_error( $resp ) ) {
			return [ 'ok' => false, 'error' => 'Gemini Vision HTTP error: ' . $resp->get_error_message() ];
		}

		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		$text = '';
		foreach ( $data['candidates'][0]['content']['parts'] ?? [] as $p ) {
			$text .= $p['text'] ?? '';
		}

		if ( '' === trim( $text ) ) {
			$err = $data['error']['message'] ?? 'Empty response from Vision model.';
			return [ 'ok' => false, 'error' => $err ];
		}

		return [
			'ok'       => true,
			'text'     => trim( $text ),
			'provider' => 'gemini-vision',
			'model'    => $model,
		];
	}
}
