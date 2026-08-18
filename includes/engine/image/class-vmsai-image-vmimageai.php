<?php
/**
 * VM Image AI provider — the sibling plugin's router, as one link in the chain.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * VM Image AI used to be called before the chain and returned early on
 * success, which meant this plugin's provider order, circuit breakers and
 * format-aware prompt suffix were all skipped whenever it was installed —
 * and whatever its own settings happened to prefer (often stock photos) won
 * every time, with nothing in this plugin's UI to explain why.
 *
 * Wrapping it as an ordinary provider puts it back under the same rules as
 * everything else: it takes its position in the chain, it can be reordered
 * or disabled, and when it fails the next provider is tried.
 */
class VMSAI_Image_VMImageAI implements VMSAI_Image_Provider {

	/**
	 * Machine slug.
	 *
	 * @return string
	 */
	public function slug() {
		return 'vmimageai';
	}

	/**
	 * Human label.
	 *
	 * @return string
	 */
	public function label() {
		return 'VM Image AI (sibling plugin)';
	}

	/**
	 * Only usable when the sibling plugin is actually active.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return class_exists( 'VMIA_Image_Router' );
	}

	/**
	 * Hand the prompt to VM Image AI's router.
	 *
	 * @param string $prompt Visual prompt.
	 * @param array  $args   Keys: width, height.
	 * @return array
	 */
	public function create( $prompt, array $args = array() ) {
		if ( ! $this->is_configured() ) {
			return $this->fail( __( 'VM Image AI is not active.', 'vm-social-ai-pro' ) );
		}

		$router = new VMIA_Image_Router();

		$res = $router->generate(
			$prompt,
			array(
				'width'  => (int) ( $args['width'] ?? 1080 ),
				'height' => (int) ( $args['height'] ?? 1350 ),
				// The prompt handed over is already a complete, format-specific
				// visual brief. VM Image AI rewrites the subject through its own
				// generic style baseline when enrich is on, which would discard
				// the business grounding and the agent's visual identity.
				'enrich' => false,
			)
		);

		if ( empty( $res['ok'] ) ) {
			return $this->fail( (string) ( $res['error'] ?? __( 'VM Image AI returned no image.', 'vm-social-ai-pro' ) ) );
		}

		// The router hands back either a URL or a local path; this engine
		// sideloads bytes itself, so fetch them here.
		$binary = '';

		if ( ! empty( $res['url'] ) ) {
			$fetched = VMSAI_Http::get( (string) $res['url'], array( 'scope' => 'image.vmimageai', 'timeout' => 60 ) );

			if ( empty( $fetched['ok'] ) ) {
				return $this->fail( (string) $fetched['error'] );
			}

			$binary = (string) $fetched['body'];
		} elseif ( ! empty( $res['path'] ) && file_exists( $res['path'] ) ) {
			$binary = (string) file_get_contents( $res['path'] ); // phpcs:ignore
		}

		if ( '' === $binary ) {
			return $this->fail( __( 'VM Image AI produced no readable image data.', 'vm-social-ai-pro' ) );
		}

		return array(
			'ok'     => true,
			'binary' => $binary,
			'url'    => (string) ( $res['url'] ?? '' ),
			'mime'   => $this->sniff_mime( $binary ),
			'credit' => '',
			'error'  => '',
			'model'  => (string) ( $res['provider'] ?? 'vmimageai' ),
		);
	}

	/**
	 * Work out the mime type from the bytes rather than trusting a filename.
	 *
	 * @param string $binary Image bytes.
	 * @return string
	 */
	private function sniff_mime( $binary ) {
		if ( 0 === strpos( $binary, "\x89PNG" ) ) {
			return 'image/png';
		}
		if ( 0 === strpos( $binary, 'RIFF' ) && false !== strpos( substr( $binary, 0, 16 ), 'WEBP' ) ) {
			return 'image/webp';
		}

		return 'image/jpeg';
	}

	/**
	 * Failure shape.
	 *
	 * @param string $error Message.
	 * @return array
	 */
	private function fail( $error ) {
		return array( 'ok' => false, 'binary' => '', 'url' => '', 'mime' => '', 'credit' => '', 'error' => (string) $error );
	}
}
