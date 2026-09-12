<?php
/**
 * Admin controller.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the admin screens and handles form posts.
 */
class VMSAI_Admin {

	const SLUG = 'vm-social-ai-pro';

	/**
	 * The screens, in order.
	 *
	 * @return array<string,string>
	 */
	public static function tabs() {
		$tabs = array(
			'dashboard' => __( 'Dashboard', 'vm-social-ai-pro' ),
		);

		// Upgrade/licensing is not day-to-day work — it lives as a section
		// inside Settings rather than taking a slot in the primary nav.
		$tabs = array_merge( $tabs, array(
			'compose'   => __( 'Compose', 'vm-social-ai-pro' ),
			'strategy'  => __( 'Strategy', 'vm-social-ai-pro' ),
			'agents'    => __( 'Agents', 'vm-social-ai-pro' ),
			'labs'      => __( 'Labs', 'vm-social-ai-pro' ),
			'inbox'     => __( 'Inbox', 'vm-social-ai-pro' ),
			'brain'     => __( 'Brain', 'vm-social-ai-pro' ),
			'plan'      => __( 'Plan', 'vm-social-ai-pro' ),
			'queue'     => __( 'Queue', 'vm-social-ai-pro' ),
			'pipeline'  => __( 'Pipeline', 'vm-social-ai-pro' ),
			'settings'  => __( 'Settings', 'vm-social-ai-pro' ),
		) );

		return $tabs;
	}

	/**
	 * Hook into the admin.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_vmsai_save', array( $this, 'handle_save' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect' ) );

		// Only schedule events if we are on a VM Social AI page or doing a save.
		if ( isset( $_GET['page'] ) && strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), self::SLUG ) !== false ) {
			add_action( 'admin_init', array( 'VMSAI_Install', 'schedule_events' ) );
		}

		// Clean up the footer on our pages.
		add_filter( 'admin_footer_text', array( $this, 'footer_left' ) );
		add_filter( 'update_footer', array( $this, 'footer_right' ), 11 );
	}

	/**
	 * Remove "Thank you for creating with WordPress".
	 */
	public function footer_left( $text ) {
		return ( isset( $_GET['page'] ) && self::SLUG === sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) ? '' : $text; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Remove the WordPress version number from the footer.
	 */
	public function footer_right( $text ) {
		return ( isset( $_GET['page'] ) && self::SLUG === sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) ? '' : $text; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Send the user to the dashboard right after activation.
	 *
	 * @return void
	 */
	public function maybe_redirect() {
		// Screens that used to be their own top-level tab and are now sections
		// inside another one. Forward old links and bookmarks rather than
		// letting them fall through to the dashboard. Runs on admin_init so a
		// real redirect is still possible, before any page output.
		$moved = array(
			'plans'     => 'settings#vmsai-section-plans',
			'image-lab' => 'labs#vmsai-section-image-lab',
			'video-lab' => 'labs#vmsai-section-video-lab',
		);

		if ( isset( $_GET['page'], $_GET['tab'] ) && self::SLUG === sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$requested = sanitize_key( wp_unslash( $_GET['tab'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

			if ( isset( $moved[ $requested ] ) ) {
				list( $tab, $hash ) = explode( '#', $moved[ $requested ] );
				wp_safe_redirect( self::url( $tab ) . '#' . $hash );
				exit;
			}
		}

		if ( get_transient( 'vmsai_activation_redirect' ) ) {
			delete_transient( 'vmsai_activation_redirect' );
			if ( ! isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
				exit;
			}
		}
	}

	/**
	 * Register the menu.
	 *
	 * @return void
	 */
	public function menu() {
		$is_wl = (bool) VMSAI_Settings::get( 'white_label' );
		$name  = $is_wl ? VMSAI_Settings::get( 'agency_name', 'Agency' ) : 'VM Social AI';

		add_menu_page(
			$name,
			$name,
			'vmsai_read',
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-megaphone',
			26
		);
	}

	/**
	 * Load styles and scripts only on our screen.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function assets( $hook ) {
		if ( 'toplevel_page_' . self::SLUG !== $hook ) {
			return;
		}

		wp_enqueue_media();

		// Emoji in the page can be rewritten into <img> tags pointing at a
		// remote host, which renders as a broken image on an install without
		// outbound access. WordPress 6.x hooked that into the admin; 7.0 no
		// longer does, but this plugin supports 6.2+, and other plugins do the
		// same substitution. Emoji are decorative here — every control carries
		// a real text label — so drop the rewrite on our own screen.
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );

		wp_enqueue_style(
			'vmsai-admin',
			VMSAI_URL . 'admin/assets/css/admin.css',
			array(),
			VMSAI_VERSION
		);

		wp_enqueue_script(
			'vmsai-admin',
			VMSAI_URL . 'admin/assets/js/admin.js',
			array( 'jquery' ),
			VMSAI_VERSION,
			false
		);

		wp_localize_script(
			'vmsai-admin',
			'VMSAI',
			array(
				'root'  => esc_url_raw( rest_url( VMSAI_Rest::NS ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'page'  => admin_url( 'admin.php?page=' . self::SLUG ),
				'i18n'  => array(
					'working'   => __( 'Working…', 'vm-social-ai-pro' ),
					'failed'    => __( 'That did not work.', 'vm-social-ai-pro' ),
					'saved'     => __( 'Saved.', 'vm-social-ai-pro' ),
					'confirm'   => __( 'Delete this post? This cannot be undone.', 'vm-social-ai-pro' ),
					'published' => __( 'Published.', 'vm-social-ai-pro' ),
				),
			)
		);
	}

	/**
	 * Render the active tab.
	 *
	 * @return void
	 */
	public function render() {
		$tabs   = self::tabs();
		$active = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$active = isset( $tabs[ $active ] ) ? $active : 'dashboard';

		$view = VMSAI_PATH . 'admin/views/' . $active . '.php';

		$theme = VMSAI_Settings::get( 'admin_theme', 'dark' );
		$class = 'wrap vmsai vmsai--' . $theme;

		echo '<div class="' . esc_attr( $class ) . '">';
		include VMSAI_PATH . 'admin/views/header.php';

		if ( is_readable( $view ) ) {
			include $view;
		}

		echo '</div>';
	}

	/**
	 * Handle every settings form post.
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! current_user_can( 'vmsai_edit' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'vm-social-ai-pro' ) );
		}

		check_admin_referer( 'vmsai_save' );

		$section = isset( $_POST['section'] ) ? sanitize_key( wp_unslash( $_POST['section'] ) ) : '';

		// Credentials, chains and channel wiring are key material — require
		// the key-management cap, not just the editor cap.
		if ( in_array( $section, array( 'engines', 'channels', 'updates' ), true ) && ! current_user_can( 'vmsai_manage_keys' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage API keys and channels.', 'vm-social-ai-pro' ) );
		}

		switch ( $section ) {
			case 'engines':
				$this->save_engines();
				break;
			case 'channels':
				$this->save_channels();
				break;
			case 'settings':
				$this->save_settings();
				break;
			case 'plans':
				$this->save_plans();
				break;
			case 'brain':
				$this->save_brain();
				break;
			case 'rag':
				$this->save_rag();
				break;
			case 'updates':
				$this->save_updates();
				break;
		}

		// Determine redirect tab and hash.
		$redirect_tab  = 'settings';
		$redirect_hash = '';

		if ( 'settings' === $section ) {
			$redirect_tab = 'settings';
		} elseif ( in_array( $section, array( 'engines', 'channels', 'logs', 'plans', 'updates' ), true ) ) {
			$redirect_tab  = 'settings';
			$redirect_hash = '#vmsai-section-' . $section;
		} elseif ( isset( self::tabs()[ $section ] ) ) {
			$redirect_tab = $section;
		} else {
			$redirect_tab = 'dashboard';
		}

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::SLUG, 'tab' => $redirect_tab, 'saved' => 1 ),
				admin_url( 'admin.php' )
			) . $redirect_hash
		);
		exit;
	}

	/**
	 * Persist engine chains, models and credentials.
	 *
	 * @return void
	 */
	private function save_engines() {
		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification

		$text_chain  = array_values( array_filter( array_map( 'sanitize_key', (array) ( $post['text_chain'] ?? array() ) ) ) );
		$image_chain = array_values( array_filter( array_map( 'sanitize_key', (array) ( $post['image_chain'] ?? array() ) ) ) );
		$video_chain = array_values( array_filter( array_map( 'sanitize_key', (array) ( $post['video_chain'] ?? array() ) ) ) );

		VMSAI_Settings::update(
			array(
				'text_chain'      => $text_chain,
				'image_chain'     => $image_chain,
				'video_chain'     => $video_chain,
				'text_model'      => array_map( 'sanitize_text_field', (array) ( $post['text_model'] ?? array() ) ),
				'image_model'     => array_map( 'sanitize_text_field', (array) ( $post['image_model'] ?? array() ) ),
				'video_model'     => array_map( 'sanitize_text_field', (array) ( $post['video_model'] ?? array() ) ),
				'video_default_template' => sanitize_key( $post['video_default_template'] ?? 'cinematic_product' ),
				'video_audio_default'    => sanitize_key( $post['video_audio_default'] ?? 'none' ),
				'request_timeout' => max( 15, min( 600, (int) ( $post['request_timeout'] ?? 90 ) ) ),
				'max_attempts'    => max( 1, min( 10, (int) ( $post['max_attempts'] ?? 3 ) ) ),
			)
		);

		$credentials = array();

		foreach ( array( 'openai_key', 'openai_image_url', 'omniroute_url', 'omniroute_key', 'aipuffer_site', 'aipuffer_key', 'aipuffer_bot_id', 'anthropic_key', 'gemini_key', 'openrouter_key', 'nvidia_key', 'nvidia_url', 'ollama_url', 'ollama_token', 'hf_token', 'cf_token', 'pollinations_token', 'comfyui_url', 'comfyui_token', 'comfyui_workflow', 'pexels_key', 'minimax_key', 'luma_key', 'heygen_key', 'heygen_avatar_id', 'heygen_voice_id', 'svd_url', 'elevenlabs_key', 'tavily_key' ) as $field ) {
			if ( isset( $post[ $field ] ) ) {
				$credentials[ $field ] = 'comfyui_workflow' === $field
					? trim( (string) $post[ $field ] )
					: trim( (string) $post[ $field ] );
			}
		}

		VMSAI_Settings::update_credentials( $credentials );

		// Saving this screen is the operator saying "I have fixed the setup,
		// try again" — so every breaker it could possibly relate to has to be
		// cleared. Image and video breakers were never reset by anything at
		// all, so a provider that tripped stayed tripped and there was no way
		// to clear it from the UI; and the old hardcoded text list silently
		// missed any provider added later. Derive the lists instead.
		$groups = array(
			'text'  => vmsai()->text_engine()->providers(),
			'image' => vmsai()->image_engine()->providers(),
		);

		if ( method_exists( vmsai(), 'video_engine' ) && method_exists( vmsai()->video_engine(), 'providers' ) ) {
			$groups['video'] = vmsai()->video_engine()->providers();
		}

		foreach ( $groups as $group => $providers ) {
			foreach ( array_keys( (array) $providers ) as $slug ) {
				VMSAI_Circuit::reset( $group . ':' . $slug );
			}
		}
	}

	/**
	 * Persist channel toggles and credentials.
	 *
	 * @return void
	 */
	private function save_channels() {
		$post    = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification
		$manager = vmsai()->channels();
		$limits  = VMSAI_License::limits();

		$enabled  = array();
		$approval = array();
		$creds    = array();
		$count    = 0;

		foreach ( $manager->all() as $slug => $channel ) {
			$is_enabled = ! empty( $post['enabled'][ $slug ] );

			if ( $is_enabled ) {
				if ( $count < $limits['channels'] ) {
					$enabled[ $slug ] = 1;
					$count++;
				} else {
					// Plan limit reached, cannot enable more.
					$enabled[ $slug ] = 0;
					/* translators: 1: channel label, 2: plan label, 3: channel limit */
					add_settings_error( 'vmsai_settings', 'limit_reached', sprintf( __( 'Channel "%1$s" could not be enabled. Your %2$s plan is limited to %3$d channel(s).', 'vm-social-ai-pro' ), $channel->label(), $limits['label'], $limits['channels'] ) );
				}
			} else {
				$enabled[ $slug ] = 0;
			}

			$approval[ $slug ] = ! empty( $post['approval'][ $slug ] ) ? 1 : 0;

			foreach ( array_keys( $channel->credential_fields() ) as $field ) {
				if ( isset( $post[ $field ] ) ) {
					$creds[ $field ] = trim( (string) $post[ $field ] );
				}
			}
		}

		if ( isset( $post['ffmpeg_path'] ) ) {
			$creds['ffmpeg_path'] = sanitize_text_field( (string) $post['ffmpeg_path'] );
		}

		VMSAI_Settings::update( array( 'enabled_channels' => $enabled, 'require_approval' => $approval ) );
		VMSAI_Settings::update_credentials( $creds );

		foreach ( array_keys( $manager->all() ) as $slug ) {
			VMSAI_Circuit::reset( 'channel:' . $slug );
		}
	}

	/**
	 * Persist general settings.
	 *
	 * @return void
	 */
	private function save_settings() {
		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification

		VMSAI_Settings::update(
			array(
				'autonomy'          => in_array( $post['autonomy'] ?? '', array( 'full', 'assisted', 'manual' ), true ) ? $post['autonomy'] : 'assisted',
				'daily_post_cap'    => max( 1, min( 200, (int) ( $post['daily_post_cap'] ?? 24 ) ) ),
				'per_channel_cap'   => max( 1, min( 12, (int) ( $post['per_channel_cap'] ?? 2 ) ) ),
				'quiet_hours'       => array(
					sanitize_text_field( (string) ( $post['quiet_start'] ?? '01:00' ) ),
					sanitize_text_field( (string) ( $post['quiet_end'] ?? '06:00' ) ),
				),
				'language'          => sanitize_text_field( (string) ( $post['language'] ?? 'en' ) ),
				'locale_flavour'    => sanitize_text_field( (string) ( $post['locale_flavour'] ?? '' ) ),
				'emoji_density'     => in_array( $post['emoji_density'] ?? '', array( 'none', 'light', 'heavy' ), true ) ? $post['emoji_density'] : 'light',
				'link_in_bio'       => esc_url_raw( (string) ( $post['link_in_bio'] ?? '' ) ),
				'utm_source'        => sanitize_key( (string) ( $post['utm_source'] ?? 'vm-social-ai-pro' ) ),
				'brand_watermark'   => ! empty( $post['brand_watermark'] ) ? 1 : 0,
				'watermark_id'      => (int) ( $post['watermark_id'] ?? 0 ),
				'white_label'       => ! empty( $post['white_label'] ) ? 1 : 0,
				'agency_name'       => sanitize_text_field( (string) ( $post['agency_name'] ?? 'Agency' ) ),
				'circuit_threshold' => max( 2, min( 20, (int) ( $post['circuit_threshold'] ?? 5 ) ) ),
				'circuit_cooldown'  => max( 300, min( 86400, (int) ( $post['circuit_cooldown'] ?? 1800 ) ) ),
				'retention_days'    => max( 7, min( 730, (int) ( $post['retention_days'] ?? 120 ) ) ),
				'log_level'         => in_array( $post['log_level'] ?? '', array( 'debug', 'info', 'warn', 'error' ), true ) ? $post['log_level'] : 'info',
				'admin_theme'       => in_array( $post['admin_theme'] ?? '', array( 'dark', 'light' ), true ) ? $post['admin_theme'] : 'dark',
				'remote_storage'    => in_array( $post['remote_storage'] ?? '', array( 'off', 'r2' ), true ) ? $post['remote_storage'] : 'off',
				'video_source'      => in_array( $post['video_source'] ?? '', array( 'off', 'aipuffer', 'pexels', 'pollinations', 'minimax', 'luma', 'heygen', 'cogvideox', 'svd' ), true ) ? $post['video_source'] : 'off',
				'alert_on_failure'  => ! empty( $post['alert_on_failure'] ) ? 1 : 0,
				'alert_email'       => sanitize_email( (string) ( $post['alert_email'] ?? '' ) ),
			)
		);

		// Credentials ride along on the settings form — only users with the
		// key-management cap may write them. Otherwise section=settings would
		// re-open the privilege-escalation hole that engines/channels/updates
		// are already gated against (outbound_proxy alone redirects every
		// API call, with all bearer tokens, through an attacker's server).
		if ( current_user_can( 'vmsai_manage_keys' ) ) {
			$credentials = array();
			foreach ( array( 'r2_account_id', 'r2_bucket', 'r2_key', 'r2_secret', 'r2_public_url', 'outbound_proxy', 'github_token' ) as $field ) {
				if ( isset( $post[ $field ] ) ) {
					$credentials[ $field ] = trim( (string) $post[ $field ] );
				}
			}
			VMSAI_Settings::update_credentials( $credentials );
		}
	}

	/**
	 * Persist and verify license key from the plans tab.
	 */
	private function save_plans() {
		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( isset( $post['license_key'] ) ) {
			$key = sanitize_text_field( $post['license_key'] );
			$res = VMSAI_License::verify( $key );

			if ( true !== $res && '' !== $key ) {
				set_transient( 'vmsai_attempted_key', $key, 30 );
				add_settings_error( 'vmsai_settings', 'license_invalid', $res );
			} elseif ( true === $res && '' !== $key ) {
				delete_transient( 'vmsai_attempted_key' );
				add_settings_error( 'vmsai_settings', 'license_ok', __( 'License activated successfully.', 'vm-social-ai-pro' ), 'updated' );
			}
		}
	}

	/**
	 * Persist Brain fields posted from the form.
	 *
	 * @return void
	 */
	private function save_brain() {
		$post   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification
		$values = array();

		foreach ( array_keys( VMSAI_Brain::schema() ) as $field ) {
			if ( isset( $post['brain'][ $field ] ) ) {
				$values[ $field ] = sanitize_textarea_field( (string) $post['brain'][ $field ] );
			}
		}

		VMSAI_Brain::set( $values );
	}

	/**
	 * Persist RAG feeds.
	 *
	 * @return void
	 */
	private function save_rag() {
		$post  = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification
		$feeds = array_filter( array_map( 'esc_url_raw', array_map( 'trim', explode( "\n", (string) ( $post['rag_feeds'] ?? '' ) ) ) ) );
		update_option( VMSAI_RAG::OPTION, array_values( $feeds ) );

		( new VMSAI_RAG() )->sync();
	}

	/**
	 * Persist GitHub access settings.
	 *
	 * @return void
	 */
	private function save_updates() {
		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( isset( $post['github_token'] ) ) {
			$token = trim( (string) $post['github_token'] );
			if ( ! preg_match( '/^•+$/', $token ) ) {
				VMSAI_Settings::update_credentials( array( 'github_token' => $token ) );
			}
		}

		// Invalidate cached update info so next load checks with new token if supplied
		delete_transient( VMSAI_Github_Updater::TRANSIENT_KEY );
	}

	/**
	 * Tab URL helper.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	public static function url( $tab = 'dashboard' ) {
		return admin_url( 'admin.php?page=' . self::SLUG . '&tab=' . rawurlencode( $tab ) );
	}

	/**
	 * Where the "upgrade" call to action points. Single source of truth so
	 * the pricing screen can be relocated without hunting down every link.
	 *
	 * @return string
	 */
	public static function upgrade_url() {
		return self::url( 'settings' ) . '#vmsai-section-plans';
	}
}
