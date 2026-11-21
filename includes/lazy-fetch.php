<?php
/**
 * Lazy Fetch REST Endpoint (optional, opt-in)
 *
 * When "Lazy Fetch" is enabled, the honeypot's baked-in time/hash token
 * (which reflects the moment the page was rendered -- the moment it was
 * cached, on a full-page-cached site, not the moment a visitor actually
 * loaded it) is refreshed client-side via a small same-origin fetch() call
 * to this endpoint as soon as the page truly loads in the browser. This
 * keeps the Minimum/Maximum Submit Time gates anchored to real visit time
 * regardless of how long the page sat in a cache.
 *
 * This endpoint is stateless: it only recomputes the same signed values
 * the render step would have produced for the given context (the time
 * hash plus the sealed JS proof bound to it). It stores nothing, matching
 * the plugin's zero-database design, and is only registered at all while
 * Lazy Fetch is enabled.
 *
 * @package Init_Void_Shield
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', 'init_plugin_suite_void_shield_register_token_route' );

/**
 * Register the token-refresh REST route, only while Lazy Fetch is enabled.
 *
 * @return void
 */
function init_plugin_suite_void_shield_register_token_route() {
	if ( '1' !== get_option( 'init_plugin_suite_void_shield_lazy_fetch', '0' ) ) {
		return;
	}

	register_rest_route(
		INIT_PLUGIN_SUITE_VOID_SHIELD_NAMESPACE,
		'/token',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'init_plugin_suite_void_shield_rest_token_callback',
			'permission_callback' => '__return_true',
			'args'                => array(
				'context' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'init_plugin_suite_void_shield_validate_token_context',
				),
			),
		)
	);
}

/**
 * Validate the `context` argument of the token-refresh route.
 *
 * Every context the plugin renders (comment_123, login, cf7_4,
 * woocommerce_login, ...) is a short string of letters, digits, and
 * underscores; dots, colons, and dashes are also accepted for contexts a
 * developer adds through init_plugin_suite_void_shield_build_guard_markup().
 * Anything else is refused before a token is computed for it.
 *
 * @param mixed $value Raw parameter value.
 * @return bool
 */
function init_plugin_suite_void_shield_validate_token_context( $value ) {
	return is_string( $value ) && 1 === preg_match( '/^[A-Za-z0-9_.:-]{1,100}$/', $value );
}

/**
 * REST callback: issue a fresh signed time token for the requested context.
 *
 * Public and unauthenticated by design, same as the rest of the honeypot:
 * a caller still needs the exact, per-site-salted field names (only ever
 * exposed by actually rendering a guarded form) to make any use of the
 * token returned here, and still has to clear every other gate (empty
 * honeypot fields, a passing JS/headless check, the minimum-time delay).
 *
 * @param WP_REST_Request $request Incoming REST request.
 * @return WP_REST_Response|WP_Error
 */
function init_plugin_suite_void_shield_rest_token_callback( WP_REST_Request $request ) {
	$context = (string) $request->get_param( 'context' );
	$context = substr( $context, 0, 100 );

	if ( '' === $context ) {
		return new WP_Error(
			'init_void_shield_invalid_context',
			__( 'Invalid context.', 'init-void-shield' ),
			array( 'status' => 400 )
		);
	}

	$current_time = time();
	$time_hash    = init_plugin_suite_void_shield_get_time_hash( $current_time, $context );

	// The JS proof is bound to the time token, so a refreshed time token
	// needs a matching refreshed proof. It is returned sealed, the same way
	// the render step embeds it, and the client swaps all three values in
	// together so the submitted fields always stay mutually consistent.
	$seal = init_plugin_suite_void_shield_seal_js_proof( init_plugin_suite_void_shield_get_js_proof( $current_time, $context ) );

	$response = new WP_REST_Response(
		array(
			'time' => $current_time,
			'hash' => $time_hash,
			'seal' => $seal,
		)
	);

	// Never let a reverse proxy, page-cache plugin, or browser cache this
	// response -- the entire point is a token that reflects "right now".
	$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
	$response->header( 'Pragma', 'no-cache' );

	return $response;
}
