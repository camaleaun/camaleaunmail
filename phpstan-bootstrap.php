<?php
/**
 * PHPStan bootstrap — defines constants required for static analysis.
 */

define( 'ABSPATH', '/tmp/wordpress/' );
define( 'WPINC', 'wp-includes' );
define( 'CAMALEAUNMAIL_VERSION', '0.2.1' );
define( 'CAMALEAUNMAIL_PATH', __DIR__ . '/' );
define( 'CAMALEAUNMAIL_URL', 'https://example.com/wp-content/plugins/camaleaunmail/' );

if ( ! function_exists( 'selfd' ) ) {
	function selfd( string $file ): void {} // phpcs:ignore
}
