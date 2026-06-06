<?php
/**
 * WP-CLI command — export/import settings as YAML.
 *
 * @package Camaleaunmail
 * @since   0.1.0
 */

namespace Camaleaunmail;

defined( 'ABSPATH' ) || exit;

/**
 * Manage Crimson Agility Mail settings via WP-CLI.
 *
 * ## EXAMPLES
 *
 *     # Export current settings to stdout
 *     wp camaleaunmail export
 *
 *     # Export to file
 *     wp camaleaunmail export --file=mail-settings.yml
 *
 *     # Import from file
 *     wp camaleaunmail import --file=mail-settings.yml
 *
 *     # Import from stdin (pipe)
 *     cat mail-settings.yml | wp camaleaunmail import
 *
 * @when after_wp_load
 */
class CliCommand {

	/**
	 * Export current settings as YAML.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<path>]
	 * : Write output to this file instead of stdout.
	 *
	 * ## EXAMPLES
	 *
	 *     wp camaleaunmail export
	 *     wp camaleaunmail export --file=mail.yml
	 *
	 * @subcommand export
	 * @param array<int,string>    $args       Positional args.
	 * @param array<string,string> $assoc_args Named args.
	 */
	public function export( array $args, array $assoc_args ): void {
		$yaml = Settings::to_yaml();

		$file = $assoc_args['file'] ?? '';
		if ( $file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- WP-CLI context, no HTTP; WP_Filesystem not available.
			file_put_contents( $file, $yaml );
			\WP_CLI::success( 'Settings exported to ' . $file );
		} else {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- YAML output to CLI stdout, not HTML.
			echo $yaml;
		}
	}

	/**
	 * Import settings from a YAML file or stdin.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<path>]
	 * : Read YAML from this file. If omitted, reads from stdin.
	 *
	 * [--dry-run]
	 * : Parse the YAML and show what would be saved without writing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp camaleaunmail import --file=mail.yml
	 *     cat mail.yml | wp camaleaunmail import
	 *
	 * @subcommand import
	 * @param array<int,string>    $args       Positional args.
	 * @param array<string,string> $assoc_args Named args.
	 */
	public function import( array $args, array $assoc_args ): void {
		self::require_spyc();

		$file = $assoc_args['file'] ?? '';
		if ( $file ) {
			if ( ! file_exists( $file ) ) {
				\WP_CLI::error( 'File not found: ' . $file );
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- WP-CLI context, local file read.
			$yaml = file_get_contents( $file );
		} else {
			$yaml = stream_get_contents( STDIN );
		}

		if ( ! $yaml ) {
			\WP_CLI::error( 'No YAML content received.' );
		}

		$data = \Spyc::YAMLLoadString( $yaml );
		if ( ! is_array( $data ) ) {
			\WP_CLI::error( 'Could not parse YAML — invalid format.' );
		}

		$dry_run = isset( $assoc_args['dry-run'] );
		if ( $dry_run ) {
			\WP_CLI::line( 'Dry run — would save:' );
			foreach ( $data as $key => $value ) {
				$display = in_array( $key, array( 'smtp_password' ), true )
					? '(secret)'
					: $value;
				\WP_CLI::line( "  $key: $display" );
			}
			\WP_CLI::success( 'Dry run complete. No changes written.' );
			return;
		}

		Settings::save( $data );
		\WP_CLI::success( 'Settings imported successfully.' );
	}

	// -------------------------------------------------------------------------

	/**
	 * Load the bundled Spyc class if not already loaded.
	 *
	 * @return void
	 */
	private static function require_spyc(): void {
		if ( class_exists( 'Spyc' ) ) {
			return;
		}
		$path = __DIR__ . '/class-spyc.php';
		if ( ! file_exists( $path ) ) {
			\WP_CLI::error( 'class-spyc.php not found. Run: composer install' );
		}
		require_once $path;
	}
}
