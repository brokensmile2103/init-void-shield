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
// comment_form() only fires `comment_form_after_fields` for guests, and only
// when the form still has at least one author/email/url field. Before 1.12
// that was the only hook used, so two setups rendered no guard at all and
// every comment was then rejected: a logged-in visitor with "Apply to
// Logged-in Users" enabled (core fires `comment_form_logged_in_after`
// instead), and a theme that removes every default guest field. Both are
// covered now, plus a last-resort `comment_form` hook (fired inside the
// <form>, after the submit field), with a per-post guard so the markup is
// never printed twice for the same form.
//

add_action( 'comment_form_after_fields', 'init_plugin_suite_void_shield_render_fields' );
add_action( 'comment_form_logged_in_after', 'init_plugin_suite_void_shield_render_fields' );
add_action( 'comment_form', 'init_plugin_suite_void_shield_render_fields_fallback' );

/**
 * Track which posts already had the comment guard printed during this request.
 *
 * @param int  $post_id Post ID.
 * @param bool $mark    Whether to mark the post as rendered.
 * @return bool Whether the guard had already been rendered for the post.
 */
function init_plugin_suite_void_shield_comment_guard_rendered( $post_id, $mark = false ) {
	static $rendered = array();

	$post_id = absint( $post_id );
	$already = isset( $rendered[ $post_id ] );

	if ( $mark ) {
		$rendered[ $post_id ] = true;
	}

	return $already;
}

/**
 * Print the comment guard for a post, at most once per request.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function init_plugin_suite_void_shield_output_comment_guard( $post_id ) {
	$post_id = absint( $post_id );

	if ( ! $post_id || ! init_plugin_suite_void_shield_is_active() ) {
		return;
	}

	if ( init_plugin_suite_void_shield_comment_guard_rendered( $post_id, true ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built internally with esc_attr()/esc_html() on every value, see field-guard.php.
	echo init_plugin_suite_void_shield_build_guard_markup( 'comment_' . $post_id );
}

/**
 * Render the honeypot guard on the comment form.
 *
 * Hooked to `comment_form_after_fields` (guests) and
 * `comment_form_logged_in_after` (logged-in users). Neither passes the post
 * ID, so it is read from the loop, as before.
 *
 * @return void
 */
function init_plugin_suite_void_shield_render_fields() {
	init_plugin_suite_void_shield_output_comment_guard( get_the_ID() );
}

/**
 * Last-resort render on the `comment_form` action, for a form where neither
 * of the hooks above fired (e.g. a theme that removed every guest field).
 *
 * @param int $post_id Post ID the comment form is for.
 * @return void
 */
function init_plugin_suite_void_shield_render_fields_fallback( $post_id = 0 ) {
	$post_id = absint( $post_id );

	init_plugin_suite_void_shield_output_comment_guard( $post_id ? $post_id : get_the_ID() );
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
		// The honeypot gates are skipped for this request (logged-in user,
		// a developer override, or a compatibility bypass such as wpDiscuz,
		// whose AJAX submissions can never carry the honeypot fields). The
		// opt-in content filters only read the comment itself, so they can
		// still run -- see init_plugin_suite_void_shield_enforce_comment_content().
		init_plugin_suite_void_shield_enforce_comment_content( $commentdata );
		return $commentdata;
	}

	// Prefer the post ID WordPress core has already resolved into
	// $commentdata. This is populated the same way regardless of which raw
	// POST field name the calling form used -- some comment plugins (e.g.
	// wpDiscuz, which submits via its own AJAX handler) never send a
	// `comment_post_ID` field at all, so re-reading the raw request directly
	// is not reliable. Fall back to the raw request only if a non-standard
	// caller ever leaves $commentdata incomplete.
	$post_id = isset( $commentdata['comment_post_ID'] ) ? absint( $commentdata['comment_post_ID'] ) : 0;

	if ( ! $post_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress core comment form does not require a nonce; this is an anti-spam gate.
		$post_id = isset( $_POST['comment_post_ID'] ) ? intval( $_POST['comment_post_ID'] ) : 0;
	}

	// A reply written from the Dashboard (Comments screen or the post
	// list's "Reply" link) arrives through core's `replyto-comment` AJAX
	// handler, which checks its own nonce and the user's capability before
	// calling wp_new_comment(). It never carries the honeypot fields, so
	// with "Apply to Logged-in Users" enabled it used to be rejected.
	if ( $post_id && init_plugin_suite_void_shield_is_admin_comment_reply( $post_id ) ) {
		return $commentdata;
	}

	// Pingbacks and trackbacks are sent server-to-server (XML-RPC and
	// wp-trackback.php), so they can never carry the honeypot fields or the
	// JS proof. Before 1.12 they were rejected anyway, but recorded under a
	// misleading reason such as "Missing or invalid JS token". They are now
	// handled explicitly: still blocked by default (unchanged behavior),
	// recorded as their own reason, and allowed only on opt-in.
	if ( init_plugin_suite_void_shield_is_linkback( $commentdata ) ) {
		if ( init_plugin_suite_void_shield_linkbacks_allowed() ) {
			return $commentdata;
		}

		init_plugin_suite_void_shield_record_block( 'comment_' . ( $post_id ? $post_id : 'unknown' ), 'linkback' );
		init_plugin_suite_void_shield_kill_bot();
	}

	if ( ! $post_id ) {
		init_plugin_suite_void_shield_record_block( 'comment_unknown', 'invalid_post_id' );
		init_plugin_suite_void_shield_kill_bot();
	}

	// Optional, opt-in: require the Referer header to point at this site.
	// See init_plugin_suite_void_shield_is_referer_same_site() for the
	// disclosed trade-off that keeps this off by default.
	if ( '1' === get_option( 'init_plugin_suite_void_shield_require_referer', '0' )
		&& ! apply_filters( 'init_plugin_suite_void_shield_referer_exempt', false )
		&& ! init_plugin_suite_void_shield_is_referer_same_site() ) {
		init_plugin_suite_void_shield_record_block( 'comment_' . $post_id, 'referer_mismatch' );
		init_plugin_suite_void_shield_kill_bot();
	}

	if ( ! init_plugin_suite_void_shield_is_submission_human( 'comment_' . $post_id ) ) {
		init_plugin_suite_void_shield_kill_bot();
	}

	init_plugin_suite_void_shield_enforce_comment_content( $commentdata );

	return $commentdata;
}

/**
 * Whether the current comment is a Dashboard reply sent through core's
 * `replyto-comment` AJAX handler by a user allowed to edit the post.
 *
 * Core's wp_ajax_replyto_comment() verifies the `replyto-comment` nonce and
 * this same capability before it ever calls wp_new_comment(), so the
 * request has already been authenticated by the time this runs.
 *
 * @param int $post_id Post ID the comment is for.
 * @return bool
 */
function init_plugin_suite_void_shield_is_admin_comment_reply( $post_id ) {
	if ( ! wp_doing_ajax() || ! is_user_logged_in() ) {
		return false;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- core verifies the replyto-comment nonce before wp_new_comment() runs.
	$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';

	if ( 'replyto-comment' !== $action ) {
		return false;
	}

	return current_user_can( 'edit_post', absint( $post_id ) );
}

/**
 * Whether a comment is a pingback or trackback.
 *
 * @param array $commentdata Comment data.
 * @return bool
 */
function init_plugin_suite_void_shield_is_linkback( $commentdata ) {
	$type = isset( $commentdata['comment_type'] ) ? (string) $commentdata['comment_type'] : '';

	return in_array( $type, array( 'pingback', 'trackback' ), true );
}

/**
 * Whether pingbacks and trackbacks are let through the comment guard.
 *
 * @return bool
 */
function init_plugin_suite_void_shield_linkbacks_allowed() {
	$allowed = '1' === get_option( 'init_plugin_suite_void_shield_allow_linkbacks', '0' );

	/**
	 * Filter whether pingbacks and trackbacks bypass the comment guard.
	 *
	 * When allowed, they are left to WordPress's own discussion settings
	 * and moderation rules, exactly as if this plugin were not installed.
	 *
	 * @param bool $allowed Whether linkbacks are allowed. Defaults to the settings-page toggle.
	 */
	return (bool) apply_filters( 'init_plugin_suite_void_shield_allow_linkbacks', $allowed );
}

// ------------------------------------------------------------------
// 3b. Comment content filters (opt-in, off by default)
// ------------------------------------------------------------------
// Everything above judges HOW a comment was submitted. These filters judge
// WHAT was submitted, which catches a different class of spam: a human
// spammer, or a real browser driven by a spam tool, that passes every
// behavioral check but still posts a link dump. Each filter is a separate
// opt-in, off by default, because unlike the behavioral layers they read
// content a genuine commenter wrote and so carry a real (if small) risk of
// a false positive on sites where legitimate comments look like that.
//

/**
 * Determine whether the comment content filters should run for this request.
 *
 * Independent from init_plugin_suite_void_shield_is_active() on purpose: a
 * request that bypasses the honeypot gates (e.g. wpDiscuz, which cannot
 * carry the honeypot fields) still carries the comment text, so the
 * content filters can still protect it. They follow the same master switch
 * and logged-in-user rule as the rest of the comment guard.
 *
 * @return bool
 */
function init_plugin_suite_void_shield_content_filters_active() {
	if ( '1' !== get_option( 'init_plugin_suite_void_shield_enabled', '1' ) ) {
		return false;
	}

	if ( is_user_logged_in() && '1' !== get_option( 'init_plugin_suite_void_shield_apply_to_logged_in', '0' ) ) {
		return false;
	}

	/**
	 * Force-disable the comment content filters for a request, regardless
	 * of their settings-page toggles.
	 *
	 * @param bool $skip Whether to skip the content filters. Default false.
	 */
	return ! apply_filters( 'init_plugin_suite_void_shield_skip_content_filters', false );
}

/**
 * Count the links in a piece of comment text.
 *
 * Counts every `http://`/`https://` URL plus every bare `www.` host that is
 * not itself part of such a URL, so `https://www.example.com` counts once.
 *
 * @param string $text Comment text.
 * @return int
 */
function init_plugin_suite_void_shield_count_links( $text ) {
	$count = preg_match_all( '#(?:https?://|(?<![/\w.])www\.)#i', (string) $text );

	return $count ? (int) $count : 0;
}

/**
 * Check a comment against the enabled content filters.
 *
 * @param array $commentdata Comment data.
 * @return string Block reason key, or an empty string if the comment passes.
 */
function init_plugin_suite_void_shield_get_comment_content_violation( $commentdata ) {
	// Pingbacks/trackbacks are machine-generated notifications, not text a
	// visitor typed, and naturally contain the linking site's URL.
	if ( init_plugin_suite_void_shield_is_linkback( $commentdata ) ) {
		return '';
	}

	$content = isset( $commentdata['comment_content'] ) ? (string) $commentdata['comment_content'] : '';
	$author  = isset( $commentdata['comment_author'] ) ? (string) $commentdata['comment_author'] : '';
	$reason  = '';

	$max_links = absint( get_option( 'init_plugin_suite_void_shield_comment_max_links', 0 ) );

	if ( '1' === get_option( 'init_plugin_suite_void_shield_block_bbcode_links', '0' )
		&& preg_match( '#\[(url|link)(?:=[^\]]*)?\].*?\[/\1\]#is', $content ) ) {
		// WordPress never renders BBCode, so a real commenter gains nothing
		// from it; it is a signature of spam tools built for forums. Only a
		// complete tag pair counts, so code snippets that merely mention
		// something like `$item[url]` are never mistaken for it.
		$reason = 'bbcode_link';
	} elseif ( $max_links > 0 && init_plugin_suite_void_shield_count_links( $content ) > $max_links ) {
		$reason = 'too_many_links';
	} elseif ( '1' === get_option( 'init_plugin_suite_void_shield_block_author_url', '0' )
		&& 1 === preg_match( '#(?:https?://|www\.)#i', $author ) ) {
		// A URL in the Name field (rather than the Website field) is a
		// classic way to sneak a keyword link into the comment byline.
		$reason = 'author_url';
	} elseif ( init_plugin_suite_void_shield_has_blocked_script( $content, $author ) ) {
		$reason = 'blocked_script';
	}

	/**
	 * Filter the content-filter verdict for a comment. Return a non-empty
	 * reason key to block it (recorded in the stats under that key), or an
	 * empty string to let it through. Lets developers add their own
	 * content rules on top of the built-in ones.
	 *
	 * @param string $reason      Block reason key, or '' if the comment passes.
	 * @param array  $commentdata Comment data.
	 */
	return (string) apply_filters( 'init_plugin_suite_void_shield_comment_content_violation', $reason, $commentdata );
}

/**
 * Get the writing systems the "Block Comments in Selected Scripts" filter
 * can target, as key => PCRE Unicode class.
 *
 * @return array
 */
function init_plugin_suite_void_shield_get_script_patterns() {
	return array(
		'cyrillic'   => '\p{Cyrillic}',
		'han'        => '\p{Han}',
		'kana'       => '[\p{Hiragana}\p{Katakana}]',
		'hangul'     => '\p{Hangul}',
		'arabic'     => '\p{Arabic}',
		'hebrew'     => '\p{Hebrew}',
		'greek'      => '\p{Greek}',
		'thai'       => '\p{Thai}',
		'devanagari' => '\p{Devanagari}',
	);
}

/**
 * Get the scripts currently selected in "Block Comments in Selected Scripts".
 *
 * @return array Selected script keys.
 */
function init_plugin_suite_void_shield_get_blocked_scripts() {
	$selected = get_option( 'init_plugin_suite_void_shield_blocked_scripts', array() );

	if ( ! is_array( $selected ) ) {
		return array();
	}

	return array_values( array_intersect( array_keys( init_plugin_suite_void_shield_get_script_patterns() ), $selected ) );
}

/**
 * Compute the share of letters in a text that belong to the given scripts.
 *
 * Only letters are counted, so punctuation, digits, emoji, URLs' slashes,
 * and whitespace never dilute or inflate the result. Text that is not
 * valid UTF-8 yields 0, so it can never trigger a block on its own.
 *
 * @param string $text     Text to measure.
 * @param array  $patterns PCRE Unicode classes to count.
 * @return float Ratio between 0 and 1 (may slightly exceed 1 for scripts with combining marks).
 */
function init_plugin_suite_void_shield_get_script_ratio( $text, $patterns ) {
	$text = wp_strip_all_tags( (string) $text );

	if ( '' === $text || empty( $patterns ) ) {
		return 0.0;
	}

	$letters = preg_match_all( '/\p{L}/u', $text );

	if ( ! $letters ) {
		return 0.0;
	}

	$hits = preg_match_all( '/(?:' . implode( '|', $patterns ) . ')/u', $text );

	return $hits ? (float) $hits / (float) $letters : 0.0;
}

/**
 * Whether the comment text or author name is written mostly in one of the
 * scripts selected in "Block Comments in Selected Scripts".
 *
 * A comment is only matched when the selected scripts make up a real share
 * of its letters (30% by default), so a genuine comment that merely quotes
 * a foreign name or word is not caught.
 *
 * @param string $content Comment text.
 * @param string $author  Comment author name.
 * @return bool
 */
function init_plugin_suite_void_shield_has_blocked_script( $content, $author ) {
	$selected = init_plugin_suite_void_shield_get_blocked_scripts();

	if ( empty( $selected ) ) {
		return false;
	}

	$all      = init_plugin_suite_void_shield_get_script_patterns();
	$patterns = array();

	foreach ( $selected as $key ) {
		$patterns[] = $all[ $key ];
	}

	/**
	 * Filter the minimum share of letters (0-1) that must belong to a
	 * selected script before a comment is blocked.
	 *
	 * @param float $threshold Minimum share of letters. Default 0.3.
	 */
	$threshold = (float) apply_filters( 'init_plugin_suite_void_shield_blocked_script_threshold', 0.3 );
	$threshold = max( 0.01, min( 1.0, $threshold ) );

	if ( init_plugin_suite_void_shield_get_script_ratio( $content, $patterns ) >= $threshold ) {
		return true;
	}

	return init_plugin_suite_void_shield_get_script_ratio( $author, $patterns ) >= $threshold;
}

/**
 * Block a comment that fails the enabled content filters, using the same
 * soft-kill response as the rest of the comment guard.
 *
 * @param array $commentdata Comment data.
 * @return void
 */
function init_plugin_suite_void_shield_enforce_comment_content( $commentdata ) {
	if ( ! init_plugin_suite_void_shield_content_filters_active() ) {
		return;
	}

	$reason = init_plugin_suite_void_shield_get_comment_content_violation( $commentdata );

	if ( '' === $reason ) {
		return;
	}

	$post_id = isset( $commentdata['comment_post_ID'] ) ? absint( $commentdata['comment_post_ID'] ) : 0;

	init_plugin_suite_void_shield_record_block( 'comment_' . ( $post_id ? $post_id : 'unknown' ), $reason );
	init_plugin_suite_void_shield_kill_bot();
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

	// Users who can moderate comments are never blocked here, so the
	// block editor and any admin screen built on the REST API keep working
	// for them even with "Apply to Logged-in Users" enabled.
	if ( current_user_can( 'moderate_comments' ) ) {
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
