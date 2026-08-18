<?php
/**
 * OpenRouter text provider.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * OpenRouter aggregator backend. Its catalogue includes zero-cost models,
 * which the model sync flags so the chain can prefer free capacity.
 */
class VMSAI_Text_Openrouter implements VMSAI_Text_Provider {

	const BASE = 'https://openrouter.ai/api/v1';

	/**
	 * Provider slug.
	 *
	 * @return string
	 */
	public function slug() {
		return 'openrouter';
	}

	/**
	 * Provider label.
	 *
	 * @return string
	 */
	public function label() {
		return 'OpenRouter';
	}

	/**
	 * Configured when an API key exists.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== VMSAI_Settings::credential( 'openrouter_key' );
	}

	/**
	 * Request headers, including the attribution headers OpenRouter expects.
	 *
	 * @return array
	 */
	private function headers() {
		return array(
			'Authorization' => 'Bearer ' . VMSAI_Settings::credential( 'openrouter_key' ),
			'HTTP-Referer'  => home_url( '/' ),
			'X-Title'       => get_bloginfo( 'name' ) . ' — VM Social AI',
		);
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
		// An empty string (saved whenever the Engines form is submitted with
		// this field left blank) is not null, so ?? alone would never reach
		// the hardcoded default — treat blank the same as unset.
		$model = ( $args['model'] ?? '' ) ?: ( ( VMSAI_Settings::get( 'text_model' )['openrouter'] ?? '' ) ?: self::default_model() );

		$payload = array(
			'model'       => $model,
			'messages'    => array(
				array( 'role' => 'system', 'content' => $system ),
				array( 'role' => 'user', 'content' => $prompt ),
			),
			'temperature' => (float) ( $args['temperature'] ?? 0.85 ),
			'max_tokens'  => (int) ( $args['max_tokens'] ?? 1600 ),
		);

		if ( ! empty( $args['json'] ) ) {
			$payload['response_format'] = array( 'type' => 'json_object' );
		}

		$response = VMSAI_Http::post(
			self::BASE . '/chat/completions',
			array(
				'headers' => $this->headers(),
				'json'    => $payload,
				'scope'   => 'engine.text.openrouter',
				'retries' => 1,
			)
		);

		if ( ! $response['ok'] ) {
			return array( 'ok' => false, 'text' => '', 'model' => $model, 'error' => $response['error'], 'status' => (int) ( $response['status'] ?? 0 ) );
		}

		$text = (string) ( $response['json']['choices'][0]['message']['content'] ?? '' );

		if ( '' === trim( $text ) ) {
			return array( 'ok' => false, 'text' => '', 'model' => $model, 'error' => 'Empty OpenRouter response.' );
		}

		return array(
			'ok'    => true,
			'text'  => $text,
			'model' => $model,
			'error' => '',
			'usage' => array(
				'prompt_tokens'     => $response['json']['usage']['prompt_tokens'] ?? 0,
				'completion_tokens' => $response['json']['usage']['completion_tokens'] ?? 0,
				'total_tokens'      => $response['json']['usage']['total_tokens'] ?? 0,
			),
		);
	}

	/**
	 * A model that's actually likely to work today. OpenRouter's free-tier
	 * model IDs churn (the previous hardcoded default was itself replaced
	 * upstream), so prefer whatever the last "Refresh model list" sync found
	 * still live, and only fall back to a hardcoded guess if no sync has
	 * ever run for OpenRouter.
	 *
	 * @return string
	 */
	private static function default_model() {
		$synced = VMSAI_Model_Sync::models( 'openrouter', 'text' );

		if ( ! empty( $synced[0]['model_id'] ) ) {
			return (string) $synced[0]['model_id'];
		}

		return 'meta-llama/llama-3.3-70b-instruct:free';
	}

	/**
	 * Live model list. This endpoint is public, so it works before a key is set.
	 *
	 * @return array
	 */
	public function list_models() {
		$response = VMSAI_Http::get(
			self::BASE . '/models',
			array( 'scope' => 'engine.text.openrouter', 'retries' => 0, 'timeout' => 25 )
		);

		if ( ! $response['ok'] ) {
			VMSAI_Logger::error( 'engine.text.openrouter', 'Model sync failed: ' . $response['error'] );
			return array();
		}

		$models = array();
		foreach ( (array) ( $response['json']['data'] ?? array() ) as $model ) {
			$id = (string) ( $model['id'] ?? '' );
			if ( ! $id ) {
				continue;
			}
			$prompt_cost = (float) ( $model['pricing']['prompt'] ?? 0 );
			$models[]    = array(
				'id'      => $id,
				'label'   => (string) ( $model['name'] ?? $id ),
				'context' => (int) ( $model['context_length'] ?? 0 ),
				'free'    => ( 0.0 === $prompt_cost ) || false !== strpos( $id, ':free' ),
			);
		}

		return $models;
	}
}
