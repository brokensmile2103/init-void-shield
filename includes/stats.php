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
		'comment_'             => 'comment',
		'rest_comment'         => 'rest',
		'login'                => 'login',
		'register'             => 'register',
		'lostpassword'         => 'lostpassword',
		'multisite_signup'     => 'multisite',
		'cf7_'                 => 'cf7',
		'wpforms_'             => 'wpforms',
		'gf_'                  => 'gravityforms',
		'woocommerce_register' => 'woocommerce',
		'bbpress_'             => 'bbpress',
		'buddypress_register'  => 'buddypress',
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

// ------------------------------------------------------------------
// Dashboard widget (optional, opt-in, off by default)
// ------------------------------------------------------------------

add_action( 'wp_dashboard_setup', 'init_plugin_suite_void_shield_maybe_register_dashboard_widget' );

/**
 * Register the "Blocked Submissions" dashboard widget, only if the site
 * owner has opted in and the current user is allowed to see it.
 *
 * @return void
 */
function init_plugin_suite_void_shield_maybe_register_dashboard_widget() {
	if ( '1' !== get_option( 'init_plugin_suite_void_shield_enable_dashboard_widget', '0' ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	wp_add_dashboard_widget(
		'init_plugin_suite_void_shield_dashboard_widget',
		__( 'Init Void Shield – Blocked Submissions', 'init-void-shield' ),
		'init_plugin_suite_void_shield_render_dashboard_widget'
	);
}

/**
 * Render the dashboard widget content.
 *
 * Uses inline `style` attributes only (no `<style>` block), matching the
 * approach already used elsewhere in this plugin's admin screens.
 *
 * @return void
 */
function init_plugin_suite_void_shield_render_dashboard_widget() {
	$stats = init_plugin_suite_void_shield_get_stats();

	if ( empty( $stats['total'] ) ) {
		echo '<p>' . esc_html__( 'No submissions have been blocked yet.', 'init-void-shield' ) . '</p>';
		return;
	}

	$channel_labels = init_plugin_suite_void_shield_get_channel_labels();
	$reason_labels  = init_plugin_suite_void_shield_get_reason_labels();

	echo '<p style="margin-top:0;">';
	echo '<strong style="font-size:20px;line-height:1;">' . esc_html( number_format_i18n( absint( $stats['total'] ) ) ) . '</strong> ';
	echo esc_html__( 'blocked in total', 'init-void-shield' );

	if ( ! empty( $stats['last_blocked'] ) ) {
		echo '<br /><span style="color:#646970;">';
		printf(
			/* translators: %s: human-readable time difference, e.g. "3 hours". */
			esc_html__( 'Last blocked %s ago', 'init-void-shield' ),
			esc_html( human_time_diff( absint( $stats['last_blocked'] ), time() ) )
		);
		echo '</span>';
	}
	echo '</p>';

	init_plugin_suite_void_shield_render_dashboard_widget_breakdown(
		isset( $stats['by_channel'] ) ? $stats['by_channel'] : array(),
		$channel_labels,
		__( 'Top channels', 'init-void-shield' )
	);

	init_plugin_suite_void_shield_render_dashboard_widget_breakdown(
		isset( $stats['by_reason'] ) ? $stats['by_reason'] : array(),
		$reason_labels,
		__( 'Top reasons', 'init-void-shield' )
	);

	echo '<p style="margin-bottom:0;">';
	echo '<a href="' . esc_url( admin_url( 'options-general.php?page=' . INIT_PLUGIN_SUITE_VOID_SHIELD_SLUG ) ) . '">';
	echo esc_html__( 'View full statistics & settings', 'init-void-shield' ) . ' &rarr;';
	echo '</a>';
	echo '</p>';
}

/**
 * Render a small, top-5 breakdown table for the dashboard widget.
 *
 * @param array  $counts Associative array of key => count.
 * @param array  $labels Human-friendly labels for the keys.
 * @param string $title  Section title.
 * @return void
 */
function init_plugin_suite_void_shield_render_dashboard_widget_breakdown( $counts, $labels, $title ) {
	if ( empty( $counts ) || ! is_array( $counts ) ) {
		return;
	}

	arsort( $counts );
	$counts = array_slice( $counts, 0, 5, true );

	echo '<p style="margin-bottom:4px;"><strong>' . esc_html( $title ) . '</strong></p>';
	echo '<table style="width:100%;border-collapse:collapse;margin-bottom:12px;">';

	foreach ( $counts as $key => $count ) {
		$label = isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
		echo '<tr>';
		echo '<td style="padding:2px 0;">' . esc_html( $label ) . '</td>';
		echo '<td style="padding:2px 0;text-align:right;font-weight:600;">' . esc_html( number_format_i18n( absint( $count ) ) ) . '</td>';
		echo '</tr>';
	}

	echo '</table>';
}
