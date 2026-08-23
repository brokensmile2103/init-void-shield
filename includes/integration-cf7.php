<?php
/**
 * Contact Form 7 Integration (optional, opt-in)
 *
 * @package Init_Void_Shield
 */

defined( 'ABSPATH' ) || exit;

add_action( 'plugins_loaded', 'init_plugin_suite_void_shield_maybe_init_cf7_integration', 20 );

/**
 * Hook into Contact Form 7 only if it's active.
 *
 * @return void
 */
function init_plugin_suite_void_shield_maybe_init_cf7_integration() {
	if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
		return;
	}

	add_filter( 'wpcf7_form_elements', 'init_plugin_suite_void_shield_cf7_render' );
	add_filter( 'wpcf7_spam', 'init_plugin_suite_void_shield_cf7_verify' );
}

/**
 * Append the honeypot guard markup to Contact Form 7 forms.
 *
 * @param string $content Form HTML.
 * @return string
 */
function init_plugin_suite_void_shield_cf7_render( $content ) {
	if ( '1' !== get_option( 'init_plugin_suite_void_shield_enable_cf7', '0' ) ) {
		return $content;
	}

	return $content . init_plugin_suite_void_shield_build_guard_markup( 'cf7_' . init_plugin_suite_void_shield_get_cf7_form_id() );
}

/**
 * Mark a Contact Form 7 submission as spam if it fails the honeypot check.
 *
 * @param bool $spam Whether the submission is already flagged as spam.
 * @return bool
 */
function init_plugin_suite_void_shield_cf7_verify( $spam ) {
	if ( $spam ) {
		return $spam;
	}

	if ( '1' !== get_option( 'init_plugin_suite_void_shield_enable_cf7', '0' ) ) {
		return $spam;
	}

	if ( ! init_plugin_suite_void_shield_is_submission_human( 'cf7_' . init_plugin_suite_void_shield_get_cf7_form_id() ) ) {
		return true;
	}

	return $spam;
}

/**
 * Best-effort lookup of the current Contact Form 7 form ID, used to scope
 * the guard context per form.
 *
 * @return string
 */
function init_plugin_suite_void_shield_get_cf7_form_id() {
	if ( method_exists( 'WPCF7_ContactForm', 'get_current' ) ) {
		$form = WPCF7_ContactForm::get_current();

		if ( $form && method_exists( $form, 'id' ) ) {
			return (string) $form->id();
		}
	}

	return 'default';
}
