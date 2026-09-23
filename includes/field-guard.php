<?php
/**
 * Field Guard
 *
 * Shared honeypot engine: dynamic field names, CSS-trap rendering, signed
 * time tokens, signed JS proof, JS/headless verification, Submit Hold, and
 * the cross-site submission check. Reused by the comment form, the default
 * WP login/registration/lost-password forms, and every optional form
 * plugin, e-commerce, and community integration.
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
// 3b. Non-browser User-Agent signatures
// ------------------------------------------------------------------

/**
 * Get the list of User-Agent substrings associated with common scripted
 * HTTP clients (as opposed to real browsers). A real browser executing the
 * JS layer always sends its own browser UA string; these signatures only
 * ever appear on requests built by a script or library, so matching one is
 * a near-zero-false-positive signal that no human loaded the page. This
 * catches the class of bot that never runs JS at all -- e.g. a script that
 * parses the static HTML, skips the honeypot fields, and replays the
 * baked-in time/hash token -- which the honeypot and JS layers alone
 * cannot see, since both are things a careful non-JS script can copy.
 *
 * The list also includes the default User-Agent of headless browser builds
 * (`HeadlessChrome`, `PhantomJS`). A person browsing with a normal Chrome,
 * Edge, Opera, Brave, or Chromium window never sends either token -- they
 * only appear when a browser engine is launched without a window by an
 * automation framework (Puppeteer, Playwright, Selenium) that did not
 * bother to override its UA, so they are just as reliable a signal.
 *
 * @return array
 */
function init_plugin_suite_void_shield_get_blocked_user_agent_signatures() {
	$signatures = array(
		'curl',
		'wget',
		'python-requests',
		'python-urllib',
		'go-http-client',
		'okhttp',
		'apache-httpclient',
		'libwww-perl',
		'scrapy',
		'postmanruntime',
		'node-fetch',
		'guzzlehttp',
		'java/',
		'httpclient',
		'headlesschrome',
		'phantomjs',
		'python-httpx',
		'aiohttp',
		'axios/',
		'undici',
		'httpie',
		'colly',
	);

	return apply_filters( 'init_plugin_suite_void_shield_blocked_user_agent_signatures', $signatures );
}

/**
 * Check whether a User-Agent string matches a known non-browser signature.
 *
 * @param string $user_agent Raw User-Agent header value.
 * @return bool
 */
function init_plugin_suite_void_shield_is_bot_user_agent( $user_agent ) {
	$user_agent = strtolower( (string) $user_agent );

	if ( '' === $user_agent ) {
		return false;
	}

	foreach ( init_plugin_suite_void_shield_get_blocked_user_agent_signatures() as $signature ) {
		$signature = strtolower( (string) $signature );

		if ( '' !== $signature && false !== strpos( $user_agent, $signature ) ) {
			return true;
		}
	}

	return false;
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

/**
 * Compute the JS proof expected back from a genuine browser for a given
 * time token and guard context.
 *
 * Before 1.11 the JS layer wrote the fixed string `human_verified` into
 * the hidden token field. That value was the same on every site running
 * this plugin, so a bot written specifically against the plugin could
 * simply post it without ever executing the script, clearing the whole JS
 * gate for free. The proof is now an HMAC bound to the exact time token
 * and context of the form it was issued for (and to this site's secret
 * keys via wp_hash()), so it cannot be precomputed, cannot be reused on a
 * different form, and changes every second a page is rendered.
 *
 * @param int    $submit_time Unix timestamp of the matching time token.
 * @param string $context     Guard context the token was issued for.
 * @return string
 */
function init_plugin_suite_void_shield_get_js_proof( $submit_time, $context ) {
	return wp_hash( (int) $submit_time . '|' . (string) $context . '|init_plugin_suite_void_shield_js_proof' );
}

/**
 * Seal a JS proof for embedding in page output.
 *
 * The proof is never printed in plain text. It is reversed and every
 * character code is XOR-ed with a per-render random key, and only the
 * inline script knows how to reassemble it. This is deliberately
 * lightweight obfuscation, not cryptography -- a bot that actually runs
 * the page's JavaScript (or that is written to reverse this exact routine)
 * can still recover the value. What it removes is the far larger class of
 * bots that scrape the static HTML and fill in whatever hidden field
 * values they find: there is no longer a ready-to-copy value anywhere in
 * the markup, so they fail the proof check even though the rest of the
 * form looks perfectly valid to them.
 *
 * @param string $proof Proof from init_plugin_suite_void_shield_get_js_proof().
 * @return array { @type int $k XOR key. @type int[] $p Sealed character codes. }
 */
function init_plugin_suite_void_shield_seal_js_proof( $proof ) {
	$key   = wp_rand( 1, 127 );
	$codes = array();

	foreach ( str_split( strrev( (string) $proof ) ) as $char ) {
		$codes[] = ord( $char ) ^ $key;
	}

	return array(
		'k' => $key,
		'p' => $codes,
	);
}

// ------------------------------------------------------------------
// 4b. Submit Hold (natively-submitted forms)
// ------------------------------------------------------------------

/**
 * Determine whether a guard context belongs to a form that submits through
 * a normal browser form submission (full page POST), as opposed to a form
 * plugin that collects and sends its fields through its own AJAX handler.
 *
 * On these forms, the inline script holds a submission that arrives before
 * the guard is ready (JS token not issued yet, or Minimum Submit Time not
 * reached yet) and releases it automatically the moment it is, instead of
 * letting it go through early and be rejected server-side. The classic
 * case is a login form the browser has already autofilled: the visitor can
 * click "Log in" well under a second after the page loads, before the JS
 * token delay has elapsed. Nothing is relaxed on the server: every gate
 * still requires exactly what it required before, the held submission
 * simply reaches the server once it satisfies them.
 *
 * AJAX-driven form plugins (Contact Form 7, WPForms, Gravity Forms) are
 * intentionally excluded: each serializes and sends the form from its own
 * script, which a held-and-replayed native submit event would conflict
 * with, and a person filling in a contact form from scratch never submits
 * it within the first second or two anyway.
 *
 * @param string $context Guard context.
 * @return bool
 */
function init_plugin_suite_void_shield_is_submit_hold_context( $context ) {
	$context  = (string) $context;
	$prefixes = array( 'comment_', 'woocommerce_', 'bbpress_' );
	$exact    = array( 'login', 'register', 'lostpassword', 'multisite_signup', 'buddypress_register' );
	$enabled  = in_array( $context, $exact, true );

	if ( ! $enabled ) {
		foreach ( $prefixes as $prefix ) {
			if ( 0 === strpos( $context, $prefix ) ) {
				$enabled = true;
				break;
			}
		}
	}

	/**
	 * Filter whether Submit Hold is used for a guard context.
	 *
	 * Only enable it for a form that submits through a normal browser form
	 * submission. Disabling it never weakens verification -- an early
	 * submission is then simply sent as-is and rejected server-side, which
	 * was the behavior before 1.11.
	 *
	 * @param bool   $enabled Whether Submit Hold is used for this context.
	 * @param string $context Guard context.
	 */
	return (bool) apply_filters( 'init_plugin_suite_void_shield_submit_hold_enabled', $enabled, $context );
}

// ------------------------------------------------------------------
// 4c. Cross-site submission check (Sec-Fetch-Site)
// ------------------------------------------------------------------

/**
 * Check whether the current request was sent cross-site according to the
 * browser's own `Sec-Fetch-Site` request header.
 *
 * Modern browsers attach `Sec-Fetch-Site` to every request themselves, and
 * page scripts cannot set or alter it. A genuine visitor submitting a form
 * on this site always produces `same-origin` (or `same-site`, for a form on
 * a sibling subdomain). `cross-site` means the POST was fired from a page
 * on a different site -- a spam page auto-submitting a hidden copy of the
 * form, or a bot driving a browser from somewhere else -- which a real
 * visitor using this site never does.
 *
 * A missing header is deliberately NOT treated as cross-site: older
 * browsers and plain scripts do not send it at all, and those are already
 * covered by the other layers. Only a positive `cross-site` value counts,
 * which keeps this check effectively free of false positives.
 *
 * @return bool
 */
function init_plugin_suite_void_shield_is_cross_site_request() {
	if ( empty( $_SERVER['HTTP_SEC_FETCH_SITE'] ) ) {
		return false;
	}

	$site = strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_SEC_FETCH_SITE'] ) ) );

	return 'cross-site' === $site;
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
	$js_proof     = init_plugin_suite_void_shield_get_js_proof( $current_time, $context );

	// Read the saved setting first; the filter still allows a per-request developer override on top of it.
	$js_delay = absint( apply_filters( 'init_plugin_suite_void_shield_js_delay', absint( get_option( 'init_plugin_suite_void_shield_js_delay', 1000 ) ) ) );

	// A small random jitter added on top of the configured delay, so the
	// exact wait a bot needs to sleep through cannot be read from the page
	// source and precomputed. Purely additive -- the effective delay is
	// never shorter than the configured value, so this cannot create a new
	// false-positive path for a genuine visitor, only a slightly longer one.
	$js_delay_jitter_max = absint( apply_filters( 'init_plugin_suite_void_shield_js_delay_jitter_max', 400 ) );
	$js_delay_actual     = $js_delay + ( $js_delay_jitter_max > 0 ? wp_rand( 0, $js_delay_jitter_max ) : 0 );

	// Minimum time, from script init, that must pass before a genuine
	// interaction event is allowed to count. Without this, a bot could
	// satisfy "Require Real User Interaction" by dispatching a single
	// synthetic event the instant the script runs, defeating the point of
	// the check. Only relevant when that setting is enabled.
	$min_interaction_delay = absint( apply_filters( 'init_plugin_suite_void_shield_min_interaction_delay', 150 ) );

	// Trap 1: text field. `readonly` is the key attribute here: every major
	// browser autofill engine and third-party password manager (Chrome,
	// Firefox, Safari, Edge, LastPass, 1Password, Bitwarden, ...) explicitly
	// skips readonly (and disabled) fields when deciding what to fill in --
	// it is not merely a convention, it is how those engines are built. This
	// field's CSS hiding (see init_plugin_suite_void_shield_get_hidden_style())
	// deliberately avoids display:none/visibility:hidden so CSS-aware bots
	// can't detect and skip it, but that same choice makes it fully "visible"
	// to autofill heuristics, which do not care about CSS at all -- only
	// about the field's own attributes. On a login form especially, that
	// visibility previously meant a browser autofilling saved credentials
	// into the real username field could also drop a value into this one,
	// since both live in the same <form>, tripping Gate 1 below and blocking
	// a genuine visitor who never touched the trap themselves. `readonly`
	// closes that gap without narrowing bot coverage: it blocks native
	// browser/password-manager writes, but does nothing to stop a scripted
	// HTTP client from posting the field name with junk data (readonly is a
	// rendering-layer restriction, irrelevant to a raw POST body), and does
	// nothing to stop a JS-driven headless browser from setting `.value`
	// directly (readonly only blocks *keyboard* entry, not scripted
	// assignment) -- both bot classes still land in the trap exactly as
	// before. `autocomplete="off"` is left in place as a second, weaker
	// layer, since some browsers honor it for non-login-adjacent forms even
	// though Chrome/Firefox largely ignore it around login fields.
	$text_trap  = '<label for="' . esc_attr( $hp_text ) . '">' . esc_html__( 'If you are human, please leave this field blank.', 'init-void-shield' ) . '</label>';
	$text_trap .= '<input type="text" name="' . esc_attr( $hp_text ) . '" id="' . esc_attr( $hp_text ) . '" value="" tabindex="-1" autocomplete="off" readonly="readonly" />';

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
	$html .= '<input type="hidden" name="' . esc_attr( $time_name ) . '" id="' . esc_attr( $time_name ) . '" value="' . esc_attr( $current_time ) . '" />';
	$html .= '<input type="hidden" name="' . esc_attr( $hash_name ) . '" id="' . esc_attr( $hash_name ) . '" value="' . esc_attr( $time_hash ) . '" />';
	$html .= '<input type="hidden" name="' . esc_attr( $js_name ) . '" id="' . esc_attr( $js_name ) . '" value="" />';
	$html .= '</div>';

	// Allow themes/plugins to modify the honeypot HTML block.
	$html = apply_filters( 'init_plugin_suite_void_shield_honeypot_html', $html, $context );

	$headless_enabled     = ( '1' === get_option( 'init_plugin_suite_void_shield_headless_detection', '1' ) ) ? 'true' : 'false';
	$interaction_required = ( '1' === get_option( 'init_plugin_suite_void_shield_require_interaction', '0' ) ) ? 'true' : 'false';
	$lazy_fetch_enabled   = ( '1' === get_option( 'init_plugin_suite_void_shield_lazy_fetch', '0' ) ) ? 'true' : 'false';
	$submit_hold_enabled  = init_plugin_suite_void_shield_is_submit_hold_context( $context ) ? 'true' : 'false';
	$rest_base_url        = esc_url_raw( rest_url( INIT_PLUGIN_SUITE_VOID_SHIELD_NAMESPACE . '/token' ) );

	// Submit Hold timing. The held submission is only released once the
	// server-side Minimum Submit Time for this context is guaranteed to be
	// met: the server compares whole-second timestamps, and floor(a + d) -
	// floor(a) is never less than floor(d), so waiting at least the minimum
	// (plus a small safety margin for clock/network jitter) measured from a
	// moment that is never earlier than the token's own issue time is
	// always enough. The anchor is script init for a baked-in token (the
	// page was necessarily rendered before it), or the moment a Lazy Fetch
	// response arrived for a refreshed one (the server issued it before
	// replying). A pending Lazy Fetch request is waited for, up to a cap,
	// so a submission never races a token that is about to be replaced.
	$min_submit_ms  = ( init_plugin_suite_void_shield_get_min_time_for_context( $context ) * 1000 ) + 300;
	$fetch_wait_max = absint( apply_filters( 'init_plugin_suite_void_shield_submit_hold_fetch_wait', 4000, $context ) );

	// Layer: JavaScript + lightweight headless-browser detection, plus an
	// optional real-interaction check. The script produces one of three
	// verdicts once the (jittered) delay elapses:
	//
	// - 'bot': a cheap, low-false-positive automation signal was found.
	// `navigator.webdriver` is set by Selenium/Puppeteer/Playwright unless
	// deliberately masked; a real browser window never reports 0x0 outer
	// dimensions; `HeadlessChrome`/`PhantomJS` only appear in the UA of a
	// windowless automation build; `callPhantom`/`_phantom`/`__nightmare`/
	// `domAutomation*` globals are injected only by those frameworks; and
	// ChromeDriver leaves `$cdc_`/`$wdc_`/`cdc_` markers on `document` or
	// `window`. None of these exist in an ordinary browsing session.
	// - 'no_interaction': Require Real User Interaction is enabled and no
	// qualifying mouse/keyboard/touch/scroll event has happened yet (an
	// event only counts once a short minimum delay has passed since init,
	// so a single synthetic event fired at page load does not satisfy it).
	// - 'human': anything else. The value written for it is the signed,
	// context-bound proof (see init_plugin_suite_void_shield_get_js_proof()),
	// reassembled from its sealed form, not a fixed string.
	//
	// The 'no_interaction' verdict can be upgraded to 'human' at submit
	// time, but only if a qualifying event has genuinely happened by then
	// (the submit click itself counts, since 'pointerdown'/'keydown' fire
	// before 'submit'); a bot with zero interaction events is unaffected.
	//
	// Optional Lazy Fetch: the baked-in time/hash pair reflects when this
	// PHP ran, which on a full-page-cached page is when the cache was built.
	// When enabled, a fresh time, hash, and sealed proof are fetched as soon
	// as the page truly loads and all three are swapped in together, so the
	// submitted fields always stay mutually consistent. If the request fails
	// the baked-in values are kept, so this can only help.
	//
	// Submit Hold (see init_plugin_suite_void_shield_is_submit_hold_context()):
	// on natively-submitted forms, a submit that arrives before the verdict
	// exists, before the Minimum Submit Time is guaranteed to be met, or
	// while a Lazy Fetch is still in flight is cancelled in the capture
	// phase (before any theme/plugin handler sees it) and replayed
	// automatically once everything is ready, via requestSubmit() so the
	// clicked button's name/value is still sent. HTMLFormElement.prototype
	// methods are called directly because a field named "submit" (e.g. the
	// comment form's own submit button) shadows form.submit on the element.
	//
	// The init routine checks document.readyState instead of relying solely
	// on 'DOMContentLoaded', so it still runs when a JS-delay/defer
	// optimization postpones inline scripts until after that event. The
	// script never emits a bare '&' character (nested ifs instead of '&&',
	// String.fromCharCode(38) for the URL separator): some minifiers,
	// multilingual plugins, output filters, or WordPress's own
	// convert_chars() rewrite a bare ampersand into '&#038;', which browsers
	// never decode inside <script> content, breaking the whole script.
	// Comments are kept out of the emitted script to keep guarded pages lean
	// and to avoid spelling out the detection logic in page source.
	$seal = init_plugin_suite_void_shield_seal_js_proof( $js_proof );

	$js = <<<'INITVOIDSHIELDJS'
(function() {
	function initVoidShieldGuard() {
		var jsInput = document.getElementById('__IVS_JS_NAME__');
		if (!jsInput) {
			return;
		}
		var headlessCheckEnabled = __IVS_HEADLESS__;
		var interactionRequired = __IVS_INTERACTION__;
		var lazyFetchEnabled = __IVS_LAZY__;
		var submitHoldEnabled = __IVS_HOLD__;
		var minInteractionDelay = __IVS_MIN_INTERACTION__;
		var minSubmitMs = __IVS_MIN_SUBMIT__;
		var fetchWaitMax = __IVS_FETCH_WAIT__;
		var initAt = Date.now();
		var timeAnchor = initAt;
		var fetchPending = false;
		var interacted = false;
		var holding = false;
		var verdict = '';
		var unseal = function(key, codes) {
			var out = '';
			for (var i = codes.length - 1; i >= 0; i--) {
				out += String.fromCharCode(codes[i] ^ key);
			}
			return out;
		};
		var proof = unseal(__IVS_SEAL_KEY__, [__IVS_SEAL_CODES__]);
		var writeVerdict = function() {
			if (verdict === 'bot') {
				jsInput.value = 'bot_detected';
			} else if (verdict === 'no_interaction') {
				jsInput.value = 'no_interaction';
			} else if (verdict === 'human') {
				jsInput.value = proof;
			}
		};
		var looksAutomated = function() {
			if (navigator.webdriver === true) {
				return true;
			}
			if (window.outerWidth === 0) {
				if (window.outerHeight === 0) {
					return true;
				}
			}
			if (/HeadlessChrome|PhantomJS/.test(navigator.userAgent || '')) {
				return true;
			}
			if (window.callPhantom || window._phantom || window.__nightmare || window.domAutomation || window.domAutomationController) {
				return true;
			}
			try {
				for (var docKey in document) {
					if (docKey.indexOf('$cdc_') === 0 || docKey.indexOf('$wdc_') === 0) {
						return true;
					}
				}
				var winKeys = Object.getOwnPropertyNames(window);
				for (var w = 0; w < winKeys.length; w++) {
					if (winKeys[w].indexOf('cdc_') === 0) {
						return true;
					}
				}
			} catch (e) {}
			return false;
		};
		if (interactionRequired) {
			var voidShieldInteractionEvents = ['mousemove', 'keydown', 'pointerdown', 'touchstart', 'scroll'];
			var markInteracted = function() {
				if (interacted) {
					return;
				}
				if (Date.now() - initAt < minInteractionDelay) {
					return;
				}
				interacted = true;
				voidShieldInteractionEvents.forEach(function(evt) {
					document.removeEventListener(evt, markInteracted);
				});
			};
			voidShieldInteractionEvents.forEach(function(evt) {
				document.addEventListener(evt, markInteracted, { passive: true });
			});
		}
		var upgradeVerdict = function() {
			if (interactionRequired) {
				if (interacted) {
					if (verdict === 'no_interaction') {
						verdict = 'human';
						writeVerdict();
					}
				}
			}
		};
		if (lazyFetchEnabled) {
			if (window.fetch) {
				var timeInput = document.getElementById('__IVS_TIME_NAME__');
				var hashInput = document.getElementById('__IVS_HASH_NAME__');
				if (timeInput) {
					if (hashInput) {
						var base = '__IVS_REST_URL__';
						var sep = base.indexOf('?') > -1 ? String.fromCharCode(38) : '?';
						var url = base + sep + 'context=' + encodeURIComponent('__IVS_CONTEXT__');
						fetchPending = true;
						fetch(url, { method: 'GET', credentials: 'omit', cache: 'no-store' })
							.then(function(response) { return response.ok ? response.json() : null; })
							.then(function(data) {
								if (data) {
									if (data.time) {
										if (data.hash) {
											if (data.seal) {
												if (data.seal.p) {
													timeInput.value = data.time;
													hashInput.value = data.hash;
													proof = unseal(data.seal.k, data.seal.p);
													timeAnchor = Date.now();
													writeVerdict();
												}
											}
										}
									}
								}
								fetchPending = false;
							})
							.catch(function() {
								fetchPending = false;
							});
					}
				}
			}
		}
		setTimeout(function() {
			if (headlessCheckEnabled) {
				if (looksAutomated()) {
					verdict = 'bot';
				}
			}
			if (verdict === '') {
				if (interactionRequired) {
					verdict = interacted ? 'human' : 'no_interaction';
				} else {
					verdict = 'human';
				}
			}
			writeVerdict();
		}, __IVS_DELAY__);
		var form = jsInput.form;
		if (!form) {
			return;
		}
		var isReady = function() {
			if (verdict === '') {
				return false;
			}
			if (Date.now() - timeAnchor < minSubmitMs) {
				return false;
			}
			if (fetchPending) {
				if (Date.now() - initAt < fetchWaitMax) {
					return false;
				}
			}
			return true;
		};
		var resubmit = function(submitter) {
			var proto = window.HTMLFormElement ? window.HTMLFormElement.prototype : null;
			if (!proto) {
				return;
			}
			if (proto.requestSubmit) {
				try {
					if (submitter) {
						proto.requestSubmit.call(form, submitter);
					} else {
						proto.requestSubmit.call(form);
					}
					return;
				} catch (e) {}
			}
			if (submitter) {
				if (submitter.name) {
					var carrier = document.createElement('input');
					carrier.type = 'hidden';
					carrier.name = submitter.name;
					carrier.value = submitter.value || '';
					form.appendChild(carrier);
				}
			}
			proto.submit.call(form);
		};
		document.addEventListener('submit', function(event) {
			if (event.target !== form) {
				return;
			}
			upgradeVerdict();
			if (!submitHoldEnabled) {
				return;
			}
			if (!holding) {
				if (isReady()) {
					return;
				}
			}
			event.preventDefault();
			event.stopImmediatePropagation();
			if (holding) {
				return;
			}
			holding = true;
			var submitter = event.submitter || null;
			if (submitter) {
				submitter.setAttribute('aria-busy', 'true');
			}
			var release = function() {
				if (!isReady()) {
					setTimeout(release, 100);
					return;
				}
				upgradeVerdict();
				holding = false;
				if (submitter) {
					submitter.removeAttribute('aria-busy');
				}
				resubmit(submitter);
			};
			setTimeout(release, 100);
		}, true);
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initVoidShieldGuard);
	} else {
		initVoidShieldGuard();
	}
})();
INITVOIDSHIELDJS;

	$js = strtr(
		$js,
		array(
			'__IVS_JS_NAME__'         => esc_js( $js_name ),
			'__IVS_TIME_NAME__'       => esc_js( $time_name ),
			'__IVS_HASH_NAME__'       => esc_js( $hash_name ),
			'__IVS_CONTEXT__'         => esc_js( $context ),
			'__IVS_REST_URL__'        => esc_js( $rest_base_url ),
			'__IVS_HEADLESS__'        => $headless_enabled,
			'__IVS_INTERACTION__'     => $interaction_required,
			'__IVS_LAZY__'            => $lazy_fetch_enabled,
			'__IVS_HOLD__'            => $submit_hold_enabled,
			'__IVS_MIN_INTERACTION__' => (string) absint( $min_interaction_delay ),
			'__IVS_MIN_SUBMIT__'      => (string) absint( $min_submit_ms ),
			'__IVS_FETCH_WAIT__'      => (string) $fetch_wait_max,
			'__IVS_DELAY__'           => (string) absint( $js_delay_actual ),
			'__IVS_SEAL_KEY__'        => (string) absint( $seal['k'] ),
			'__IVS_SEAL_CODES__'      => implode( ',', array_map( 'absint', $seal['p'] ) ),
		)
	);

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
// 5b. Referer check (opt-in, used by individual guards)
// ------------------------------------------------------------------

/**
 * Check whether the current request's Referer header points to this site.
 *
 * Disclosed trade-off, same spirit as the Login Guard Scope's Referer
 * heuristic further up the plugin: some privacy-focused browsers and
 * extensions strip the Referer header even on same-origin navigation,
 * which would make a genuine visitor look like a mismatch. This is why
 * every guard that uses this check keeps it opt-in and off by default --
 * enable it only after confirming it doesn't affect real visitors on your
 * site, and prefer it as an addition on top of the other layers, not a
 * replacement for them.
 *
 * @return bool
 */
function init_plugin_suite_void_shield_is_referer_same_site() {
	if ( empty( $_SERVER['HTTP_REFERER'] ) ) {
		return false;
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
	$referer_host = wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ), PHP_URL_HOST );
	$site_host    = wp_parse_url( home_url(), PHP_URL_HOST );

	if ( empty( $referer_host ) || empty( $site_host ) ) {
		return false;
	}

	return strtolower( $referer_host ) === strtolower( $site_host );
}

// ------------------------------------------------------------------
// 5c. Per-context Minimum Submit Time (account forms vs. content forms)
// ------------------------------------------------------------------

/**
 * Determine whether a guard context is a login/account-credentials style
 * form -- login, registration, lost password, and equivalents from the
 * form-plugin integrations -- as opposed to a content-submission form (a
 * comment, a forum topic/reply, a contact form). Account forms are the ones
 * a browser's autofill/password manager most commonly pre-fills, letting a
 * genuine visitor submit meaningfully faster than someone typing content
 * from scratch, so they get their own, separately-tunable minimum-submit-
 * time floor instead of sharing the one general setting.
 *
 * @param string $context Guard context.
 * @return bool
 */
function init_plugin_suite_void_shield_is_account_context( $context ) {
	$account_contexts = array(
		'login',
		'register',
		'lostpassword',
		'multisite_signup',
		'woocommerce_register',
		'woocommerce_login',
		'woocommerce_lostpassword',
		'buddypress_register',
	);

	/**
	 * Filter which guard contexts are treated as "account forms" for the
	 * purpose of the separate Account Forms Minimum Submit Time setting.
	 * Comment forms and the content-style form-plugin integrations (CF7,
	 * WPForms, Gravity Forms, bbPress topics/replies) are intentionally
	 * left out of the default list and continue using the general
	 * Minimum Submit Time -- add a context here only if it is itself a
	 * credentials/account form a browser would realistically autofill.
	 *
	 * @param array  $account_contexts Contexts treated as account forms.
	 * @param string $context          The context currently being checked.
	 */
	$account_contexts = apply_filters( 'init_plugin_suite_void_shield_account_contexts', $account_contexts, $context );

	return in_array( (string) $context, (array) $account_contexts, true );
}

/**
 * Resolve the effective Minimum Submit Time (in seconds) for a given guard
 * context. Account-style contexts (see
 * init_plugin_suite_void_shield_is_account_context()) use their own,
 * independently-configured setting; everything else keeps using the
 * general one. Both paths remain filterable via the same
 * `init_plugin_suite_void_shield_min_time` filter as before, now with the
 * context passed through as a second argument so a developer can override
 * any single form individually if the grouped setting isn't granular
 * enough.
 *
 * @param string $context Guard context.
 * @return int
 */
function init_plugin_suite_void_shield_get_min_time_for_context( $context ) {
	if ( init_plugin_suite_void_shield_is_account_context( $context ) ) {
		$min_time = absint( get_option( 'init_plugin_suite_void_shield_account_min_time', 1 ) );
	} else {
		$min_time = absint( get_option( 'init_plugin_suite_void_shield_min_time', 3 ) );
	}

	return absint( apply_filters( 'init_plugin_suite_void_shield_min_time', $min_time, $context ) );
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

	if ( '1' === get_option( 'init_plugin_suite_void_shield_block_bot_user_agents', '1' ) ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );

		if ( init_plugin_suite_void_shield_is_bot_user_agent( $user_agent ) ) {
			init_plugin_suite_void_shield_record_block( $context, 'bot_user_agent' );
			return false;
		}
	}

	// Browser-reported cross-site submission. See
	// init_plugin_suite_void_shield_is_cross_site_request() for why only a
	// positive `cross-site` value counts and a missing header never does.
	if ( '1' === get_option( 'init_plugin_suite_void_shield_block_cross_site', '1' )
		&& init_plugin_suite_void_shield_is_cross_site_request() ) {
		/**
		 * Force-exempt a request from the Block Cross-Site Submissions check.
		 *
		 * Intended for a setup where a form on a different registrable
		 * domain legitimately posts to this site (e.g. a Multisite network
		 * whose subsites are mapped to unrelated domains and share one
		 * login form). Every other layer still applies to the request.
		 *
		 * @param bool   $exempt  Whether to exempt this request. Default false.
		 * @param string $context Guard context being verified.
		 */
		if ( ! apply_filters( 'init_plugin_suite_void_shield_cross_site_exempt', false, $context ) ) {
			init_plugin_suite_void_shield_record_block( $context, 'cross_site_request' );
			return false;
		}
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

	// The actual proof value is checked in Gate 3b below, once the time
	// token it is bound to has itself been validated. Here only a missing
	// value is rejected (the script never ran, or never reached its verdict).
	if ( '' === $js_value ) {
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

	// Gate 3b: the JS proof must match the one issued for this exact time
	// token and context. A static value (including the pre-1.11 fixed
	// `human_verified` string, e.g. from a page still served out of a cache
	// built before updating) or a proof lifted from a different form or a
	// different render fails here.
	$expected_proof = init_plugin_suite_void_shield_get_js_proof( $submit_time, $context );

	if ( ! hash_equals( $expected_proof, $js_value ) ) {
		init_plugin_suite_void_shield_record_block( $context, 'js_proof_invalid' );
		return false;
	}

	// Gate 4: speed limit (human-friendly minimum) and freshness ceiling
	// (rejects a token that is older than the configured maximum age). The
	// ceiling exists so a token scraped once cannot be cached and replayed
	// indefinitely; it only has to be generous enough that a real visitor
	// who takes a while to read the page and fill the form is never caught
	// by it. The floor is resolved per-context: login/account-style forms
	// (see init_plugin_suite_void_shield_get_min_time_for_context()) use
	// their own, shorter setting by default, since a browser autofilling
	// saved credentials lets a genuine visitor submit faster than the
	// general minimum -- tuned for typing a comment or filling out a
	// contact form -- assumes.
	$min_time = init_plugin_suite_void_shield_get_min_time_for_context( $context );
	$max_time = absint( apply_filters( 'init_plugin_suite_void_shield_max_time', absint( get_option( 'init_plugin_suite_void_shield_max_time', 3600 ) ), $context ) );

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
