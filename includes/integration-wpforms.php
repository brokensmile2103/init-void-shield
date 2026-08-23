<?php
/**
 * WPForms Integration (optional, opt-in)
 *
 * @package Init_Void_Shield
 */

defined( 'ABSPATH' ) || exit;

add_action( 'plugins_loaded', 'init_plugin_suite_void_shield_maybe_init_wpforms_integration', 20 );

/**
 * Hook into WPForms only if it's active.
 *
 * @return void
 */
function init_plugin_suite_void_shield_maybe_init_wpforms_integration() {
	if ( ! function_exists( 'wpforms' ) ) {
		return;
	}

	add_action( 'wpforms_display_submit_before', 'init_plugin_suite_void_shield_wpforms_render', 10, 1 );
	add_action( 'wpforms_process_before', 'init_plugin_suite_void_shield_wpforms_verify', 10, 2 );
}

/**
 * Render the honeypot guard just before the WPForms submit button.
 *
 * @param array $form_data Form data.
 * @return void
 */
function init_plugin_suite_void_shield_wpforms_render( $form_data ) {
	if ( '1' !== get_option( 'init_plugin_suite_void_shield_enable_wpforms', '0' ) ) {
		return;
	}

	$form_id = isset( $form_data['id'] ) ? absint( $form_data['id'] ) : 0;

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built internally with esc_attr()/esc_html() on every value, see field-guard.php.
	echo init_plugin_suite_void_shield_build_guard_markup( 'wpforms_' . $form_id );
}

/**
 * Block a WPForms submission if it fails the honeypot check.
 *
 * Sets a general (non field-specific) "footer" processing error, which
 * WPForms uses to halt saving/notifications for the entry.
 *
 * @param array $entry     Raw entry ($_POST).
 * @param array $form_data Form data.
 * @return void
 */
function init_plugin_suite_void_shield_wpforms_verify( $entry, $form_data ) {
	unset( $entry );

	if ( '1' !== get_option( 'init_plugin_suite_void_shield_enable_wpforms', '0' ) ) {
		return;
	}

	if ( ! function_exists( 'wpforms' ) || ! isset( $form_data['id'] ) ) {
		return;
	}

	$form_id = absint( $form_data['id'] );

	if ( ! init_plugin_suite_void_shield_is_submission_human( 'wpforms_' . $form_id ) ) {
		wpforms()->process->errors[ $form_id ]['footer'] = apply_filters(
			'init_plugin_suite_void_shield_wpforms_blocked_message',
			__( 'Something went wrong. Please try again.', 'init-void-shield' )
		);
	}
}
