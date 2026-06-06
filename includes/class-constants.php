<?php
/**
 * Plugin-wide constants and path helpers.
 *
 * @package Camaleaunmail
 * @since   0.1.0
 */

namespace Camaleaunmail;

defined( 'ABSPATH' ) || exit;

/**
 * Centralises plugin paths and URLs.
 *
 * @since 0.1.0
 */
class Constants {

	/**
	 * Absolute path to the plugin root directory (with trailing slash).
	 *
	 * @since  0.1.0
	 * @return string
	 */
	public static function plugin_path(): string {
		return CAMALEAUNMAIL_PATH;
	}

	/**
	 * URL to the plugin root directory (with trailing slash).
	 *
	 * @since  0.1.0
	 * @return string
	 */
	public static function plugin_url(): string {
		return CAMALEAUNMAIL_URL;
	}

	/**
	 * Plugin version string.
	 *
	 * @since  0.1.0
	 * @return string
	 */
	public static function version(): string {
		return CAMALEAUNMAIL_VERSION;
	}
}
