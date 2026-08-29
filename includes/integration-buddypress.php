<?php
/**
 * BuddyPress Integration (optional, opt-in)
 *
 * Guards the front-end user registration (signup) form. Uses the same
 * shared field-guard engine as the rest of the plugin.
 *
 * @package Init_Void_Shield
 */

defined( 'ABSPATH' ) || exit;

add_action( 'plugins_loaded', 'init_plugin_suite_void_shield_maybe_init_buddypress_integration', 20 );

/**
 * Hook into BuddyPress only if it's active.
 *
 * @return void
 */
function init_plugin_suite_void_shield_maybe_init_buddypress_integration() {
	if ( ! function_exists( 'buddypress' ) ) {
		return;
	}

	add_action( 'bp_before_registration_submit_buttons', 'init_plugin_suite_void_shield_buddypress_render' );
	add_action( 'bp_signup_validate', 'init_plugin_suite_void_shield_buddypress_verify' );
}

/**
 * Whether the BuddyPress registration guard is enabled.
 *
 * @return bool
 */
function init_plugin_suite_void_shield_buddypress_enabled() {
	return '1' === get_option( 'init_plugin_suite_void_shield_enable_buddypress', '0' );
}

/**
 * Render the honeypot guard on the BuddyPress registration form, just
 * before the submit button.
 *
 * @return void
 */
function init_plugin_suite_void_shield_buddypress_render() {
	if ( ! init_plugin_suite_void_shield_buddypress_enabled() ) {
		return;
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built internally with esc_attr()/esc_html() on every value, see field-guard.php.
	echo init_plugin_suite_void_shield_build_guard_markup( 'buddypress_register' );
}

/**
 * Reject a BuddyPress signup that fails the honeypot check.
 *
 * Fires via `bp_signup_validate`. BuddyPress core validation functions
 * hooked to the same action add errors the same way: keying an entry onto
 * `buddypress()->signup->errors`, which the signup screen checks before
 * allowing the request to proceed.
 *
 * @return void
 */
function init_plugin_suite_void_shield_buddypress_verify() {
	if ( ! init_plugin_suite_void_shield_buddypress_enabled() ) {
		return;
	}

	if ( ! function_exists( 'buddypress' ) ) {
		return;
	}

	$bp = buddypress();

	if ( ! isset( $bp->signup ) ) {
		return;
	}

	if ( ! init_plugin_suite_void_shield_is_submission_human( 'buddypress_register' ) ) {
		$bp->signup->errors['init_void_shield_blocked'] = apply_filters(
			'init_plugin_suite_void_shield_buddypress_blocked_message',
			__( 'Registration could not be completed. Please try again.', 'init-void-shield' )
		);
	}
}
