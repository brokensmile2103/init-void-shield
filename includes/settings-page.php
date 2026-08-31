<?php
/**
 * Settings Page
 *
 * @package Init_Void_Shield
 */

defined( 'ABSPATH' ) || exit;

// ------------------------------------------------------------------
// Admin Menu
// ------------------------------------------------------------------
add_action(
	'admin_menu',
	function () {
		add_options_page(
			esc_html__( 'Init Void Shield Settings', 'init-void-shield' ),
			esc_html__( 'Init Void Shield', 'init-void-shield' ),
			'manage_options',
			INIT_PLUGIN_SUITE_VOID_SHIELD_SLUG,
			'init_plugin_suite_void_shield_render_settings_page'
		);
	}
);

// ------------------------------------------------------------------
// Shared sanitizers
// ------------------------------------------------------------------

/**
 * Sanitize a settings checkbox value to '1' or '0'.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function init_plugin_suite_void_shield_sanitize_checkbox( $value ) {
	return ( isset( $value ) && '1' === $value ) ? '1' : '0';
}

// ------------------------------------------------------------------
// Register Settings
// ------------------------------------------------------------------
add_action(
	'admin_init',
	function () {
		$group = INIT_PLUGIN_SUITE_VOID_SHIELD_OPTION;

		// --- Comments -------------------------------------------------- .

		register_setting(
			$group,
			'init_plugin_suite_void_shield_enabled',
			array(
				'sanitize_callback' => 'init_plugin_suite_void_shield_sanitize_checkbox',
			)
		);

		register_setting(
			$group,
			'init_plugin_suite_void_shield_apply_to_logged_in',
			array(
				'sanitize_callback' => 'init_plugin_suite_void_shield_sanitize_checkbox',
			)
		);

		register_setting(
			$group,
			'init_plugin_suite_void_shield_block_rest',
			array(
				'sanitize_callback' => 'init_plugin_suite_void_shield_sanitize_checkbox',
			)
		);

		register_setting(
			$group,
			'init_plugin_suite_void_shield_min_time',
			array(
				'type'              => 'integer',
				'sanitize_callback' => function ( $v ) {
					return max( 1, min( 60, absint( $v ) ) );
				},
			)
		);

		register_setting(
			$group,
			'init_plugin_suite_void_shield_js_delay',
			array(
				'type'              => 'integer',
				'sanitize_callback' => function ( $v ) {
					return max( 0, min( 10000, absint( $v ) ) );
				},
			)
		);

		register_setting(
			$group,
			'init_plugin_suite_void_shield_max_time',
			array(
				'type'              => 'integer',
				'sanitize_callback' => function ( $v ) {
					return max( 60, min( 2592000, absint( $v ) ) );
				},
			)
		);

		// --- WordPress core forms ---------------------------------------.

		register_setting(
			$group,
			'init_plugin_suite_void_shield_enable_login_guard',
			array(
				'sanitize_callback' => 'init_plugin_suite_void_shield_sanitize_checkbox',
			)
		);

		register_setting(
			$group,
			'init_plugin_suite_void_shield_login_guard_scope',
			array(
				'sanitize_callback' => function ( $v ) {
					return in_array( $v, array( 'all', 'wp_login_only' ), true ) ? $v : 'all';
				},
			)
		);

		register_setting(
			$group,
			'init_plugin_suite_void_shield_enable_register_guard',
			array(
				'sanitize_callback' => 'init_plugin_suite_void_shield_sanitize_checkbox',
			)
		);

		register_setting(
			$group,
			'init_plugin_suite_void_shield_enable_lostpassword_guard',
			array(
				'sanitize_callback' => 'init_plugin_suite_void_shield_sanitize_checkbox',
			)
		);

		register_setting(
			$group,
			'init_plugin_suite_void_shield_enable_multisite_signup',
			array(
				'sanitize_callback' => 'init_plugin_suite_void_shield_sanitize_checkbox',
			)
		);

		// --- Form plugin integrations ------------------------------------.

		register_setting(
			$group,
			'init_plugin_suite_void_shield_enable_cf7',
			array(
				'sanitize_callback' => 'init_plugin_suite_void_shield_sanitize_checkbox',
			)
		);

		register_setting(
			$group,
			'init_plugin_suite_void_shield_enable_wpforms',
			array(
				'sanitize_callback' => 'init_plugin_suite_void_shield_sanitize_checkbox',
			)
		);

		register_setting(
			$group,
			'init_plugin_suite_void_shield_enable_gravityforms',
			array(
				'sanitize_callback' => 'init_plugin_suite_void_shield_sanitize_checkbox',
			)
		);

		// --- Community & e-commerce integrations --------------------------.

		register_setting(
			$group,
			'init_plugin_suite_void_shield_enable_woocommerce_register',
			array(
				'sanitize_callback' => 'init_plugin_suite_void_shield_sanitize_checkbox',
			)
		);

		register_setting(
			$group,
			'init_plugin_suite_void_shield_enable_bbpress',
			array(
				'sanitize_callback' => 'init_plugin_suite_void_shield_sanitize_checkbox',
			)
		);

		register_setting(
			$group,
			'init_plugin_suite_void_shield_enable_buddypress',
			array(
				'sanitize_callback' => 'init_plugin_suite_void_shield_sanitize_checkbox',
			)
		);

		// --- Advanced protection -----------------------------------------.

		register_setting(
			$group,
			'init_plugin_suite_void_shield_field_prefix',
			array(
				'sanitize_callback' => 'init_plugin_suite_void_shield_sanitize_field_prefix',
			)
		);

		register_setting(
			$group,
			'init_plugin_suite_void_shield_enable_css_rotation',
			array(
				'sanitize_callback' => 'init_plugin_suite_void_shield_sanitize_checkbox',
			)
		);

		register_setting(
			$group,
			'init_plugin_suite_void_shield_headless_detection',
			array(
				'sanitize_callback' => 'init_plugin_suite_void_shield_sanitize_checkbox',
			)
		);

		register_setting(
			$group,
			'init_plugin_suite_void_shield_require_interaction',
			array(
				'sanitize_callback' => 'init_plugin_suite_void_shield_sanitize_checkbox',
			)
		);

		register_setting(
			$group,
			'init_plugin_suite_void_shield_lazy_fetch',
			array(
				'sanitize_callback' => 'init_plugin_suite_void_shield_sanitize_checkbox',
			)
		);

		// --- Stats ---------------------------------------------------------.

		register_setting(
			$group,
			'init_plugin_suite_void_shield_enable_stats',
			array(
				'sanitize_callback' => 'init_plugin_suite_void_shield_sanitize_checkbox',
			)
		);

		register_setting(
			$group,
			'init_plugin_suite_void_shield_enable_dashboard_widget',
			array(
				'sanitize_callback' => 'init_plugin_suite_void_shield_sanitize_checkbox',
			)
		);
	}
);

// ------------------------------------------------------------------
// Reset stats action
// ------------------------------------------------------------------

add_action( 'admin_post_init_plugin_suite_void_shield_reset_stats', 'init_plugin_suite_void_shield_handle_reset_stats' );

/**
 * Handle the "Reset statistics" button.
 *
 * @return void
 */
function init_plugin_suite_void_shield_handle_reset_stats() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'init-void-shield' ) );
	}

	check_admin_referer( 'init_plugin_suite_void_shield_reset_stats' );

	init_plugin_suite_void_shield_reset_stats();

	$redirect = add_query_arg(
		array(
			'page'        => INIT_PLUGIN_SUITE_VOID_SHIELD_SLUG,
			'stats-reset' => '1',
		),
		admin_url( 'options-general.php' )
	);

	wp_safe_redirect( $redirect );
	exit;
}

// ------------------------------------------------------------------
// Display labels
// ------------------------------------------------------------------

/**
 * Human-friendly labels for stats channel keys.
 *
 * @return array
 */
function init_plugin_suite_void_shield_get_channel_labels() {
	return array(
		'comment'      => __( 'Comments', 'init-void-shield' ),
		'rest'         => __( 'REST API', 'init-void-shield' ),
		'login'        => __( 'Login form', 'init-void-shield' ),
		'register'     => __( 'Registration form', 'init-void-shield' ),
		'lostpassword' => __( 'Lost password form', 'init-void-shield' ),
		'multisite'    => __( 'Multisite signup form', 'init-void-shield' ),
		'cf7'          => __( 'Contact Form 7', 'init-void-shield' ),
		'wpforms'      => __( 'WPForms', 'init-void-shield' ),
		'gravityforms' => __( 'Gravity Forms', 'init-void-shield' ),
		'woocommerce'  => __( 'WooCommerce registration', 'init-void-shield' ),
		'bbpress'      => __( 'bbPress topic/reply', 'init-void-shield' ),
		'buddypress'   => __( 'BuddyPress registration', 'init-void-shield' ),
		'other'        => __( 'Other', 'init-void-shield' ),
	);
}

/**
 * Human-friendly labels for stats block-reason keys.
 *
 * @return array
 */
function init_plugin_suite_void_shield_get_reason_labels() {
	return array(
		'honeypot_field'     => __( 'Filled a hidden trap field', 'init-void-shield' ),
		'js_token'           => __( 'Missing or invalid JS token', 'init-void-shield' ),
		'headless_browser'   => __( 'Headless browser detected', 'init-void-shield' ),
		'no_interaction'     => __( 'No real interaction detected', 'init-void-shield' ),
		'time_token_missing' => __( 'Missing time token', 'init-void-shield' ),
		'time_token_invalid' => __( 'Invalid time token', 'init-void-shield' ),
		'token_expired'      => __( 'Time token expired', 'init-void-shield' ),
		'too_fast'           => __( 'Submitted too fast', 'init-void-shield' ),
		'no_user_agent'      => __( 'No user agent header', 'init-void-shield' ),
		'invalid_post_id'    => __( 'Invalid post ID', 'init-void-shield' ),
		'rest_blocked'       => __( 'REST API endpoint blocked', 'init-void-shield' ),
	);
}

/**
 * Print a small "detected / not detected" status badge for a form plugin.
 *
 * @param bool $active Whether the target plugin is currently active.
 * @return void
 */
function init_plugin_suite_void_shield_render_detection_badge( $active ) {
	if ( $active ) {
		echo '<span style="color:#046a04;">&#10003; ' . esc_html__( 'Detected on this site.', 'init-void-shield' ) . '</span>';
		return;
	}

	echo '<span style="color:#a94442;">&#8212; ' . esc_html__( 'Not detected on this site. Install and activate it first.', 'init-void-shield' ) . '</span>';
}

// ------------------------------------------------------------------
// Render Page
// ------------------------------------------------------------------

/**
 * Render the settings page.
 *
 * @return void
 */
function init_plugin_suite_void_shield_render_settings_page() {
	$enabled         = get_option( 'init_plugin_suite_void_shield_enabled', '1' );
	$apply_logged_in = get_option( 'init_plugin_suite_void_shield_apply_to_logged_in', '0' );
	$min_time        = absint( get_option( 'init_plugin_suite_void_shield_min_time', 3 ) );
	$js_delay        = absint( get_option( 'init_plugin_suite_void_shield_js_delay', 1000 ) );
	$max_time        = absint( get_option( 'init_plugin_suite_void_shield_max_time', 3600 ) );
	$block_rest      = get_option( 'init_plugin_suite_void_shield_block_rest', '0' );

	$enable_login            = get_option( 'init_plugin_suite_void_shield_enable_login_guard', '0' );
	$login_guard_scope       = get_option( 'init_plugin_suite_void_shield_login_guard_scope', 'all' );
	$enable_register         = get_option( 'init_plugin_suite_void_shield_enable_register_guard', '0' );
	$enable_lostpassword     = get_option( 'init_plugin_suite_void_shield_enable_lostpassword_guard', '0' );
	$enable_multisite_signup = get_option( 'init_plugin_suite_void_shield_enable_multisite_signup', '0' );

	$enable_cf7          = get_option( 'init_plugin_suite_void_shield_enable_cf7', '0' );
	$enable_wpforms      = get_option( 'init_plugin_suite_void_shield_enable_wpforms', '0' );
	$enable_gravityforms = get_option( 'init_plugin_suite_void_shield_enable_gravityforms', '0' );

	$enable_woocommerce = get_option( 'init_plugin_suite_void_shield_enable_woocommerce_register', '0' );
	$enable_bbpress     = get_option( 'init_plugin_suite_void_shield_enable_bbpress', '0' );
	$enable_buddypress  = get_option( 'init_plugin_suite_void_shield_enable_buddypress', '0' );

	$field_prefix        = init_plugin_suite_void_shield_get_field_prefix();
	$enable_css_rotation = get_option( 'init_plugin_suite_void_shield_enable_css_rotation', '1' );
	$headless_detection  = get_option( 'init_plugin_suite_void_shield_headless_detection', '1' );
	$require_interaction = get_option( 'init_plugin_suite_void_shield_require_interaction', '0' );
	$lazy_fetch          = get_option( 'init_plugin_suite_void_shield_lazy_fetch', '0' );

	$enable_stats            = get_option( 'init_plugin_suite_void_shield_enable_stats', '1' );
	$enable_dashboard_widget = get_option( 'init_plugin_suite_void_shield_enable_dashboard_widget', '0' );

	$cf7_active          = class_exists( 'WPCF7_ContactForm' );
	$wpforms_active      = function_exists( 'wpforms' );
	$gravityforms_active = class_exists( 'GFForms' );

	$woocommerce_active = class_exists( 'WooCommerce' );
	$bbpress_active     = class_exists( 'bbPress' );
	$buddypress_active  = function_exists( 'buddypress' );
	$is_multisite       = is_multisite();

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag, no state change is performed here.
	if ( isset( $_GET['stats-reset'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Statistics have been reset.', 'init-void-shield' ) . '</p></div>';
	}

	// No explicit settings_errors() call here: WordPress core already prints it
	// automatically for any page registered via add_options_page() (parent_file
	// 'options-general.php' auto-includes wp-admin/options-head.php, which calls
	// settings_errors() itself). Calling it again here would show "Settings saved."
	// twice.
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

		<form method="post" action="options.php">
			<?php settings_fields( INIT_PLUGIN_SUITE_VOID_SHIELD_OPTION ); ?>

			<h2><?php esc_html_e( 'Comments', 'init-void-shield' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="init_plugin_suite_void_shield_enabled">
							<?php esc_html_e( 'Enable Init Void Shield', 'init-void-shield' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="init_plugin_suite_void_shield_enabled" id="init_plugin_suite_void_shield_enabled" value="1" <?php checked( $enabled, '1' ); ?>>
							<?php esc_html_e( 'Activate the honeypot defense on comment forms.', 'init-void-shield' ); ?>
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="init_plugin_suite_void_shield_apply_to_logged_in">
							<?php esc_html_e( 'Apply to Logged-in Users', 'init-void-shield' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="init_plugin_suite_void_shield_apply_to_logged_in" id="init_plugin_suite_void_shield_apply_to_logged_in" value="1" <?php checked( $apply_logged_in, '1' ); ?>>
							<?php esc_html_e( 'Also verify comments from logged-in users.', 'init-void-shield' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'By default, logged-in users are trusted and bypass all checks. Enable this if your site has open registration or untrusted members.', 'init-void-shield' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="init_plugin_suite_void_shield_min_time">
							<?php esc_html_e( 'Minimum Submit Time', 'init-void-shield' ); ?>
						</label>
					</th>
					<td>
						<input type="number" min="1" max="60" step="1"
								name="init_plugin_suite_void_shield_min_time"
								id="init_plugin_suite_void_shield_min_time"
								value="<?php echo esc_attr( $min_time ); ?>"
								class="small-text">
						<span class="description"><?php esc_html_e( 'seconds', 'init-void-shield' ); ?></span>
						<p class="description">
							<?php esc_html_e( 'Comments submitted faster than this will be rejected. Humans need time to read and type.', 'init-void-shield' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="init_plugin_suite_void_shield_js_delay">
							<?php esc_html_e( 'JavaScript Token Delay', 'init-void-shield' ); ?>
						</label>
					</th>
					<td>
						<input type="number" min="0" max="10000" step="100"
								name="init_plugin_suite_void_shield_js_delay"
								id="init_plugin_suite_void_shield_js_delay"
								value="<?php echo esc_attr( $js_delay ); ?>"
								class="small-text">
						<span class="description"><?php esc_html_e( 'milliseconds', 'init-void-shield' ); ?></span>
						<p class="description">
							<?php esc_html_e( 'Delay before the hidden JS token is injected. Catches headless browsers that submit instantly.', 'init-void-shield' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="init_plugin_suite_void_shield_max_time">
							<?php esc_html_e( 'Maximum Token Age', 'init-void-shield' ); ?>
						</label>
					</th>
					<td>
						<input type="number" min="60" max="2592000" step="60"
								name="init_plugin_suite_void_shield_max_time"
								id="init_plugin_suite_void_shield_max_time"
								value="<?php echo esc_attr( $max_time ); ?>"
								class="small-text">
						<span class="description"><?php esc_html_e( 'seconds', 'init-void-shield' ); ?></span>
						<p class="description">
							<?php esc_html_e( 'A submission carrying a token older than this is rejected, so a token cannot be captured once and replayed indefinitely. On a site with full-page caching, a token is baked in at the moment a page is cached, not the moment a visitor actually loads it — if comments from real visitors are being rejected as expired, raise this to comfortably cover your cache lifetime, or enable Lazy Fetch below instead of raising it.', 'init-void-shield' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="init_plugin_suite_void_shield_block_rest">
							<?php esc_html_e( 'Block REST API Comments', 'init-void-shield' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="init_plugin_suite_void_shield_block_rest" id="init_plugin_suite_void_shield_block_rest" value="1" <?php checked( $block_rest, '1' ); ?>>
							<?php esc_html_e( 'Reject comments submitted directly through the REST API (wp/v2/comments).', 'init-void-shield' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'The honeypot and JS token checks above only cover the classic comment form; REST API submissions bypass them entirely. Enable this only if your site does not rely on a headless app or other legitimate client that posts comments via the REST API.', 'init-void-shield' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'WordPress Core Forms', 'init-void-shield' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Optional honeypot protection for the default WordPress login, registration, and lost-password forms at wp-login.php. Disabled by default, since these guard authentication itself — review your setup after enabling.', 'init-void-shield' ); ?>
			</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="init_plugin_suite_void_shield_enable_login_guard">
							<?php esc_html_e( 'Guard Login Form', 'init-void-shield' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="init_plugin_suite_void_shield_enable_login_guard" id="init_plugin_suite_void_shield_enable_login_guard" value="1" <?php checked( $enable_login, '1' ); ?>>
							<?php esc_html_e( 'Protect the default WP login form against automated login attempts.', 'init-void-shield' ); ?>
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="init_plugin_suite_void_shield_login_guard_scope">
							<?php esc_html_e( 'Login Guard Scope', 'init-void-shield' ); ?>
						</label>
					</th>
					<td>
						<select name="init_plugin_suite_void_shield_login_guard_scope" id="init_plugin_suite_void_shield_login_guard_scope">
							<option value="all" <?php selected( $login_guard_scope, 'all' ); ?>><?php esc_html_e( 'Everywhere (wp-login.php + any front-end wp_login_form() usage)', 'init-void-shield' ); ?></option>
							<option value="wp_login_only" <?php selected( $login_guard_scope, 'wp_login_only' ); ?>><?php esc_html_e( 'wp-login.php only', 'init-void-shield' ); ?></option>
						</select>
						<p class="description">
							<?php esc_html_e( 'If your theme or a plugin renders a login form on the front end with wp_login_form() (e.g. a login page, widget, or modal) and that page may be served by a full-page cache, choose "wp-login.php only" to leave that specific form unguarded rather than risk real visitors being rejected. This uses the Referer header as a heuristic to tell the two forms apart, which a determined bot could spoof — the default ("Everywhere") gives the strongest protection and is recommended unless you hit this specific situation.', 'init-void-shield' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="init_plugin_suite_void_shield_enable_register_guard">
							<?php esc_html_e( 'Guard Registration Form', 'init-void-shield' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="init_plugin_suite_void_shield_enable_register_guard" id="init_plugin_suite_void_shield_enable_register_guard" value="1" <?php checked( $enable_register, '1' ); ?>>
							<?php esc_html_e( 'Protect the default WP registration form (wp-login.php?action=register).', 'init-void-shield' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Only applies if user registration is open. Multisite signup (wp-signup.php) uses a different form and is not covered.', 'init-void-shield' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="init_plugin_suite_void_shield_enable_lostpassword_guard">
							<?php esc_html_e( 'Guard Lost Password Form', 'init-void-shield' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="init_plugin_suite_void_shield_enable_lostpassword_guard" id="init_plugin_suite_void_shield_enable_lostpassword_guard" value="1" <?php checked( $enable_lostpassword, '1' ); ?>>
							<?php esc_html_e( 'Protect the default WP lost-password form.', 'init-void-shield' ); ?>
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="init_plugin_suite_void_shield_enable_multisite_signup">
							<?php esc_html_e( 'Guard Multisite Signup Form', 'init-void-shield' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="init_plugin_suite_void_shield_enable_multisite_signup" id="init_plugin_suite_void_shield_enable_multisite_signup" value="1" <?php checked( $enable_multisite_signup, '1' ); ?>>
							<?php esc_html_e( 'Protect the site/user signup form at wp-signup.php.', 'init-void-shield' ); ?>
						</label>
						<p class="description">
							<?php
							if ( $is_multisite ) {
								echo '<span style="color:#046a04;">&#10003; ' . esc_html__( 'Multisite detected.', 'init-void-shield' ) . '</span>';
							} else {
								echo '<span style="color:#a94442;">&#8212; ' . esc_html__( 'This site is not a Multisite network; this guard has no effect here.', 'init-void-shield' ) . '</span>';
							}
							?>
						</p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Form Plugin Integrations', 'init-void-shield' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Optional honeypot protection for popular third-party form plugins. Each toggle only takes effect if the corresponding plugin is active.', 'init-void-shield' ); ?>
			</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="init_plugin_suite_void_shield_enable_cf7">
							<?php esc_html_e( 'Contact Form 7', 'init-void-shield' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="init_plugin_suite_void_shield_enable_cf7" id="init_plugin_suite_void_shield_enable_cf7" value="1" <?php checked( $enable_cf7, '1' ); ?>>
							<?php esc_html_e( 'Add the honeypot guard to all Contact Form 7 forms.', 'init-void-shield' ); ?>
						</label>
						<p class="description"><?php init_plugin_suite_void_shield_render_detection_badge( $cf7_active ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="init_plugin_suite_void_shield_enable_wpforms">
							<?php esc_html_e( 'WPForms', 'init-void-shield' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="init_plugin_suite_void_shield_enable_wpforms" id="init_plugin_suite_void_shield_enable_wpforms" value="1" <?php checked( $enable_wpforms, '1' ); ?>>
							<?php esc_html_e( 'Add the honeypot guard to all WPForms forms.', 'init-void-shield' ); ?>
						</label>
						<p class="description"><?php init_plugin_suite_void_shield_render_detection_badge( $wpforms_active ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="init_plugin_suite_void_shield_enable_gravityforms">
							<?php esc_html_e( 'Gravity Forms', 'init-void-shield' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="init_plugin_suite_void_shield_enable_gravityforms" id="init_plugin_suite_void_shield_enable_gravityforms" value="1" <?php checked( $enable_gravityforms, '1' ); ?>>
							<?php esc_html_e( 'Add the honeypot guard to all Gravity Forms forms.', 'init-void-shield' ); ?>
						</label>
						<p class="description"><?php init_plugin_suite_void_shield_render_detection_badge( $gravityforms_active ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Community & E-commerce Integrations', 'init-void-shield' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Optional honeypot protection for WooCommerce, bbPress, and BuddyPress. Each toggle only takes effect if the corresponding plugin is active.', 'init-void-shield' ); ?>
			</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="init_plugin_suite_void_shield_enable_woocommerce_register">
							<?php esc_html_e( 'WooCommerce Registration', 'init-void-shield' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="init_plugin_suite_void_shield_enable_woocommerce_register" id="init_plugin_suite_void_shield_enable_woocommerce_register" value="1" <?php checked( $enable_woocommerce, '1' ); ?>>
							<?php esc_html_e( 'Add the honeypot guard to the WooCommerce "My Account" registration form.', 'init-void-shield' ); ?>
						</label>
						<p class="description"><?php init_plugin_suite_void_shield_render_detection_badge( $woocommerce_active ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="init_plugin_suite_void_shield_enable_bbpress">
							<?php esc_html_e( 'bbPress Topics & Replies', 'init-void-shield' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="init_plugin_suite_void_shield_enable_bbpress" id="init_plugin_suite_void_shield_enable_bbpress" value="1" <?php checked( $enable_bbpress, '1' ); ?>>
							<?php esc_html_e( 'Add the honeypot guard to the New Topic and Reply forms.', 'init-void-shield' ); ?>
						</label>
						<p class="description"><?php init_plugin_suite_void_shield_render_detection_badge( $bbpress_active ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="init_plugin_suite_void_shield_enable_buddypress">
							<?php esc_html_e( 'BuddyPress Registration', 'init-void-shield' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="init_plugin_suite_void_shield_enable_buddypress" id="init_plugin_suite_void_shield_enable_buddypress" value="1" <?php checked( $enable_buddypress, '1' ); ?>>
							<?php esc_html_e( 'Add the honeypot guard to the BuddyPress signup form.', 'init-void-shield' ); ?>
						</label>
						<p class="description"><?php init_plugin_suite_void_shield_render_detection_badge( $buddypress_active ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Advanced Protection', 'init-void-shield' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="init_plugin_suite_void_shield_field_prefix">
							<?php esc_html_e( 'Custom Field Prefix', 'init-void-shield' ); ?>
						</label>
					</th>
					<td>
						<input type="text" maxlength="16" pattern="[a-z0-9_]+"
								name="init_plugin_suite_void_shield_field_prefix"
								id="init_plugin_suite_void_shield_field_prefix"
								value="<?php echo esc_attr( $field_prefix ); ?>"
								class="regular-text">
						<p class="description">
							<?php esc_html_e( 'Prefix used to build the hidden honeypot field names (lowercase letters, numbers, underscore only). Change this if you suspect a spammer has targeted your site specifically.', 'init-void-shield' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="init_plugin_suite_void_shield_enable_css_rotation">
							<?php esc_html_e( 'CSS Trap Rotation', 'init-void-shield' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="init_plugin_suite_void_shield_enable_css_rotation" id="init_plugin_suite_void_shield_enable_css_rotation" value="1" <?php checked( $enable_css_rotation, '1' ); ?>>
							<?php esc_html_e( 'Randomize the CSS technique and field order used to hide honeypot traps on every render.', 'init-void-shield' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Makes it harder for CSS-aware bots to learn a single fixed pattern. Traps remain invisible to humans either way.', 'init-void-shield' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="init_plugin_suite_void_shield_headless_detection">
							<?php esc_html_e( 'Headless Browser Detection', 'init-void-shield' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="init_plugin_suite_void_shield_headless_detection" id="init_plugin_suite_void_shield_headless_detection" value="1" <?php checked( $headless_detection, '1' ); ?>>
							<?php esc_html_e( 'Flag common automation signals (navigator.webdriver, zero-size browser window) picked up by the JS token script.', 'init-void-shield' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Lightweight, client-side only, no external calls. Disable this if you use browser-automation tools to test submissions on your own site.', 'init-void-shield' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="init_plugin_suite_void_shield_require_interaction">
							<?php esc_html_e( 'Require Real User Interaction', 'init-void-shield' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="init_plugin_suite_void_shield_require_interaction" id="init_plugin_suite_void_shield_require_interaction" value="1" <?php checked( $require_interaction, '1' ); ?>>
							<?php esc_html_e( 'Also require at least one real mouse, keyboard, touch, or scroll event before the form is accepted.', 'init-void-shield' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Catches bots that simply wait out the JavaScript Token Delay instead of interacting with the page. Off by default; works best with a JavaScript Token Delay of at least 1-2 seconds.', 'init-void-shield' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="init_plugin_suite_void_shield_lazy_fetch">
							<?php esc_html_e( 'Lazy Fetch (Cache-Safe Tokens)', 'init-void-shield' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="init_plugin_suite_void_shield_lazy_fetch" id="init_plugin_suite_void_shield_lazy_fetch" value="1" <?php checked( $lazy_fetch, '1' ); ?>>
							<?php esc_html_e( 'Refresh the time token via a small same-origin JavaScript request as soon as the page truly loads, instead of relying only on the value baked in when the page was rendered.', 'init-void-shield' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Recommended for sites with aggressive full-page caching. On such a site, the token baked into a cached page reflects the moment the page was cached, not the moment a real visitor loaded it, which can make legitimate comments look expired under a low Maximum Token Age. When enabled, this refreshes the token client-side (plain JavaScript, no jQuery) right after the page loads, so timing is always measured from the real visit — applies to every guarded form, not just comments. If the request fails or JavaScript is unavailable, the original baked-in token is used as a fallback, so this can only help. Off by default.', 'init-void-shield' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Statistics', 'init-void-shield' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="init_plugin_suite_void_shield_enable_stats">
							<?php esc_html_e( 'Track Blocked Submissions', 'init-void-shield' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="init_plugin_suite_void_shield_enable_stats" id="init_plugin_suite_void_shield_enable_stats" value="1" <?php checked( $enable_stats, '1' ); ?>>
							<?php esc_html_e( 'Keep lightweight counters of blocked submissions, shown below.', 'init-void-shield' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Stored as a single, non-autoloaded option. No per-submission logs or personal data are kept.', 'init-void-shield' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="init_plugin_suite_void_shield_enable_dashboard_widget">
							<?php esc_html_e( 'Show Dashboard Widget', 'init-void-shield' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="init_plugin_suite_void_shield_enable_dashboard_widget" id="init_plugin_suite_void_shield_enable_dashboard_widget" value="1" <?php checked( $enable_dashboard_widget, '1' ); ?>>
							<?php esc_html_e( 'Show a compact "Blocked Submissions" widget on the WordPress Dashboard, visible to users who can manage options.', 'init-void-shield' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Off by default. Requires "Track Blocked Submissions" above to have data to show.', 'init-void-shield' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>

		<?php init_plugin_suite_void_shield_render_stats_panel(); ?>
	</div>
	<?php
}

/**
 * Render the read-only statistics panel and the "Reset statistics" form.
 *
 * @return void
 */
function init_plugin_suite_void_shield_render_stats_panel() {
	$stats          = init_plugin_suite_void_shield_get_stats();
	$channel_labels = init_plugin_suite_void_shield_get_channel_labels();
	$reason_labels  = init_plugin_suite_void_shield_get_reason_labels();
	?>
	<h2><?php esc_html_e( 'Blocked Submissions', 'init-void-shield' ); ?></h2>

	<p>
		<strong><?php esc_html_e( 'Total blocked:', 'init-void-shield' ); ?></strong>
		<?php echo esc_html( number_format_i18n( absint( $stats['total'] ) ) ); ?>
		<?php if ( ! empty( $stats['last_blocked'] ) ) : ?>
			&nbsp;&mdash;&nbsp;
			<?php
			printf(
				// translators: %s: human-readable time difference, e.g. "3 hours".
				esc_html__( 'last blocked %s ago', 'init-void-shield' ),
				esc_html( human_time_diff( absint( $stats['last_blocked'] ), time() ) )
			);
			?>
		<?php endif; ?>
	</p>

	<?php if ( ! empty( $stats['by_channel'] ) && is_array( $stats['by_channel'] ) ) : ?>
		<p><strong><?php esc_html_e( 'By channel:', 'init-void-shield' ); ?></strong></p>
		<ul style="list-style: disc; margin-left: 1.5em;">
			<?php foreach ( $stats['by_channel'] as $channel => $count ) : ?>
				<li>
					<?php
					$label = isset( $channel_labels[ $channel ] ) ? $channel_labels[ $channel ] : $channel;
					echo esc_html( $label ) . ': ' . esc_html( number_format_i18n( absint( $count ) ) );
					?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( ! empty( $stats['by_reason'] ) && is_array( $stats['by_reason'] ) ) : ?>
		<p><strong><?php esc_html_e( 'By reason:', 'init-void-shield' ); ?></strong></p>
		<ul style="list-style: disc; margin-left: 1.5em;">
			<?php foreach ( $stats['by_reason'] as $reason => $count ) : ?>
				<li>
					<?php
					$label = isset( $reason_labels[ $reason ] ) ? $reason_labels[ $reason ] : $reason;
					echo esc_html( $label ) . ': ' . esc_html( number_format_i18n( absint( $count ) ) );
					?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( empty( $stats['total'] ) ) : ?>
		<p class="description"><?php esc_html_e( 'No submissions have been blocked yet.', 'init-void-shield' ); ?></p>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="init_plugin_suite_void_shield_reset_stats">
		<?php wp_nonce_field( 'init_plugin_suite_void_shield_reset_stats' ); ?>
		<?php submit_button( __( 'Reset Statistics', 'init-void-shield' ), 'secondary', 'submit', false ); ?>
	</form>
	<?php
}
