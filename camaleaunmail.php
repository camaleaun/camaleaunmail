<?php
/**
 * Plugin Name:       Camaleaunmail
 * Plugin URI:        https://github.com/camaleaun/camaleaunmail
 * Description:       Sends WordPress email through SMTP, logs every email and blocks sending on local sites.
 * Version:           0.4.0
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Tested up to:      7.0
 * Author:            Gilberto Tavares
 * Author URI:        https://github.com/camaleaun
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       camaleaunmail
 * Domain Path:       /languages
 *
 * @package           Camaleaunmail
 */

defined( 'ABSPATH' ) || exit;

define( 'CAMALEAUNMAIL_VERSION', '0.4.0' );
define( 'CAMALEAUNMAIL_PATH', plugin_dir_path( __FILE__ ) );
define( 'CAMALEAUNMAIL_URL', plugin_dir_url( __FILE__ ) );

require_once CAMALEAUNMAIL_PATH . 'lib/selfdirectory/class-selfdirectory.php';
require CAMALEAUNMAIL_PATH . 'includes/class-autoloader.php';
require CAMALEAUNMAIL_PATH . 'includes/class-packages.php';

if ( ! \Camaleaunmail\Autoloader::init() ) {
	return;
}
\Camaleaunmail\Packages::init();

add_action(
	'init',
	function () {
		load_plugin_textdomain( 'camaleaunmail', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);

add_action(
	'selfd_register',
	function () {
		selfd( __FILE__ );
	}
);

register_activation_hook(
	__FILE__,
	function () {
		\Camaleaunmail\Logs::install();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		\Camaleaunmail\Logs::unschedule_purge();

		$plugin_settings = get_option( 'camaleaunmail_plugin_settings', array() );
		if ( ! empty( $plugin_settings['clear_on_deactivate'] ) ) {
			delete_option( 'camaleaunmail_settings' );
			delete_option( 'camaleaunmail_plugin_settings' );
			\Camaleaunmail\Logs::uninstall();
		}
	}
);
