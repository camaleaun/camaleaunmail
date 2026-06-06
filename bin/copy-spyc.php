#!/usr/bin/env php
<?php
/**
 * Builds includes/class-spyc.php from vendor/wp-cli/mustangostang-spyc.
 *
 * Concatenates src/Spyc.php (Mustangostang\Spyc class) +
 * includes/functions.php + a class_alias so \Spyc still works.
 * Patches @version to the installed Composer version.
 *
 * Run automatically via composer post-install-cmd / post-update-cmd.
 */

$root   = dirname( __DIR__ );
$base   = $root . '/vendor/wp-cli/mustangostang-spyc';
$src    = $base . '/src/Spyc.php';
$funcs  = $base . '/includes/functions.php';
$dest   = $root . '/includes/class-spyc.php';
$lock   = $root . '/composer.lock';

foreach ( array( $src, $funcs ) as $f ) {
	if ( ! file_exists( $f ) ) {
		fwrite( STDERR, "copy-spyc: source not found at $f\n" );
		exit( 1 );
	}
}

// Resolve version from composer.lock.
$version = '0.6';
if ( file_exists( $lock ) ) {
	$data = json_decode( file_get_contents( $lock ), true );
	foreach ( array_merge( $data['packages'] ?? [], $data['packages-dev'] ?? [] ) as $pkg ) {
		if ( 'wp-cli/mustangostang-spyc' === $pkg['name'] ) {
			$version = ltrim( $pkg['version'], 'v' );
			break;
		}
	}
}

// src/Spyc.php — convert to bracketed namespace block, patch @version.
$spyc_body = file_get_contents( $src );
// Remove opening <?php tag and unbracketed namespace declaration (same or next line).
$spyc_body = preg_replace( '/^<\?php\s*(namespace\s+Mustangostang;\s*)?/s', '', $spyc_body );
$spyc_body = preg_replace( '/^namespace\s+Mustangostang;\s*/m', '', $spyc_body );
$spyc_body = preg_replace( '/(@version\s+)[\d.]+/', '${1}' . $version, $spyc_body );
// Wrap in bracketed namespace block with class_exists guard.
$spyc_body = "namespace Mustangostang {\nif ( ! class_exists( 'Mustangostang\\\\Spyc', false ) ) {\n" . trim( $spyc_body ) . "\n}\n}";

// includes/functions.php — strip <?php + use statement; wrap in global namespace block.
$funcs_body = file_get_contents( $funcs );
$funcs_body = preg_replace( '/^<\?php\s*/s', '', $funcs_body );
// Remove the bare `use` statement (will reference via FQCN inside namespace {} block).
$funcs_body = preg_replace( '/^use\s+[^;]+;\s*/m', '', $funcs_body );
// Replace bare Spyc:: calls with fully-qualified \Mustangostang\Spyc::.
$funcs_body = str_replace( 'Spyc::', '\Mustangostang\Spyc::', $funcs_body );
// Wrap in global namespace block.
$funcs_body = "namespace {\n" . trim( $funcs_body ) . "\n}";

$alias = <<<'PHP'

namespace {
	// Global alias so \Spyc:: calls work without the namespace.
	if ( ! class_exists( 'Spyc' ) ) {
		class_alias( 'Mustangostang\Spyc', 'Spyc' );
	}
}
PHP;

$out = "<?php\n" . $spyc_body . "\n" . $funcs_body . $alias . "\n";

file_put_contents( $dest, $out );
echo "copy-spyc: wrote $dest (version $version)\n";
