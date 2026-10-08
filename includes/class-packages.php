<?php
/**
 * Package bootstrap.
 *
 * @package Camaleaunmail
 * @since   0.1.0
 */

namespace Camaleaunmail;

defined( 'ABSPATH' ) || exit;

/**
 * Initialises all first-party packages.
 *
 * @since 0.1.0
 */
class Packages {

	/**
	 * Constructor — private, use ::init().
	 *
	 * @since 0.1.0
	 */
	private function __construct() {}

	/**
	 * Boot all packages.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public static function init(): void {
		Mailer::init();
		Logs::init();
		MailLogger::init();
		RestApi::init();
		AdminPage::init();

		// WP-CLI command.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'camaleaunmail', CliCommand::class );
			\WP_CLI::add_command( 'camaleaunmail logs', LogsCliCommand::class );
		}
	}
}
