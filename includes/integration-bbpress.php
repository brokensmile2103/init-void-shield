<?php
/**
 * Integration: bbPress (optional, opt-in)
 *
 * Guards the front-end New Topic and New Reply forms. Uses the same
 * shared field-guard engine as the rest of the plugin.
 *
 * @package Init_Void_Shield
 */

defined( 'ABSPATH' ) || exit;

add_action( 'plugins_loaded', 'init_plugin_suite_void_shield_maybe_init_bbpress_integration', 20 );

/**
 * Hook into bbPress only if it's active.
 *
 * @return void
 */
function init_plugin_suite_void_shield_maybe_init_bbpress_integration() {
	if ( ! class_exists( 'bbPress' ) ) {
		return;
	}

	add_action( 'bbp_theme_after_topic_form_content', 'init_plugin_suite_void_shield_bbpress_render_topic' );
	add_action( 'bbp_new_topic_pre_extras', 'init_plugin_suite_void_shield_bbpress_verify_topic' );

	add_action( 'bbp_theme_after_reply_form_content', 'init_plugin_suite_void_shield_bbpress_render_reply' );
	add_action( 'bbp_new_reply_pre_extras', 'init_plugin_suite_void_shield_bbpress_verify_reply' );
}

/**
 * Whether the bbPress guard is enabled.
 *
 * @return bool
 */
function init_plugin_suite_void_shield_bbpress_enabled() {
	return '1' === get_option( 'init_plugin_suite_void_shield_enable_bbpress', '0' );
}

/**
 * Render the honeypot guard on the New Topic form.
 *
 * @return void
 */
function init_plugin_suite_void_shield_bbpress_render_topic() {
	if ( ! init_plugin_suite_void_shield_bbpress_enabled() ) {
		return;
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built internally with esc_attr()/esc_html() on every value, see field-guard.php.
	echo init_plugin_suite_void_shield_build_guard_markup( 'bbpress_topic' );
}

/**
 * Reject a new topic submission that fails the honeypot check.
 *
 * Fires via `bbp_new_topic_pre_extras`, called right before bbPress checks
 * `bbp_has_errors()` and decides whether to proceed with the insert.
 *
 * @return void
 */
function init_plugin_suite_void_shield_bbpress_verify_topic() {
	if ( ! init_plugin_suite_void_shield_bbpress_enabled() ) {
		return;
	}

	if ( ! function_exists( 'bbp_add_error' ) ) {
		return;
	}

	if ( ! init_plugin_suite_void_shield_is_submission_human( 'bbpress_topic' ) ) {
		bbp_add_error(
			'init_void_shield_blocked',
			apply_filters(
				'init_plugin_suite_void_shield_bbpress_blocked_message',
				__( '<strong>Error</strong>: Your submission could not be processed. Please try again.', 'init-void-shield' )
			)
		);
	}
}

/**
 * Render the honeypot guard on the Reply form.
 *
 * @return void
 */
function init_plugin_suite_void_shield_bbpress_render_reply() {
	if ( ! init_plugin_suite_void_shield_bbpress_enabled() ) {
		return;
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built internally with esc_attr()/esc_html() on every value, see field-guard.php.
	echo init_plugin_suite_void_shield_build_guard_markup( 'bbpress_reply' );
}

/**
 * Reject a new reply submission that fails the honeypot check.
 *
 * Fires via `bbp_new_reply_pre_extras`, the reply-form counterpart of
 * `bbp_new_topic_pre_extras` above.
 *
 * @return void
 */
function init_plugin_suite_void_shield_bbpress_verify_reply() {
	if ( ! init_plugin_suite_void_shield_bbpress_enabled() ) {
		return;
	}

	if ( ! function_exists( 'bbp_add_error' ) ) {
		return;
	}

	if ( ! init_plugin_suite_void_shield_is_submission_human( 'bbpress_reply' ) ) {
		bbp_add_error(
			'init_void_shield_blocked',
			apply_filters(
				'init_plugin_suite_void_shield_bbpress_blocked_message',
				__( '<strong>Error</strong>: Your submission could not be processed. Please try again.', 'init-void-shield' )
			)
		);
	}
}
