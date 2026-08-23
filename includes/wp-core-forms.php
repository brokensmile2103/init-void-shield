<?php
/**
 * WordPress Core Forms Guard (optional, opt-in)
 *
 * Honeypot protection for the default WP login, registration, and lost
 * password forms. Each covers only WordPress's own default markup at
 * wp-login.php; a custom login page/plugin or Multisite's wp-signup.php
 * flow renders different forms and is not covered here.
 *
 * @package Init_Void_Shield
 */

defined( 'ABSPATH' ) || exit;

// ------------------------------------------------------------------
// 1. Login form
// ------------------------------------------------------------------

add_action( 'login_form', 'init_plugin_suite_void_shield_render_login_guard' );

/**
 * Render the honeypot guard on the default WP login form.
 *
 * @return void
 */
function init_plugin_suite_void_shield_render_login_guard() {
	if ( '1' !== get_option( 'init_plugin_suite_void_shield_enable_login_guard', '0' ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built internally with esc_attr()/esc_html() on every value, see field-guard.php.
	echo init_plugin_suite_void_shield_build_guard_markup( 'login' );
}

add_filter( 'authenticate', 'init_plugin_suite_void_shield_guard_login', 999, 1 );

/**
 * Reject login attempts that fail the honeypot check.
 *
 * Runs at a very late priority so it evaluates after WordPress core (and
 * most other plugins) have already resolved the $user param, and can
 * override even an otherwise-successful authentication.
 *
 * @param WP_User|WP_Error|null $user User/error from earlier in the authenticate filter chain.
 * @return WP_User|WP_Error|null
 */
function init_plugin_suite_void_shield_guard_login( $user ) {
	if ( '1' !== get_option( 'init_plugin_suite_void_shield_enable_login_guard', '0' ) ) {
		return $user;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- anti-spam gate; only a structural check that this is a real login form POST.
	if ( ! init_plugin_suite_void_shield_is_post_request() || ! isset( $_POST['log'] ) ) {
		return $user;
	}

	if ( init_plugin_suite_void_shield_is_submission_human( 'login' ) ) {
		return $user;
	}

	return new WP_Error(
		'init_void_shield_blocked',
		apply_filters(
			'init_plugin_suite_void_shield_login_blocked_message',
			__( 'Invalid login attempt.', 'init-void-shield' )
		)
	);
}

// ------------------------------------------------------------------
// 2. Registration form
// ------------------------------------------------------------------

add_action( 'register_form', 'init_plugin_suite_void_shield_render_register_guard' );

/**
 * Render the honeypot guard on the default WP registration form.
 *
 * @return void
 */
function init_plugin_suite_void_shield_render_register_guard() {
	if ( '1' !== get_option( 'init_plugin_suite_void_shield_enable_register_guard', '0' ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built internally with esc_attr()/esc_html() on every value, see field-guard.php.
	echo init_plugin_suite_void_shield_build_guard_markup( 'register' );
}

add_filter( 'registration_errors', 'init_plugin_suite_void_shield_guard_register', 10, 1 );

/**
 * Reject registrations that fail the honeypot check.
 *
 * @param WP_Error $errors Registration errors collection.
 * @return WP_Error
 */
function init_plugin_suite_void_shield_guard_register( $errors ) {
	if ( '1' !== get_option( 'init_plugin_suite_void_shield_enable_register_guard', '0' ) ) {
		return $errors;
	}

	if ( ! init_plugin_suite_void_shield_is_post_request() || ! is_wp_error( $errors ) ) {
		return $errors;
	}

	if ( ! init_plugin_suite_void_shield_is_submission_human( 'register' ) ) {
		$errors->add(
			'init_void_shield_blocked',
			apply_filters(
				'init_plugin_suite_void_shield_register_blocked_message',
				__( 'Registration could not be completed. Please try again.', 'init-void-shield' )
			)
		);
	}

	return $errors;
}

// ------------------------------------------------------------------
// 3. Lost password form
// ------------------------------------------------------------------

add_action( 'lostpassword_form', 'init_plugin_suite_void_shield_render_lostpassword_guard' );

/**
 * Render the honeypot guard on the default WP lost-password form.
 *
 * @return void
 */
function init_plugin_suite_void_shield_render_lostpassword_guard() {
	if ( '1' !== get_option( 'init_plugin_suite_void_shield_enable_lostpassword_guard', '0' ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built internally with esc_attr()/esc_html() on every value, see field-guard.php.
	echo init_plugin_suite_void_shield_build_guard_markup( 'lostpassword' );
}

add_action( 'lostpassword_post', 'init_plugin_suite_void_shield_guard_lostpassword', 10, 1 );

/**
 * Reject lost-password requests that fail the honeypot check.
 *
 * Fires via `lostpassword_post`, called as `do_action( 'lostpassword_post', $errors, $user_data )`
 * in WordPress core (the $errors param has existed since WP 4.4, well
 * before this plugin's WP 5.7 minimum). $errors is a WP_Error object, so
 * calling ->add() on it mutates the same instance WordPress checks afterward.
 *
 * @param WP_Error $errors Lost password errors collection.
 * @return void
 */
function init_plugin_suite_void_shield_guard_lostpassword( $errors ) {
	if ( '1' !== get_option( 'init_plugin_suite_void_shield_enable_lostpassword_guard', '0' ) ) {
		return;
	}

	if ( ! init_plugin_suite_void_shield_is_post_request() || ! is_wp_error( $errors ) ) {
		return;
	}

	if ( ! init_plugin_suite_void_shield_is_submission_human( 'lostpassword' ) ) {
		$errors->add(
			'init_void_shield_blocked',
			apply_filters(
				'init_plugin_suite_void_shield_lostpassword_blocked_message',
				__( 'Your request could not be processed. Please try again.', 'init-void-shield' )
			)
		);
	}
}
