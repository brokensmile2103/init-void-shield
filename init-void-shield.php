<?php
/**
 * Plugin Name: Init Void Shield
 * Plugin URI:  https://inithtml.com/plugin/init-void-shield/
 * Description: Zero-DB, zero-external-JS honeypot anti-spam for WordPress comments, core forms, and popular form plugins. Invisible to humans, a void to bots.
 * Version:     1.3
 * Author:      Init HTML
 * Author URI:  https://inithtml.com/
 * Text Domain: init-void-shield
 * Domain Path: /languages
 * Requires at least: 5.7
 * Tested up to: 7.1
 * Requires PHP: 7.4
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Init_Void_Shield
 */

defined( 'ABSPATH' ) || exit;

// ===== CONSTANTS ===== //
define( 'INIT_PLUGIN_SUITE_VOID_SHIELD_VERSION', '1.3' );
define( 'INIT_PLUGIN_SUITE_VOID_SHIELD_SLUG', 'init-void-shield' );
define( 'INIT_PLUGIN_SUITE_VOID_SHIELD_OPTION', 'init_plugin_suite_void_shield_settings' );
define( 'INIT_PLUGIN_SUITE_VOID_SHIELD_URL', plugin_dir_url( __FILE__ ) );
define( 'INIT_PLUGIN_SUITE_VOID_SHIELD_PATH', plugin_dir_path( __FILE__ ) );
define( 'INIT_PLUGIN_SUITE_VOID_SHIELD_INC', INIT_PLUGIN_SUITE_VOID_SHIELD_PATH . 'includes/' );

// ===== INCLUDES ===== //
// Shared engine and infrastructure (order matters: field-guard/stats first).
require_once INIT_PLUGIN_SUITE_VOID_SHIELD_INC . 'field-guard.php';
require_once INIT_PLUGIN_SUITE_VOID_SHIELD_INC . 'stats.php';

// Comment form guard (core feature, always loaded).
require_once INIT_PLUGIN_SUITE_VOID_SHIELD_INC . 'honeypot-core.php';

// Optional guards (each checks its own settings/plugin-detection internally).
require_once INIT_PLUGIN_SUITE_VOID_SHIELD_INC . 'wp-core-forms.php';
require_once INIT_PLUGIN_SUITE_VOID_SHIELD_INC . 'integration-cf7.php';
require_once INIT_PLUGIN_SUITE_VOID_SHIELD_INC . 'integration-wpforms.php';
require_once INIT_PLUGIN_SUITE_VOID_SHIELD_INC . 'integration-gravityforms.php';

// Settings screen.
require_once INIT_PLUGIN_SUITE_VOID_SHIELD_INC . 'settings-page.php';

// ===== SETTINGS LINK ===== //
add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		$url = admin_url( 'options-general.php?page=' . INIT_PLUGIN_SUITE_VOID_SHIELD_SLUG );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . __( 'Settings', 'init-void-shield' ) . '</a>' );
		return $links;
	}
);
