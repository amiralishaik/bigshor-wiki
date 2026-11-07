<?php
/**
 * Plugin Name: BIGSHOR WIKI Admin UI
 * Description: Independent white-label administration interface for BIGSHOR WIKI.
 * Version: 1.5.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: BIGSHOR WIKI
 * Text Domain: bigshor-wiki-admin-ui
 */
if (!defined('ABSPATH')) exit;

define('BSW_ADMIN_UI_VERSION', '1.5.0');
define('BSW_ADMIN_UI_FILE', __FILE__);

add_action('plugins_loaded', function () {
    if (!defined('BSW_CORE_PLUGIN_VERSION')) {
        add_action('admin_notices', function () {
            if (!current_user_can('activate_plugins')) return;
            echo '<div class="notice notice-error"><p><strong>BIGSHOR WIKI Admin UI</strong> requires the BIGSHOR WIKI Core plugin to be active. Activate the Core plugin, then reload this page.</p></div>';
        });
        return;
    }
    require_once __DIR__ . '/includes/admin-white-label.php';
}, 20);

register_activation_hook(__FILE__, function () {
    if (!defined('BSW_CORE_PLUGIN_VERSION')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('BIGSHOR WIKI Admin UI requires BIGSHOR WIKI Core. Activate Core first, then activate Admin UI.', 'bigshor-wiki-admin-ui'),
            esc_html__('BIGSHOR WIKI Core required', 'bigshor-wiki-admin-ui'),
            array('back_link' => true)
        );
    }
});
