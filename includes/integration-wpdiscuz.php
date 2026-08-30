<?php
/**
 * Compatibility bypass for wpDiscuz
 *
 * This bypass exists because wpDiscuz replaces the classic WordPress
 * comment form with its own AJAX-driven template and JavaScript submission
 * handler. That handler builds its request from a fixed, hardcoded set of
 * field names (action, wc_comment, wc_name, wc_email, wc_website, postId,
 * wpdiscuz_nonce, etc.) rather than serializing the form generically, so
 * any extra field this plugin's honeypot engine would render -- however it
 * renders it, and wherever in the markup it's placed -- is never included
 * in the request wpDiscuz actually sends. There is currently no supported
 * way to make wpDiscuz forward an arbitrary custom field through to
 * wp_new_comment() without reverse-engineering and depending on its
 * internal JS, which would be fragile across its updates.
 *
 * Given that, this integration does not attempt to guard wpDiscuz
 * submissions at all. It simply exempts them from this plugin's comment
 * verification via the `init_plugin_suite_void_shield_skip_verification`
 * filter, so real visitors commenting through wpDiscuz are never rejected
 * by a check they have no way to satisfy. This is strictly a compatibility
 * bypass, not protection -- comments made through wpDiscuz are left to
 * wpDiscuz's own spam handling (and any other anti-spam plugin that
 * integrates with it directly).
 *
 * @package Init_Void_Shield
 */

defined( 'ABSPATH' ) || exit;

add_action( 'plugins_loaded', 'init_plugin_suite_void_shield_maybe_init_wpdiscuz_bypass', 20 );

/**
 * Exempt wpDiscuz comment submissions from verification, but only if
 * wpDiscuz is actually active.
 *
 * @return void
 */
function init_plugin_suite_void_shield_maybe_init_wpdiscuz_bypass() {
	if ( ! class_exists( 'WpdiscuzCore' ) ) {
		return;
	}

	add_filter( 'init_plugin_suite_void_shield_skip_verification', '__return_true' );
}
