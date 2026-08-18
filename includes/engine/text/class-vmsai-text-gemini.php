<?php
/**
 * Google Gemini text provider.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Google AI Studio (Generative Language API) backend.
 */
class VMSAI_Text_Gemini implements VMSAI_Text_Provider {

	const BASE = 'https://generativelanguage.googleapis.com/v1beta';

	/**
	 * Provider slug.
	 *
	 * @return string
	 */
	public function slug() {
		return 'gemini';
	}

	/**
	 * Provider label.
	 *
	 * @return string
	 */
	public function label() {
		return 'Google Gemini';
	}

	/**
	 * Configured when an API key exists.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== VMSAI_Settings::credential( 'gemini_key' );
	}

	/**
	 * Generate text.
	 *
	 * @param string $system System instruction.
	 * @param string $prompt User prompt.
	 * @param array  $args   Options.
	 * @return array
	 */
	public function generate( $system, $prompt, array $args = array() ) {
		$key   = VMSAI_Settings::credential( 'gemini_key' );
		// An empty string (saved whenever the Engines form is submitted with
		// this field left blank) is not null, so ?? alone would never reach
		// the hardcoded default — treat blank the same as unset.
		$model = ( $args['model'] ?? '' ) ?: ( ( VMSAI_Settings::get( 'text_model' )['gemini'] ?? '' ) ?: self::default_model() );

		$payload = array(
			'systemInstruction' => array( 'parts' => array( array( 'text' => $system ) ) ),
			'contents'          => array(
				array(
					'role'  => 'user',
					'parts' => array( array( 'text' => $prompt ) ),
				),
			),
			'generationConfig'  => array(
				'temperature'     => (float) ( $args['temperature'] ?? 0.85 ),
				'maxOutputTokens' => (int) ( $args['max_tokens'] ?? 1600 ),
			),
			'safetySettings'    => array(),
		);

		// VISION SUPPORT: Handle attached image data
		if ( ! empty($args['image_binary']) && ! empty($args['image_mime']) ) {
			$payload['contents'][0]['parts'][] = array(
				'inlineData' => array(
					'mimeType' => $args['image_mime'],
					'data'     => base64_encode( $args['image_binary'] )
				)
			);
		}

		if ( ! empty( $args['json'] ) ) {
			$payload['generationConfig']['responseMimeType'] = 'application/json';
		}

		// GROUNDING: Enable Google Search Retrieval if requested or if trending context is needed.
		if ( ! empty( $args['grounding'] ) || ! empty( $args['search'] ) ) {
			$payload['tools'] = array( array( 'google_search_retrieval' => array() ) );
		}

		$url = self::BASE . '/models/' . rawurlencode( $model ) . ':generateContent';

		$response = VMSAI_Http::post(
			$url,
			array(
				'headers' => array( 'x-goog-api-key' => $key ),
				'json'    => $payload,
				'scope'   => 'engine.text.gemini',
				'retries' => 1,
			)
		);

		if ( ! $response['ok'] ) {
			return array( 'ok' => false, 'text' => '', 'model' => $model, 'error' => $response['error'], 'status' => (int) ( $response['status'] ?? 0 ) );
		}

		$parts = $response['json']['candidates'][0]['content']['parts'] ?? array();
		$text  = '';
		foreach ( (array) $parts as $part ) {
			$text .= (string) ( $part['text'] ?? '' );
		}

		if ( '' === trim( $text ) ) {
			$candidate = $response['json']['candidates'][0] ?? array();
			$reason    = $candidate['finishReason'] ?? 'unknown';
			$msg       = 'Empty Gemini response (' . $reason . ').';

			if ( 'SAFETY' === $reason ) {
				$msg = 'Gemini safety filters blocked this prompt.';
			} elseif ( 'RECITATION' === $reason ) {
				$msg = 'Gemini refused to output copyright content.';
			}

			return array( 'ok' => false, 'text' => '', 'model' => $model, 'error' => $msg );
		}

		return array(
			'ok'    => true,
			'text'  => $text,
			'model' => $model,
			'error' => '',
			'usage' => array(
				'prompt_tokens'     => $response['json']['usageMetadata']['promptTokenCount'] ?? 0,
				'completion_tokens' => $response['json']['usageMetadata']['candidatesTokenCount'] ?? 0,
				'total_tokens'      => $response['json']['usageMetadata']['totalTokenCount'] ?? 0,
			),
		);
	}

	/**
	 * A model that's actually likely to work today. Google periodically
	 * deprecates model IDs (a hardcoded string here inevitably goes stale —
	 * this is what actually happened with the previous default), so prefer
	 * whatever the last "Refresh model list" sync found still live, and only
	 * fall back to a hardcoded guess if no sync has ever run for Gemini.
	 *
	 * @return string
	 */
	private static function default_model() {
		$synced = VMSAI_Model_Sync::models( 'gemini', 'text' );

		// Priority 1: Pick the first synced model that matches the user's "Banana/Flash" preference.
		foreach ( $synced as $m ) {
			if ( preg_match( '/gemini-(3\.[56]|1\.5|2\.0)-flash/i', $m['model_id'] ) ) {
				return (string) $m['model_id'];
			}
		}

		if ( ! empty( $synced[0]['model_id'] ) ) {
			return (string) $synced[0]['model_id'];
		}

		return 'gemini-1.5-flash';
	}

	/**
	 * Live model list.
	 *
	 * @return array
	 */
	public function list_models() {
		if ( ! $this->is_configured() ) {
			return array();
		}

		$key = VMSAI_Settings::credential( 'gemini_key' );

		// Try with key in URL for maximum compatibility across regions
		$response = VMSAI_Http::get(
			self::BASE . '/models?key=' . $key,
			array(
				'scope'   => 'engine.text.gemini',
				'retries' => 0,
				'timeout' => 25,
			)
		);

		if ( ! $response['ok'] ) {
			VMSAI_Logger::error( 'engine.text.gemini', 'Model sync failed: ' . $response['error'] );
			return array();
		}

		$models = array();
		foreach ( (array) ( $response['json']['models'] ?? array() ) as $model ) {
			$methods = (array) ( $model['supportedGenerationMethods'] ?? array() );
			if ( ! in_array( 'generateContent', $methods, true ) ) {
				continue;
			}
			$id       = str_replace( 'models/', '', (string) ( $model['name'] ?? '' ) );
			$models[] = array(
				'id'      => $id,
				'label'   => (string) ( $model['displayName'] ?? $id ),
				'context' => (int) ( $model['inputTokenLimit'] ?? 0 ),
				'free'    => ( false !== strpos( $id, 'flash' ) ) || ( false !== strpos( $id, 'nano' ) ),
			);
		}

		return $models;
	}
}
