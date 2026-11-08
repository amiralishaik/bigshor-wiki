<?php
/**
 * Plugin Name: BIGSHOR WIKI Article Studio — Migration Stub
 * Description: Article Studio is included in BIGSHOR WIKI Core v34.11.0+. This stub only deactivates itself after the Core module is available.
 * Version: 1.4.1
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: BIGSHOR WIKI
 */
if (!defined('ABSPATH')) exit;
add_action('plugins_loaded', function () {
    if (defined('BSW_CORE_PLUGIN_ACTIVE') && function_exists('deactivate_plugins')) {
        deactivate_plugins(plugin_basename(__FILE__));
    }
}, 100);
add_action('admin_notices', function () {
    if (!defined('BSW_CORE_PLUGIN_ACTIVE') && current_user_can('activate_plugins')) {
        echo '<div class="notice notice-warning"><p><strong>BIGSHOR WIKI Article Studio</strong> is now included in BIGSHOR WIKI Core. Update and activate Core v34.11.0 or later.</p></div>';
    }
});
