<?php
/**
 * Anthropic (Claude) text engine.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Interface to Anthropic's Claude models.
 */
class VMSAI_Text_Anthropic implements VMSAI_Text_Provider {

	const BASE = 'https://api.anthropic.com/v1';

	public function slug() {
		return 'anthropic';
	}

	public function label() {
		return 'Anthropic (Claude)';
	}

	public function is_configured() {
		return (bool) VMSAI_Settings::credential( 'anthropic_key' );
	}

	public function generate( $system, $prompt, array $args = array() ) {
		$key   = VMSAI_Settings::credential( 'anthropic_key' );
		$model = $args['model'] ?? 'claude-3-5-sonnet-20240620';

		$payload = array(
			'model'     => $model,
			'max_tokens' => $args['max_tokens'] ?? 2000,
			'system'    => $system,
			'messages'  => array(
				array( 'role' => 'user', 'content' => $prompt )
			),
			'temperature' => (float) ($args['temperature'] ?? 0.7),
		);

		$response = VMSAI_Http::post( self::BASE . '/messages', array(
			'headers' => array(
				'x-api-key'         => $key,
				'anthropic-version' => '2023-06-01',
				'Content-Type'      => 'application/json'
			),
			'json'    => $payload,
			'scope'   => 'engine.text.anthropic'
		) );

		if ( ! $response['ok'] ) {
			return array( 'ok' => false, 'text' => '', 'model' => $model, 'error' => $response['error'] );
		}

		$text = $response['json']['content'][0]['text'] ?? '';

		return array(
			'ok'    => true,
			'text'  => $text,
			'model' => $model,
			'error' => '',
			'usage' => array(
				'prompt_tokens'     => $response['json']['usage']['input_tokens'] ?? 0,
				'completion_tokens' => $response['json']['usage']['output_tokens'] ?? 0,
			),
		);
	}

	public function list_models() {
		return array(
			array( 'id' => 'claude-3-5-sonnet-20240620', 'label' => 'Claude 3.5 Sonnet (Elite)', 'context' => 200000, 'free' => false ),
			array( 'id' => 'claude-3-haiku-20240307', 'label' => 'Claude 3 Haiku (Fast)', 'context' => 200000, 'free' => false ),
		);
	}
}
