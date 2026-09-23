<?php
/**
 * WooCommerce Integration (optional, opt-in)
 *
 * Guards the WooCommerce "My Account" registration form, and optionally
 * the WooCommerce login form (My Account and the checkout login prompt)
 * and the WooCommerce lost-password form. Uses the same shared field-guard
 * engine as the rest of the plugin.
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

	add_action( 'woocommerce_login_form', 'init_plugin_suite_void_shield_woocommerce_render_login' );
	add_filter( 'woocommerce_process_login_errors', 'init_plugin_suite_void_shield_woocommerce_verify_login', 10, 3 );

	add_action( 'woocommerce_lostpassword_form', 'init_plugin_suite_void_shield_woocommerce_render_lostpassword' );
	add_action( 'lostpassword_post', 'init_plugin_suite_void_shield_woocommerce_verify_lostpassword', 10, 1 );
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

// ------------------------------------------------------------------
// Login form (My Account + checkout login prompt)
// ------------------------------------------------------------------

/**
 * Whether the WooCommerce login guard is enabled.
 *
 * @return bool
 */
function init_plugin_suite_void_shield_woocommerce_login_enabled() {
	return '1' === get_option( 'init_plugin_suite_void_shield_enable_woocommerce_login', '0' );
}

/**
 * Render the honeypot guard on the WooCommerce login form.
 *
 * `woocommerce_login_form` fires inside both the My Account login form and
 * the "Returning customer? Click here to login" form on the checkout page;
 * both post to the same WC_Form_Handler::process_login() handler.
 *
 * @return void
 */
function init_plugin_suite_void_shield_woocommerce_render_login() {
	if ( ! init_plugin_suite_void_shield_woocommerce_login_enabled() ) {
		return;
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built internally with esc_attr()/esc_html() on every value, see field-guard.php.
	echo init_plugin_suite_void_shield_build_guard_markup( 'woocommerce_login' );
}

/**
 * Reject WooCommerce login attempts that fail the honeypot check.
 *
 * WooCommerce's login form posts `username`/`password` rather than the
 * core `log`/`pwd` fields, so the core Login Guard (which only acts on a
 * POST carrying `log`) never interferes with it; this guard covers it
 * separately, under its own context.
 *
 * @param WP_Error $validation_error Login validation errors collection.
 * @param string   $username         Submitted username or email.
 * @param string   $password         Submitted password.
 * @return WP_Error
 */
function init_plugin_suite_void_shield_woocommerce_verify_login( $validation_error, $username, $password ) {
	unset( $username, $password );

	if ( ! init_plugin_suite_void_shield_woocommerce_login_enabled() ) {
		return $validation_error;
	}

	if ( ! is_wp_error( $validation_error ) ) {
		return $validation_error;
	}

	if ( ! init_plugin_suite_void_shield_is_submission_human( 'woocommerce_login' ) ) {
		$validation_error->add(
			'init_void_shield_blocked',
			apply_filters(
				'init_plugin_suite_void_shield_woocommerce_login_blocked_message',
				__( 'Invalid login attempt.', 'init-void-shield' )
			)
		);
	}

	return $validation_error;
}

// ------------------------------------------------------------------
// Lost password form
// ------------------------------------------------------------------

/**
 * Whether the WooCommerce lost-password guard is in effect.
 *
 * Also in effect whenever the core Lost Password guard is enabled.
 * WooCommerce's own lost-password handler fires the same core
 * `lostpassword_post` action the core guard listens on, so before 1.11 a
 * site with the core guard enabled rejected every WooCommerce password
 * reset request -- genuine ones included, since WooCommerce's form never
 * carried the honeypot fields. Guarding the WooCommerce form alongside the
 * core one fixes that without leaving either endpoint unguarded.
 *
 * @return bool
 */
function init_plugin_suite_void_shield_woocommerce_lostpassword_enabled() {
	if ( '1' === get_option( 'init_plugin_suite_void_shield_enable_woocommerce_lostpassword', '0' ) ) {
		return true;
	}

	return function_exists( 'init_plugin_suite_void_shield_lostpassword_guard_enabled' )
		&& init_plugin_suite_void_shield_lostpassword_guard_enabled();
}

/**
 * Whether the current request is a WooCommerce lost-password form submission.
 *
 * WooCommerce's form always carries a hidden `wc_reset_password` field,
 * which WC_Form_Handler::process_lost_password() itself requires; the core
 * wp-login.php form never sends it.
 *
 * @return bool
 */
function init_plugin_suite_void_shield_is_woocommerce_lostpassword_request() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- structural check only; WooCommerce verifies its own nonce for this form.
	return init_plugin_suite_void_shield_is_post_request() && isset( $_POST['wc_reset_password'] );
}

/**
 * Render the honeypot guard on the WooCommerce lost-password form.
 *
 * @return void
 */
function init_plugin_suite_void_shield_woocommerce_render_lostpassword() {
	if ( ! init_plugin_suite_void_shield_woocommerce_lostpassword_enabled() ) {
		return;
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built internally with esc_attr()/esc_html() on every value, see field-guard.php.
	echo init_plugin_suite_void_shield_build_guard_markup( 'woocommerce_lostpassword' );
}

/**
 * Reject WooCommerce lost-password requests that fail the honeypot check.
 *
 * Hooked to the core `lostpassword_post` action, which
 * WC_Shortcode_My_Account::retrieve_password() fires with a WP_Error it
 * checks right afterward. Only acts on WooCommerce's own form; the core
 * guard in wp-core-forms.php steps aside for that form while this one is in
 * effect, so each form is verified exactly once, against its own context.
 *
 * @param WP_Error $errors Lost password errors collection.
 * @return void
 */
function init_plugin_suite_void_shield_woocommerce_verify_lostpassword( $errors ) {
	if ( ! init_plugin_suite_void_shield_woocommerce_lostpassword_enabled() ) {
		return;
	}

	if ( ! is_wp_error( $errors ) || ! init_plugin_suite_void_shield_is_woocommerce_lostpassword_request() ) {
		return;
	}

	if ( ! init_plugin_suite_void_shield_is_submission_human( 'woocommerce_lostpassword' ) ) {
		$errors->add(
			'init_void_shield_blocked',
			apply_filters(
				'init_plugin_suite_void_shield_woocommerce_lostpassword_blocked_message',
				__( 'Your request could not be processed. Please try again.', 'init-void-shield' )
			)
		);
	}
}
