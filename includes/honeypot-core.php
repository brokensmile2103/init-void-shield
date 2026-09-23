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
	$type = isset( $commentdata['comment_type'] ) ? (string) $commentdata['comment_type'] : '';

	// Pingbacks/trackbacks are machine-generated notifications, not text a
	// visitor typed, and naturally contain the linking site's URL.
	if ( in_array( $type, array( 'pingback', 'trackback' ), true ) ) {
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
