<?php
/**
 * Plugin Name:       VM Social AI Pro
 * Plugin URI:        https://vmstudio.digital/plugins/vm-social-ai
 * Description:       Enterprise-grade autonomous social media growth engine. Dual AI engines with live model sync, Agentic War Room logic, Digital Twin simulation, News-Jacking (RAG), and Cloudflare R2 offloading.
 * Version:           1.17.1
 * Requires at least: 6.2
 * Requires PHP:      8.0
 * Author:            VM Studio Creatives
 * Author URI:        https://vmstudio.digital
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       vm-social-ai-pro
 * Domain Path:       /languages
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

define( 'VMSAI_VERSION', '1.17.1' );
define( 'VMSAI_FILE', __FILE__ );
define( 'VMSAI_PATH', plugin_dir_path( __FILE__ ) );
define( 'VMSAI_URL', plugin_dir_url( __FILE__ ) );
define( 'VMSAI_BASENAME', plugin_basename( __FILE__ ) );
define( 'VMSAI_SLUG', 'vm-social-ai-pro' );

require_once VMSAI_PATH . 'includes/class-vmsai-autoloader.php';
VMSAI_Autoloader::register();

register_activation_hook( __FILE__, array( 'VMSAI_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'VMSAI_Install', 'deactivate' ) );

/**
 * Force Capability Sync for Administrators
 */
add_action( 'admin_init', function() {
	$admin = get_role( 'administrator' );
	if ( $admin && ! $admin->has_cap( 'vmsai_read' ) ) {
		VMSAI_Install::create_roles();
	}
} );

/**
 * Main container accessor.
 *
 * @return VMSAI_Plugin
 */
function vmsai() {
	return VMSAI_Plugin::instance();
}

add_action( 'plugins_loaded', 'vmsai', 5 );
