<?php
/**
 * Gravity Forms Integration (optional, opt-in)
 *
 * @package Init_Void_Shield
 */

defined( 'ABSPATH' ) || exit;

add_action( 'plugins_loaded', 'init_plugin_suite_void_shield_maybe_init_gravityforms_integration', 20 );

/**
 * Hook into Gravity Forms only if it's active.
 *
 * @return void
 */
function init_plugin_suite_void_shield_maybe_init_gravityforms_integration() {
	if ( ! class_exists( 'GFForms' ) ) {
		return;
	}

	add_action( 'gform_form_footer', 'init_plugin_suite_void_shield_gravityforms_render', 10, 2 );
	add_filter( 'gform_entry_is_spam', 'init_plugin_suite_void_shield_gravityforms_verify', 10, 3 );
}

/**
 * Render the honeypot guard right before the closing </form> tag.
 *
 * @param array $form Form meta array.
 * @param bool  $ajax Whether the form uses AJAX submission.
 * @return void
 */
function init_plugin_suite_void_shield_gravityforms_render( $form, $ajax ) {
	unset( $ajax );

	if ( '1' !== get_option( 'init_plugin_suite_void_shield_enable_gravityforms', '0' ) ) {
		return;
	}

	$form_id = isset( $form['id'] ) ? absint( $form['id'] ) : 0;

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built internally with esc_attr()/esc_html() on every value, see field-guard.php.
	echo init_plugin_suite_void_shield_build_guard_markup( 'gf_' . $form_id );
}

/**
 * Mark a Gravity Forms entry as spam if it fails the honeypot check.
 *
 * @param bool  $is_spam Whether the entry is already flagged as spam.
 * @param array $form    Form meta array.
 * @param array $entry   Entry array.
 * @return bool
 */
function init_plugin_suite_void_shield_gravityforms_verify( $is_spam, $form, $entry ) {
	unset( $entry );

	if ( $is_spam ) {
		return $is_spam;
	}

	if ( '1' !== get_option( 'init_plugin_suite_void_shield_enable_gravityforms', '0' ) ) {
		return $is_spam;
	}

	$form_id = isset( $form['id'] ) ? absint( $form['id'] ) : 0;

	if ( ! init_plugin_suite_void_shield_is_submission_human( 'gf_' . $form_id ) ) {
		if ( class_exists( 'GFCommon' ) && method_exists( 'GFCommon', 'set_spam_filter' ) ) {
			GFCommon::set_spam_filter(
				$form_id,
				'Init Void Shield',
				__( 'Blocked by honeypot guard.', 'init-void-shield' )
			);
		}

		return true;
	}

	return $is_spam;
}
