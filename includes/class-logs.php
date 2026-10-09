<?php
/**
 * Logs — storage for the email log table.
 *
 * @package Camaleaunmail
 * @since   0.2.0
 */

namespace Camaleaunmail;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the log table and reads/writes its rows.
 *
 * Table: {prefix}camaleaunmail_logs
 *
 * @since 0.2.0
 */
class Logs {

	/**
	 * Table name without the WordPress prefix.
	 *
	 * @var string
	 */
	const TABLE = 'camaleaunmail_logs';

	/**
	 * Schema version — bump when the CREATE TABLE statement changes.
	 *
	 * @var string
	 */
	const DB_VERSION = '1';

	/**
	 * Option that stores the installed schema version.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'camaleaunmail_db_version';

	/**
	 * Daily cron hook that purges old rows.
	 *
	 * @var string
	 */
	const PURGE_HOOK = 'camaleaunmail_purge_logs';

	/**
	 * Allowed values for the status column.
	 *
	 * @var string[]
	 */
	const STATUSES = array( 'pending', 'sent', 'failed', 'blocked', 'short_circuited' );

	/**
	 * Columns that can be written through insert() and update().
	 *
	 * @var string[]
	 */
	const COLUMNS = array(
		'created_at',
		'status',
		'transport',
		'from_email',
		'from_name',
		'to_email',
		'cc',
		'bcc',
		'subject',
		'headers',
		'message',
		'content_type',
		'attachments',
		'error',
	);

	/**
	 * Register hooks.
	 *
	 * @since  0.2.0
	 * @return void
	 */
	public static function init(): void {
		add_action( 'plugins_loaded', array( static::class, 'maybe_install' ) );
		add_action( 'init', array( static::class, 'schedule_purge' ) );
		add_action( self::PURGE_HOOK, array( static::class, 'purge' ) );
	}

	/**
	 * Full table name with the site prefix.
	 *
	 * @since  0.2.0
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Create or upgrade the table when the stored schema version is outdated.
	 *
	 * Runs on every load so updating the plugin without reactivating it still
	 * creates the table.
	 *
	 * @since  0.2.0
	 * @return void
	 */
	public static function maybe_install(): void {
		if ( self::DB_VERSION !== get_option( self::DB_VERSION_OPTION ) ) {
			self::install();
		}
	}

	/**
	 * Create the table with dbDelta().
	 *
	 * @since  0.2.0
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		// dbDelta() needs two spaces before PRIMARY KEY and one column per line.
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			status varchar(20) NOT NULL DEFAULT 'pending',
			transport varchar(20) NOT NULL DEFAULT '',
			from_email varchar(255) NOT NULL DEFAULT '',
			from_name varchar(255) NOT NULL DEFAULT '',
			to_email text NOT NULL,
			cc text NOT NULL,
			bcc text NOT NULL,
			subject text NOT NULL,
			headers longtext NOT NULL,
			message longtext NOT NULL,
			content_type varchar(100) NOT NULL DEFAULT '',
			attachments text NOT NULL,
			error text NOT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY status (status),
			KEY to_email (to_email(100))
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Drop the table and its version option.
	 *
	 * @since  0.2.0
	 * @return void
	 */
	public static function uninstall(): void {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is not user input.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		delete_option( self::DB_VERSION_OPTION );
	}

	/**
	 * Insert a row.
	 *
	 * @since  0.2.0
	 * @param  array<string,mixed> $data Column values.
	 * @return int Row id, or 0 on failure.
	 */
	public static function insert( array $data ): int {
		global $wpdb;

		$row = array_merge(
			array(
				'created_at'   => gmdate( 'Y-m-d H:i:s' ),
				'status'       => 'pending',
				'transport'    => '',
				'from_email'   => '',
				'from_name'    => '',
				'to_email'     => '',
				'cc'           => '',
				'bcc'          => '',
				'subject'      => '',
				'headers'      => '',
				'message'      => '',
				'content_type' => '',
				'attachments'  => '',
				'error'        => '',
			),
			self::only_columns( $data )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table.
		$ok = $wpdb->insert( self::table(), $row );
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Update a row.
	 *
	 * @since  0.2.0
	 * @param  int                 $id   Row id.
	 * @param  array<string,mixed> $data Column values.
	 * @return bool
	 */
	public static function update( int $id, array $data ): bool {
		global $wpdb;

		$data = self::only_columns( $data );
		if ( ! $id || ! $data ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		return false !== $wpdb->update( self::table(), $data, array( 'id' => $id ) );
	}

	/**
	 * Fetch one row.
	 *
	 * @since  0.2.0
	 * @param  int $id Row id.
	 * @return array<string,mixed>|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return $row ? self::format( $row ) : null;
	}

	/**
	 * List rows, newest first.
	 *
	 * Accepted args: page, per_page, status, search, after, before (Y-m-d H:i:s, GMT).
	 * The list leaves out the message and headers columns to keep responses small.
	 *
	 * @since  0.2.0
	 * @param  array<string,mixed> $args Query arguments.
	 * @return array{items: array<int,array<string,mixed>>, total: int, pages: int}
	 */
	public static function query( array $args = array() ): array {
		global $wpdb;

		list( $where, $params, $page, $per_page ) = self::build_query( $args );

		$table  = self::table();
		$offset = ( $page - 1 ) * $per_page;
		$cols   = 'id, created_at, status, transport, from_email, from_name, to_email, cc, bcc, subject, content_type, attachments, error';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- custom table, $where only holds placeholders and $params matches them.
		$total = (int) $wpdb->get_var(
			$params
				? $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where}", $params )
				: "SELECT COUNT(*) FROM {$table} {$where}"
		);
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT {$cols} FROM {$table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d",
				array_merge( $params, array( $per_page, $offset ) )
			),
			ARRAY_A
		);
		// phpcs:enable

		return array(
			'items' => array_map( array( static::class, 'format' ), $rows ? $rows : array() ),
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Turn query args into a WHERE clause, its params and pagination.
	 *
	 * @since  0.2.0
	 * @param  array<string,mixed> $args Query arguments.
	 * @return array{0: string, 1: array<int,mixed>, 2: int, 3: int}
	 */
	public static function build_query( array $args ): array {
		global $wpdb;

		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );

		$clauses = array();
		$params  = array();

		$status = (string) ( $args['status'] ?? '' );
		if ( in_array( $status, self::STATUSES, true ) ) {
			$clauses[] = 'status = %s';
			$params[]  = $status;
		}

		$search = trim( (string) ( $args['search'] ?? '' ) );
		if ( '' !== $search ) {
			$like      = '%' . $wpdb->esc_like( $search ) . '%';
			$clauses[] = '(to_email LIKE %s OR subject LIKE %s)';
			$params[]  = $like;
			$params[]  = $like;
		}

		if ( ! empty( $args['after'] ) ) {
			$clauses[] = 'created_at >= %s';
			$params[]  = (string) $args['after'];
		}

		if ( ! empty( $args['before'] ) ) {
			$clauses[] = 'created_at <= %s';
			$params[]  = (string) $args['before'];
		}

		$where = $clauses ? 'WHERE ' . implode( ' AND ', $clauses ) : '';

		return array( $where, $params, $page, $per_page );
	}

	/**
	 * Delete rows by id.
	 *
	 * @since  0.2.0
	 * @param  int[] $ids Row ids.
	 * @return int Rows deleted.
	 */
	public static function delete( array $ids ): int {
		global $wpdb;

		$ids = array_filter( array_map( 'absint', $ids ) );
		if ( ! $ids ) {
			return 0;
		}

		$table        = self::table();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- custom table, placeholders built above.
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", $ids ) );
	}

	/**
	 * Delete every row.
	 *
	 * @since  0.2.0
	 * @return void
	 */
	public static function truncate(): void {
		global $wpdb;
		$table = self::table();
		// DELETE rather than TRUNCATE: works on every host, including the SQLite drop-in.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table.
		$wpdb->query( "DELETE FROM {$table}" );
	}

	/**
	 * Delete rows older than a number of days.
	 *
	 * @since  0.2.0
	 * @param  int $days Days to keep. 0 keeps everything.
	 * @return int Rows deleted.
	 */
	public static function purge_older_than( int $days ): int {
		global $wpdb;

		if ( $days <= 0 ) {
			return 0;
		}

		$table  = self::table();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table.
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) );
	}

	/**
	 * Cron callback — purge rows older than the configured retention.
	 *
	 * @since  0.2.0
	 * @return void
	 */
	public static function purge(): void {
		self::purge_older_than( (int) Settings::plugin_settings()['log_retention_days'] );
	}

	/**
	 * Schedule the daily purge event if it is missing.
	 *
	 * @since  0.2.0
	 * @return void
	 */
	public static function schedule_purge(): void {
		if ( ! wp_next_scheduled( self::PURGE_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::PURGE_HOOK );
		}
	}

	/**
	 * Remove the scheduled purge event.
	 *
	 * @since  0.2.0
	 * @return void
	 */
	public static function unschedule_purge(): void {
		wp_clear_scheduled_hook( self::PURGE_HOOK );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Keep only known columns.
	 *
	 * @param  array<string,mixed> $data Column values.
	 * @return array<string,mixed>
	 */
	private static function only_columns( array $data ): array {
		return array_intersect_key( $data, array_flip( self::COLUMNS ) );
	}

	/**
	 * Cast a database row for API output.
	 *
	 * @param  array<string,mixed> $row Raw row.
	 * @return array<string,mixed>
	 */
	private static function format( array $row ): array {
		$row['id']          = (int) $row['id'];
		$row['created_at']  = mysql_to_rfc3339( (string) $row['created_at'] );
		$attachments        = json_decode( (string) ( $row['attachments'] ?? '' ), true );
		$row['attachments'] = is_array( $attachments ) ? $attachments : array();
		return $row;
	}
}
