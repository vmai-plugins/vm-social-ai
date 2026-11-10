<?php
/**
 * Class autoloader.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Maps VMSAI_Foo_Bar to the correct file inside includes/ or admin/.
 */
class VMSAI_Autoloader {

	/**
	 * Directories scanned for class files, in priority order.
	 *
	 * @var string[]
	 */
	private static $dirs = array(
		'includes/',
		'includes/engine/',
		'includes/engine/text/',
		'includes/engine/image/',
		'includes/brain/',
		'includes/channels/',
		'admin/',
	);

	/**
	 * Resolved class path cache.
	 *
	 * @var array<string,string>
	 */
	private static $resolved = array();

	/**
	 * Register the autoloader with SPL.
	 *
	 * @return void
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * Resolve and require a class file.
	 *
	 * @param string $class Fully qualified class name.
	 * @return void
	 */
	public static function load( $class ) {
		if ( 0 !== strpos( $class, 'VMSAI_' ) ) {
			return;
		}

		if ( isset( self::$resolved[ $class ] ) ) {
			require_once self::$resolved[ $class ];
			return;
		}

		$slug     = strtolower( str_replace( '_', '-', $class ) );
		$variants = array( 'class-' . $slug . '.php', 'interface-' . $slug . '.php', 'abstract-' . $slug . '.php' );

		// Targeted directory prioritization based on naming convention
		$search_dirs = self::$dirs;
		if ( 0 === strpos( $class, 'VMSAI_Channel_' ) ) {
			array_unshift( $search_dirs, 'includes/channels/' );
		} elseif ( 0 === strpos( $class, 'VMSAI_Text_' ) ) {
			array_unshift( $search_dirs, 'includes/engine/text/' );
		} elseif ( 0 === strpos( $class, 'VMSAI_Image_' ) ) {
			array_unshift( $search_dirs, 'includes/engine/image/' );
		} elseif ( 0 === strpos( $class, 'VMSAI_Brain' ) || 0 === strpos( $class, 'VMSAI_Agent' ) ) {
			array_unshift( $search_dirs, 'includes/brain/' );
		}

		foreach ( $search_dirs as $dir ) {
			foreach ( $variants as $file ) {
				$path = VMSAI_PATH . $dir . $file;
				if ( is_readable( $path ) ) {
					self::$resolved[ $class ] = $path;
					require_once $path;
					return;
				}
			}
		}
	}
}
