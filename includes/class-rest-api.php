<?php
/**
 * REST API — exposes settings and test-send endpoint.
 *
 * @package Camaleaunmail
 * @since   0.1.0
 */

namespace Camaleaunmail;

defined( 'ABSPATH' ) || exit;

/**
 * Registers /wp-json/camaleaunmail/v1/ routes.
 *
 * Routes:
 *  GET  /settings        → return current settings (passwords redacted)
 *  POST /settings        → save settings
 *  POST /test-send       → send a test email
 *
 * @since 0.1.0
 */
class RestApi {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'camaleaunmail/v1';

	/**
	 * Register REST routes.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( static::class, 'register_routes' ) );
	}

	/**
	 * Register all routes.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( static::class, 'get_settings' ),
					'permission_callback' => array( static::class, 'check_permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( static::class, 'save_settings' ),
					'permission_callback' => array( static::class, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/test-send',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( static::class, 'test_send' ),
				'permission_callback' => array( static::class, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/plugin-settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( static::class, 'get_plugin_settings' ),
					'permission_callback' => array( static::class, 'check_permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( static::class, 'save_plugin_settings' ),
					'permission_callback' => array( static::class, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/export',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( static::class, 'export_settings' ),
				'permission_callback' => array( static::class, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/import',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( static::class, 'import_settings' ),
				'permission_callback' => array( static::class, 'check_permission' ),
			)
		);
	}

	/**
	 * Permission check — manage_options capability required.
	 *
	 * @since  0.1.0
	 * @return bool|\WP_Error
	 */
	public static function check_permission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage mail settings.', 'camaleaunmail' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * GET /settings
	 *
	 * Returns current settings with sensitive values redacted for display.
	 *
	 * @since  0.1.0
	 * @return \WP_REST_Response
	 */
	public static function get_settings(): \WP_REST_Response {
		$s = Settings::get();
		// Redact secrets so they are not exposed in the response.
		$redacted = self::redact( $s );
		// Tell the UI whether there is anything non-default to export.
		$redacted['_has_custom_settings'] = Settings::has_custom_settings();
		return rest_ensure_response( $redacted );
	}

	/**
	 * POST /settings
	 *
	 * Accepts a JSON body with any subset of setting keys.
	 * Placeholder values (REDACTED) are stripped so stored values are preserved.
	 *
	 * @since  0.1.0
	 * @param  \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function save_settings( \WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return new \WP_Error( 'invalid_body', __( 'Invalid request body.', 'camaleaunmail' ), array( 'status' => 400 ) );
		}

		// Remove placeholder value sent back by the UI.
		if ( isset( $body['smtp_password'] ) && self::is_placeholder( $body['smtp_password'] ) ) {
			unset( $body['smtp_password'] );
		}

		Settings::save( $body );
		return rest_ensure_response(
			array(
				'saved'                => true,
				'_has_custom_settings' => Settings::has_custom_settings(),
			)
		);
	}

	/**
	 * POST /test-send
	 *
	 * Sends a test email to the current admin email (or a provided address).
	 *
	 * @since  0.1.0
	 * @param  \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function test_send( \WP_REST_Request $request ) {
		$body = $request->get_json_params();
		$to   = sanitize_email( $body['to'] ?? get_option( 'admin_email' ) );

		if ( ! is_email( $to ) ) {
			return new \WP_Error( 'invalid_email', __( 'Invalid recipient email address.', 'camaleaunmail' ), array( 'status' => 400 ) );
		}

		// Set a hard connection timeout so test-send never hangs the browser.
		$timeout_cb = function ( $phpmailer ) {
			$phpmailer->Timeout = 15;
		};
		add_action( 'phpmailer_init', $timeout_cb, 99 );

		$subject = sanitize_text_field( $body['subject'] ?? __( 'Crimson Agility Mail — test email', 'camaleaunmail' ) );
		$mode    = ( 'html' === ( $body['mode'] ?? '' ) ) ? 'html' : 'plain';
		$message = wp_kses_post( $body['body'] ?? __( 'This is a test email sent from the Crimson Agility Mail plugin.', 'camaleaunmail' ) );

		$headers = array();
		if ( 'html' === $mode ) {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
		}

		$sent = wp_mail( $to, $subject, $message, $headers );

		remove_action( 'phpmailer_init', $timeout_cb, 99 );

		if ( ! $sent ) {
			// phpmailer_init populates the global; grab last error.
			global $phpmailer;
			$error_info = '';
			if ( ! isset( $phpmailer ) ) {
				require_once ABSPATH . WPINC . '/class-phpmailer.php';
			}
			if ( isset( $phpmailer ) && ! empty( $phpmailer->ErrorInfo ) ) {
				$error_info = $phpmailer->ErrorInfo;
			}
			return new \WP_Error(
				'send_failed',
				/* translators: %s: PHPMailer error message */
				sprintf( __( 'Test email could not be sent. %s', 'camaleaunmail' ), $error_info ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'sent' => true,
				'to'   => $to,
			)
		);
	}

	/**
	 * Option key for plugin-level settings.
	 *
	 * @var string
	 */
	const PLUGIN_OPTION_KEY = 'camaleaunmail_plugin_settings';

	/**
	 * Return plugin-level settings.
	 *
	 * GET /plugin-settings
	 *
	 * @since  0.1.0
	 * @return \WP_REST_Response
	 */
	public static function get_plugin_settings(): \WP_REST_Response {
		$defaults = array(
			'clear_on_deactivate' => false,
			'export_format'       => 'yaml',
			'include_schema'      => true,
			'json_pretty_print'   => true,
			'json_indent_type'    => 'tab',
			'json_indent'         => 4,
		);
		$saved    = get_option( self::PLUGIN_OPTION_KEY, array() );
		return rest_ensure_response( array_merge( $defaults, is_array( $saved ) ? $saved : array() ) );
	}

	/**
	 * POST /plugin-settings
	 *
	 * @since  0.1.0
	 * @param  \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function save_plugin_settings( \WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return new \WP_Error( 'invalid_body', __( 'Invalid request body.', 'camaleaunmail' ), array( 'status' => 400 ) );
		}
		$clean = array(
			'clear_on_deactivate' => ! empty( $body['clear_on_deactivate'] ),
			'export_format'       => in_array( $body['export_format'] ?? '', array( 'yaml', 'json' ), true )
				? $body['export_format']
				: 'yaml',
			'include_schema'      => isset( $body['include_schema'] ) ? (bool) $body['include_schema'] : true,
			'json_pretty_print'   => isset( $body['json_pretty_print'] ) ? (bool) $body['json_pretty_print'] : true,
			'json_indent_type'    => in_array( $body['json_indent_type'] ?? '', array( 'tab', 'space' ), true )
				? $body['json_indent_type']
				: 'tab',
			'json_indent'         => in_array( (int) ( $body['json_indent'] ?? 4 ), array( 2, 4, 8 ), true )
				? (int) $body['json_indent']
				: 4,
		);
		update_option( self::PLUGIN_OPTION_KEY, $clean );
		return rest_ensure_response( array( 'saved' => true ) );
	}

	/**
	 * GET /export
	 *
	 * Returns current settings as a YAML or JSON file download.
	 * Accepts an optional `format` query parameter: `yaml` (default) or `json`.
	 *
	 * @since  0.1.0
	 * @param  \WP_REST_Request $request Incoming request.
	 * @return void  (sends file and exits)
	 */
	public static function export_settings( \WP_REST_Request $request ): void {
		$format = in_array( $request->get_param( 'format' ), array( 'json', 'yaml' ), true )
			? $request->get_param( 'format' )
			: 'yaml';

		$plugin_settings = get_option( self::PLUGIN_OPTION_KEY, array() );
		$schema          = isset( $plugin_settings['include_schema'] ) ? (bool) $plugin_settings['include_schema'] : true;

		if ( 'json' === $format ) {
			$pretty      = isset( $plugin_settings['json_pretty_print'] ) ? (bool) $plugin_settings['json_pretty_print'] : true;
			$indent_type = in_array( $plugin_settings['json_indent_type'] ?? '', array( 'tab', 'space' ), true )
				? $plugin_settings['json_indent_type']
				: 'tab';
			$indent_size = in_array( (int) ( $plugin_settings['json_indent'] ?? 4 ), array( 2, 4, 8 ), true )
				? (int) $plugin_settings['json_indent']
				: 4;
			$output      = Settings::to_json( $pretty, $indent_type, $indent_size, $schema );
			$filename    = 'camaleaunmail-settings.json';
			header( 'Content-Type: application/json; charset=utf-8' );
		} else {
			$output   = Settings::to_yaml( $schema );
			$filename = 'camaleaunmail-settings.yml';
			header( 'Content-Type: application/x-yaml; charset=utf-8' );
		}

		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Cache-Control: no-cache, no-store' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- file download, not HTML output.
		echo $output;
		exit;
	}

	/**
	 * POST /import
	 *
	 * Accepts either a YAML string in `yaml` or a JSON string in `json`.
	 * The `$schema` key is stripped before saving.
	 *
	 * @since  0.1.0
	 * @param  \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function import_settings( \WP_REST_Request $request ) {
		$body = $request->get_json_params();

		if ( ! empty( $body['json'] ) ) {
			// JSON import.
			$data = json_decode( $body['json'], true );
			if ( ! is_array( $data ) ) {
				return new \WP_Error( 'invalid_json', __( 'Could not parse JSON.', 'camaleaunmail' ), array( 'status' => 400 ) );
			}
			// Strip the $schema meta-key before saving.
			unset( $data['$schema'] );
		} elseif ( ! empty( $body['yaml'] ) ) {
			// YAML import.
			self::require_spyc();
			$data = \Spyc::YAMLLoadString( $body['yaml'] );
			if ( ! is_array( $data ) ) {
				return new \WP_Error( 'invalid_yaml', __( 'Could not parse YAML.', 'camaleaunmail' ), array( 'status' => 400 ) );
			}
		} else {
			return new \WP_Error( 'missing_content', __( 'No YAML or JSON content provided.', 'camaleaunmail' ), array( 'status' => 400 ) );
		}

		// Remove placeholder value that may have been round-tripped.
		if ( isset( $data['smtp_password'] ) && self::is_placeholder( $data['smtp_password'] ) ) {
			unset( $data['smtp_password'] );
		}

		Settings::save( $data );
		return rest_ensure_response( array( 'imported' => true ) );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Redact sensitive fields before returning them to the UI.
	 *
	 * @param  array<string,mixed> $s Settings array.
	 * @return array<string,mixed>
	 */
	private static function redact( array $s ): array {
		if ( ! empty( $s['smtp_password'] ) ) {
			$s['smtp_password'] = '__REDACTED__';
		}
		return $s;
	}

	/**
	 * Returns true if a value is a placeholder sent by the UI.
	 *
	 * @param  mixed $value Incoming value.
	 * @return bool
	 */
	private static function is_placeholder( $value ): bool {
		return '__REDACTED__' === $value;
	}

	/**
	 * Load the bundled Spyc class if not already loaded.
	 *
	 * @return void
	 */
	private static function require_spyc(): void {
		if ( class_exists( 'Spyc' ) ) {
			return;
		}
		$path = CAMALEAUNMAIL_PATH . 'includes/class-spyc.php'; // Generated by bin/copy-spyc.php.
		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
}
