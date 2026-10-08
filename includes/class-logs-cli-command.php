<?php
/**
 * WP-CLI command — list and purge the email log.
 *
 * @package Camaleaunmail
 * @since   0.2.0
 */

namespace Camaleaunmail;

defined( 'ABSPATH' ) || exit;

/**
 * Manage the Camaleaunmail email log.
 *
 * ## EXAMPLES
 *
 *     # Last 20 emails
 *     wp camaleaunmail logs list
 *
 *     # Only failed emails, as JSON
 *     wp camaleaunmail logs list --status=failed --format=json
 *
 *     # Delete entries older than the configured retention
 *     wp camaleaunmail logs purge
 *
 *     # Delete every entry
 *     wp camaleaunmail logs purge --all
 *
 * @when after_wp_load
 */
class LogsCliCommand {

	/**
	 * List log entries, newest first.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : Only entries with this status.
	 * ---
	 * options:
	 *   - pending
	 *   - sent
	 *   - failed
	 *   - blocked
	 *   - short_circuited
	 * ---
	 *
	 * [--search=<text>]
	 * : Match recipient or subject.
	 *
	 * [--per-page=<number>]
	 * : Number of entries.
	 * ---
	 * default: 20
	 * ---
	 *
	 * [--page=<number>]
	 * : Page number.
	 * ---
	 * default: 1
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 *   - count
	 * ---
	 *
	 * @subcommand list
	 * @param array<int,string>    $args       Positional args.
	 * @param array<string,string> $assoc_args Named args.
	 */
	public function list_( array $args, array $assoc_args ): void {
		$result = Logs::query(
			array(
				'status'   => $assoc_args['status'] ?? '',
				'search'   => $assoc_args['search'] ?? '',
				'per_page' => $assoc_args['per-page'] ?? 20,
				'page'     => $assoc_args['page'] ?? 1,
			)
		);

		$format = $assoc_args['format'] ?? 'table';
		if ( 'count' === $format ) {
			\WP_CLI::line( (string) $result['total'] );
			return;
		}

		\WP_CLI\Utils\format_items(
			$format,
			$result['items'],
			array( 'id', 'created_at', 'status', 'transport', 'to_email', 'subject', 'error' )
		);
	}

	/**
	 * Delete old log entries.
	 *
	 * ## OPTIONS
	 *
	 * [--days=<days>]
	 * : Delete entries older than this many days. Defaults to the retention setting.
	 *
	 * [--all]
	 * : Delete every entry.
	 *
	 * [--yes]
	 * : Skip the confirmation for --all.
	 *
	 * @subcommand purge
	 * @param array<int,string>    $args       Positional args.
	 * @param array<string,string> $assoc_args Named args.
	 */
	public function purge( array $args, array $assoc_args ): void {
		if ( isset( $assoc_args['all'] ) ) {
			\WP_CLI::confirm( 'Delete every log entry?', $assoc_args );
			Logs::truncate();
			\WP_CLI::success( 'All log entries deleted.' );
			return;
		}

		$days = isset( $assoc_args['days'] )
			? absint( $assoc_args['days'] )
			: (int) Settings::plugin_settings()['log_retention_days'];

		if ( $days <= 0 ) {
			\WP_CLI::warning( 'Retention is set to keep logs forever. Use --days or --all.' );
			return;
		}

		$deleted = Logs::purge_older_than( $days );
		\WP_CLI::success( sprintf( 'Deleted %d entries older than %d days.', $deleted, $days ) );
	}
}
