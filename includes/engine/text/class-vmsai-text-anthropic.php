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
		$model = $args['model'] ?? self::default_model();

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

	/**
	 * A model that's actually likely to work today. Anthropic retires dated
	 * model IDs (claude-3-5-sonnet-20240620 is already retired), so prefer
	 * whatever the last model sync found still live — same pattern as the
	 * Gemini provider — and only fall back to a hardcoded current-model
	 * guess if no sync has ever run.
	 *
	 * @return string
	 */
	private static function default_model() {
		$synced = VMSAI_Model_Sync::models( 'anthropic', 'text' );

		// Priority 1: the newest Sonnet from the sync (best default balance).
		foreach ( $synced as $m ) {
			if ( preg_match( '/claude-(sonnet-4|3-7-sonnet|3-5-sonnet)/i', (string) ( $m['model_id'] ?? '' ) ) ) {
				return (string) $m['model_id'];
			}
		}

		// Priority 2: anything the sync returned at all.
		if ( ! empty( $synced[0]['model_id'] ) ) {
			return (string) $synced[0]['model_id'];
		}

		return 'claude-sonnet-4-20250514';
	}

	public function list_models() {
		$static = array(
			array( 'id' => 'claude-sonnet-4-20250514', 'label' => 'Claude Sonnet 4 (Default)', 'context' => 200000, 'free' => false ),
			array( 'id' => 'claude-3-5-haiku-20241022', 'label' => 'Claude 3.5 Haiku (Fast)', 'context' => 200000, 'free' => false ),
		);

		// Merge in models discovered by the sync so the picker reflects what
		// Anthropic actually serves today.
		$synced = VMSAI_Model_Sync::models( 'anthropic', 'text' );
		foreach ( $synced as $m ) {
			$id = (string) ( $m['model_id'] ?? '' );
			if ( $id && ! wp_list_filter( $static, array( 'id' => $id ) ) ) {
				$static[] = array(
					'id'      => $id,
					'label'   => (string) ( $m['label'] ?? $id ),
					'context' => (int) ( $m['context_length'] ?? 200000 ),
					'free'    => ! empty( $m['is_free'] ),
				);
			}
		}

		return $static;
	}
}
