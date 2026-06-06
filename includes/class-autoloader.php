<?php
/**
 * Includes the Composer autoloader used for packages and classes in the includes/ directory.
 * Falls back to explicit require_once for each class file when vendor/ is absent (production builds).
 *
 * @package Camaleaunmail
 * @since   0.1.0
 */

namespace Camaleaunmail;

defined( 'ABSPATH' ) || exit;

/**
 * Autoloader class.
 *
 * @since 0.1.0
 */
class Autoloader {

	/**
	 * Static-only class.
	 *
	 * @since 0.1.0
	 */
	private function __construct() {}

	/**
	 * Initialise the autoloader.
	 *
	 * Prefers vendor/autoload.php (dev environment). Falls back to explicit
	 * require_once calls for production builds where vendor/ is absent.
	 *
	 * @since  0.1.0
	 * @return bool
	 */
	public static function init() {
		$plugin_dir = dirname( __DIR__ );
		$vendor     = $plugin_dir . '/vendor/autoload.php';

		if ( is_readable( $vendor ) ) {
			$result = require $vendor;
			return (bool) $result;
		}

		// Production build: vendor/ was excluded via .gitattributes export-ignore.
		// Load each class file explicitly — mirrors composer classmap behaviour.
		self::load_files( $plugin_dir );
		return true;
	}

	/**
	 * Register a spl autoloader that maps Camaleaunmail\ClassName → includes/class-class-name.php.
	 *
	 * Converts PascalCase to lowercase-kebab:
	 *   RestApi      → class-rest-api.php
	 *   AdminPage    → class-admin-page.php
	 *   CliCommand   → class-cli-command.php
	 *
	 * @since  0.1.0
	 * @param  string $plugin_dir Absolute path to the plugin root directory.
	 * @return void
	 */
	private static function load_files( string $plugin_dir ) {
		$includes  = $plugin_dir . '/includes/';
		$namespace = 'Camaleaunmail\\';
		$length    = strlen( $namespace );

		spl_autoload_register(
			function ( $class_name ) use ( $includes, $namespace, $length ) {
				if ( strncmp( $class_name, $namespace, $length ) !== 0 ) {
					return;
				}
				// Strip namespace, convert PascalCase → lowercase-kebab.
				$short = substr( $class_name, $length );
				$kebab = strtolower( str_replace( '_', '-', preg_replace( '/([A-Z])/', '_$1', lcfirst( $short ) ) ) );
				$file  = $includes . 'class-' . $kebab . '.php';
				if ( is_readable( $file ) ) {
					require_once $file;
				}
			}
		);
	}
}
