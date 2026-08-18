<?php
/**
 * Settings and credential vault.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes plugin configuration. Secrets are encrypted at rest.
 */
class VMSAI_Settings {

	const OPTION      = 'vmsai_settings';
	const CRED_OPTION = 'vmsai_credentials';

	/**
	 * Runtime cache.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Credential runtime cache.
	 *
	 * @var array|null
	 */
	private static $creds = null;

	/**
	 * Shipping defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'autonomy'            => 'assisted', // full | assisted | manual.
			'text_chain'          => array( 'aipuffer', 'anthropic', 'gemini', 'openrouter', 'nvidia', 'ollama' ),
			// Free-first, and every entry actually generates. AI Puffer leads
			// because it fronts many models behind one setup; Pollinations is
			// the keyless floor. Pexels is deliberately absent: it returns an
			// existing stock photo, which cannot honour a visual brief and
			// effectively never fails, so including it by default meant it
			// silently absorbed every request once anything above it faltered.
			'image_chain'         => array( 'aipuffer', 'pollinations', 'gemini', 'huggingface', 'cloudflare', 'openrouter', 'vmimageai', 'comfyui' ),
			'text_model'          => array(),   // provider => model id.
			'image_model'         => array(),
			'request_timeout'     => 90,
			'max_attempts'        => 3,
			'circuit_threshold'   => 5,
			'circuit_cooldown'    => 1800,
			'daily_post_cap'      => 24,
			'per_channel_cap'     => 4,
			'quiet_hours'         => array( '01:00', '06:00' ),
			'timezone_source'     => 'site',
			'brand_watermark'     => 0,
			'watermark_id'        => 0,
			'image_size'          => '1080x1350',
			'language'            => 'en',
			'locale_flavour'      => '',     // e.g. "Hinglish for Tier-2 India".
			'hashtag_count'       => 8,
			'emoji_density'       => 'light',
			'link_in_bio'         => '',
			'utm_source'          => 'vm-social-ai-pro',
			'require_approval'    => array( 'facebook' => 0, 'instagram' => 0, 'x' => 0, 'linkedin' => 0, 'gbp' => 1, 'youtube' => 1 ),
			'enabled_channels'    => array(),
			'retention_days'      => 120,
			'log_level'           => 'info',
			'admin_theme'         => 'dark',
			'remote_storage'      => 'off', // off | r2
			'video_source'        => 'pexels', // pexels | pollinations | off
			'elevenlabs_voice'    => 'pNInz6ov9TqWwaY67P6D',
			'white_label'         => 0,
			'agency_name'         => 'Agency',
			'telegram_owner_id'   => '',
			'alert_on_failure'    => 1,
			'alert_email'         => '',
		);
	}

	/**
	 * Get all settings merged over defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			// Performance: Use object cache if available to reduce DB hits.
			$cache_key = 'vmsai_settings_all';
			$stored    = wp_cache_get( $cache_key, 'vmsai' );

			if ( false === $stored ) {
				$stored = get_option( self::OPTION, array() );
				wp_cache_set( $cache_key, $stored, 'vmsai', 3600 );
			}

			self::$cache = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * Read a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Persist a partial settings array.
	 *
	 * @param array $patch Values to merge.
	 * @return void
	 */
	public static function update( array $patch ) {
		$all = array_merge( self::all(), $patch );
		update_option( self::OPTION, $all, false );
		wp_cache_delete( 'vmsai_settings_all', 'vmsai' );
		self::$cache = $all;
	}

	/**
	 * Read a decrypted credential.
	 *
	 * @param string $key     Credential key, e.g. "gemini_api_key".
	 * @param string $default Fallback.
	 * @return string
	 */
	public static function credential( $key, $default = '' ) {
		if ( null === self::$creds ) {
			$raw         = get_option( self::CRED_OPTION, array() );
			self::$creds = is_array( $raw ) ? $raw : array();
		}

		// Constants always win.
		$constant = 'VMSAI_' . strtoupper( $key );
		if ( defined( $constant ) ) {
			return (string) constant( $constant );
		}

		$value = '';
		if ( ! empty( self::$creds[ $key ] ) ) {
			$value = VMSAI_Crypto::decrypt( self::$creds[ $key ] );
		}

		// Pro Feature: Cross-Plugin Credential Sync.
		// If key is missing here, check VM SEO Brain and VM Image AI to avoid re-entering.
		if ( '' === $value ) {
			// 1. Check VM SEO Brain
			if ( class_exists( 'VMSB_Settings' ) ) {
				$vmsb_val = VMSB_Settings::get( $key );
				if ( $vmsb_val ) return (string) $vmsb_val;
			}

			// 2. Check VM Image AI
			if ( class_exists( 'VMIA_Settings' ) ) {
				$vmia_val = VMIA_Settings::get( $key );
				if ( $vmia_val ) return (string) $vmia_val;
			}
		}

		return '' === $value ? $default : $value;
	}

	/**
	 * Store credentials, encrypting non-empty values.
	 *
	 * @param array $patch Key => plaintext value.
	 * @return void
	 */
	public static function update_credentials( array $patch ) {
		$raw = get_option( self::CRED_OPTION, array() );
		$raw = is_array( $raw ) ? $raw : array();
		$updated = false;

		foreach ( $patch as $key => $value ) {
			$key = sanitize_key( $key );

			// If the value is purely bullets, the user didn't change it.
			if ( self::is_masked( $value ) ) {
				continue;
			}

			if ( '' === $value || null === $value ) {
				if ( isset( $raw[ $key ] ) ) {
					unset( $raw[ $key ] );
					$updated = true;
				}
				continue;
			}

			$encrypted = VMSAI_Crypto::encrypt( (string) $value );
			if ( ! isset( $raw[ $key ] ) || $raw[ $key ] !== $encrypted ) {
				$raw[ $key ] = $encrypted;
				$updated = true;
			}
		}

		if ( $updated ) {
			update_option( self::CRED_OPTION, $raw, false );
			self::$creds = $raw;
		}
	}

	/**
	 * Detect the placeholder we render in the admin instead of a real secret.
	 *
	 * @param string $value Submitted value.
	 * @return bool
	 */
	public static function is_masked( $value ) {
		return (bool) preg_match( '/^•+$/u', trim( (string) $value ) );
	}

	/**
	 * Render-safe mask for a stored secret.
	 *
	 * @param string $key Credential key.
	 * @return string
	 */
	public static function mask( $key ) {
		return self::credential( $key ) ? str_repeat( '•', 24 ) : '';
	}

	/**
	 * Whether a channel is switched on and holds credentials.
	 *
	 * @param string $channel Channel slug.
	 * @return bool
	 */
	public static function channel_enabled( $channel ) {
		$enabled = (array) self::get( 'enabled_channels', array() );
		return ! empty( $enabled[ $channel ] );
	}
}
