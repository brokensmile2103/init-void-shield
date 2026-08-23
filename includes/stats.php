<?php
/**
 * Stats
 *
 * Lightweight blocked-submission counters. Stored as a single option with
 * autoload explicitly disabled, since the data changes on every blocked
 * request but is only ever read on the settings screen.
 *
 * @package Init_Void_Shield
 */

defined( 'ABSPATH' ) || exit;

define( 'INIT_PLUGIN_SUITE_VOID_SHIELD_STATS_OPTION', 'init_plugin_suite_void_shield_stats' );

/**
 * Map a raw guard context string to a human-friendly channel key.
 *
 * @param string $context Raw context string (e.g. comment_123, cf7_4).
 * @return string
 */
function init_plugin_suite_void_shield_get_channel_from_context( $context ) {
	$context = (string) $context;

	$map = array(
		'comment_'     => 'comment',
		'rest_comment' => 'rest',
		'login'        => 'login',
		'register'     => 'register',
		'lostpassword' => 'lostpassword',
		'cf7_'         => 'cf7',
		'wpforms_'     => 'wpforms',
		'gf_'          => 'gravityforms',
	);

	foreach ( $map as $needle => $channel ) {
		if ( 0 === strpos( $context, $needle ) ) {
			return $channel;
		}
	}

	return 'other';
}

/**
 * Record a blocked submission.
 *
 * @param string $context Guard context (e.g. comment_123, login, cf7_4).
 * @param string $reason  Block reason (e.g. honeypot_field, js_token, too_fast).
 * @return void
 */
function init_plugin_suite_void_shield_record_block( $context, $reason ) {
	if ( '1' !== get_option( 'init_plugin_suite_void_shield_enable_stats', '1' ) ) {
		return;
	}

	$stats = get_option( INIT_PLUGIN_SUITE_VOID_SHIELD_STATS_OPTION, array() );

	if ( ! is_array( $stats ) ) {
		$stats = array();
	}

	$stats['total'] = isset( $stats['total'] ) ? absint( $stats['total'] ) + 1 : 1;

	if ( ! isset( $stats['by_channel'] ) || ! is_array( $stats['by_channel'] ) ) {
		$stats['by_channel'] = array();
	}

	if ( ! isset( $stats['by_reason'] ) || ! is_array( $stats['by_reason'] ) ) {
		$stats['by_reason'] = array();
	}

	$channel = init_plugin_suite_void_shield_get_channel_from_context( $context );
	$reason  = sanitize_key( $reason );

	$stats['by_channel'][ $channel ] = isset( $stats['by_channel'][ $channel ] ) ? absint( $stats['by_channel'][ $channel ] ) + 1 : 1;
	$stats['by_reason'][ $reason ]   = isset( $stats['by_reason'][ $reason ] ) ? absint( $stats['by_reason'][ $reason ] ) + 1 : 1;
	$stats['last_blocked']           = time();

	// Explicit non-autoload option (3rd param false): this value changes on
	// every blocked request and is only ever read on the settings screen,
	// so it must never be pulled into the autoloaded options cache.
	update_option( INIT_PLUGIN_SUITE_VOID_SHIELD_STATS_OPTION, $stats, false );
}

/**
 * Get current stats with safe defaults.
 *
 * @return array
 */
function init_plugin_suite_void_shield_get_stats() {
	$stats = get_option( INIT_PLUGIN_SUITE_VOID_SHIELD_STATS_OPTION, array() );

	if ( ! is_array( $stats ) ) {
		$stats = array();
	}

	return wp_parse_args(
		$stats,
		array(
			'total'        => 0,
			'by_channel'   => array(),
			'by_reason'    => array(),
			'last_blocked' => 0,
		)
	);
}

/**
 * Reset all stats.
 *
 * @return void
 */
function init_plugin_suite_void_shield_reset_stats() {
	delete_option( INIT_PLUGIN_SUITE_VOID_SHIELD_STATS_OPTION );
}
