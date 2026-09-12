<?php
/**
 * GitHub Updater for VM Social AI Pro.
 *
 * Checks for updates from https://github.com/vmai-plugins/vm-social-ai
 * and integrates directly with WordPress Core updater and 1-click admin updates.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles GitHub repository polling, version comparison, download packaging,
 * and seamless in-place updates.
 */
class VMSAI_Github_Updater {

	/**
	 * GitHub Repository owner/repo.
	 */
	const GITHUB_REPO = 'vmai-plugins/vm-social-ai';

	/**
	 * Default GitHub branch.
	 */
	const GITHUB_BRANCH = 'master';

	/**
	 * Transient key for caching GitHub release info.
	 */
	const TRANSIENT_KEY = 'vmsai_github_update_info';

	/**
	 * Cache TTL in seconds (12 hours).
	 */
	const CACHE_TTL = 43200;

	/**
	 * Transient key set for 10 minutes after both GitHub probes fail, so
	 * rate-limited/outage conditions do not hang every admin page load
	 * with repeated synchronous retries.
	 */
	const FAIL_LOCK_KEY = 'vmsai_github_update_fail';

	/**
	 * Singleton instance.
	 *
	 * @var VMSAI_Github_Updater|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return VMSAI_Github_Updater
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Registers WordPress hooks.
	 */
	public function __construct() {
		// Hook into WordPress Plugin Update Checks
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update_transient' ) );
		add_filter( 'plugins_api', array( $this, 'plugins_api_info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_folder' ), 10, 4 );

		// Add Action Links in wp-admin/plugins.php
		add_filter( 'plugin_action_links_' . VMSAI_BASENAME, array( $this, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
	}

	/**
	 * Add "Check for Updates" link to plugin actions on plugins.php.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public function action_links( $links ) {
		$update_url = admin_url( 'admin.php?page=' . VMSAI_Admin::SLUG . '&tab=settings#vmsai-section-updates' );
		$new_links  = array(
			'github_update' => '<a href="' . esc_url( $update_url ) . '" style="color:#c9a227;font-weight:600;">' . esc_html__( 'GitHub Updates', 'vm-social-ai-pro' ) . '</a>',
		);
		return array_merge( $new_links, $links );
	}

	/**
	 * Add repository link and version check to plugin row meta on plugins.php.
	 *
	 * @param array  $links Existing row meta links.
	 * @param string $file  Plugin file basename.
	 * @return array
	 */
	public function row_meta( $links, $file ) {
		if ( VMSAI_BASENAME === $file ) {
			$links[] = '<a href="https://github.com/' . esc_attr( self::GITHUB_REPO ) . '" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-external" style="font-size:14px;vertical-align:text-top;"></span> ' . esc_html__( 'GitHub Repo', 'vm-social-ai-pro' ) . '</a>';
		}
		return $links;
	}

	/**
	 * Fetch the latest release/tag information or raw master metadata from GitHub.
	 *
	 * @param bool $force Bypass cache.
	 * @return array
	 */
	public function get_remote_info( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( self::TRANSIENT_KEY );
			if ( is_array( $cached ) && ! empty( $cached['version'] ) ) {
				return $cached;
			}

			// Back-off window: both probes failed recently (rate limit,
			// outage). Return the no-update shape immediately instead of
			// blocking the admin page with another round of slow requests.
			if ( get_transient( self::FAIL_LOCK_KEY ) ) {
				return array(
					'version'       => VMSAI_VERSION,
					'current'       => VMSAI_VERSION,
					'has_update'    => false,
					'download_url'  => '',
					'release_notes' => '',
					'published_at'  => '',
					'is_release'    => false,
					'repo'          => self::GITHUB_REPO,
					'branch'        => self::GITHUB_BRANCH,
					'last_checked'  => current_time( 'mysql' ),
				);
			}
		}

		$token = VMSAI_Settings::credential( 'github_token' );
		$headers = array(
			'Accept'     => 'application/vnd.github.v3+json',
			'User-Agent' => 'VM-Social-AI-Pro-WordPress/' . VMSAI_VERSION,
		);

		if ( ! empty( $token ) ) {
			$headers['Authorization'] = 'token ' . trim( $token );
		}

		$latest_version  = '';
		$download_url    = '';
		$release_notes   = '';
		$published_at    = '';
		$is_release      = false;

		// 1. First attempt: Query latest GitHub Release API
		$release_endpoint = 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest';
		$response         = wp_remote_get( $release_endpoint, array( 'headers' => $headers, 'timeout' => 10 ) );

		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( is_array( $body ) && ! empty( $body['tag_name'] ) ) {
				$latest_version = ltrim( (string) $body['tag_name'], 'vV' );
				$release_notes  = (string) ( $body['body'] ?? '' );
				$published_at   = (string) ( $body['published_at'] ?? '' );
				$is_release     = true;

				// Check if there is an attached zip asset, otherwise fallback to zipball
				if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
					foreach ( $body['assets'] as $asset ) {
						if ( isset( $asset['browser_download_url'] ) && '.zip' === substr( (string) $asset['browser_download_url'], -4 ) ) {
							$download_url = $asset['browser_download_url'];
							break;
						}
					}
				}

				if ( empty( $download_url ) && ! empty( $body['zipball_url'] ) ) {
					$download_url = $body['zipball_url'];
				}
			}
		}

		// 2. Second attempt: Check raw plugin header from master branch if no release tag found or if checking master
		if ( empty( $latest_version ) ) {
			$raw_file_url = 'https://raw.githubusercontent.com/' . self::GITHUB_REPO . '/' . self::GITHUB_BRANCH . '/vm-social-ai.php';
			$raw_res      = wp_remote_get( $raw_file_url, array( 'headers' => $headers, 'timeout' => 15 ) );

			if ( ! is_wp_error( $raw_res ) && 200 === wp_remote_retrieve_response_code( $raw_res ) ) {
				$file_contents = wp_remote_retrieve_body( $raw_res );
				if ( preg_match( '/^[ \t\/*#@]*Version:\s*(.+)$/m', $file_contents, $matches ) ) {
					$latest_version = trim( $matches[1] );
					$release_notes  = "Latest updates directly from GitHub master branch.";
				}
			}
		}

		// Fallback download URL points directly to master archive
		if ( empty( $download_url ) ) {
			$download_url = 'https://github.com/' . self::GITHUB_REPO . '/archive/refs/heads/' . self::GITHUB_BRANCH . '.zip';
		}

		// Both probes failed (rate limit, outage, timeouts) — open a back-off
		// window so subsequent update-transient refreshes return the cached
		// no-update shape immediately instead of blocking wp-admin with two
		// more slow HTTP requests.
		if ( '' === $latest_version ) {
			set_transient( self::FAIL_LOCK_KEY, 1, 15 * MINUTE_IN_SECONDS );
		}

		// If version still couldn't be parsed, fallback to current
		if ( empty( $latest_version ) ) {
			$latest_version = VMSAI_VERSION;
		}

		$has_update = version_compare( $latest_version, VMSAI_VERSION, '>' );

		$info = array(
			'version'       => $latest_version,
			'current'       => VMSAI_VERSION,
			'has_update'    => $has_update,
			'download_url'  => $download_url,
			'release_notes' => $release_notes,
			'published_at'  => $published_at,
			'is_release'    => $is_release,
			'repo'          => self::GITHUB_REPO,
			'branch'        => self::GITHUB_BRANCH,
			'last_checked'  => current_time( 'mysql' ),
		);

		set_transient( self::TRANSIENT_KEY, $info, self::CACHE_TTL );

		return $info;
	}

	/**
	 * Inject GitHub update info into WordPress core update transient.
	 *
	 * @param object $transient Update plugins transient.
	 * @return object
	 */
	public function inject_update_transient( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}

		$info = $this->get_remote_info( false );

		if ( ! empty( $info['has_update'] ) ) {
			$obj              = new stdClass();
			$obj->slug        = VMSAI_SLUG;
			$obj->plugin      = VMSAI_BASENAME;
			$obj->new_version = $info['version'];
			$obj->url         = 'https://github.com/' . self::GITHUB_REPO;
			$obj->package     = $info['download_url'];
			$obj->icons       = array(
				'1x' => VMSAI_URL . 'admin/assets/icon-128.png',
				'2x' => VMSAI_URL . 'admin/assets/icon-256.png',
			);

			$transient->response[ VMSAI_BASENAME ] = $obj;
		} else {
			$item              = new stdClass();
			$item->slug        = VMSAI_SLUG;
			$item->plugin      = VMSAI_BASENAME;
			$item->new_version = VMSAI_VERSION;
			$item->url         = 'https://github.com/' . self::GITHUB_REPO;
			$item->package     = '';
			$transient->no_update[ VMSAI_BASENAME ] = $item;
		}

		return $transient;
	}

	/**
	 * Provide detailed plugin information popup for WordPress updates screen.
	 *
	 * @param false|object|array $result Default result.
	 * @param string             $action Action being performed.
	 * @param object             $args   Arguments.
	 * @return false|object
	 */
	public function plugins_api_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || VMSAI_SLUG !== $args->slug ) {
			return $result;
		}

		$info = $this->get_remote_info( false );

		$res                = new stdClass();
		$res->name          = 'VM Social AI Pro';
		$res->slug          = VMSAI_SLUG;
		$res->version       = $info['version'];
		$res->author        = '<a href="https://vmstudio.digital">VM Studio Creatives</a>';
		$res->homepage      = 'https://github.com/' . self::GITHUB_REPO;
		$res->download_link = $info['download_url'];
		$res->sections      = array(
			'description' => '<p>Autonomous Enterprise Social Media Growth Engine with dual AI engines, News-Jacking RAG, Digital Twin, and Cloudflare R2 offloading.</p>',
			'changelog'   => ! empty( $info['release_notes'] ) ? nl2br( esc_html( $info['release_notes'] ) ) : '<p>Updates and refinements synchronized from GitHub master repository.</p>',
		);

		return $res;
	}

	/**
	 * Rename the extracted GitHub zipball directory to 'vm-social-ai'.
	 *
	 * When GitHub creates zipballs, they unpack into directories like
	 * `vmai-plugins-vm-social-ai-7b2aa3f` or `vm-social-ai-master`.
	 * WordPress needs the folder to match the existing plugin directory `vm-social-ai`.
	 *
	 * @param string      $source        Path on local filesystem for extracted archive.
	 * @param string      $remote_source Remote source path.
	 * @param WP_Upgrader $upgrader      WP_Upgrader instance.
	 * @param array       $hook_extra    Extra hook data.
	 * @return string|WP_Error
	 */
	public function fix_source_folder( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;

		if ( ! is_object( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Check if this upgrade is for our plugin
		$is_our_plugin = false;
		if ( isset( $hook_extra['plugin'] ) && VMSAI_BASENAME === $hook_extra['plugin'] ) {
			$is_our_plugin = true;
		} elseif ( isset( $hook_extra['slug'] ) && VMSAI_SLUG === $hook_extra['slug'] ) {
			$is_our_plugin = true;
		} else {
			// No plugin/slug hint arrives here in bulk updates — for ANY
			// package. A bare substring match on 'vm-social-ai' could claim
			// an unrelated extracted folder and force-rename it. Only accept
			// directory names that actually look like our zipballs
			// (vmai-plugins-vm-social-ai-<sha>, vm-social-ai-<branch>).
			$dir = basename( untrailingslashit( $source ) );
			$is_our_plugin = (
				'vm-social-ai' === $dir
				|| 0 === strpos( $dir, 'vmai-plugins-vm-social-ai' )
				|| preg_match( '/^vm-social-ai-[A-Za-z0-9._-]+$/', $dir )
			);
		}

		if ( ! $is_our_plugin ) {
			return $source;
		}

		$corrected_source = trailingslashit( $remote_source ) . 'vm-social-ai/';

		if ( trailingslashit( $source ) === $corrected_source ) {
			return $source;
		}

		// Move / Rename to the standard folder name
		$wp_filesystem->move( $source, $corrected_source, true );

		return $corrected_source;
	}

	/**
	 * Perform direct 1-click in-place update from GitHub.
	 *
	 * @return array Result array with status, message, and updated version.
	 */
	public function perform_direct_update() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'You do not have permission to update plugins.', 'vm-social-ai-pro' ),
			);
		}

		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		include_once ABSPATH . 'wp-admin/includes/file.php';
		include_once ABSPATH . 'wp-admin/includes/misc.php';

		// Force fetch fresh GitHub info
		$info = $this->get_remote_info( true );
		$download_url = $info['download_url'];

		if ( empty( $download_url ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Could not find a valid download package URL from GitHub.', 'vm-social-ai-pro' ),
			);
		}

		// Initialize WP Filesystem
		global $wp_filesystem;
		if ( ! WP_Filesystem() ) {
			return array(
				'ok'      => false,
				'message' => __( 'Unable to initialize WordPress filesystem credentials.', 'vm-social-ai-pro' ),
			);
		}

		// Prepare Silent Upgrader Skin
		$skin = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );

		// Set transient for the upgrade run
		$this->inject_update_transient( get_site_transient( 'update_plugins' ) );

		// Perform upgrade using Plugin_Upgrader
		$result = $upgrader->upgrade( VMSAI_BASENAME, array(
			'clear_update_cache' => true,
		) );

		// Clear update transient and OPcache
		delete_transient( self::TRANSIENT_KEY );
		delete_site_transient( 'update_plugins' );
		if ( function_exists( 'opcache_reset' ) ) {
			@opcache_reset();
		}

		// Re-activate plugin if it was deactivated during upgrade
		if ( ! is_plugin_active( VMSAI_BASENAME ) ) {
			activate_plugin( VMSAI_BASENAME );
		}

		if ( is_wp_error( $result ) ) {
			return array(
				'ok'      => false,
				'message' => $result->get_error_message(),
			);
		}

		if ( false === $result || null === $result ) {
			// Fallback: If Plugin_Upgrader returned false/null, do direct package download & replace
			return $this->fallback_manual_update( $download_url );
		}

		return array(
			'ok'           => true,
			'message'      => sprintf( __( 'Successfully updated VM Social AI Pro from GitHub to version %s!', 'vm-social-ai-pro' ), $info['version'] ),
			'version'      => $info['version'],
			'last_checked' => current_time( 'mysql' ),
		);
	}

	/**
	 * Fallback direct downloader in case Plugin_Upgrader is blocked by environment constraints.
	 *
	 * @param string $download_url Package zip url.
	 * @return array
	 */
	private function fallback_manual_update( $download_url ) {
		global $wp_filesystem;

		$token = VMSAI_Settings::credential( 'github_token' );
		$args  = array(
			'timeout'  => 60,
			'headers'  => array(
				'User-Agent' => 'VM-Social-AI-Pro-WordPress/' . VMSAI_VERSION,
			),
		);
		if ( ! empty( $token ) ) {
			$args['headers']['Authorization'] = 'token ' . trim( $token );
		}

		// Download temporary package
		$temp_file = download_url( $download_url, 60 );
		if ( is_wp_error( $temp_file ) ) {
			return array(
				'ok'      => false,
				'message' => sprintf( __( 'Download failed: %s', 'vm-social-ai-pro' ), $temp_file->get_error_message() ),
			);
		}

		$temp_dir = trailingslashit( WP_CONTENT_DIR . '/upgrade' ) . 'vmsai_update_' . time();
		$unzip    = unzip_file( $temp_file, $temp_dir );
		@unlink( $temp_file );

		if ( is_wp_error( $unzip ) ) {
			return array(
				'ok'      => false,
				'message' => sprintf( __( 'Unzip failed: %s', 'vm-social-ai-pro' ), $unzip->get_error_message() ),
			);
		}

		// Locate the unpacked plugin root directory
		$unpacked_dirs = glob( $temp_dir . '/*', GLOB_ONLYDIR );
		$source_dir    = ! empty( $unpacked_dirs ) ? $unpacked_dirs[0] : $temp_dir;

		$destination_dir = VMSAI_PATH;

		// Copy files over to destination
		$copied = copy_dir( $source_dir, $destination_dir );
		$wp_filesystem->delete( $temp_dir, true );

		if ( is_wp_error( $copied ) ) {
			return array(
				'ok'      => false,
				'message' => sprintf( __( 'Filesystem copy failed: %s', 'vm-social-ai-pro' ), $copied->get_error_message() ),
			);
		}

		// Read new version from updated file
		$new_plugin_data = get_plugin_data( VMSAI_FILE, false, false );
		$new_version     = $new_plugin_data['Version'] ?? VMSAI_VERSION;

		if ( function_exists( 'opcache_reset' ) ) {
			@opcache_reset();
		}

		return array(
			'ok'           => true,
			'message'      => sprintf( __( 'Updated VM Social AI Pro from GitHub to version %s!', 'vm-social-ai-pro' ), $new_version ),
			'version'      => $new_version,
			'last_checked' => current_time( 'mysql' ),
		);
	}
}
