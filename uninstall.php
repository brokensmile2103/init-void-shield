<?php
/**
 * Uninstall – Init Void Shield
 *
 * Cleanly remove all plugin options.
 *
 * @package Init_Void_Shield
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$init_plugin_suite_void_shield_options = array(
	'init_plugin_suite_void_shield_enabled',
	'init_plugin_suite_void_shield_apply_to_logged_in',
	'init_plugin_suite_void_shield_min_time',
	'init_plugin_suite_void_shield_js_delay',
	'init_plugin_suite_void_shield_max_time',
	'init_plugin_suite_void_shield_block_rest',
	'init_plugin_suite_void_shield_enable_login_guard',
	'init_plugin_suite_void_shield_enable_register_guard',
	'init_plugin_suite_void_shield_enable_lostpassword_guard',
	'init_plugin_suite_void_shield_enable_multisite_signup',
	'init_plugin_suite_void_shield_enable_cf7',
	'init_plugin_suite_void_shield_enable_wpforms',
	'init_plugin_suite_void_shield_enable_gravityforms',
	'init_plugin_suite_void_shield_enable_woocommerce_register',
	'init_plugin_suite_void_shield_enable_bbpress',
	'init_plugin_suite_void_shield_enable_buddypress',
	'init_plugin_suite_void_shield_field_prefix',
	'init_plugin_suite_void_shield_enable_css_rotation',
	'init_plugin_suite_void_shield_headless_detection',
	'init_plugin_suite_void_shield_require_interaction',
	'init_plugin_suite_void_shield_enable_stats',
	'init_plugin_suite_void_shield_enable_dashboard_widget',
	'init_plugin_suite_void_shield_stats',
);

foreach ( $init_plugin_suite_void_shield_options as $init_plugin_suite_void_shield_option ) {
	delete_option( $init_plugin_suite_void_shield_option );
	delete_site_option( $init_plugin_suite_void_shield_option );
}
