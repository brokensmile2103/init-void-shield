<?php
/**
 * Settings Page
 * Minimal controls: on/off, apply to logged-in, min time, JS delay.
 */

defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------------
 * Admin Menu
 * ------------------------------------------------------------------ */
add_action( 'admin_menu', function () {
    add_options_page(
        esc_html__( 'Init Void Shield', 'init-void-shield' ),
        esc_html__( 'Init Void Shield', 'init-void-shield' ),
        'manage_options',
        INIT_PLUGIN_SUITE_VOID_SHIELD_SLUG,
        'init_plugin_suite_void_shield_render_settings_page'
    );
} );

/* ------------------------------------------------------------------
 * Register Settings
 * ------------------------------------------------------------------ */
add_action( 'admin_init', function () {
    $group = INIT_PLUGIN_SUITE_VOID_SHIELD_OPTION;

    register_setting( $group, 'init_plugin_suite_void_shield_enabled', [
        'type'              => 'string',
        'sanitize_callback' => function ( $v ) {
            return isset( $v ) ? '1' : '0';
        },
        'default'           => '1',
    ] );

    register_setting( $group, 'init_plugin_suite_void_shield_apply_to_logged_in', [
        'type'              => 'string',
        'sanitize_callback' => function ( $v ) {
            return isset( $v ) ? '1' : '0';
        },
        'default'           => '0',
    ] );

    register_setting( $group, 'init_plugin_suite_void_shield_block_rest', [
        'type'              => 'string',
        'sanitize_callback' => function ( $v ) {
            return isset( $v ) ? '1' : '0';
        },
        'default'           => '0',
    ] );

    register_setting( $group, 'init_plugin_suite_void_shield_min_time', [
        'type'              => 'integer',
        'sanitize_callback' => function ( $v ) {
            $v = absint( $v );
            return max( 1, min( 60, $v ) );
        },
        'default'           => 3,
    ] );

    register_setting( $group, 'init_plugin_suite_void_shield_js_delay', [
        'type'              => 'integer',
        'sanitize_callback' => function ( $v ) {
            $v = absint( $v );
            return max( 0, min( 10000, $v ) );
        },
        'default'           => 1000,
    ] );
} );

/* ------------------------------------------------------------------
 * Render Page
 * ------------------------------------------------------------------ */
function init_plugin_suite_void_shield_render_settings_page() {
    $enabled         = get_option( 'init_plugin_suite_void_shield_enabled', '1' );
    $apply_logged_in = get_option( 'init_plugin_suite_void_shield_apply_to_logged_in', '0' );
    $min_time        = absint( get_option( 'init_plugin_suite_void_shield_min_time', 3 ) );
    $js_delay        = absint( get_option( 'init_plugin_suite_void_shield_js_delay', 1000 ) );
    $block_rest      = get_option( 'init_plugin_suite_void_shield_block_rest', '0' );
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

        <form method="post" action="options.php">
            <?php settings_fields( INIT_PLUGIN_SUITE_VOID_SHIELD_OPTION ); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="init_plugin_suite_void_shield_enabled">
                            <?php esc_html_e( 'Enable Void Shield', 'init-void-shield' ); ?>
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

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
