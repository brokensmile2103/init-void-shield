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
	$rest_base_url        = esc_url_raw( rest_url( INIT_PLUGIN_SUITE_VOID_SHIELD_NAMESPACE . '/token' ) );

	// Layer: JavaScript + lightweight headless-browser detection, plus an
	// optional real-interaction check. `navigator.webdriver` is set to true
	// by Selenium/Puppeteer/Playwright unless deliberately masked, and a
	// real browser window never reports 0x0 outer dimensions, so both are
	// cheap, low-false-positive signals. The interaction check catches
	// bots that simply sleep() past the delay: it listens for one genuine
	// mouse, keyboard, touch, or scroll event (any of which a real visitor
	// naturally triggers while reading/filling the page) before the timer
	// fires, without recording anything about that event.
	//
	// The interaction verdict is finalized twice, not once. Originally the
	// hidden token's value was only ever written inside the delayed
	// setTimeout below: if no qualifying event had happened by the time it
	// fired, the field was permanently stamped 'no_interaction', even if the
	// visitor's very next action -- the click that submits the form -- would
	// itself have satisfied the check an instant later. This is exactly what
	// happens on a login form the browser has already autofilled: a real
	// visitor does nothing else on the page (no mouse movement, no typing,
	// nothing to scroll to) until they click "Log in", so if that click
	// lands even slightly after the delay has already elapsed and stamped
	// the field, a genuine visitor was permanently misjudged as a bot with
	// no way to correct it. The fix re-runs the same verdict once more, at
	// the moment the form actually submits, but ONLY ever upgrades an
	// already-stamped 'no_interaction' to 'human_verified' -- and only if a
	// qualifying interaction event (the very click that triggered this
	// submission counts, since `markInteracted` for 'pointerdown'/'keydown'
	// runs synchronously before the browser's own 'submit' event fires) has
	// now genuinely happened. A bot with zero interaction events is
	// completely unaffected: `interacted` is still false at submit time, so
	// the value is left exactly as the timer already set it. This
	// resubmission hook is only attached when "Require Real User
	// Interaction" is enabled in the first place -- when it's off (the
	// default), nothing here changes, and the delay continues to enforce
	// its original minimum-wait-before-a-valid-token-exists behavior with
	// no new way for a script to submit before that delay elapses.
	//
	// Optional lazy-fetch layer: the time/hash pair rendered into the page
	// above reflects the moment this PHP ran, which on a full-page-cached
	// page is the moment the cache was generated -- not the moment a real
	// visitor actually loaded it. When enabled, this replaces that baked-in
	// pair with a freshly issued one from a lightweight REST endpoint as
	// soon as the page truly loads in the visitor's browser, so the
	// Minimum/Maximum Submit Time gates measure real visit time instead of
	// cache age. If the request fails (or JS/fetch is unavailable), the
	// baked-in value is left untouched as a fallback, so this only ever
	// helps and never introduces a new failure mode.
	//
	// The init routine below checks document.readyState instead of relying
	// solely on a 'DOMContentLoaded' listener. Several JS-delay/defer
	// optimizations (present in most caching/performance plugins) postpone
	// running inline scripts like this one until the visitor's first
	// interaction with the page -- which, on a comment form, is often the
	// click on the submit button itself. By the time such a deferred script
	// actually executes, 'DOMContentLoaded' has already fired, so a listener
	// registered for it at that point never runs, the token silently never
	// gets set, and a genuine first-time visitor's submission is rejected as
	// unverified -- succeeding only on a retry once the script has caught up
	// in the background. Running immediately when the document is already
	// past the loading state closes that gap without weakening any check.
	//
	// The script below also never emits a bare '&' character (nested ifs
	// instead of '&&', String.fromCharCode(38) instead of a literal '&' in
	// a URL separator). Some environments -- an HTML minifier, a
	// multilingual plugin's string scanner, a security/output-filtering
	// plugin, or WordPress's own convert_chars() if this markup is ever
	// routed through it -- normalize a bare ampersand in page output into
	// '&#038;'. Browsers never decode HTML entities inside <script>
	// content (it's raw text, not markup), so a literal '&&' would become
	// the literal text '&#038;&#038;' on the page and break JS parsing
	// entirely. Comments are also kept out of the emitted script below,
	// both to keep guarded pages lean (this renders on every comment form,
	// login form, etc.) and to avoid spelling out the detection logic in
	// page source for anyone reading it.
	$js = "(function() {
		function initVoidShieldGuard() {
			var jsInput = document.getElementById('" . esc_js( $js_name ) . "');
			if (!jsInput) {
				return;
			}
			var headlessCheckEnabled = " . $headless_enabled . ';
			var interactionRequired = ' . $interaction_required . ';
			var lazyFetchEnabled = ' . $lazy_fetch_enabled . ';
			var minInteractionDelay = ' . $min_interaction_delay . ";
			var initAt = Date.now();
			var interacted = false;
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
			if (lazyFetchEnabled) {
				if (window.fetch) {
					var timeInput = document.getElementById('" . esc_js( $time_name ) . "');
					var hashInput = document.getElementById('" . esc_js( $hash_name ) . "');
					if (timeInput) {
						if (hashInput) {
							var base = '" . esc_js( $rest_base_url ) . "';
							var sep = base.indexOf('?') > -1 ? String.fromCharCode(38) : '?';
							var url = base + sep + 'context=' + encodeURIComponent('" . esc_js( $context ) . "');
							fetch(url, { method: 'GET', credentials: 'omit', cache: 'no-store' })
								.then(function(response) { return response.ok ? response.json() : null; })
								.then(function(data) {
									if (data) {
										if (data.time) {
											if (data.hash) {
												timeInput.value = data.time;
												hashInput.value = data.hash;
											}
										}
									}
								})
								.catch(function() {});
						}
					}
				}
			}
			setTimeout(function() {
				var isBot = false;
				if (headlessCheckEnabled) {
					if (navigator.webdriver === true) {
						isBot = true;
					}
					if (window.outerWidth === 0) {
						if (window.outerHeight === 0) {
							isBot = true;
						}
					}
				}
				if (isBot) {
					jsInput.value = 'bot_detected';
				} else if (interactionRequired) {
					jsInput.value = interacted ? 'human_verified' : 'no_interaction';
				} else {
					jsInput.value = 'human_verified';
				}
			}, " . absint( $js_delay_actual ) . ');
			if (interactionRequired) {
				if (jsInput.form) {
					jsInput.form.addEventListener("submit", function() {
						if (interacted) {
							if (jsInput.value === "no_interaction") {
								jsInput.value = "human_verified";
							}
						}
					});
				}
			}
		}
		if (document.readyState === "loading") {
			document.addEventListener("DOMContentLoaded", initVoidShieldGuard);
		} else {
			initVoidShieldGuard();
		}
	})();';

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
