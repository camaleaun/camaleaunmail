=== Camaleaunmail ===
Contributors: (this should be a list of wordpress.org userid's)
Tags:
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

PLUGIN DESCRIPTION HERE

== Description ==

PLUGIN DESCRIPTION HERE

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/camaleaunmail` directory,
   or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.

== Logs and privacy ==

Every email sent through `wp_mail()` is recorded in the `{prefix}camaleaunmail_logs` table, including its body, under Settings > Mail > Logs. Bodies can contain password reset links and other private data, so logs are deleted after 30 days by default. Retention can be changed, and logging turned off, in the plugin settings.

To block all outgoing mail (for staging or local copies of a site), turn on "Disable sending" in the Transport tab, or add this to `wp-config.php`:

`define( 'CAMALEAUNMAIL_DISABLE_SENDING', true );`

Blocked emails are recorded in the log with the status "Blocked".

== Changelog ==

= 0.2.0 =
* New: Logs tab that records every email sent, failed or blocked, with its content, and lets you resend or delete it.
* New: Disable sending, so emails are only recorded in the log. Can be forced with the `CAMALEAUNMAIL_DISABLE_SENDING` constant.
* New: Log retention (30 days by default) and an option to turn logging off.
* New: `wp camaleaunmail logs list` and `wp camaleaunmail logs purge` commands.

= 0.1.0 =
* Initial release.
