<?php
/**
 * Honeypot Core
 * 4-layer defense: dynamic fields, CSS-clipped traps, signed time tokens, JS verification.
 */

defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------------
 * 1. Helpers
 * ------------------------------------------------------------------ */

/**
 * Build a dynamic field name from post ID + type + site salt.
 *
 * @param int    $post_id Post ID.
 * @param string $type    Field type suffix.
 * @return string
 */
function init_plugin_suite_void_shield_get_field_name( $post_id, $type ) {
    $hash = md5( $post_id . $type . wp_salt( 'nonce' ) );
    return 'ipsvs_' . substr( $hash, 0, 8 );
}

/**
 * Check if the honeypot system should be active for this request.
 *
 * @return bool
 */
function init_plugin_suite_void_shield_is_active() {
    // Master switch.
    if ( ! get_option( 'init_plugin_suite_void_shield_enabled', '1' ) ) {
        return false;
    }

    // Skip logged-in users unless explicitly enabled.
    if ( is_user_logged_in() && ! get_option( 'init_plugin_suite_void_shield_apply_to_logged_in', '0' ) ) {
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
    exit;
}

/* ------------------------------------------------------------------
 * 2. Render honeypot fields (Layer 1-4)
 * ------------------------------------------------------------------ */

add_action( 'comment_form_after_fields', 'init_plugin_suite_void_shield_render_fields' );

function init_plugin_suite_void_shield_render_fields() {
    if ( ! init_plugin_suite_void_shield_is_active() ) {
        return;
    }

    $post_id = get_the_ID();
    if ( ! $post_id ) {
        return;
    }

    // Dynamic field names (Layer 1).
    $hp_text   = init_plugin_suite_void_shield_get_field_name( $post_id, 'text' );
    $hp_check  = init_plugin_suite_void_shield_get_field_name( $post_id, 'checkbox' );
    $time_name = init_plugin_suite_void_shield_get_field_name( $post_id, 'time' );
    $hash_name = init_plugin_suite_void_shield_get_field_name( $post_id, 'hash' );
    $js_name   = init_plugin_suite_void_shield_get_field_name( $post_id, 'js_token' );

    $current_time = time();
    $time_hash    = wp_hash( $current_time . 'init_plugin_suite_void_shield_secret' );

    $min_time = absint( apply_filters( 'init_plugin_suite_void_shield_min_time', 3 ) );
    $js_delay = absint( apply_filters( 'init_plugin_suite_void_shield_js_delay', 1000 ) );

    // Layer 2: CSS-clipped container.
    $html = '<div aria-hidden="true" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0;">';

    // Trap 1: text field.
    $html .= '<label for="' . esc_attr( $hp_text ) . '">' . esc_html__( 'If you are human, please leave this field blank.', 'init-void-shield' ) . '</label>';
    $html .= '<input type="text" name="' . esc_attr( $hp_text ) . '" id="' . esc_attr( $hp_text ) . '" value="" tabindex="-1" autocomplete="off" />';

    // Trap 2: checkbox.
    $html .= '<label><input type="checkbox" name="' . esc_attr( $hp_check ) . '" value="1" tabindex="-1" /> ' . esc_html__( 'Do not check this box.', 'init-void-shield' ) . '</label>';

    // Layer 3: signed time token.
    $html .= '<input type="hidden" name="' . esc_attr( $time_name ) . '" value="' . esc_attr( $current_time ) . '" />';
    $html .= '<input type="hidden" name="' . esc_attr( $hash_name ) . '" value="' . esc_attr( $time_hash ) . '" />';

    // Layer 4: JS token placeholder.
    $html .= '<input type="hidden" name="' . esc_attr( $js_name ) . '" id="' . esc_attr( $js_name ) . '" value="" />';

    $html .= '</div>';

    // Allow themes/plugins to modify the honeypot HTML block.
    echo apply_filters( 'init_plugin_suite_void_shield_honeypot_html', $html, $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

    // Layer 4: JavaScript token injector.
    // WPCS-compliant inline script via wp_get_inline_script_tag() (WP 5.7+, guaranteed by our min requirement).
    $js = "document.addEventListener('DOMContentLoaded', function() {
        var jsInput = document.getElementById('" . esc_js( $js_name ) . "');
        if (jsInput) {
            setTimeout(function() {
                jsInput.value = 'human_verified';
            }, " . absint( $js_delay ) . ");
        }
    });";

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_inline_script_tag() escapes internally.
    echo wp_get_inline_script_tag(
        $js,
        array(
            'id'   => 'init-plugin-suite-void-shield-inline',
            'type' => 'text/javascript',
        )
    );
}

/* ------------------------------------------------------------------
 * 3. Verification (the judge)
 * ------------------------------------------------------------------ */

add_filter( 'preprocess_comment', 'init_plugin_suite_void_shield_verify_submission' );

function init_plugin_suite_void_shield_verify_submission( $commentdata ) {
    if ( ! init_plugin_suite_void_shield_is_active() ) {
        return $commentdata;
    }

    // Basic header check.
    if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
        init_plugin_suite_void_shield_kill_bot();
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress core comment form does not require a nonce; this is an anti-spam gate.
    $post_id = isset( $_POST['comment_post_ID'] ) ? intval( $_POST['comment_post_ID'] ) : 0;
    if ( ! $post_id ) {
        init_plugin_suite_void_shield_kill_bot();
    }

    // Rebuild dynamic field names.
    $hp_text   = init_plugin_suite_void_shield_get_field_name( $post_id, 'text' );
    $hp_check  = init_plugin_suite_void_shield_get_field_name( $post_id, 'checkbox' );
    $time_name = init_plugin_suite_void_shield_get_field_name( $post_id, 'time' );
    $hash_name = init_plugin_suite_void_shield_get_field_name( $post_id, 'hash' );
    $js_name   = init_plugin_suite_void_shield_get_field_name( $post_id, 'js_token' );

    // Gate 1: Honeypots must stay empty.
    // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if ( ! empty( $_POST[ $hp_text ] ) || ! empty( $_POST[ $hp_check ] ) ) {
        init_plugin_suite_void_shield_kill_bot();
    }

    // Gate 2: JS token must be present and correct.
    // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if ( empty( $_POST[ $js_name ] ) || 'human_verified' !== $_POST[ $js_name ] ) {
        init_plugin_suite_void_shield_kill_bot();
    }

    // Gate 3: Time token must exist and be valid.
    // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if ( empty( $_POST[ $time_name ] ) || empty( $_POST[ $hash_name ] ) ) {
        init_plugin_suite_void_shield_kill_bot();
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $submit_time   = intval( $_POST[ $time_name ] );
    // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
    $provided_hash = sanitize_text_field( wp_unslash( $_POST[ $hash_name ] ) );
    $expected_hash = wp_hash( $submit_time . 'init_plugin_suite_void_shield_secret' );

    if ( ! hash_equals( $expected_hash, $provided_hash ) ) {
        init_plugin_suite_void_shield_kill_bot();
    }

    // Gate 4: Speed limit (human-friendly threshold).
    $min_time = absint( apply_filters( 'init_plugin_suite_void_shield_min_time', 3 ) );
    $time_diff = time() - $submit_time;
    if ( $time_diff < $min_time ) {
        init_plugin_suite_void_shield_kill_bot();
    }

    return $commentdata;
}

/* ------------------------------------------------------------------
 * 4. Layer 5: REST API guard (opt-in)
 * ------------------------------------------------------------------
 * The classic comment form flow above is enforced via `preprocess_comment`,
 * which never runs for comments submitted through `POST /wp/v2/comments`.
 * REST-submitted comments cannot carry a honeypot/JS token in the first
 * place, so this layer is a separate, explicit opt-in for site owners who
 * don't rely on the REST comments endpoint and want it closed to bots.
 */

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

    if ( ! get_option( 'init_plugin_suite_void_shield_block_rest', '0' ) ) {
        return $prepared_comment;
    }

    // Skip logged-in users unless explicitly enabled, same rule as the form-based gate.
    if ( is_user_logged_in() && ! get_option( 'init_plugin_suite_void_shield_apply_to_logged_in', '0' ) ) {
        return $prepared_comment;
    }

    if ( apply_filters( 'init_plugin_suite_void_shield_skip_verification', false, $request ) ) {
        return $prepared_comment;
    }

    return new WP_Error(
        'init_plugin_suite_void_shield_rest_blocked',
        apply_filters(
            'init_plugin_suite_void_shield_rest_blocked_message',
            __( 'Comments submitted via the REST API are not accepted on this site.', 'init-void-shield' )
        ),
        array( 'status' => 403 )
    );
}
