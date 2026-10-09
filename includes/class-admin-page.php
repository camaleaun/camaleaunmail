<?php
/**
 * AdminPage — Settings > Mail submenu page.
 *
 * @package Camaleaunmail
 * @since   0.1.0
 */

namespace Camaleaunmail;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the mail settings page in wp-admin.
 *
 * @since 0.1.0
 */
class AdminPage {

	/**
	 * Admin menu slug.
	 *
	 * @var string
	 */
	const MENU_SLUG = 'camaleaunmail';

	/**
	 * Register hooks.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( static::class, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( static::class, 'enqueue_scripts' ) );
	}

	/**
	 * Add "Mail" submenu under Settings.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public static function add_menu(): void {
		add_submenu_page(
			'options-general.php',
			__( 'Mail', 'camaleaunmail' ),
			__( 'Mail', 'camaleaunmail' ),
			'manage_options',
			self::MENU_SLUG,
			array( static::class, 'render_page' )
		);
	}

	/**
	 * Render the admin page.
	 *
	 * Inline critical styles that remove the default wp-admin padding
	 * and hide the footer.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public static function render_page(): void {
		?>
		<style>
			/* Remove default wp-admin padding so the page fills the content area. */
			#wpcontent { padding-left: 0; }
			#wpbody-content { padding-bottom: 0; }
			#wpbody-content > .wrap:not(#camaleaunmail-wrap) { display: none; }
			#wpfooter { display: none; }
		</style>
		<div id="camaleaunmail-wrap">
			<div id="camaleaunmail-app"></div>
		</div>
		<?php
	}

	/**
	 * Enqueue JS + CSS only on our admin page.
	 *
	 * @since  0.1.0
	 * @param  string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public static function enqueue_scripts( string $hook_suffix ): void {
		if ( 'settings_page_' . self::MENU_SLUG !== $hook_suffix ) {
			return;
		}

		$build_dir = Constants::plugin_path() . 'build/';
		$build_url = Constants::plugin_url() . 'build/';

		$asset_file = $build_dir . 'index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'camaleaunmail-settings',
			$build_url . 'index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		if ( file_exists( $build_dir . 'index.css' ) ) {
			wp_enqueue_style(
				'camaleaunmail-settings',
				$build_url . 'index.css',
				array( 'wp-components' ),
				$asset['version']
			);
		}

		// Translations come from the language pack (wp-content/languages/plugins).
		wp_set_script_translations( 'camaleaunmail-settings', 'camaleaunmail' );

		wp_localize_script(
			'camaleaunmail-settings',
			'camaleaunMailData',
			array(
				'apiRoot'    => esc_url_raw( rest_url() ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'adminEmail' => get_option( 'admin_email' ),
			)
		);
	}
}
