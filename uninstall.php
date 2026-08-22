<?php
/**
 * Uninstall – Init Void Shield
 *
 * Cleanly remove all plugin options.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$options = array(
    'init_plugin_suite_void_shield_enabled',
    'init_plugin_suite_void_shield_apply_to_logged_in',
    'init_plugin_suite_void_shield_min_time',
    'init_plugin_suite_void_shield_js_delay',
    'init_plugin_suite_void_shield_block_rest',
);

foreach ( $options as $option ) {
    delete_option( $option );
    delete_site_option( $option );
}
