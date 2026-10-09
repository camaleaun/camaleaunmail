<?php
/**
 * MailLogger — records every wp_mail() call and blocks sending when disabled.
 *
 * @package Camaleaunmail
 * @since   0.2.0
 */

namespace Camaleaunmail;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks into wp_mail() to write log rows.
 *
 * Flow inside wp_mail():
 *  pre_wp_mail        → insert the row (pending), or block/short-circuit it
 *  phpmailer_init     → fill in the final From, content type and transport
 *  wp_mail_succeeded  → sent
 *  wp_mail_failed     → failed + error
 *
 * @since 0.2.0
 */
class MailLogger {

	/**
	 * Ids of the rows for the wp_mail() calls in progress (nested calls push more).
	 *
	 * @var int[]
	 */
	private static $stack = array();

	/**
	 * Register hooks.
	 *
	 * @since  0.2.0
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'pre_wp_mail', array( static::class, 'pre_wp_mail' ), PHP_INT_MAX, 2 );
		add_action( 'phpmailer_init', array( static::class, 'phpmailer_init' ), PHP_INT_MAX );
		add_action( 'wp_mail_succeeded', array( static::class, 'succeeded' ) );
		add_action( 'wp_mail_failed', array( static::class, 'failed' ) );
	}

	/**
	 * Filter pre_wp_mail — create the row and block the email when sending is disabled.
	 *
	 * @since  0.2.0
	 * @param  null|bool           $pre  Short-circuit value from earlier filters.
	 * @param  array<string,mixed> $atts wp_mail() arguments after the wp_mail filter.
	 * @return null|bool
	 */
	public static function pre_wp_mail( $pre, $atts ) {
		$atts     = is_array( $atts ) ? $atts : array();
		$blocked  = null === $pre && Settings::sending_disabled();
		$logging  = Settings::logging_enabled();
		$finished = null !== $pre || $blocked;

		if ( $logging ) {
			$row = self::row_from_atts( $atts );

			if ( null !== $pre ) {
				$row['status'] = 'short_circuited';
			} elseif ( $blocked ) {
				$row['status']    = 'blocked';
				$row['transport'] = 'disabled';
				$row              = array_merge( $row, self::blocked_from( $row ) );
			} else {
				$row['transport'] = (string) Settings::get()['transport'];
			}

			$id = Logs::insert( $row );

			// Only track the row when wp_mail() goes on to send it.
			if ( ! $finished ) {
				self::$stack[] = $id;
			}
		}

		return $blocked ? true : $pre;
	}

	/**
	 * Action phpmailer_init — record what PHPMailer will actually send.
	 *
	 * @since  0.2.0
	 * @param  \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 * @return void
	 */
	public static function phpmailer_init( $phpmailer ): void {
		$id = self::current();
		if ( ! $id ) {
			return;
		}

		Logs::update(
			$id,
			array(
				'from_email'   => (string) $phpmailer->From,
				'from_name'    => (string) $phpmailer->FromName,
				'content_type' => (string) $phpmailer->ContentType,
				'transport'    => (string) $phpmailer->Mailer,
			)
		);
	}

	/**
	 * Action wp_mail_succeeded.
	 *
	 * @since  0.2.0
	 * @return void
	 */
	public static function succeeded(): void {
		$id = array_pop( self::$stack );
		if ( $id ) {
			Logs::update( $id, array( 'status' => 'sent' ) );
		}
	}

	/**
	 * Action wp_mail_failed.
	 *
	 * @since  0.2.0
	 * @param  \WP_Error $error Error from wp_mail().
	 * @return void
	 */
	public static function failed( \WP_Error $error ): void {
		$id = array_pop( self::$stack );
		if ( $id ) {
			Logs::update(
				$id,
				array(
					'status' => 'failed',
					'error'  => $error->get_error_message(),
				)
			);
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Id of the row for the wp_mail() call in progress.
	 *
	 * @return int
	 */
	private static function current(): int {
		return self::$stack ? (int) end( self::$stack ) : 0;
	}

	/**
	 * Build log columns from wp_mail() arguments.
	 *
	 * @since  0.2.0
	 * @param  array<string,mixed> $atts wp_mail() arguments.
	 * @return array<string,mixed>
	 */
	public static function row_from_atts( array $atts ): array {
		$headers = self::header_lines( $atts['headers'] ?? array() );
		$cc      = array();
		$bcc     = array();
		$type    = '';
		$from    = '';

		foreach ( $headers as $line ) {
			if ( false === strpos( $line, ':' ) ) {
				continue;
			}
			list( $name, $value ) = array_map( 'trim', explode( ':', $line, 2 ) );
			switch ( strtolower( $name ) ) {
				case 'cc':
					$cc[] = $value;
					break;
				case 'bcc':
					$bcc[] = $value;
					break;
				case 'from':
					$from = $value;
					break;
				case 'content-type':
					$type = trim( explode( ';', $value )[0] );
					break;
			}
		}

		$from_name  = '';
		$from_email = $from;
		if ( preg_match( '/^(.*)<(.+)>\s*$/', $from, $m ) ) {
			$from_name  = trim( $m[1], " \"'" );
			$from_email = trim( $m[2] );
		}

		return array(
			'from_email'   => $from_email,
			'from_name'    => $from_name,
			'to_email'     => implode( ', ', self::to_list( $atts['to'] ?? '' ) ),
			'cc'           => implode( ', ', $cc ),
			'bcc'          => implode( ', ', $bcc ),
			'subject'      => (string) ( $atts['subject'] ?? '' ),
			'headers'      => implode( "\n", $headers ),
			'message'      => (string) ( $atts['message'] ?? '' ),
			'content_type' => $type,
			'attachments'  => (string) wp_json_encode( self::attachment_names( $atts['attachments'] ?? array() ) ),
		);
	}

	/**
	 * From address and name for a blocked email, which never reaches PHPMailer.
	 *
	 * Mirrors wp_mail(): the From header or the default, then the wp_mail_from filters.
	 *
	 * @param  array<string,mixed> $row Row built by row_from_atts().
	 * @return array<string,string>
	 */
	private static function blocked_from( array $row ): array {
		$email = (string) $row['from_email'];
		$name  = (string) $row['from_name'];

		if ( '' === $email ) {
			$host = strtolower( (string) wp_parse_url( network_home_url(), PHP_URL_HOST ) );
			if ( 0 === strpos( $host, 'www.' ) ) {
				$host = substr( $host, 4 );
			}
			$email = 'wordpress@' . $host;
		}
		if ( '' === $name ) {
			$name = 'WordPress';
		}

		/** This filter is documented in wp-includes/pluggable.php */
		$email = (string) apply_filters( 'wp_mail_from', $email );
		/** This filter is documented in wp-includes/pluggable.php */
		$name = (string) apply_filters( 'wp_mail_from_name', $name );

		return array(
			'from_email' => $email,
			'from_name'  => $name,
		);
	}

	/**
	 * Normalise headers (string or array) to a list of lines.
	 *
	 * @param  mixed $headers Headers as passed to wp_mail().
	 * @return string[]
	 */
	private static function header_lines( $headers ): array {
		if ( ! is_array( $headers ) ) {
			$headers = explode( "\n", str_replace( "\r\n", "\n", (string) $headers ) );
		}
		return array_values( array_filter( array_map( 'trim', array_map( 'strval', $headers ) ) ) );
	}

	/**
	 * Normalise the "to" argument to a list of addresses.
	 *
	 * @param  mixed $to Recipients as passed to wp_mail().
	 * @return string[]
	 */
	private static function to_list( $to ): array {
		if ( ! is_array( $to ) ) {
			$to = explode( ',', (string) $to );
		}
		return array_values( array_filter( array_map( 'trim', array_map( 'strval', $to ) ) ) );
	}

	/**
	 * File names of the attachments (paths are not stored).
	 *
	 * @param  mixed $attachments Attachments as passed to wp_mail().
	 * @return string[]
	 */
	private static function attachment_names( $attachments ): array {
		if ( ! is_array( $attachments ) ) {
			$attachments = explode( "\n", str_replace( "\r\n", "\n", (string) $attachments ) );
		}

		$names = array();
		foreach ( $attachments as $key => $path ) {
			$path = trim( (string) $path );
			if ( '' === $path ) {
				continue;
			}
			// Since WP 6.2 a string key is the file name shown to the recipient.
			$names[] = is_string( $key ) ? $key : wp_basename( $path );
		}
		return $names;
	}
}
