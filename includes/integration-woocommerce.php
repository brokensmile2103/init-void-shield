<?php
/**
 * WooCommerce Integration (optional, opt-in)
 *
 * Guards the WooCommerce "My Account" registration form. Uses the same
 * shared field-guard engine as the rest of the plugin.
 *
 * @package Init_Void_Shield
 */

defined( 'ABSPATH' ) || exit;

add_action( 'plugins_loaded', 'init_plugin_suite_void_shield_maybe_init_woocommerce_integration', 20 );

/**
 * Hook into WooCommerce only if it's active.
 *
 * @return void
 */
function init_plugin_suite_void_shield_maybe_init_woocommerce_integration() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	add_action( 'woocommerce_register_form', 'init_plugin_suite_void_shield_woocommerce_render' );
	add_filter( 'woocommerce_registration_errors', 'init_plugin_suite_void_shield_woocommerce_verify', 10, 3 );
}

/**
 * Whether the WooCommerce registration guard is enabled.
 *
 * @return bool
 */
function init_plugin_suite_void_shield_woocommerce_enabled() {
	return '1' === get_option( 'init_plugin_suite_void_shield_enable_woocommerce_register', '0' );
}

/**
 * Render the honeypot guard on the WooCommerce registration form, just
 * before the Register button (`woocommerce_register_form` fires right
 * before it in the core template).
 *
 * @return void
 */
function init_plugin_suite_void_shield_woocommerce_render() {
	if ( ! init_plugin_suite_void_shield_woocommerce_enabled() ) {
		return;
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built internally with esc_attr()/esc_html() on every value, see field-guard.php.
	echo init_plugin_suite_void_shield_build_guard_markup( 'woocommerce_register' );
}

/**
 * Reject WooCommerce registrations that fail the honeypot check.
 *
 * @param WP_Error $errors   Registration errors collection.
 * @param string   $username Sanitized customer username.
 * @param string   $email    Customer email address.
 * @return WP_Error
 */
function init_plugin_suite_void_shield_woocommerce_verify( $errors, $username, $email ) {
	unset( $username, $email );

	if ( ! init_plugin_suite_void_shield_woocommerce_enabled() ) {
		return $errors;
	}

	if ( ! is_wp_error( $errors ) ) {
		return $errors;
	}

	if ( ! init_plugin_suite_void_shield_is_submission_human( 'woocommerce_register' ) ) {
		$errors->add(
			'init_void_shield_blocked',
			apply_filters(
				'init_plugin_suite_void_shield_woocommerce_blocked_message',
				__( 'Registration could not be completed. Please try again.', 'init-void-shield' )
			)
		);
	}

	return $errors;
}
