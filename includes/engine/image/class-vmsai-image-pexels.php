<?php
/**
 * Pexels stock image provider.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Last link in the image chain. Generation can fail; a licensed stock search
 * almost never does, so a scheduled post still ships with visual media.
 */
class VMSAI_Image_Pexels implements VMSAI_Image_Provider {

	const BASE = 'https://api.pexels.com/v1/search';

	/**
	 * Provider slug.
	 *
	 * @return string
	 */
	public function slug() {
		return 'pexels';
	}

	/**
	 * Provider label.
	 *
	 * @return string
	 */
	public function label() {
		return 'Pexels (stock)';
	}

	/**
	 * Configured when an API key exists.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== VMSAI_Settings::credential( 'pexels_key' );
	}

	/**
	 * Find a matching photo.
	 *
	 * @param string $prompt Visual prompt, reduced to search terms.
	 * @param array  $args   Options.
	 * @return array
	 */
	public function create( $prompt, array $args = array() ) {
		$height      = (int) ( $args['height'] ?? 1350 );
		$width       = (int) ( $args['width'] ?? 1080 );
		$orientation = $height > $width ? 'portrait' : ( $width > $height ? 'landscape' : 'square' );

		$url = add_query_arg(
			array(
				'query'       => rawurlencode( $this->keywords( $prompt ) ),
				'per_page'    => 15,
				'orientation' => $orientation,
			),
			self::BASE
		);

		$response = VMSAI_Http::get(
			$url,
			array(
				'headers' => array( 'Authorization' => VMSAI_Settings::credential( 'pexels_key' ) ),
				'scope'   => 'engine.image.pexels',
				'retries' => 1,
				'timeout' => 30,
			)
		);

		if ( ! $response['ok'] ) {
			return $this->fail( $response['error'] );
		}

		$photos = (array) ( $response['json']['photos'] ?? array() );

		if ( ! $photos ) {
			return $this->fail( __( 'No Pexels result matched that topic.', 'vm-social-ai-pro' ) );
		}

		$photo  = $photos[ array_rand( $photos ) ];
		$source = $photo['src']['large2x'] ?? $photo['src']['original'] ?? '';

		if ( ! $source ) {
			return $this->fail( __( 'Pexels result had no usable file.', 'vm-social-ai-pro' ) );
		}

		$binary = VMSAI_Http::fetch_binary( $source, 60 );

		if ( ! $binary ) {
			return $this->fail( __( 'Could not download the Pexels file.', 'vm-social-ai-pro' ) );
		}

		return array(
			'ok'     => true,
			'binary' => $binary,
			'url'    => $source,
			'mime'   => 'image/jpeg',
			'credit' => sprintf(
				/* translators: 1: photographer name, 2: Pexels page URL */
				__( 'Photo by %1$s on Pexels (%2$s)', 'vm-social-ai-pro' ),
				(string) ( $photo['photographer'] ?? 'Pexels' ),
				(string) ( $photo['url'] ?? 'https://www.pexels.com' )
			),
			'error'  => '',
		);
	}

	/**
	 * Reduce a rich visual prompt to a short stock search phrase.
	 *
	 * @param string $prompt Prompt.
	 * @return string
	 */
	private function keywords( $prompt ) {
		$prompt = strtolower( wp_strip_all_tags( (string) $prompt ) );

		// Remove parenthetical style notes often added by the engine
		$prompt = preg_replace( '/\(.*?\)/', '', $prompt );
		$prompt = preg_replace( '/[^a-z0-9\s]/', ' ', $prompt );

		$noise = array( 'photo', 'image', 'shot', 'high', 'quality', 'detailed', 'cinematic', 'lighting', 'render', '4k', '8k', 'ultra', 'realistic', 'style', 'background', 'with', 'and', 'the', 'for', 'a', 'of', 'in', 'on', 'macro', 'lens', 'focus', 'professional', 'commercial', 'advertising', 'photography', 'visual', 'brief', 'technical', 'details', 'subject', 'literal' );
		$words = array_diff( array_filter( explode( ' ', $prompt ) ), $noise );

		// If the prompt is long, the first few words are now highly likely to be the subject
		// thanks to the "Subject-First" composer update.
		return implode( ' ', array_slice( $words, 0, 3 ) );
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
