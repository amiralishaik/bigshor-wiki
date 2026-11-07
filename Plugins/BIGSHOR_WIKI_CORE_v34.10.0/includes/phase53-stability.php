<?php
/**
 * BIGSHOR Wiki Phase 53 — production stability diagnostics.
 * Read-only health checks; this module never mutates wiki content or database schema.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase53_Stability')) {
final class BSW_Phase53_Stability {
    const VERSION = '53.0.0';

    public static function boot() {
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 90);
    }

    public static function admin_menu() {
        add_management_page(
            'BIGSHOR Platform Health',
            'BIGSHOR Platform Health',
            'manage_options',
            'bsw-platform-health',
            array(__CLASS__, 'render_page')
        );
    }

    private static function table_exists($table) {
        global $wpdb;
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        return $found === $table;
    }

    private static function check($label, $ok, $detail, $severity = 'error') {
        return array('label' => $label, 'ok' => (bool) $ok, 'detail' => $detail, 'severity' => $severity);
    }

    public static function collect_checks() {
        global $wpdb;
        $checks = array();
        $php = PHP_VERSION;
        $wp = get_bloginfo('version');
        $checks[] = self::check('PHP runtime', version_compare($php, '7.4', '>='), 'Detected PHP ' . $php . '; plugin header requires PHP 7.4 or newer.');
        $checks[] = self::check('WordPress runtime', version_compare($wp, '6.4', '>='), 'Detected WordPress ' . $wp . '; plugin header requires WordPress 6.4 or newer.');
        $checks[] = self::check('Database connection', empty($wpdb->last_error), empty($wpdb->last_error) ? 'WordPress database object is available.' : 'Database reports an error: ' . $wpdb->last_error);
        $checks[] = self::check('Wiki article post type', post_type_exists('wiki_article'), post_type_exists('wiki_article') ? 'wiki_article is registered.' : 'wiki_article is not registered.');
        $checks[] = self::check('Namespace taxonomy', taxonomy_exists('wiki_namespace'), taxonomy_exists('wiki_namespace') ? 'wiki_namespace is registered.' : 'Namespace taxonomy is not registered; namespace routes and filters may be unavailable.', 'warning');
        $checks[] = self::check('Advanced template engine', class_exists('BSW_Phase54_Template_Engine'), class_exists('BSW_Phase54_Template_Engine') ? 'Nested template and parser-function engine is loaded.' : 'Phase 2 template engine is not loaded.');
        $checks[] = self::check('Wiki template content type', post_type_exists('wiki_template'), post_type_exists('wiki_template') ? 'Administrator-managed wiki_template content type is registered.' : 'Wiki template content type is not registered.');

        $search_table = $wpdb->prefix . 'bsw_wiki_search';
        $checks[] = self::check('Search index table', self::table_exists($search_table), self::table_exists($search_table) ? 'Search index table exists.' : 'Search index table is missing; verify Phase 52 upgrade ran.');

        $audit_table = $wpdb->prefix . 'bsw_editorial_audit';
        $audit_exists = self::table_exists($audit_table);
        $checks[] = self::check('Editorial audit table', $audit_exists, $audit_exists ? 'Editorial audit table exists.' : 'Audit table is not present. It may be created on first activation of the editorial intelligence module.', 'warning');

        $index_event = wp_next_scheduled('bsw52_index_batch');
        $mail_event = wp_next_scheduled('bsw51_process_mail_queue');
        $checks[] = self::check('Search index schedule', (bool) $index_event || (bool) get_option('bsw52_index_complete'), $index_event ? 'Hourly index backfill event is scheduled.' : (get_option('bsw52_index_complete') ? 'Initial index backfill is marked complete.' : 'No search index event is scheduled.'), 'warning');
        $checks[] = self::check('Notification queue schedule', (bool) $mail_event, $mail_event ? 'Notification queue processor is scheduled.' : 'Notification processor event is not scheduled; check Phase 51 activation.', 'warning');

        $schema = get_option('bsw_core_schema_version', 'not set');
        $checks[] = self::check('Core schema marker', $schema === BSW_CORE_PLUGIN_VERSION, 'Stored marker: ' . $schema . '; expected: ' . BSW_CORE_PLUGIN_VERSION . '.');
        $checks[] = self::check('Phase 52 schema marker', get_option('bsw52_schema_version') === '52.0.0', 'Stored marker: ' . get_option('bsw52_schema_version', 'not set') . '; expected: 52.0.0.', 'warning');
        $checks[] = self::check('Search index backfill', (bool) get_option('bsw52_index_complete'), get_option('bsw52_index_complete') ? 'Initial legacy article index backfill is complete.' : 'Initial legacy article index backfill is still pending.', 'warning');
        return $checks;
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) wp_die(esc_html__('You do not have permission to view platform diagnostics.', 'bigshor-wiki'));
        $checks = self::collect_checks();
        $passed = 0;
        foreach ($checks as $check) if ($check['ok']) $passed++;
        echo '<div class="wrap"><h1>BIGSHOR Platform Health</h1>';
        echo '<p>Read-only diagnostics for the BIGSHOR Wiki Core runtime. These checks do not replace staging acceptance, security testing or backups.</p>';
        echo '<p><strong>' . esc_html($passed . ' of ' . count($checks) . ' checks passed') . '</strong></p>';
        echo '<table class="widefat striped"><thead><tr><th>Check</th><th>Status</th><th>Details</th></tr></thead><tbody>';
        foreach ($checks as $check) {
            $status = $check['ok'] ? 'PASS' : ($check['severity'] === 'warning' ? 'REVIEW' : 'FAIL');
            echo '<tr><td>' . esc_html($check['label']) . '</td><td><strong>' . esc_html($status) . '</strong></td><td>' . esc_html($check['detail']) . '</td></tr>';
        }
        echo '</tbody></table><p><em>For a valid production sign-off, repeat these checks on the actual staging site and attach screenshots/log evidence.</em></p></div>';
    }
}
BSW_Phase53_Stability::boot();
}
