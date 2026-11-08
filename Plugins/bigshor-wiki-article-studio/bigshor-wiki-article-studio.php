<?php
/**
 * Plugin Name: BIGSHOR WIKI Article Studio — Core Compatibility Stub
 * Description: Article Studio is bundled with BIGSHOR WIKI Core v34.11.0+. This stub prevents duplicate loading during upgrades.
 * Version: 1.4.1
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: BIGSHOR WIKI
 */
if (!defined('ABSPATH')) exit;
add_action('admin_notices', function () {
    if (!defined('BSW_CORE_PLUGIN_ACTIVE') && current_user_can('activate_plugins')) {
        echo '<div class="notice notice-warning"><p><strong>BIGSHOR WIKI Article Studio</strong> is now included in BIGSHOR WIKI Core. Activate/update Core v34.11.0 or later, then deactivate this compatibility plugin.</p></div>';
    }
});
