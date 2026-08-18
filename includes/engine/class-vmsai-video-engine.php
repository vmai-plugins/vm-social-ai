<?php
/**
 * Engine 3 — video.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles cinematic video generation and sourcing.
 */
class VMSAI_Video_Engine {

	/**
	 * Available providers.
	 *
	 * @return array
	 */
	public function providers() {
		return array(
			'aipuffer'     => __( 'AI Puffer (Veo via AIPKit)', 'vm-social-ai-pro' ),
			'minimax'      => __( 'Minimax / Hailuo AI', 'vm-social-ai-pro' ),
			'luma'         => __( 'Luma Dream Machine', 'vm-social-ai-pro' ),
			'heygen'       => __( 'HeyGen AI Avatar (Digital Twin)', 'vm-social-ai-pro' ),
			'cogvideox'    => __( 'CogVideoX (HF)', 'vm-social-ai-pro' ),
			'pexels'       => __( 'Pexels Stock', 'vm-social-ai-pro' ),
			'pollinations' => __( 'Pollinations (free)', 'vm-social-ai-pro' ),
			'svd'          => __( 'Stable Video Diffusion', 'vm-social-ai-pro' ),
		);
	}

	/**
	 * Whether AI Puffer can be called for video: either an explicit key is
	 * saved, or AIPKit is installed on this same site and exposes its own.
	 *
	 * @return bool
	 */
	public static function aipuffer_ready() {
		return '' !== VMSAI_Settings::credential( 'aipuffer_key' ) || '' !== self::aipuffer_local_key();
	}

	/**
	 * Models a provider is known to offer.
	 *
	 * @param string $slug Provider slug.
	 * @return array List of {id, label}.
	 */
	public function list_models( $slug ) {
		$models = array();

		if ( 'aipuffer' === $slug ) {
			// LOCAL SYNC: If on same site, try to reach into the backend classes directly.
			$site = VMSAI_Settings::credential( 'aipuffer_site' );
			$is_local = ( ! $site || strpos( home_url(), (string) $site ) !== false );

			if ( $is_local && class_exists( '\WPAICG\AIPKit_Providers' ) ) {
				if ( method_exists( '\WPAICG\AIPKit_Providers', 'get_google_video_models' ) ) {
					$list = \WPAICG\AIPKit_Providers::get_google_video_models();
					foreach ( (array) $list as $m ) {
						$id = is_array( $m ) ? ( $m['id'] ?? '' ) : (string) $m;
						if ( $id ) {
							$models[] = array(
								'id'    => $id,
								'label' => 'AIP: ' . ( is_array( $m ) ? ( $m['name'] ?? $id ) : $id )
							);
						}
					}
				}
			}

			// Fallback to known models if local sync found nothing or isn't local.
			if ( empty($models) ) {
				$models = array(
					array( 'id' => 'veo-3.0-generate-preview', 'label' => 'Google Veo 3 (preview)' ),
					array( 'id' => 'veo-3.0-fast-generate-preview', 'label' => 'Google Veo 3 Fast (preview)' ),
					array( 'id' => 'veo-2.0-generate-001', 'label' => 'Google Veo 2' ),
				);
			}
		}

		if ( 'minimax' === $slug ) {
			$models = array(
				array( 'id' => 'video-01', 'label' => 'MiniMax Video-01' ),
				array( 'id' => 'video-01-live', 'label' => 'MiniMax Video-01 Live' ),
			);
		}

		if ( 'luma' === $slug ) {
			$models = array(
				array( 'id' => 'dream-machine', 'label' => 'Dream Machine' ),
			);
		}

		if ( 'cogvideox' === $slug ) {
			$models = array(
				array( 'id' => 'cogvideox-5b', 'label' => 'CogVideoX-5b' ),
			);
		}

		return $models;
	}

	/**
	 * Available Video Ad Templates for trending platforms.
	 *
	 * @return array
	 */
	public static function templates() {
		return array(
			'cinematic_product' => array(
				'label'  => __( 'Cinematic Product Spotlight', 'vm-social-ai-pro' ),
				'prompt' => 'Macro lens, slow motion, shallow depth of field, warm cinematic lighting, professional product videography, 8k resolution, elegant movements.',
				'audio'  => 'corporate_modern',
			),
			'urban_aesthetic'   => array(
				'label'  => __( 'Urban Aesthetic / Street', 'vm-social-ai-pro' ),
				'prompt' => 'Handheld camera movement, grainy film texture, vibrant street lighting, fast-paced transitions, high contrast, trendy urban vibe.',
				'audio'  => 'lofi_street',
			),
			'minimal_tech'      => array(
				'label'  => __( 'Minimal Tech / Modern', 'vm-social-ai-pro' ),
				'prompt' => 'Clean white backgrounds, smooth gimbal shots, high-tech interface vibes, soft shadows, blue and white color palette, professional tech presentation.',
				'audio'  => 'tech_pulse',
			),
			'fast_fashion'      => array(
				'label'  => __( 'Fast-Paced Fashion', 'vm-social-ai-pro' ),
				'prompt' => 'Strobe lighting effects, rapid cuts, high-energy model movements, colorful backgrounds, 9:16 vertical, trendy fashion editorial style.',
				'audio'  => 'energetic_pop',
			),
			'relaxing_wellness' => array(
				'label'  => __( 'Relaxing Wellness / Nature', 'vm-social-ai-pro' ),
				'prompt' => 'Natural sunlight, slow panning shots, organic textures, greenery, soft pastel colors, peaceful and airy atmosphere.',
				'audio'  => 'nature_ambience',
			),
			'info_product'      => array(
				'label'  => __( 'Info Product / Educational', 'vm-social-ai-pro' ),
				'prompt' => 'Clean desk, high-end laptop, notebook with sketches, person writing on whiteboard, focus on educational materials, professional and bright.',
				'audio'  => 'motivational_business',
			),
			'anime_stylized'    => array(
				'label'  => __( 'Anime Mode / Animation', 'vm-social-ai-pro' ),
				'prompt' => 'High-octane Shonen anime style, vibrant cel-shading, dynamic camera angles, expressive line art, cinematic lighting, magical particles, epic energy.',
				'audio'  => 'energetic_synth',
			),
			'web_agency_sleek'  => array(
				'label'  => __( 'Web Design / UI Showcase', 'vm-social-ai-pro' ),
				'prompt' => 'Sleek glassmorphism UI elements, smooth scrolling laptop screens, minimalist workspace, tech-forward aesthetic, 4k macro shots of code and design.',
				'audio'  => 'tech_pulse',
			),
			'travel_adventure'  => array(
				'label'  => __( 'Travel / Epic Adventure', 'vm-social-ai-pro' ),
				'prompt' => 'Breathtaking 4k drone footage, expansive landscapes, turquoise water, golden hour lighting, epic handheld movement, cinematic nature transitions.',
				'audio'  => 'cinematic_orchestral',
			),
			'local_community'   => array(
				'label'  => __( 'Hyper-Local / Shop Hero', 'vm-social-ai-pro' ),
				'prompt' => 'Warm handheld shot of a local storefront, welcoming atmosphere, artisanal product close-ups, friendly community vibes, natural sun-drenched lighting.',
				'audio'  => 'warm_acoustic',
			),
			'vsl_promo'         => array(
				'label'  => __( 'Video Sales Letter (VSL)', 'vm-social-ai-pro' ),
				'prompt' => 'Professional studio environment, soft key lighting, shallow depth of field, minimalist backgrounds, person presenting (optional), high-end corporate aesthetic.',
				'audio'  => 'motivational_vsl',
			),
		);
	}

	/**
	 * Map an agent style to a video template.
	 */
	public static function map_style_to_template( $style ) {
		$map = array(
			'ghibli'      => 'anime_stylized',
			'banner'      => 'vsl_promo',
			'infographic' => 'minimal_tech',
			'photo'       => 'cinematic_product',
			'quote_card'  => 'relaxing_wellness',
		);
		return $map[ $style ] ?? 'cinematic_product';
	}

	/**
	 * Find or generate a video clip.
	 *
	 * @param string $prompt   Topic or hook for the video.
	 * @param string $template Optional template slug.
	 * @param string $provider Optional provider override.
	 * @return array{ok:bool,binary:string,error:string}
	 */
	public function create( $prompt, $template = 'cinematic_product', $provider = null ) {
		$chain = $provider ? array( $provider ) : (array) VMSAI_Settings::get( 'video_chain', array( 'minimax', 'luma', 'heygen', 'cogvideox', 'pollinations', 'pexels', 'svd' ) );

		if ( empty($chain) ) {
			return array( 'ok' => false, 'binary' => '', 'error' => 'Video engine disabled or no providers in chain.' );
		}

		$templates = self::templates();
		$tpl = $templates[ $template ] ?? $templates['cinematic_product'];
		$enriched_prompt = "{$tpl['prompt']} Subject: {$prompt}. High-end commercial quality, no text, no logos, 9:16 vertical.";

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 );
		}

		$tried = array();

		foreach ( $chain as $source ) {
			if ( 'off' === $source ) continue;

			VMSAI_Logger::info( 'engine.video', 'Attempting video generation via ' . $source, array( 'prompt' => $enriched_prompt ) );

			$res = array( 'ok' => false, 'binary' => '', 'error' => 'Unsupported provider.' );

			if ( 'aipuffer' === $source )     $res = $this->from_aipuffer( $enriched_prompt );
			elseif ( 'pollinations' === $source ) $res = $this->from_pollinations( $enriched_prompt );
			elseif ( 'pexels' === $source )   $res = $this->from_pexels( $prompt );
			elseif ( 'minimax' === $source )  $res = $this->from_minimax( $prompt );
			elseif ( 'luma' === $source )     $res = $this->from_luma( $prompt );
			elseif ( 'heygen' === $source )   $res = $this->from_heygen( $prompt );
			elseif ( 'cogvideox' === $source ) $res = $this->from_cogvideox( $prompt );
			elseif ( 'svd' === $source )      $res = $this->from_svd( $prompt );

			if ( $res['ok'] && ! empty($res['binary']) ) {
				$res['tried'] = $tried;
				return $res;
			}

			$tried[$source] = $res['error'] ?: 'Unknown error';
			VMSAI_Logger::warn( 'engine.video', "Provider $source failed.", array( 'error' => $res['error'] ) );
		}

		return array( 'ok' => false, 'binary' => '', 'error' => 'All video providers in chain failed.', 'tried' => $tried );
	}

	/**
	 * AI Puffer (AIPKit) — Google Veo.
	 *
	 * AIPKit has no separate video endpoint. Its image route branches into
	 * video generation when the requested model is a Veo model, polls the
	 * long-running operation on Google's side, downloads the result and hands
	 * back a URL. So this posts to images/generate with a video model, which
	 * is why the code below looks like the image provider.
	 *
	 * @param string $prompt Enriched prompt.
	 * @return array{ok:bool,binary:string,error:string}
	 */
	private function from_aipuffer( $prompt ) {
		$site     = VMSAI_Settings::credential( 'aipuffer_site' );
		$is_local = ( ! $site || strpos( home_url(), (string) $site ) !== false );
		$site     = $site ? untrailingslashit( $site ) : untrailingslashit( home_url() );
		$key      = VMSAI_Settings::credential( 'aipuffer_key' );

		if ( $is_local && empty( $key ) ) {
			$key = self::aipuffer_local_key();
		}

		if ( empty( $key ) ) {
			return array( 'ok' => false, 'binary' => '', 'error' => 'AI Puffer key missing and no local AIPKit install found.' );
		}

		$model = VMSAI_Settings::get( 'video_model' )['aipuffer'] ?? '';

		if ( ! $model ) {
			$model = 'veo-3.0-generate-preview';
		}

		$payload = array(
			'prompt' => $prompt,
			'model'  => $model,
			'n'      => 1,
		);

		if ( $is_local ) {
			$request = new WP_REST_Request( 'POST', '/aipkit/v1/images/generate' );
			$request->set_header( 'Authorization', 'Bearer ' . $key );
			$request->set_body_params( $payload );

			try {
				$response = rest_do_request( $request );
			} catch ( \Throwable $e ) {
				VMSAI_Logger::error( 'engine.video.aipuffer', 'Local REST dispatch crashed.', array( 'message' => $e->getMessage() ) );
				return array( 'ok' => false, 'binary' => '', 'error' => "AI Puffer's backend plugin crashed." );
			}

			if ( $response->is_error() ) {
				$data = $response->get_data();
				return array( 'ok' => false, 'binary' => '', 'error' => (string) ( $data['message'] ?? 'Local AI Puffer video call failed.' ) );
			}

			$json = (array) $response->get_data();
		} else {
			// Veo renders take minutes, and AIPKit blocks while it polls, so
			// this needs a far longer ceiling than an image call.
			$response = VMSAI_Http::post(
				$site . '/wp-json/aipkit/v1/images/generate',
				array(
					'headers' => array( 'Authorization' => 'Bearer ' . $key ),
					'json'    => $payload,
					'scope'   => 'engine.video.aipuffer',
					'timeout' => 300,
					'retries' => 0,
				)
			);

			if ( empty( $response['ok'] ) ) {
				return array( 'ok' => false, 'binary' => '', 'error' => (string) $response['error'] );
			}

			$json = (array) $response['json'];
		}

		$url = $json['videos'][0]['url']
			?? $json['data'][0]['url']
			?? $json['images'][0]['url']
			?? $json['url']
			?? '';

		if ( ! $url ) {
			VMSAI_Logger::warn( 'engine.video.aipuffer', 'No video URL in AIPKit response.', array( 'keys' => array_keys( $json ) ) );
			return array( 'ok' => false, 'binary' => '', 'error' => 'AI Puffer returned no video. Check that a Veo model is configured and its Google key has video access.' );
		}

		$binary = VMSAI_Http::fetch_binary( $url, 300 );

		if ( ! $binary ) {
			return array( 'ok' => false, 'binary' => '', 'error' => 'AI Puffer returned a video URL that could not be downloaded.' );
		}

		return array( 'ok' => true, 'binary' => $binary, 'error' => '' );
	}

	/**
	 * CogVideoX (Via Hugging Face).
	 */
	private function from_cogvideox( $prompt ) {
		$token = VMSAI_Settings::credential( 'hf_token' );
		if ( ! $token ) {
			return array( 'ok' => false, 'binary' => '', 'error' => 'Hugging Face token missing.' );
		}

		// This uses the THUDM/CogVideoX-5b model on HF Inference
		$url = 'https://api-inference.huggingface.co/models/THUDM/CogVideoX-5b';
		$res = VMSAI_Http::post( $url, array(
			'headers' => array( 'Authorization' => 'Bearer ' . $token ),
			'json'    => array( 'inputs' => $prompt ),
			'scope'   => 'engine.video.cogvideox',
			'timeout' => 120
		) );

		if ( ! $res['ok'] || empty($res['body']) ) {
			return array( 'ok' => false, 'binary' => '', 'error' => $res['error'] ?: 'HF Inference failed.' );
		}

		return array( 'ok' => true, 'binary' => $res['body'], 'error' => '' );
	}

	/**
	 * Stable Video Diffusion (Local or S3).
	 */
	private function from_svd( $prompt ) {
		$url = VMSAI_Settings::credential( 'svd_url' );
		if ( ! $url ) {
			return array( 'ok' => false, 'binary' => '', 'error' => 'SVD Server URL missing. Add it under Settings > AI Engines.' );
		}

		$res = VMSAI_Http::post( $url, array(
			'json'    => array( 'prompt' => $prompt, 'width' => 1024, 'height' => 576 ), // SVD standard
			'scope'   => 'engine.video.svd',
			'timeout' => 120
		) );

		if ( ! $res['ok'] || empty($res['body']) ) {
			return array( 'ok' => false, 'binary' => '', 'error' => $res['error'] ?: 'SVD server connection failed.' );
		}

		return array( 'ok' => true, 'binary' => $res['body'], 'error' => '' );
	}

	/**
	 * Search Pexels for a matching stock video.
	 */
	private function from_pexels( $prompt ) {
		$key = VMSAI_Settings::credential( 'pexels_key' );
		if ( ! $key ) {
			return array( 'ok' => false, 'binary' => '', 'error' => 'Pexels API key missing.' );
		}

		$url = 'https://api.pexels.com/videos/search?query=' . urlencode( $prompt ) . '&per_page=1&orientation=portrait';
		$res = VMSAI_Http::get( $url, array( 'headers' => array( 'Authorization' => $key ), 'scope' => 'engine.video.pexels' ) );

		if ( ! $res['ok'] || empty( $res['json']['videos'] ) ) {
			return array( 'ok' => false, 'binary' => '', 'error' => $res['error'] ?: 'No videos found.' );
		}

		$video_url = $res['json']['videos'][0]['video_files'][0]['link'] ?? '';
		if ( ! $video_url ) {
			return array( 'ok' => false, 'binary' => '', 'error' => 'No video link in Pexels response.' );
		}

		$download = VMSAI_Http::get( $video_url, array( 'scope' => 'engine.video.download', 'timeout' => 60 ) );

		return array(
			'ok'     => $download['ok'],
			'binary' => $download['body'] ?? '',
			'error'  => $download['error'],
		);
	}

	/**
	 * Pollinations AI (Experimental video generation).
	 */
	private function from_pollinations( $prompt ) {
		// Pollinations has a simple GET endpoint for experimental video.
		$url      = 'https://pollinations.ai/p/' . rawurlencode( $prompt ) . '?width=1080&height=1920&model=video';
		$download = VMSAI_Http::get( $url, array( 'scope' => 'engine.video.pollinations', 'timeout' => 90 ) );

		if ( ! $download['ok'] || empty($download['body']) ) {
			return array( 'ok' => false, 'binary' => '', 'error' => $download['error'] );
		}

		$binary = $download['body'];

		// Pro Optimization: If ffmpeg is available, we could stitch or add audio here.
		return array(
			'ok'     => true,
			'binary' => $binary,
			'error'  => '',
		);
	}

	/**
	 * Resolve a usable ffmpeg binary: the saved override from Channels →
	 * ffmpeg path (this was collected in the UI and saved but never actually
	 * read anywhere — that's the whole reason produce() was fatally erroring
	 * on every call), then common install locations, then whatever the
	 * shell's PATH resolves. Returns '' if nothing usable is found so
	 * produce() can fail gracefully instead of calling exec() with a bad
	 * path.
	 *
	 * @return string
	 */
	private static function ffmpeg_binary() {
		$configured = trim( (string) VMSAI_Settings::credential( 'ffmpeg_path' ) );

		if ( $configured && self::is_usable_binary( $configured ) ) {
			return $configured;
		}

		$candidates = array(
			'/usr/bin/ffmpeg',
			'/usr/local/bin/ffmpeg',
			'/opt/homebrew/bin/ffmpeg',
			'C:\\ffmpeg\\bin\\ffmpeg.exe',
		);

		foreach ( $candidates as $candidate ) {
			if ( self::is_usable_binary( $candidate ) ) {
				return $candidate;
			}
		}

		// Last resort: ask the shell to resolve it from PATH. Many hosts
		// disable exec() entirely, so this has to fail quietly, not fatally.
		if ( function_exists( 'exec' ) ) {
			$lookup = ( 0 === stripos( PHP_OS, 'WIN' ) ) ? 'where ffmpeg' : 'command -v ffmpeg';
			@exec( $lookup, $out, $status ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions

			if ( 0 === $status && ! empty( $out[0] ) ) {
				return trim( $out[0] );
			}
		}

		return '';
	}

	/**
	 * Whether a path looks like it points at a real, invokable ffmpeg.
	 *
	 * @param string $path Candidate path.
	 * @return bool
	 */
	private static function is_usable_binary( $path ) {
		if ( '' === $path ) {
			return false;
		}

		// is_executable() is unreliable on Windows; a plain existence check
		// is the practical option there.
		if ( 0 === stripos( PHP_OS, 'WIN' ) ) {
			return file_exists( $path );
		}

		return is_executable( $path );
	}

	/**
	 * Generate speech from text via ElevenLabs.
	 */
	public function text_to_speech( $text ) {
		$key = VMSAI_Settings::credential( 'elevenlabs_key' );
		if ( ! $key ) return false;

		$voice_id = VMSAI_Settings::get( 'elevenlabs_voice', 'pNInz6ov9TqWwaY67P6D' ); // Default 'Adam'
		$url = "https://api.elevenlabs.io/v1/text-to-speech/{$voice_id}";

		$res = VMSAI_Http::post( $url, array(
			'headers' => array( 'xi-api-key' => $key, 'Content-Type' => 'application/json' ),
			'json'    => array( 'text' => $text, 'model_id' => 'eleven_monolingual_v1' ),
			'scope'   => 'engine.video.tts'
		) );

		if ( $res['ok'] && ! empty($res['body']) ) {
			$uploads = wp_upload_dir();
			$file = trailingslashit( $uploads['basedir'] ) . 'vmsai-tts-' . uniqid() . '.mp3';
			file_put_contents( $file, $res['body'] );
			return $file;
		}

		return false;
	}

	/**
	 * High-End Production: Finalize a raw video or image into a branded social asset.
	 *
	 * @param array  $post     Queue row.
	 * @param string $provider Optional provider override.
	 * @return string Absolute path to the produced MP4.
	 */
	public function produce( array $post, $provider = null ) {
		$ffmpeg = self::ffmpeg_binary();
		if ( ! $ffmpeg ) {
			VMSAI_Logger::error( 'engine.video', 'ffmpeg not found. Production aborted.' );
			return '';
		}

		$uploads = wp_upload_dir();
		$output  = trailingslashit( $uploads['basedir'] ) . 'vmsai-prod-' . uniqid() . '.mp4';

		// 1. Determine Source (Video Binary vs Image Path)
		$source_video = '';
		$source_image = '';

		// Check if we have a generated video binary first
		$video_topic = ! empty($post['video_hook']) ? $post['video_hook'] : ($post['title'] ?: $post['body']);
		$gen_res = $this->create( $video_topic, $post['video_template'] ?? 'cinematic_product', $provider );

		if ( $gen_res['ok'] && $gen_res['binary'] ) {
			$source_video = trailingslashit( $uploads['basedir'] ) . 'vmsai-raw-' . uniqid() . '.mp4';
			file_put_contents( $source_video, $gen_res['binary'] );
		} else {
			// Fallback to Image Source (Ken Burns)
			$image_id = (int) $post['media_id'];
			$source_image = $image_id ? get_attached_file( $image_id ) : '';
		}

		if ( ! $source_video && ! $source_image ) {
			return '';
		}

		// 2. Branding Assets
		$mark_id = (int) VMSAI_Settings::get( 'watermark_id' );
		$logo    = ( VMSAI_Settings::get( 'brand_watermark' ) && $mark_id ) ? get_attached_file( $mark_id ) : '';
		$hook    = (string) ( $post['video_hook'] ?? '' );

		// 3. Construct FFmpeg Filters
		$filters = array();
		$inputs  = array();
		$in_idx  = 0;

		if ( $source_video ) {
			$inputs[] = "-i " . escapeshellarg( $source_video );
			$filters[] = "[0:v]scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920[v0]";
		} else {
			$inputs[] = "-loop 1 -i " . escapeshellarg( $source_image );
			$filters[] = "[0:v]scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,zoompan=z='min(zoom+0.0006,1.12)':d=240:s=1080x1920:fps=30[v0]";
		}
		$in_idx++;

		$last_v = "v0";

		// Overlay Logo
		if ( $logo && file_exists( $logo ) ) {
			$inputs[] = "-i " . escapeshellarg( $logo );
			$filters[] = "[{$in_idx}:v]scale=180:-1[logo]";
			$filters[] = "[{$last_v}][logo]overlay=W-w-40:40[v_logo]";
			$last_v = "v_logo";
			$in_idx++;
		}

		// PRO: Burned-in Dynamic Subtitles
		$body = wp_strip_all_tags( $post['body'] );
		$body = preg_replace( '/#[a-zA-Z0-9_]+/', '', $body ); // Strip hashtags
		$words = array_filter( explode( ' ', trim($body) ) );
		$chunk_size = 3; // Words per subtitle block
		$chunks = array_chunk( $words, $chunk_size );
		$duration = 8; // Total video duration
		$time_per_chunk = $duration / max( 1, count($chunks) );

		foreach ( $chunks as $i => $chunk ) {
			$text = strtoupper( implode( ' ', $chunk ) );
			$safe_text = str_replace( array( ':', "'" ), array( '\\:', "\\'" ), $text );
			$start = $i * $time_per_chunk;
			$end = ( $i + 1 ) * $time_per_chunk;

			// Subtitle Filter: Bottom-centered, bright yellow/white, bold.
			$sub_filter = "drawtext=text='{$safe_text}':fontcolor=yellow:fontsize=64:font='Sans':x=(w-text_w)/2:y=h-400:enable='between(t,{$start},{$end})':borderw=3:bordercolor=black";
			$filters[] = "[{$last_v}]{$sub_filter}[v_sub{$i}]";
			$last_v = "v_sub{$i}";
		}

		// Overlay Text Hook (Centered Bold with smart wrapping for FFmpeg)
		if ( $hook ) {
			// Pro Fix: Manual line wrapping to ensure readability on all FFmpeg versions
			$wrapped_hook = wordwrap( strtoupper( $hook ), 20, "\n" );
			$safe_hook = str_replace( array( ':', "'" ), array( '\\:', "\\'" ), $wrapped_hook );

			// Pro Text Filter: Centered, white, high-contrast semi-transparent box.
			$text_filter = "drawtext=text='{$safe_hook}':fontcolor=white:fontsize=72:font='Sans':x=(w-text_w)/2:y=(h-text_h)/2-120:box=1:boxcolor=black@0.6:boxborderw=40:shadowcolor=black@0.4:shadowx=4:shadowy=4:line_spacing=15";
			$filters[] = "[{$last_v}]{$text_filter}[v_text]";
			$last_v = "v_text";
		}

		// Add Audio (Priority: TTS > Background Vibe > Silence)
		$tts_file = $hook ? $this->text_to_speech( $hook ) : false;
		$audio_input = "";

		if ( $tts_file && file_exists( $tts_file ) ) {
			$inputs[] = "-i " . escapeshellarg( $tts_file );
			$audio_input = "-map {$in_idx}:a";
			$in_idx++;
		} else {
			$audio_input = "-f lavfi -i anullsrc=channel_layout=stereo:sample_rate=44100 -map {$in_idx}:a";
			$in_idx++;
		}

		$command = sprintf(
			'%s -y %s -filter_complex %s -map "[%s]" %s -c:v libx264 -profile:v high -pix_fmt yuv420p -c:a aac -shortest -t 8 %s 2>&1',
			escapeshellcmd( $ffmpeg ),
			implode( ' ', $inputs ),
			escapeshellarg( implode( ';', $filters ) ),
			$last_v,
			$audio_input,
			escapeshellarg( $output )
		);

		@exec( $command, $out, $status );

		if ( $source_video && file_exists($source_video) ) @unlink($source_video);
		if ( $tts_file && file_exists($tts_file) ) @unlink($tts_file);

		if ( 0 !== $status || ! file_exists( $output ) ) {
			VMSAI_Logger::error( 'engine.video', 'Production failed.', array( 'status' => $status, 'cmd' => $command ) );
			return '';
		}

		return $output;
	}

	/**
	 * Minimax (Hailuo AI) Video Generation.
	 */
	private function from_minimax( $prompt ) {
		$key = VMSAI_Settings::credential( 'minimax_key' );
		if ( ! $key ) {
			return array( 'ok' => false, 'binary' => '', 'error' => 'Minimax API key missing.' );
		}

		$base = 'https://api.minimax.chat/v1';
		$res  = VMSAI_Http::post( $base . '/video_generation', array(
			'headers' => array( 'Authorization' => 'Bearer ' . $key ),
			'json'    => array(
				'prompt' => $prompt,
				'model'  => 'video-01',
			),
			'scope'   => 'engine.video.minimax'
		) );

		if ( ! $res['ok'] || empty( $res['json']['task_id'] ) ) {
			return array( 'ok' => false, 'binary' => '', 'error' => $res['error'] ?: 'Failed to create Minimax task.' );
		}

		$task_id = $res['json']['task_id'];

		// Polling loop (max 2 minutes)
		for ( $i = 0; $i < 24; $i++ ) {
			sleep( 5 );
			$status = VMSAI_Http::get( $base . '/query_video_generation?task_id=' . $task_id, array(
				'headers' => array( 'Authorization' => 'Bearer ' . $key ),
				'scope'   => 'engine.video.minimax'
			) );

			if ( ! $status['ok'] ) continue;

			$state = $status['json']['status'] ?? '';
			if ( 'Success' === $state && ! empty( $status['json']['file_url'] ) ) {
				$download = VMSAI_Http::get( $status['json']['file_url'], array( 'timeout' => 60 ) );
				return array( 'ok' => $download['ok'], 'binary' => $download['body'] ?? '', 'error' => $download['error'] );
			}

			if ( 'Fail' === $state ) {
				return array( 'ok' => false, 'binary' => '', 'error' => 'Minimax generation failed.' );
			}
		}

		return array( 'ok' => false, 'binary' => '', 'error' => 'Minimax generation timed out.' );
	}

	/**
	 * Luma Dream Machine Video Generation.
	 */
	private function from_luma( $prompt ) {
		$key = VMSAI_Settings::credential( 'luma_key' );
		if ( ! $key ) {
			return array( 'ok' => false, 'binary' => '', 'error' => 'Luma API key missing.' );
		}

		$base = 'https://api.lumalabs.ai/v1';
		$res  = VMSAI_Http::post( $base . '/generations', array(
			'headers' => array( 'Authorization' => 'Bearer ' . $key ),
			'json'    => array(
				'prompt' => $prompt,
				'aspect_ratio' => '9:16',
			),
			'scope'   => 'engine.video.luma'
		) );

		if ( ! $res['ok'] || empty( $res['json']['id'] ) ) {
			return array( 'ok' => false, 'binary' => '', 'error' => $res['error'] ?: 'Failed to create Luma generation.' );
		}

		$gen_id = $res['json']['id'];

		// Polling loop (max 2 minutes)
		for ( $i = 0; $i < 24; $i++ ) {
			sleep( 5 );
			$status = VMSAI_Http::get( $base . '/generations/' . $gen_id, array(
				'headers' => array( 'Authorization' => 'Bearer ' . $key ),
				'scope'   => 'engine.video.luma'
			) );

			if ( ! $status['ok'] ) continue;

			$state = $status['json']['state'] ?? '';
			if ( 'completed' === $state && ! empty( $status['json']['assets']['video'] ) ) {
				$download = VMSAI_Http::get( $status['json']['assets']['video'], array( 'timeout' => 60 ) );
				return array( 'ok' => $download['ok'], 'binary' => $download['body'] ?? '', 'error' => $download['error'] );
			}

			if ( 'failed' === $state ) {
				return array( 'ok' => false, 'binary' => '', 'error' => 'Luma generation failed.' );
			}
		}

		return array( 'ok' => false, 'binary' => '', 'error' => 'Luma generation timed out.' );
	}

	/**
	 * HeyGen AI Avatar (Digital Twin) Video Generation.
	 */
	private function from_heygen( $prompt ) {
		$key = VMSAI_Settings::credential( 'heygen_key' );
		if ( ! $key ) return array( 'ok' => false, 'binary' => '', 'error' => 'HeyGen API key missing.' );

		$avatar_id = VMSAI_Settings::get( 'heygen_avatar_id', 'josh_lite_20230714' );
		$voice_id  = VMSAI_Settings::get( 'heygen_voice_id', '1bd001e7e50f421d891976aad8a4055e' );

		$res = VMSAI_Http::post( 'https://api.heygen.com/v2/video/generate', array(
			'headers' => array( 'X-Api-Key' => $key, 'Content-Type' => 'application/json' ),
			'json'    => array(
				'video_gen' => array(
					'caption'   => false,
					'dimension' => array( 'width' => 1080, 'height' => 1920 ),
					'elements'  => array(
						array(
							'type'      => 'avatar',
							'avatar_id' => $avatar_id,
							'voice'     => array( 'type' => 'text', 'input_text' => $prompt, 'voice_id' => $voice_id )
						)
					)
				)
			),
			'scope' => 'engine.video.heygen'
		) );

		if ( ! $res['ok'] || empty($res['json']['data']['video_id']) ) {
			return array( 'ok' => false, 'binary' => '', 'error' => $res['error'] ?: 'Failed to create HeyGen task.' );
		}

		$video_id = $res['json']['data']['video_id'];

		// Polling loop
		for ( $i = 0; $i < 30; $i++ ) {
			sleep( 10 );
			$status = VMSAI_Http::get( "https://api.heygen.com/v2/video/{$video_id}/status", array(
				'headers' => array( 'X-Api-Key' => $key )
			) );

			if ( ! $status['ok'] ) continue;

			if ( 'completed' === $status['json']['data']['status'] ) {
				$download = VMSAI_Http::get( $status['json']['data']['video_url'], array( 'timeout' => 60 ) );
				return array( 'ok' => $download['ok'], 'binary' => $download['body'] ?? '', 'error' => $download['error'] );
			}

			if ( 'failed' === $status['json']['data']['status'] ) return array( 'ok' => false, 'binary' => '', 'error' => 'HeyGen failed.' );
		}

		return array( 'ok' => false, 'binary' => '', 'error' => 'HeyGen timeout.' );
	}

	/**
	 * The public API key of an AIPKit install on this same site, if any.
	 *
	 * @return string
	 */
	private static function aipuffer_local_key() {
		$opts = get_option( 'aipkit_options' );
		if ( is_array( $opts ) && ! empty( $opts['api_keys']['public_api_key'] ) ) {
			return (string) $opts['api_keys']['public_api_key'];
		}

		$legacy = get_option( 'wpaicg_options' );
		if ( is_array( $legacy ) && ! empty( $legacy['rest_api_key'] ) ) {
			return (string) $legacy['rest_api_key'];
		}

		return '';
	}
}
