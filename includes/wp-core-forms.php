<?php
/**
 * WordPress Core Forms Guard (optional, opt-in)
 *
 * Honeypot protection for the default WP login, registration, and lost
 * password forms. Each covers WordPress's own default markup at
 * wp-login.php, plus wp_login_form() when used on the front end (e.g. a
 * [loginform] block/widget or a theme template); a custom login page/plugin
 * or Multisite's wp-signup.php flow renders different forms and is not
 * covered here.
 *
 * @package Init_Void_Shield
 */

defined( 'ABSPATH' ) || exit;

// ------------------------------------------------------------------
// 1. Login form
// ------------------------------------------------------------------

/**
 * Determine whether the login guard should be active for this request.
 *
 * Centralizes the settings check plus the developer override filter so the
 * native wp-login.php form (`login_form` action), the front-end
 * wp_login_form() form (`login_form_middle` filter), and the `authenticate`
 * check all agree on the same answer.
 *
 * @return bool
 */
function init_plugin_suite_void_shield_login_guard_enabled() {
	if ( '1' !== get_option( 'init_plugin_suite_void_shield_enable_login_guard', '0' ) ) {
		return false;
	}

	/**
	 * Force-disable the login form guard, regardless of the settings-page
	 * toggle. Intended for developers who need to rule this guard out for
	 * a specific request (e.g. a custom login flow, staging environment,
	 * or another plugin authenticating on the visitor's behalf).
	 *
	 * @param bool $skip Whether to skip the login guard. Default false.
	 */
	return ! apply_filters( 'init_plugin_suite_void_shield_skip_login_verification', false );
}

add_action( 'login_form', 'init_plugin_suite_void_shield_render_login_guard' );

/**
 * Render the honeypot guard on the native wp-login.php login form.
 *
 * @return void
 */
function init_plugin_suite_void_shield_render_login_guard() {
	if ( ! init_plugin_suite_void_shield_login_guard_enabled() ) {
		return;
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built internally with esc_attr()/esc_html() on every value, see field-guard.php.
	echo init_plugin_suite_void_shield_build_guard_markup( 'login' );
}

add_filter( 'login_form_middle', 'init_plugin_suite_void_shield_render_login_guard_frontend' );

/**
 * Render the honeypot guard on the front-end wp_login_form() login form.
 *
 * wp_login_form() (used to place a login form anywhere on the front end,
 * e.g. via a widget, a block, or a theme template) builds its own markup
 * and never fires the `login_form` action that the native wp-login.php
 * screen uses above, so that hook never gets a chance to run for it. Every
 * submission is still routed through the same `authenticate` filter chain
 * that init_plugin_suite_void_shield_guard_login() hooks into below, so
 * without this filter such forms would carry no honeypot fields at all and
 * would always be rejected once the login guard is enabled. `login_form_middle`
 * is one of three filters ( *_top / *_middle / *_bottom ) WordPress core
 * runs specifically to let plugins extend wp_login_form() output.
 *
 * @param string $content Existing middle content of the form. Empty by default.
 * @return string
 */
function init_plugin_suite_void_shield_render_login_guard_frontend( $content ) {
	if ( ! init_plugin_suite_void_shield_login_guard_enabled() ) {
		return $content;
	}

	return $content . init_plugin_suite_void_shield_build_guard_markup( 'login' );
}

add_filter( 'authenticate', 'init_plugin_suite_void_shield_guard_login', 999, 1 );

/**
 * Reject login attempts that fail the honeypot check.
 *
 * Runs at a very late priority so it evaluates after WordPress core (and
 * most other plugins) have already resolved the $user param, and can
 * override even an otherwise-successful authentication. Covers both the
 * native wp-login.php form and the front-end wp_login_form() form, since
 * both post the same 'log' field and route through this same filter.
 *
 * @param WP_User|WP_Error|null $user User/error from earlier in the authenticate filter chain.
 * @return WP_User|WP_Error|null
 */
function init_plugin_suite_void_shield_guard_login( $user ) {
	if ( ! init_plugin_suite_void_shield_login_guard_enabled() ) {
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

/**
 * Determine whether the registration guard should be active for this request.
 *
 * @return bool
 */
function init_plugin_suite_void_shield_register_guard_enabled() {
	if ( '1' !== get_option( 'init_plugin_suite_void_shield_enable_register_guard', '0' ) ) {
		return false;
	}

	/**
	 * Force-disable the registration form guard, regardless of the
	 * settings-page toggle.
	 *
	 * @param bool $skip Whether to skip the registration guard. Default false.
	 */
	return ! apply_filters( 'init_plugin_suite_void_shield_skip_register_verification', false );
}

add_action( 'register_form', 'init_plugin_suite_void_shield_render_register_guard' );

/**
 * Render the honeypot guard on the default WP registration form.
 *
 * @return void
 */
function init_plugin_suite_void_shield_render_register_guard() {
	if ( ! init_plugin_suite_void_shield_register_guard_enabled() ) {
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
	if ( ! init_plugin_suite_void_shield_register_guard_enabled() ) {
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

/**
 * Determine whether the lost-password guard should be active for this request.
 *
 * @return bool
 */
function init_plugin_suite_void_shield_lostpassword_guard_enabled() {
	if ( '1' !== get_option( 'init_plugin_suite_void_shield_enable_lostpassword_guard', '0' ) ) {
		return false;
	}

	/**
	 * Force-disable the lost-password form guard, regardless of the
	 * settings-page toggle.
	 *
	 * @param bool $skip Whether to skip the lost-password guard. Default false.
	 */
	return ! apply_filters( 'init_plugin_suite_void_shield_skip_lostpassword_verification', false );
}

add_action( 'lostpassword_form', 'init_plugin_suite_void_shield_render_lostpassword_guard' );

/**
 * Render the honeypot guard on the default WP lost-password form.
 *
 * @return void
 */
function init_plugin_suite_void_shield_render_lostpassword_guard() {
	if ( ! init_plugin_suite_void_shield_lostpassword_guard_enabled() ) {
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
	if ( ! init_plugin_suite_void_shield_lostpassword_guard_enabled() ) {
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
