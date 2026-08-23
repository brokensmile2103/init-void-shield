<?php
/**
 * Comment Form Guard
 *
 * Wires the shared field-guard engine (see includes/field-guard.php) into
 * the classic WordPress comment form, plus an optional REST API guard.
 *
 * @package Init_Void_Shield
 */

defined( 'ABSPATH' ) || exit;

// ------------------------------------------------------------------
// 1. Helpers
// ------------------------------------------------------------------

/**
 * Check if the honeypot system should be active for this request.
 *
 * @return bool
 */
function init_plugin_suite_void_shield_is_active() {
	// Master switch.
	if ( '1' !== get_option( 'init_plugin_suite_void_shield_enabled', '1' ) ) {
		return false;
	}

	// Skip logged-in users unless explicitly enabled.
	if ( is_user_logged_in() && '1' !== get_option( 'init_plugin_suite_void_shield_apply_to_logged_in', '0' ) ) {
		return false;
	}

	// Allow developers to force-disable per request.
	return ! apply_filters( 'init_plugin_suite_void_shield_skip_verification', false );
}

/**
 * Soft-kill: return 200 OK so the bot thinks it succeeded.
 *
 * @return void
 */
function init_plugin_suite_void_shield_kill_bot() {
	$message = apply_filters(
		'init_plugin_suite_void_shield_kill_response_message',
		__( 'Your comment is awaiting moderation.', 'init-void-shield' )
	);

	$title = apply_filters(
		'init_plugin_suite_void_shield_kill_response_title',
		__( 'Success', 'init-void-shield' )
	);

	$response_code = apply_filters(
		'init_plugin_suite_void_shield_kill_response_code',
		200
	);

	wp_die( esc_html( $message ), esc_html( $title ), array( 'response' => absint( $response_code ) ) );
}

// ------------------------------------------------------------------
// 2. Render honeypot fields on the comment form
// ------------------------------------------------------------------

add_action( 'comment_form_after_fields', 'init_plugin_suite_void_shield_render_fields' );

/**
 * Render the honeypot guard on the comment form.
 *
 * @return void
 */
function init_plugin_suite_void_shield_render_fields() {
	if ( ! init_plugin_suite_void_shield_is_active() ) {
		return;
	}

	$post_id = get_the_ID();

	if ( ! $post_id ) {
		return;
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built internally with esc_attr()/esc_html() on every value, see field-guard.php.
	echo init_plugin_suite_void_shield_build_guard_markup( 'comment_' . $post_id );
}

// ------------------------------------------------------------------
// 3. Verification (the judge)
// ------------------------------------------------------------------

add_filter( 'preprocess_comment', 'init_plugin_suite_void_shield_verify_submission' );

/**
 * Verify a comment submission against the honeypot guard.
 *
 * @param array $commentdata Comment data.
 * @return array
 */
function init_plugin_suite_void_shield_verify_submission( $commentdata ) {
	if ( ! init_plugin_suite_void_shield_is_active() ) {
		return $commentdata;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress core comment form does not require a nonce; this is an anti-spam gate.
	$post_id = isset( $_POST['comment_post_ID'] ) ? intval( $_POST['comment_post_ID'] ) : 0;

	if ( ! $post_id ) {
		init_plugin_suite_void_shield_record_block( 'comment_unknown', 'invalid_post_id' );
		init_plugin_suite_void_shield_kill_bot();
	}

	if ( ! init_plugin_suite_void_shield_is_submission_human( 'comment_' . $post_id ) ) {
		init_plugin_suite_void_shield_kill_bot();
	}

	return $commentdata;
}

// ------------------------------------------------------------------
// 4. REST API guard (opt-in)
// ------------------------------------------------------------------
// The classic comment form flow above is enforced via `preprocess_comment`,
// which never runs for comments submitted through `POST /wp/v2/comments`.
// REST-submitted comments cannot carry a honeypot/JS token in the first
// place, so this layer is a separate, explicit opt-in for site owners who
// don't rely on the REST comments endpoint and want it closed to bots.
//

add_filter( 'rest_pre_insert_comment', 'init_plugin_suite_void_shield_guard_rest_comment', 10, 2 );

/**
 * Optionally block comments submitted via the REST API.
 *
 * @param array|WP_Error  $prepared_comment Prepared comment data for wp_insert_comment().
 * @param WP_REST_Request $request          The current request.
 * @return array|WP_Error
 */
function init_plugin_suite_void_shield_guard_rest_comment( $prepared_comment, $request ) {
	if ( is_wp_error( $prepared_comment ) ) {
		return $prepared_comment;
	}

	if ( '1' !== get_option( 'init_plugin_suite_void_shield_block_rest', '0' ) ) {
		return $prepared_comment;
	}

	// Skip logged-in users unless explicitly enabled, same rule as the form-based gate.
	if ( is_user_logged_in() && '1' !== get_option( 'init_plugin_suite_void_shield_apply_to_logged_in', '0' ) ) {
		return $prepared_comment;
	}

	if ( apply_filters( 'init_plugin_suite_void_shield_skip_verification', false, $request ) ) {
		return $prepared_comment;
	}

	init_plugin_suite_void_shield_record_block( 'rest_comment', 'rest_blocked' );

	return new WP_Error(
		'init_plugin_suite_void_shield_rest_blocked',
		apply_filters(
			'init_plugin_suite_void_shield_rest_blocked_message',
			__( 'Comments submitted via the REST API are not accepted on this site.', 'init-void-shield' )
		),
		array( 'status' => 403 )
	);
}
