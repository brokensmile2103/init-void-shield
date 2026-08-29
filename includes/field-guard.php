<?php
/**
 * Field Guard
 *
 * Shared honeypot engine: dynamic field names, CSS-trap rendering, signed
 * time tokens, JS/headless verification. Reused by the comment form, the
 * default WP login/registration/lost-password forms, and the optional
 * Contact Form 7 / WPForms / Gravity Forms integrations.
 *
 * @package Init_Void_Shield
 */

defined( 'ABSPATH' ) || exit;

// ------------------------------------------------------------------
// 1. Field prefix + dynamic field names
// ------------------------------------------------------------------

/**
 * Sanitize a custom honeypot field name prefix.
 *
 * @param string $prefix Raw prefix.
 * @return string
 */
function init_plugin_suite_void_shield_sanitize_field_prefix( $prefix ) {
	$prefix = strtolower( (string) $prefix );
	$prefix = preg_replace( '/[^a-z0-9_]/', '', $prefix );
	$prefix = substr( (string) $prefix, 0, 16 );

	if ( '' === $prefix ) {
		$prefix = 'ipsvs';
	}

	return $prefix;
}

/**
 * Get the active honeypot field name prefix.
 *
 * @return string
 */
function init_plugin_suite_void_shield_get_field_prefix() {
	$prefix = get_option( 'init_plugin_suite_void_shield_field_prefix', 'ipsvs' );

	return init_plugin_suite_void_shield_sanitize_field_prefix( $prefix );
}

/**
 * Build a dynamic field name from context + type + site salt.
 *
 * @param string $context Guard context (e.g. comment_123, login, cf7_4).
 * @param string $type    Field type suffix.
 * @return string
 */
function init_plugin_suite_void_shield_get_field_name( $context, $type ) {
	$hash = md5( $context . '|' . $type . '|' . wp_salt( 'nonce' ) );

	return init_plugin_suite_void_shield_get_field_prefix() . '_' . substr( $hash, 0, 8 );
}

// ------------------------------------------------------------------
// 2. Request helpers
// ------------------------------------------------------------------

/**
 * Check whether the current request is a POST request.
 *
 * @return bool
 */
function init_plugin_suite_void_shield_is_post_request() {
	if ( ! isset( $_SERVER['REQUEST_METHOD'] ) ) {
		return false;
	}

	$method = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) );

	return 'POST' === strtoupper( $method );
}

// ------------------------------------------------------------------
// 3. CSS-aware bot bypass: rotating hidden-style variants
// ------------------------------------------------------------------

/**
 * Get the pool of inline CSS techniques used to visually hide honeypot
 * fields. None rely on `display:none` or `visibility:hidden`, since those
 * are the two patterns CSS-aware bots specifically check for and skip.
 *
 * @return array
 */
function init_plugin_suite_void_shield_get_hidden_style_variants() {
	$variants = array(
		'position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;',
		'position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden;border:0;',
		'position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap;border:0;padding:0;margin:-1px;',
		'position:absolute;height:0;width:0;overflow:hidden;opacity:0;pointer-events:none;border:0;',
	);

	return apply_filters( 'init_plugin_suite_void_shield_hidden_style_variants', $variants );
}

/**
 * Pick the inline CSS used to hide the guard container for this render.
 *
 * @return string
 */
function init_plugin_suite_void_shield_get_hidden_style() {
	$variants = init_plugin_suite_void_shield_get_hidden_style_variants();

	if ( empty( $variants ) || ! is_array( $variants ) ) {
		return 'position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0;';
	}

	$variants = array_values( $variants );

	if ( '1' !== get_option( 'init_plugin_suite_void_shield_enable_css_rotation', '1' ) ) {
		return $variants[0];
	}

	$index = wp_rand( 0, count( $variants ) - 1 );

	return $variants[ $index ];
}

// ------------------------------------------------------------------
// 4. Time token signing
// ------------------------------------------------------------------

/**
 * Compute the signed hash for a time token, bound to both the submit time
 * AND the guard context.
 *
 * Binding the hash to the context prevents a token captured from one form
 * (e.g. a public comment form) from being replayed against a different,
 * more sensitive context (e.g. the login guard) that happens to be
 * rendered on the same site. Without this, the hash was only a function of
 * the timestamp, so any valid (time, hash) pair worked for every context.
 *
 * @param int    $submit_time Unix timestamp the token was issued at.
 * @param string $context     Guard context the token was issued for.
 * @return string
 */
function init_plugin_suite_void_shield_get_time_hash( $submit_time, $context ) {
	return wp_hash( $submit_time . '|' . (string) $context . '|init_plugin_suite_void_shield_secret' );
}

// ------------------------------------------------------------------
// 5. Render
// ------------------------------------------------------------------

/**
 * Build the full honeypot guard markup (hidden trap fields + signed time
 * token + JS/headless verification script) for a given context.
 *
 * @param string $context Guard context (e.g. comment_123, login, cf7_4).
 * @return string
 */
function init_plugin_suite_void_shield_build_guard_markup( $context ) {
	$context = (string) $context;

	$hp_text   = init_plugin_suite_void_shield_get_field_name( $context, 'text' );
	$hp_check  = init_plugin_suite_void_shield_get_field_name( $context, 'checkbox' );
	$time_name = init_plugin_suite_void_shield_get_field_name( $context, 'time' );
	$hash_name = init_plugin_suite_void_shield_get_field_name( $context, 'hash' );
	$js_name   = init_plugin_suite_void_shield_get_field_name( $context, 'js_token' );

	$current_time = time();
	$time_hash    = init_plugin_suite_void_shield_get_time_hash( $current_time, $context );

	// Read the saved setting first; the filter still allows a per-request developer override on top of it.
	$js_delay = absint( apply_filters( 'init_plugin_suite_void_shield_js_delay', absint( get_option( 'init_plugin_suite_void_shield_js_delay', 1000 ) ) ) );

	// Trap 1: text field.
	$text_trap  = '<label for="' . esc_attr( $hp_text ) . '">' . esc_html__( 'If you are human, please leave this field blank.', 'init-void-shield' ) . '</label>';
	$text_trap .= '<input type="text" name="' . esc_attr( $hp_text ) . '" id="' . esc_attr( $hp_text ) . '" value="" tabindex="-1" autocomplete="off" />';

	// Trap 2: checkbox.
	$check_trap = '<label><input type="checkbox" name="' . esc_attr( $hp_check ) . '" value="1" tabindex="-1" /> ' . esc_html__( 'Do not check this box.', 'init-void-shield' ) . '</label>';

	$traps = array( $text_trap, $check_trap );

	// CSS-aware bypass: also vary the order the two traps render in.
	if ( '1' === get_option( 'init_plugin_suite_void_shield_enable_css_rotation', '1' ) && 1 === wp_rand( 0, 1 ) ) {
		$traps = array_reverse( $traps );
	}

	$hidden_style = init_plugin_suite_void_shield_get_hidden_style();

	$html  = '<div aria-hidden="true" style="' . esc_attr( $hidden_style ) . '">';
	$html .= implode( '', $traps );
	$html .= '<input type="hidden" name="' . esc_attr( $time_name ) . '" value="' . esc_attr( $current_time ) . '" />';
	$html .= '<input type="hidden" name="' . esc_attr( $hash_name ) . '" value="' . esc_attr( $time_hash ) . '" />';
	$html .= '<input type="hidden" name="' . esc_attr( $js_name ) . '" id="' . esc_attr( $js_name ) . '" value="" />';
	$html .= '</div>';

	// Allow themes/plugins to modify the honeypot HTML block.
	$html = apply_filters( 'init_plugin_suite_void_shield_honeypot_html', $html, $context );

	$headless_enabled     = ( '1' === get_option( 'init_plugin_suite_void_shield_headless_detection', '1' ) ) ? 'true' : 'false';
	$interaction_required = ( '1' === get_option( 'init_plugin_suite_void_shield_require_interaction', '0' ) ) ? 'true' : 'false';

	// Layer: JavaScript + lightweight headless-browser detection, plus an
	// optional real-interaction check. `navigator.webdriver` is set to true
	// by Selenium/Puppeteer/Playwright unless deliberately masked, and a
	// real browser window never reports 0x0 outer dimensions, so both are
	// cheap, low-false-positive signals. The interaction check catches
	// bots that simply sleep() past the delay: it listens for one genuine
	// mouse, keyboard, touch, or scroll event (any of which a real visitor
	// naturally triggers while reading/filling the page) before the timer
	// fires, without recording anything about that event.
	$js = "document.addEventListener('DOMContentLoaded', function() {
		var jsInput = document.getElementById('" . esc_js( $js_name ) . "');
		if (!jsInput) {
			return;
		}
		var headlessCheckEnabled = " . $headless_enabled . ';
		var interactionRequired = ' . $interaction_required . ";
		var interacted = false;
		if (interactionRequired) {
			var markInteracted = function() { interacted = true; };
			['mousemove', 'keydown', 'pointerdown', 'touchstart', 'scroll'].forEach(function(evt) {
				document.addEventListener(evt, markInteracted, { passive: true, once: true });
			});
		}
		setTimeout(function() {
			var isBot = false;
			if (headlessCheckEnabled) {
				if (navigator.webdriver === true) {
					isBot = true;
				}
				if (window.outerWidth === 0 && window.outerHeight === 0) {
					isBot = true;
				}
			}
			if (isBot) {
				jsInput.value = 'bot_detected';
			} else if (interactionRequired && !interacted) {
				jsInput.value = 'no_interaction';
			} else {
				jsInput.value = 'human_verified';
			}
		}, " . absint( $js_delay ) . ');
	});';

	// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- inline script tag, not an enqueued asset.
	$script = wp_get_inline_script_tag(
		$js,
		array(
			'id'   => 'init-plugin-suite-void-shield-inline-' . sanitize_html_class( $context ),
			'type' => 'text/javascript',
		)
	);

	return $html . $script;
}

// ------------------------------------------------------------------
// 6. Verify
// ------------------------------------------------------------------

/**
 * Verify a submission against the honeypot guard for a given context.
 * Records a stats entry on failure via init_plugin_suite_void_shield_record_block().
 *
 * @param string $context Guard context (must match the one used to render).
 * @return bool True if the submission looks human, false if it looks like a bot.
 */
function init_plugin_suite_void_shield_is_submission_human( $context ) {
	$context = (string) $context;

	if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
		init_plugin_suite_void_shield_record_block( $context, 'no_user_agent' );
		return false;
	}

	$hp_text   = init_plugin_suite_void_shield_get_field_name( $context, 'text' );
	$hp_check  = init_plugin_suite_void_shield_get_field_name( $context, 'checkbox' );
	$time_name = init_plugin_suite_void_shield_get_field_name( $context, 'time' );
	$hash_name = init_plugin_suite_void_shield_get_field_name( $context, 'hash' );
	$js_name   = init_plugin_suite_void_shield_get_field_name( $context, 'js_token' );

	// Gate 1: honeypot fields must stay empty.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- anti-spam gate, not a nonce-protected action.
	if ( ! empty( $_POST[ $hp_text ] ) || ! empty( $_POST[ $hp_check ] ) ) {
		init_plugin_suite_void_shield_record_block( $context, 'honeypot_field' );
		return false;
	}

	// Gate 2: JS / headless-detection / interaction token.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
	$js_value = isset( $_POST[ $js_name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $js_name ] ) ) : '';

	if ( 'bot_detected' === $js_value ) {
		init_plugin_suite_void_shield_record_block( $context, 'headless_browser' );
		return false;
	}

	if ( 'no_interaction' === $js_value ) {
		init_plugin_suite_void_shield_record_block( $context, 'no_interaction' );
		return false;
	}

	if ( 'human_verified' !== $js_value ) {
		init_plugin_suite_void_shield_record_block( $context, 'js_token' );
		return false;
	}

	// Gate 3: signed time token must exist and be valid for THIS context.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( empty( $_POST[ $time_name ] ) || empty( $_POST[ $hash_name ] ) ) {
		init_plugin_suite_void_shield_record_block( $context, 'time_token_missing' );
		return false;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$submit_time = intval( $_POST[ $time_name ] );
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
	$provided_hash = sanitize_text_field( wp_unslash( $_POST[ $hash_name ] ) );
	$expected_hash = init_plugin_suite_void_shield_get_time_hash( $submit_time, $context );

	if ( ! hash_equals( $expected_hash, $provided_hash ) ) {
		init_plugin_suite_void_shield_record_block( $context, 'time_token_invalid' );
		return false;
	}

	// Gate 4: speed limit (human-friendly minimum) and freshness ceiling
	// (rejects a token that is older than the configured maximum age). The
	// ceiling exists so a token scraped once cannot be cached and replayed
	// indefinitely; it only has to be generous enough that a real visitor
	// who takes a while to read the page and fill the form is never caught
	// by it.
	$min_time = absint( apply_filters( 'init_plugin_suite_void_shield_min_time', absint( get_option( 'init_plugin_suite_void_shield_min_time', 3 ) ) ) );
	$max_time = absint( apply_filters( 'init_plugin_suite_void_shield_max_time', absint( get_option( 'init_plugin_suite_void_shield_max_time', 3600 ) ) ) );

	$time_diff = time() - $submit_time;

	if ( $time_diff < $min_time ) {
		init_plugin_suite_void_shield_record_block( $context, 'too_fast' );
		return false;
	}

	if ( $max_time > 0 && $time_diff > $max_time ) {
		init_plugin_suite_void_shield_record_block( $context, 'token_expired' );
		return false;
	}

	return true;
}
