<?php
/**
 * Settings — stores and retrieves SMTP/Google Mail configuration.
 *
 * @package Camaleaunmail
 * @since   0.1.0
 */

namespace Camaleaunmail;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the mail-transport settings stored in wp_options.
 *
 * Option key: camaleaunmail_settings
 *
 * @since 0.1.0
 */
class Settings {

	/**
	 * WordPress option name.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'camaleaunmail_settings';

	/**
	 * Return the full settings array with defaults applied.
	 *
	 * @since  0.1.0
	 * @return array<string,mixed>
	 */
	public static function get(): array {
		$saved = get_option( self::OPTION_KEY, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Persist a (partial or full) settings array.
	 *
	 * @since  0.1.0
	 * @param  array<string,mixed> $data Key/value pairs to save.
	 * @return bool                True on success, false on failure.
	 */
	public static function save( array $data ): bool {
		$current   = self::get();
		$merged    = array_merge( $current, $data );
		$sanitized = self::sanitize( $merged );
		return update_option( self::OPTION_KEY, $sanitized );
	}

	/**
	 * Returns true when at least one setting differs from its default and is non-empty.
	 *
	 * @since  0.1.0
	 * @return bool
	 */
	public static function has_custom_settings(): bool {
		$defaults = self::defaults();
		$current  = self::get();
		foreach ( $current as $key => $value ) {
			if ( '' === $value || null === $value ) {
				continue;
			}
			if ( ! array_key_exists( $key, $defaults ) || $value !== $defaults[ $key ] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Export current settings as a YAML string with an embedded JSON Schema comment.
	 *
	 * @since  0.1.0
	 * @param  bool $include_schema Whether to include the yaml-language-server schema comment.
	 * @return string YAML content ready to write to a file.
	 */
	public static function to_yaml( bool $include_schema = true ): string {
		if ( ! class_exists( 'Spyc' ) ) {
			$path = __DIR__ . '/class-spyc.php';
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}

		$comment = '';
		if ( $include_schema ) {
			$schema_url = plugin_dir_url( dirname( __DIR__ ) . '/camaleaunmail.php' ) . 'schema/settings.json';
			$comment    = '# yaml-language-server: $schema=' . $schema_url . "\n";
		}

		$defaults = self::defaults();
		$current  = self::get();

		// Only export keys that differ from defaults and are non-empty.
		$export = array_filter(
			$current,
			function ( $value, $key ) use ( $defaults ) {
				if ( '' === $value || null === $value ) {
					return false;
				}
				return ! array_key_exists( $key, $defaults ) || $value !== $defaults[ $key ];
			},
			ARRAY_FILTER_USE_BOTH
		);

		$yaml = \Spyc::YAMLDump( $export, 2, 0, true );

		return $comment . $yaml;
	}



	/**
	 * Export current settings as a JSON string.
	 *
	 * Includes a `$schema` key pointing to the bundled JSON Schema.
	 * Only keys that differ from defaults and are non-empty are exported.
	 *
	 * @since  0.1.0
	 * @param  bool   $pretty         Whether to pretty-print the JSON output.
	 * @param  string $indent_type    Indentation character: 'tab' or 'space'.
	 * @param  int    $indent_size    Spaces per level when $indent_type is 'space' (2, 4 or 8).
	 * @param  bool   $include_schema Whether to include a $schema key in the output.
	 * @return string JSON content ready to write to a file.
	 */
	public static function to_json( bool $pretty = true, string $indent_type = 'tab', int $indent_size = 4, bool $include_schema = true ): string {
		$defaults = self::defaults();
		$current  = self::get();

		// Only export keys that differ from defaults and are non-empty.
		$export = array_filter(
			$current,
			function ( $value, $key ) use ( $defaults ) {
				if ( '' === $value || null === $value ) {
					return false;
				}
				return ! array_key_exists( $key, $defaults ) || $value !== $defaults[ $key ];
			},
			ARRAY_FILTER_USE_BOTH
		);

		if ( $include_schema ) {
			$schema_url = plugin_dir_url( dirname( __DIR__ ) . '/camaleaunmail.php' ) . 'schema/settings.json';
			$payload    = array_merge( array( '$schema' => $schema_url ), $export );
		} else {
			$payload = $export;
		}

		$flags  = JSON_UNESCAPED_SLASHES | ( $pretty ? JSON_PRETTY_PRINT : 0 );
		$output = (string) wp_json_encode( $payload, $flags );

		// PHP's JSON_PRETTY_PRINT always uses 4 spaces; replace with the requested indentation.
		if ( $pretty ) {
			$pad    = 'tab' === $indent_type ? "\t" : str_repeat( ' ', $indent_size );
			$output = preg_replace_callback(
				'/^( {4})+/m',
				function ( $m ) use ( $pad ) {
					return str_repeat( $pad, (int) ( strlen( $m[0] ) / 4 ) );
				},
				$output
			) ?? $output;
		}

		return $output . "\n";
	}

	/**
	 * Default values for every setting key.
	 *
	 * @since  0.1.0
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'transport'        => 'default',
			'smtp_host'        => '',
			'smtp_port'        => 25,
			'smtp_encryption'  => 'none',
			'smtp_auth'        => false,
			'smtp_username'    => '',
			'smtp_password'    => '',
			'from_email'       => '',
			'from_name'        => '',
			'sending_disabled' => false,
		);
	}

	/**
	 * Option key for plugin-level settings.
	 *
	 * @var string
	 */
	const PLUGIN_OPTION_KEY = 'camaleaunmail_plugin_settings';

	/**
	 * Allowed values for log_retention_days. 0 keeps logs forever.
	 *
	 * @var int[]
	 */
	const LOG_RETENTION_DAYS = array( 0, 7, 14, 30, 60, 90, 365 );

	/**
	 * Default values for plugin-level settings.
	 *
	 * @since  0.2.0
	 * @return array<string,mixed>
	 */
	public static function plugin_defaults(): array {
		return array(
			'clear_on_deactivate' => false,
			'export_format'       => 'yaml',
			'include_schema'      => true,
			'json_pretty_print'   => true,
			'json_indent_type'    => 'tab',
			'json_indent'         => 4,
			'logging_enabled'     => true,
			'log_retention_days'  => 30,
		);
	}

	/**
	 * Return plugin-level settings with defaults applied.
	 *
	 * @since  0.2.0
	 * @return array<string,mixed>
	 */
	public static function plugin_settings(): array {
		$saved = get_option( self::PLUGIN_OPTION_KEY, array() );
		return array_merge( self::plugin_defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Whether outgoing mail is blocked (recorded in the log only).
	 *
	 * The CAMALEAUNMAIL_DISABLE_SENDING constant forces it on, e.g. for staging.
	 *
	 * @since  0.2.0
	 * @return bool
	 */
	public static function sending_disabled(): bool {
		return self::sending_disabled_by_constant() || ! empty( self::get()['sending_disabled'] );
	}

	/**
	 * Whether the CAMALEAUNMAIL_DISABLE_SENDING constant forces sending off.
	 *
	 * @since  0.2.0
	 * @return bool
	 */
	public static function sending_disabled_by_constant(): bool {
		return defined( 'CAMALEAUNMAIL_DISABLE_SENDING' ) && CAMALEAUNMAIL_DISABLE_SENDING;
	}

	/**
	 * Whether emails are recorded in the log.
	 *
	 * @since  0.2.0
	 * @return bool
	 */
	public static function logging_enabled(): bool {
		return ! empty( self::plugin_settings()['logging_enabled'] );
	}

	/**
	 * Sanitize a settings array before persisting.
	 *
	 * @since  0.1.0
	 * @param  array<string,mixed> $data Raw data.
	 * @return array<string,mixed>       Sanitized data.
	 */
	private static function sanitize( array $data ): array {
		$clean = array();

		$transport  = in_array( $data['transport'] ?? '', array( 'smtp', 'default' ), true )
			? $data['transport']
			: 'default';
		$encryption = in_array( $data['smtp_encryption'] ?? '', array( 'tls', 'ssl', 'none' ), true )
			? $data['smtp_encryption']
			: 'none';

		$clean['transport']       = $transport;
		$clean['smtp_host']       = sanitize_text_field( $data['smtp_host'] ?? '' );
		$clean['smtp_port']       = absint( $data['smtp_port'] ?? 25 );
		$clean['smtp_encryption'] = $encryption;
		$clean['smtp_auth']       = (bool) ( $data['smtp_auth'] ?? false );
		$clean['smtp_username']   = sanitize_text_field( $data['smtp_username'] ?? '' );
		// Password is stored as-is (encryption is out of scope for v1).
		$clean['smtp_password'] = $data['smtp_password'] ?? '';
		$clean['from_email']    = sanitize_email( $data['from_email'] ?? '' );
		$clean['from_name']     = sanitize_text_field( $data['from_name'] ?? '' );

		$clean['sending_disabled'] = (bool) ( $data['sending_disabled'] ?? false );

		return $clean;
	}
}
