<?php
/**
 * Engine 2 — images.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Runs the image chain, then files the winning image into the media library
 * with an SEO filename, alt text, title and caption.
 */
class VMSAI_Image_Engine {

	/**
	 * Instantiated providers keyed by slug.
	 *
	 * @var VMSAI_Image_Provider[]
	 */
	private $providers = array();

	/**
	 * Platform-native canvas sizes.
	 *
	 * @var array<string,array{0:int,1:int}>
	 */
	private static $sizes = array(
		'facebook'  => array( 1200, 630 ),
		'instagram' => array( 1080, 1350 ),
		'x'         => array( 1600, 900 ),
		'linkedin'  => array( 1200, 627 ),
		'gbp'       => array( 1200, 900 ),
		'youtube'   => array( 1080, 1920 ),
		'threads'   => array( 1080, 1350 ),
		'bluesky'   => array( 1200, 675 ),
		'tiktok'    => array( 1080, 1920 ),
		'telegram'  => array( 1280, 720 ),
		'pinterest' => array( 1000, 1500 ),
	);

	/**
	 * Build the provider registry.
	 */
	public function __construct() {
		$registry = array(
			'openai'       => 'VMSAI_Image_OpenAI',
			'aipuffer'     => 'VMSAI_Image_Aipuffer',
			'huggingface'  => 'VMSAI_Image_HuggingFace',
			'cloudflare'   => 'VMSAI_Image_Cloudflare',
			'gemini'       => 'VMSAI_Image_Gemini',
			'pollinations' => 'VMSAI_Image_Pollinations',
			'comfyui'      => 'VMSAI_Image_Comfyui',
			'openrouter'   => 'VMSAI_Image_OpenRouter',
			'omniroute'    => 'VMSAI_Image_OmniRoute',
			'vmimageai'    => 'VMSAI_Image_VMImageAI',
			'pexels'       => 'VMSAI_Image_Pexels',
		);

		/**
		 * Filter the image provider class map.
		 *
		 * @param array $registry slug => class name.
		 */
		$registry = apply_filters( 'vmsai_image_providers', $registry );

		foreach ( $registry as $slug => $class ) {
			if ( class_exists( $class ) ) {
				$this->providers[ $slug ] = new $class();
			}
		}
	}

	/**
	 * All registered providers.
	 *
	 * @return VMSAI_Image_Provider[]
	 */
	public function providers() {
		return $this->providers;
	}

	/**
	 * Canvas size for a channel.
	 *
	 * @param string $channel Channel slug.
	 * @return array{0:int,1:int}
	 */
	public static function size_for( $channel ) {
		return self::$sizes[ $channel ] ?? array( 1080, 1350 );
	}

	/**
	 * Providers that return an existing stock photograph rather than making
	 * a new image. They cannot honour a visual brief, cannot produce a
	 * banner, infographic or quote card, and effectively never fail — so
	 * they must always sit behind every real generator in the chain.
	 *
	 * @return string[]
	 */
	public static function stock_providers() {
		return array( 'pexels' );
	}

	/**
	 * The active chain.
	 *
	 * @return string[]
	 */
	public function chain() {
		$chain = (array) VMSAI_Settings::get( 'image_chain', array() );
		$out   = array();

		foreach ( $chain as $slug ) {
			$provider = $this->providers[ $slug ] ?? null;
			if ( ! $provider || ! $provider->is_configured() ) {
				continue;
			}
			if ( ! VMSAI_Circuit::is_open( 'image:' . $slug ) ) {
				continue;
			}
			$out[] = $slug;
		}

		return $out;
	}

	/**
	 * Generate an image and attach it to the media library.
	 *
	 * @param string $prompt   Visual prompt.
	 * @param array  $meta     Keys: channel, topic, keyword, alt, title, post_id.
	 * @return array{ok:bool,attachment_id:int,url:string,provider:string,alt:string,credit:string,error:string}
	 */
	public function create( $prompt, array $meta = array() ) {
		$channel        = $meta['channel'] ?? 'instagram';
		list( $w, $h )  = self::size_for( $channel );
		$args           = array( 'width' => $w, 'height' => $h );
		$chain          = $this->chain();

		// Ensure stock-photo providers (which almost always "succeed") are always
		// pushed to the absolute end of the chain. Otherwise, if a user puts
		// Pexels first, real generation is never attempted.
		$stock    = array_values( array_intersect( $chain, self::stock_providers() ) );
		$generate = array_values( array_diff( $chain, $stock ) );

		// Pollinations needs no key, so it is the guaranteed floor generator —
		// but only when the operator left it configured. If they removed it
		// from image_chain on purpose, respect that; if their whole chain
		// is currently unusable (tripped breakers / missing keys), fall back
		// to it rather than failing outright.
		$configured     = array_map( 'strval', (array) VMSAI_Settings::get( 'image_chain', array() ) );
		$poll_configured = in_array( 'pollinations', $configured, true );
		$poll_usable     = isset( $this->providers['pollinations'] )
			&& $this->providers['pollinations']->is_configured()
			&& VMSAI_Circuit::is_open( 'image:pollinations' );
		if ( ! in_array( 'pollinations', $generate, true ) && $poll_usable && ( $poll_configured || empty( $generate ) ) ) {
			$generate[] = 'pollinations';
		}

		$chain = array_merge( $generate, $stock );

		$tried          = array();

		// Pro Test UX: If a specific provider is preferred (e.g. from a Test button),
		// ensure it is in the chain even if it's currently tripped or missing
		// from the settings-defined order.
		if ( ! empty( $meta['prefer'] ) && isset( $this->providers[ $meta['prefer'] ] ) ) {
			if ( ! in_array( $meta['prefer'], $chain, true ) ) {
				VMSAI_Logger::debug( 'engine.image', sprintf( 'Injecting preferred provider %s into chain.', $meta['prefer'] ) );
				$chain = array_merge( array( $meta['prefer'] ), $chain );
			} else {
				$chain = array_merge( array( $meta['prefer'] ), array_diff( $chain, array( $meta['prefer'] ) ) );
			}
		}

		if ( ! $chain ) {
			return $this->fail( __( 'No image provider is available. Pollinations needs no key — check the chain order.', 'vm-social-ai-pro' ), $tried );
		}

		// ELITE QUALITY ENHANCER: append a finishing suffix that matches the
		// requested format instead of always assuming photography — a banner
		// or infographic prompt asked for graphic design, not a photo shoot,
		// and the AI-written prompt text can't be reliably keyword-sniffed
		// for style (it won't necessarily echo back words like "banner").
		$format = (string) ( $meta['format'] ?? 'image' );

		if ( 'banner' === $format ) {
			$prompt .= ', high-end graphic design, premium typography, sharp vector graphics, professional layout, bold marketing aesthetic, 8k resolution';
		} elseif ( 'infographic' === $format ) {
			$prompt .= ', masterfully organized infographic, clean educational layout, modern professional icons, flat vector style, readable design elements, high-resolution';
		} else {
			$prompt .= ', professional advertising photography, commercial editorial style, cinematic lighting, 8k resolution, highly detailed, shot on 35mm lens, sharp focus, vibrant natural colors, masterfully composed';
		}

		VMSAI_Logger::debug( 'engine.image', 'Starting image generation chain.', array( 'chain' => $chain, 'format' => $format ) );

		foreach ( $chain as $slug ) {
			$provider = $this->providers[ $slug ] ?? null;
			if ( ! $provider ) {
				VMSAI_Logger::debug( 'engine.image', sprintf( 'Provider %s not found in registry.', $slug ) );
				continue;
			}

			VMSAI_Logger::debug( 'engine.image', sprintf( 'Trying provider: %s', $slug ) );
			$result = $provider->create( $prompt, $args );

			if ( empty( $result['ok'] ) ) {
				$tried[ $slug ] = $result['error'];
				VMSAI_Circuit::failure( 'image:' . $slug, $result['error'] );
				VMSAI_Logger::warn( 'engine.image', sprintf( '%s failed, falling through.', $slug ), array( 'error' => $result['error'] ) );
				continue;
			}

			VMSAI_Circuit::success( 'image:' . $slug );

			// Record Usage.
			VMSAI_Usage::record( array(
				'provider'   => $slug,
				'model'      => $result['model'] ?? $slug,
				'modality'   => 'image',
				'usage_type' => 'generation',
				'tokens_in'  => 1,
				'tokens_out' => 1,
				'cost'       => (float) ( $result['cost'] ?? 0 ),
			) );

			$attachment_id = $this->sideload( $result['binary'], $result['mime'], $meta, $slug, $result['credit'] );

			if ( is_wp_error( $attachment_id ) ) {
				$err_code = $attachment_id->get_error_code();
				$tried[ $slug ] = 'Sideload failed: ' . $attachment_id->get_error_message();
				VMSAI_Logger::warn( 'engine.image', sprintf( '%s sideload failed (%s).', $slug, $err_code ), array( 'error' => $tried[ $slug ] ) );

				// If it's a local filesystem error, don't bother trying other providers.
				if ( 'vmsai_upload_failed' === $err_code ) {
					return $this->fail( $tried[ $slug ], $tried );
				}
				continue;
			}

			// Priority: Remote URL > Local URL
			$remote_url = get_post_meta( $attachment_id, '_vmsai_remote_url', true );
			$url = $remote_url ?: (string) wp_get_attachment_url( $attachment_id );

			// Local-safe scheme: Use WordPress standard to match the current protocol,
			// which prevents SSL mixed-content blocks or broken local links.
			$url = set_url_scheme( $url );

			return array(
				'ok'            => true,
				'attachment_id' => $attachment_id,
				'url'           => $url,
				'provider'      => $slug,
				'alt'           => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
				'credit'        => (string) $result['credit'],
				'error'         => '',
				'tried'         => $tried,
			);
		}

		VMSAI_Logger::error( 'engine.image', 'Every image provider failed.', array( 'tried' => $tried, 'prompt' => substr( $prompt, 0, 500 ) ) );
		return $this->fail( __( 'Every image provider in the chain failed. Check the War Room logs for specific errors from each backend.', 'vm-social-ai-pro' ), $tried );
	}

	/**
	 * Write bytes into the uploads folder and register the attachment.
	 *
	 * @param string $binary   Raw image bytes.
	 * @param string $mime     Mime type.
	 * @param array  $meta     SEO metadata.
	 * @param string $provider Winning provider slug.
	 * @param string $credit   Attribution line, if any.
	 * @return int|WP_Error
	 */
	private function sideload( $binary, $mime, array $meta, $provider, $credit ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$map = array(
			'image/png'  => 'png',
			'image/webp' => 'webp',
			'image/gif'  => 'gif',
		);
		$extension = $map[ $mime ] ?? 'jpg';
		$filename  = $this->seo_filename( $meta, $extension );

		$upload = wp_upload_bits( $filename, null, $binary );

		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'vmsai_upload_failed', $upload['error'] );
		}

		$filetype = wp_check_filetype( $upload['file'], null );

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $filetype['type'] ?: $mime,
				'post_title'     => $this->seo_title( $meta ),
				'post_excerpt'   => $credit ?: $this->seo_caption( $meta ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$upload['file'],
			isset( $meta['post_id'] ) ? (int) $meta['post_id'] : 0
		);

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		// Pro Resilience: Generating thumbnails and watermarking are memory intensive.
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'image' );
		}

		// Apply branding BEFORE generating thumbnails so all sizes are branded.
		$this->maybe_watermark( $upload['file'] );

		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $this->seo_alt( $meta ) );
		update_post_meta( $attachment_id, '_vmsai_provider', $provider );
		update_post_meta( $attachment_id, '_vmsai_prompt', substr( (string) ( $meta['prompt'] ?? '' ), 0, 1000 ) );

		// Engine tests produce a throwaway image. Tagging it lets the next
		// test clear the previous one, instead of every click of "Test the
		// Picture Chain" leaving another orphan in the media library forever.
		if ( ! empty( $meta['is_test'] ) ) {
			update_post_meta( $attachment_id, '_vmsai_test', 1 );
		}

		// Offload to remote storage if enabled.
		if ( 'off' !== VMSAI_Settings::get( 'remote_storage', 'off' ) ) {
			$remote_url = VMSAI_Storage::upload( $upload['file'], $filename, $mime );
			if ( ! is_wp_error( $remote_url ) ) {
				update_post_meta( $attachment_id, '_vmsai_remote_url', $remote_url );
				// Note: We keep the local file for now to ensure watermarking and
				// internal WP metadata works. Retention cleanup will handle it later.
			} else {
				VMSAI_Logger::warn( 'engine.storage', 'Remote upload failed: ' . $remote_url->get_error_message() );
			}
		}

		return (int) $attachment_id;
	}

	/**
	 * Keyword-first, hyphenated, unique filename.
	 *
	 * @param array  $meta      Metadata.
	 * @param string $extension File extension.
	 * @return string
	 */
	private function seo_filename( array $meta, $extension ) {
		$base = $meta['keyword'] ?? $meta['topic'] ?? get_bloginfo( 'name' );
		$slug = sanitize_title( mb_substr( wp_strip_all_tags( (string) $base ), 0, 60 ) );
		$slug = $slug ?: 'social-post';

		return $slug . '-' . ( $meta['channel'] ?? 'social' ) . '-' . wp_generate_password( 5, false, false ) . '.' . $extension;
	}

	/**
	 * Attachment title.
	 *
	 * @param array $meta Metadata.
	 * @return string
	 */
	private function seo_title( array $meta ) {
		$topic = $meta['topic'] ?? $meta['keyword'] ?? __( 'Social post visual', 'vm-social-ai-pro' );
		return wp_trim_words( wp_strip_all_tags( (string) $topic ), 12, '' );
	}

	/**
	 * Descriptive alt text, which is what actually earns image search traffic.
	 *
	 * @param array $meta Metadata.
	 * @return string
	 */
	private function seo_alt( array $meta ) {
		if ( ! empty( $meta['alt'] ) ) {
			return mb_substr( wp_strip_all_tags( (string) $meta['alt'] ), 0, 220 );
		}

		$keyword = (string) ( $meta['keyword'] ?? '' );
		$topic   = (string) ( $meta['topic'] ?? '' );
		$brand   = (string) VMSAI_Brain::get( 'business_name', get_bloginfo( 'name' ) );

		$parts = array_filter( array( $keyword ?: $topic, $brand ) );
		return mb_substr( implode( ' — ', $parts ), 0, 220 );
	}

	/**
	 * Caption used when a provider supplies no attribution.
	 *
	 * @param array $meta Metadata.
	 * @return string
	 */
	private function seo_caption( array $meta ) {
		$brand = (string) VMSAI_Brain::get( 'business_name', get_bloginfo( 'name' ) );
		return sprintf(
			/* translators: %s: business name */
			__( 'Created for %s with VM Social AI.', 'vm-social-ai-pro' ),
			$brand
		);
	}

	/**
	 * Composite the brand mark onto the saved file when enabled.
	 *
	 * @param string $path Absolute file path.
	 * @return void
	 */
	private function maybe_watermark( $path ) {
		if ( ! VMSAI_Settings::get( 'brand_watermark' ) ) {
			return;
		}

		// Hardening: Ensure we have a local path. Remote storage plugins
		// (S3/DO) might give us a URL or a stream wrapper here.
		if ( ! file_exists( $path ) ) {
			return;
		}

		$mark_id = (int) VMSAI_Settings::get( 'watermark_id' );
		$mark    = $mark_id ? get_attached_file( $mark_id ) : '';

		if ( ! $mark || ! file_exists( $mark ) ) {
			return;
		}

		if ( ! extension_loaded( 'gd' ) ) {
			VMSAI_Logger::warn( 'engine.image', 'Watermark skipped: PHP GD extension not found.' );
			return;
		}

		$editor = wp_get_image_editor( $path );
		if ( is_wp_error( $editor ) ) {
			return;
		}

		$size = $editor->get_size();
		$base = @imagecreatefromstring( (string) file_get_contents( $path ) ); // phpcs:ignore
		$logo = @imagecreatefrompng( $mark ); // phpcs:ignore

		if ( ! $base || ! $logo ) {
			return;
		}

		$logo_w = (int) ( $size['width'] * 0.18 );
		$logo_h = (int) ( imagesy( $logo ) * ( $logo_w / imagesx( $logo ) ) );
		$pad    = (int) ( $size['width'] * 0.04 );

		imagealphablending( $base, true );
		imagesavealpha( $base, true );

		imagecopyresampled(
			$base,
			$logo,
			$size['width'] - $logo_w - $pad,
			$size['height'] - $logo_h - $pad,
			0,
			0,
			$logo_w,
			$logo_h,
			imagesx( $logo ),
			imagesy( $logo )
		);

		$ext = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( 'png' === $ext ) {
			imagepng( $base, $path, 8 );
		} elseif ( ( 'webp' === $ext || 'image/webp' === $size['mime'] ) && function_exists( 'imagewebp' ) ) {
			imagewebp( $base, $path, 85 );
		} else {
			imagejpeg( $base, $path, 88 );
		}

		imagedestroy( $base );
		imagedestroy( $logo );
	}

	/**
	 * Failure shape.
	 *
	 * @param string $error Message.
	 * @param array  $tried Attempted providers and their errors.
	 * @return array
	 */
	private function fail( $error, array $tried = array() ) {
		return array( 'ok' => false, 'attachment_id' => 0, 'url' => '', 'provider' => '', 'alt' => '', 'credit' => '', 'error' => $error, 'tried' => $tried );
	}
}
