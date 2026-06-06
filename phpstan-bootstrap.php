<?php
/**
 * PHPStan bootstrap — defines constants required for static analysis.
 */

define( 'ABSPATH', '/tmp/wordpress/' );
define( 'WPINC', 'wp-includes' );
define( 'CAMALEAUNMAIL_VERSION', '0.1.0' );
define( 'CAMALEAUNMAIL_PATH', __DIR__ . '/' );
define( 'CAMALEAUNMAIL_URL', 'https://example.com/wp-content/plugins/camaleaunmail/' );

if ( ! function_exists( 'selfd' ) ) {
	function selfd( string $file ): void {} // phpcs:ignore
}
