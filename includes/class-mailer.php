<?php
/**
 * Mailer — hooks into phpmailer_init to configure the transport.
 *
 * @package Camaleaunmail
 * @since   0.1.0
 */

namespace Camaleaunmail;

defined( 'ABSPATH' ) || exit;

/**
 * Configures PHPMailer based on the saved settings.
 *
 * @since 0.1.0
 */
class Mailer {

	/**
	 * Register WordPress hooks.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public static function init(): void {
		add_action( 'phpmailer_init', array( static::class, 'configure' ) );
		add_filter( 'wp_mail_from', array( static::class, 'from_email' ) );
		add_filter( 'wp_mail_from_name', array( static::class, 'from_name' ) );
	}

	/**
	 * Configure PHPMailer with the saved transport settings.
	 *
	 * @since  0.1.0
	 * @param  \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 * @return void
	 */
	public static function configure( $phpmailer ): void {
		$s = Settings::get();

		if ( 'smtp' === $s['transport'] ) {
			self::configure_smtp( $phpmailer, $s );
		}
		// 'default' → no changes; WordPress uses PHP mail().
	}

	/**
	 * Filter wp_mail_from.
	 *
	 * @since  0.1.0
	 * @param  string $email Default from email.
	 * @return string
	 */
	public static function from_email( string $email ): string {
		$s = Settings::get();
		return ! empty( $s['from_email'] ) ? $s['from_email'] : $email;
	}

	/**
	 * Filter wp_mail_from_name.
	 *
	 * @since  0.1.0
	 * @param  string $name Default from name.
	 * @return string
	 */
	public static function from_name( string $name ): string {
		$s = Settings::get();
		return ! empty( $s['from_name'] ) ? $s['from_name'] : $name;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Configure PHPMailer for plain SMTP.
	 *
	 * @since  0.1.0
	 * @param  \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 * @param  array<string,mixed>            $s         Settings array.
	 * @return void
	 */
	private static function configure_smtp( $phpmailer, array $s ): void {
		$phpmailer->isSMTP();
		$phpmailer->Host     = $s['smtp_host'];
		$phpmailer->Port     = (int) $s['smtp_port'];
		$phpmailer->SMTPAuth = (bool) $s['smtp_auth'];
		$phpmailer->Username = $s['smtp_username'];
		$phpmailer->Password = $s['smtp_password'];

		// Port 465 always requires SSL (SMTPS); STARTTLS/TLS is for 587/25.
		$enc = $s['smtp_encryption'];
		if ( 465 === (int) $s['smtp_port'] && 'ssl' !== $enc ) {
			$enc = 'ssl';
		}

		if ( 'ssl' === $enc ) {
			$phpmailer->SMTPSecure = 'ssl';
		} elseif ( 'tls' === $enc ) {
			$phpmailer->SMTPSecure = 'tls';
		} else {
			$phpmailer->SMTPSecure  = '';
			$phpmailer->SMTPAutoTLS = false;
		}
	}
}
