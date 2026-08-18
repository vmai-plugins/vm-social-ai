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
		'includes/integrations/',
	);

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

		$slug     = strtolower( str_replace( '_', '-', $class ) );
		$variants = array( 'class-' . $slug . '.php', 'interface-' . $slug . '.php', 'abstract-' . $slug . '.php' );

		foreach ( self::$dirs as $dir ) {
			foreach ( $variants as $file ) {
				$path = VMSAI_PATH . $dir . $file;
				if ( is_readable( $path ) ) {
					require_once $path;
					return;
				}
			}
		}
	}
}
