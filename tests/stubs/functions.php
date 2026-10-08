<?php
/**
 * Minimal WordPress function stubs for PHPUnit unit tests.
 *
 * PHP 7.4+ compatible — no union types, no mixed, no intersection types.
 * Brain\Monkey intercepts these at test time so they can be mocked.
 */

declare( strict_types=1 );

// Escaping & sanitization.
function esc_html( string $text ): string { return $text; }
function esc_attr( string $text ): string { return $text; }
function esc_url( string $url ): string { return $url; }
function esc_url_raw( string $url ): string { return $url; }
function sanitize_text_field( string $str ): string { return $str; }
function sanitize_key( string $key ): string { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $key ) ); }
function wp_kses_post( string $data ): string { return $data; }
function absint( $maybeint ): int { return abs( (int) $maybeint ); }
function sanitize_email( string $email ): string { return $email; }

// i18n.
function __( string $text, string $domain = 'default' ): string { return $text; }
function _e( string $text, string $domain = 'default' ): void { echo $text; }
function esc_html__( string $text, string $domain = 'default' ): string { return $text; }
function esc_attr__( string $text, string $domain = 'default' ): string { return $text; }
function esc_html_e( string $text, string $domain = 'default' ): void { echo $text; }
// Hooks.
function add_action( string $tag, callable $cb, int $priority = 10, int $args = 1 ): bool { return true; }
function add_filter( string $tag, callable $cb, int $priority = 10, int $args = 1 ): bool { return true; }
function do_action( string $tag, ...$args ): void {}
function apply_filters( string $tag, $value, ...$args ) { return $value; }
function remove_action( string $tag, callable $cb, int $priority = 10 ): bool { return true; }
function remove_filter( string $tag, callable $cb, int $priority = 10 ): bool { return true; }
function register_activation_hook( string $file, callable $cb ): void {}
function register_deactivation_hook( string $file, callable $cb ): void {}

// Options.
function get_option( string $option, $default = false ) { return $default; }
function update_option( string $option, $value, $autoload = true ): bool { return true; }
function delete_option( string $option ): bool { return true; }

// Plugin helpers.
function plugin_dir_path( string $file ): string { return trailingslashit( dirname( $file ) ); }
function plugin_dir_url( string $file ): string { return 'http://example.com/wp-content/plugins/' . basename( dirname( $file ) ) . '/'; }
function plugins_url( string $path = '', string $plugin = '' ): string { return 'http://example.com/wp-content/plugins/' . $path; }
function trailingslashit( string $string ): string { return rtrim( $string, '/\\' ) . '/'; }
function get_bloginfo( string $show = '', string $filter = 'raw' ): string { return 'Test Site'; }
function admin_url( string $path = '', string $scheme = 'admin' ): string { return 'http://example.com/wp-admin/' . $path; }
function get_option_default( string $option, $default = false ) { return $default; }

// Misc.
function is_admin(): bool { return false; }
function is_user_logged_in(): bool { return false; }
function current_user_can( string $capability, ...$args ): bool { return false; }
function wp_die( $message = '', $title = '', array $args = array() ): void {
	throw new \RuntimeException( is_string( $message ) ? $message : 'wp_die' );
}
function wp_verify_nonce( string $nonce, $action = -1 ) { return 1; }
function wp_create_nonce( $action = -1 ): string { return 'test-nonce'; }
function wp_nonce_field( $action = -1, string $name = '_wpnonce', bool $referer = true, bool $echo = true ): string { return ''; }
function check_ajax_referer( $action = -1, $query_arg = false, bool $die = true ) { return 1; }
function wp_send_json_success( $data = null, int $status_code = 200, int $flags = 0 ): void {}
function wp_send_json_error( $data = null, int $status_code = 200, int $flags = 0 ): void {}
function wp_mail( $to, string $subject, string $message, $headers = '', $attachments = array() ): bool { return true; }
function wp_remote_post( string $url, array $args = array() ) { return array(); }
function wp_remote_get( string $url, array $args = array() ) { return array(); }
function wp_remote_retrieve_body( $response ): string { return ''; }
function is_wp_error( $thing ): bool { return $thing instanceof \WP_Error; }
function wp_safe_redirect( string $location, int $status = 302, string $x_redirect_by = 'WordPress' ): bool { return true; }
function add_query_arg( ...$args ): string { return ''; }
function remove_query_arg( $key, string $query = '' ): string { return ''; }
function wp_unslash( $value ) { return $value; }
function wp_localize_script( string $handle, string $object_name, array $l10n ): bool { return true; }
function wp_enqueue_script( string $handle, string $src = '', array $deps = array(), $ver = false, $args = false ): void {}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		/** @var string */
		public $code;
		/** @var string */
		public $message;
		public function __construct( string $code = '', string $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}
		public function get_error_message(): string { return $this->message; }
	}
}

// Added for the email log (0.2.0).
function wp_json_encode( $data, int $options = 0, int $depth = 512 ) { return json_encode( $data, $options, $depth ); }
function wp_basename( string $path, string $suffix = '' ): string { return basename( $path, $suffix ); }
function wp_parse_url( string $url, int $component = -1 ) { return parse_url( $url, $component ); }
function network_home_url( string $path = '', $scheme = null ): string { return 'http://www.example.com' . $path; }
function mysql_to_rfc3339( string $date_string ): string { return str_replace( ' ', 'T', $date_string ); }
