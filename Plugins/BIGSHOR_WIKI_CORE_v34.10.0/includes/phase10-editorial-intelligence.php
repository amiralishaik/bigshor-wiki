<?php
/**
 * BIGSHOR Wiki Phase 10: Editorial Intelligence & Audit Trail.
 * Records editorial events, provides an admin activity dashboard and CSV export.
 * Audit rows are append-only through this plugin's interface.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase10_Editorial_Intelligence')) {
final class BSW_Phase10_Editorial_Intelligence {
    const VERSION = '24.1.0';
    const OPTION = 'bsw_phase10_settings';
    public static function table() { global $wpdb; return $wpdb->prefix . 'bsw_editorial_audit'; }
    public static function init() {
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('init', array(__CLASS__, 'maybe_upgrade'), 1);
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 43);
        add_action('transition_post_status', array(__CLASS__, 'status_change'), 10, 3);
        add_action('post_updated', array(__CLASS__, 'post_updated'), 10, 3);
        add_action('added_post_meta', array(__CLASS__, 'correction_meta'), 10, 4);
        add_action('updated_post_meta', array(__CLASS__, 'correction_meta'), 10, 4);
        add_action('admin_post_bsw_p10_export', array(__CLASS__, 'export_csv'));
        add_action('bsw_p10_daily_maintenance', array(__CLASS__, 'purge_expired'));
        add_action('wp_save_post_revision', array(__CLASS__, 'revision_saved'), 10, 2);
        add_action('admin_post_bsw_p10_purge', array(__CLASS__, 'manual_purge'));
        if (!wp_next_scheduled('bsw_p10_daily_maintenance')) wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'bsw_p10_daily_maintenance');
    }
    public static function defaults() { return array('enabled' => 1, 'retention_days' => 0, 'track_revisions' => 1, 'audit_capability' => 'manage_options'); }
    public static function settings() { $v = get_option(self::OPTION, array()); return wp_parse_args(is_array($v) ? $v : array(), self::defaults()); }
    public static function maybe_upgrade() {
        if (get_option('bsw_phase10_schema_version') !== self::VERSION) { self::activate(); update_option('bsw_phase10_schema_version', self::VERSION, false); }
    }
    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table(); $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_time datetime NOT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            object_id bigint(20) unsigned NOT NULL DEFAULT 0,
            object_type varchar(32) NOT NULL DEFAULT '',
            event_type varchar(48) NOT NULL DEFAULT '',
            old_value text NULL,
            new_value text NULL,
            PRIMARY KEY  (id),
            KEY event_time (event_time),
            KEY object_id (object_id),
            KEY event_type (event_type)
        ) {$charset};";
        dbDelta($sql);
        if (!get_option(self::OPTION)) add_option(self::OPTION, self::defaults(), '', false);
    }
    private static function log($event, $object_id, $object_type, $old = '', $new = '') {
        $s = self::settings(); if (empty($s['enabled'])) return;
        global $wpdb;
        $wpdb->insert(self::table(), array(
            'event_time' => current_time('mysql'), 'user_id' => get_current_user_id(),
            'object_id' => absint($object_id), 'object_type' => sanitize_key($object_type),
            'event_type' => sanitize_key($event), 'old_value' => $old ? wp_strip_all_tags((string)$old) : null,
            'new_value' => $new ? wp_strip_all_tags((string)$new) : null
        ), array('%s','%d','%d','%s','%s','%s','%s'));
    }
    public static function status_change($new, $old, $post) {
        if (!$post || wp_is_post_revision($post->ID) || wp_is_post_autosave($post->ID) || in_array($post->post_type, array('revision','nav_menu_item'), true)) return;
        if ($new !== $old) self::log('status_change', $post->ID, $post->post_type, $old, $new);
    }
    public static function post_updated($post_id, $after, $before) {
        if (!$after || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || in_array($after->post_type, array('revision','nav_menu_item'), true)) return;
        if ($after->post_title !== $before->post_title || $after->post_content !== $before->post_content) {
            self::log('content_updated', $post_id, $after->post_type, 'Previous content version updated', 'Article content/title changed');
        }
    }
    public static function correction_meta($meta_id, $object_id, $meta_key, $meta_value) {
        if ($meta_key !== '_bsw_report_status' || get_post_type($object_id) !== 'bsw_correction' || !is_scalar($meta_value)) return;
        $status = sanitize_key((string)$meta_value);
        self::log('correction_' . $status, $object_id, 'bsw_correction', '', $status);
    }
    public static function register_settings() { register_setting('bsw_phase10_group', self::OPTION, array('sanitize_callback' => array(__CLASS__, 'sanitize_settings'))); }
    public static function sanitize_settings($input) {
        $input = is_array($input) ? $input : array();
        return array('enabled' => empty($input['enabled']) ? 0 : 1, 'track_revisions' => empty($input['track_revisions']) ? 0 : 1, 'retention_days' => isset($input['retention_days']) ? min(3650, max(0, absint($input['retention_days']))) : 0, 'audit_capability' => (isset($input['audit_capability']) && $input['audit_capability'] === 'edit_others_posts') ? 'edit_others_posts' : 'manage_options');
    }
    public static function admin_menu() { add_submenu_page('options-general.php', 'BIGSHOR Editorial Intelligence', 'BIGSHOR Audit & Quality', 'edit_others_posts', 'bsw-phase10', array(__CLASS__, 'page')); }
    public static function page() {
        $s = self::settings(); $cap = $s['audit_capability'];
        if (!current_user_can($cap)) return;
        global $wpdb; $table = self::table();
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) { self::activate(); }
        $page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1; $per_page = 30; $offset = ($page - 1) * $per_page;
        $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset));
        $posts = wp_count_posts('wiki_article'); if (!$posts || !isset($posts->publish)) $posts = wp_count_posts('post'); $pages = wp_count_posts('page');
        $unresolved = 0; if (post_type_exists('bsw_correction')) $unresolved = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_bsw_report_status' AND meta_value IN ('new','reviewing')");
        echo '<div class="wrap"><h1>BIGSHOR Editorial Intelligence</h1><p>Editorial activity history and content-quality overview. Audit entries are displayed read-only here.</p>';
        echo '<div style="display:flex;gap:16px;flex-wrap:wrap"><div class="card"><h2>Published articles</h2><p style="font-size:26px">'.absint(isset($posts->publish)?$posts->publish:0).'</p></div><div class="card"><h2>Published pages</h2><p style="font-size:26px">'.absint(isset($pages->publish)?$pages->publish:0).'</p></div><div class="card"><h2>Open correction reports</h2><p style="font-size:26px">'.absint($unresolved).'</p></div><div class="card"><h2>Audit events</h2><p style="font-size:26px">'.absint($total).'</p></div></div>';
        echo '<h2>Audit activity</h2><p><a class="button button-secondary" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=bsw_p10_export'),'bsw_p10_export')).'">Export audit log (CSV)</a></p>';
        echo '<table class="widefat striped"><thead><tr><th>Date</th><th>User</th><th>Content</th><th>Type</th><th>Event</th><th>Details</th></tr></thead><tbody>';
        if ($rows) foreach ($rows as $r) {
            $user = $r->user_id ? get_user_by('id', $r->user_id) : false; $obj = $r->object_id ? get_post($r->object_id) : false;
            $content = $obj ? '<a href="'.esc_url(get_edit_post_link($obj->ID)).'">'.esc_html(get_the_title($obj->ID)).'</a>' : 'Content unavailable';
            $details = trim((string)$r->old_value . ' → ' . (string)$r->new_value, ' →');
            echo '<tr><td>'.esc_html($r->event_time).'</td><td>'.esc_html($user ? $user->display_name : 'System/Unknown').'</td><td>'.$content.'</td><td>'.esc_html($r->object_type).'</td><td>'.esc_html($r->event_type).'</td><td>'.esc_html($details).'</td></tr>';
        } else echo '<tr><td colspan="6">No audit events recorded yet.</td></tr>';
        echo '</tbody></table>';
        $pages_count = (int)ceil($total/$per_page); if ($pages_count > 1) echo '<p>'.paginate_links(array('base'=>add_query_arg('paged','%#%'),'format'=>'','current'=>$page,'total'=>$pages_count)).'</p>';
        echo '<hr><h2>Settings</h2><form method="post" action="options.php">'; settings_fields('bsw_phase10_group');
        echo '<p><label><input type="checkbox" name="'.esc_attr(self::OPTION).'[enabled]" value="1" '.checked($s['enabled'],1,false).'> Enable editorial audit logging</label></p>';
        echo '<p><label>Retention days (0 = keep indefinitely): <input type="number" min="0" max="3650" name="'.esc_attr(self::OPTION).'[retention_days]" value="'.absint($s['retention_days']).'"></label></p>';
        echo '<p><label><input type="checkbox" name="'.esc_attr(self::OPTION).'[track_revisions]" value="1" '.checked($s['track_revisions'],1,false).'> Record revision creation events</label></p>';
        echo '<p><label>Who can view audit records? <select name="'.esc_attr(self::OPTION).'[audit_capability]"><option value="manage_options" '.selected($s['audit_capability'],'manage_options',false).'>Administrators only</option><option value="edit_others_posts" '.selected($s['audit_capability'],'edit_others_posts',false).'>Editors and administrators</option></select></label></p>';
        echo '<p class="description">Automatic daily retention cleanup is enabled when retention is greater than zero. CSV export and manual cleanup remain administrator-only.</p>'; submit_button('Save settings'); echo '</form>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; echo '<input type="hidden" name="action" value="bsw_p10_purge">'; wp_nonce_field('bsw_p10_purge'); submit_button('Run retention cleanup now','secondary'); echo '</form></div>';
    }
    public static function revision_saved($revision_id, $post_id) {
        $s = self::settings(); if (empty($s['enabled']) || empty($s['track_revisions'])) return;
        $parent = get_post($post_id); $revision = get_post($revision_id);
        if (!$parent || !$revision || wp_is_post_autosave($revision_id)) return;
        self::log('revision_created', $post_id, $parent->post_type, '', 'Revision #' . absint($revision_id) . ' created');
    }
    public static function purge_expired() {
        $days = absint(self::settings()['retention_days']); if (!$days) return 0;
        global $wpdb; $table = self::table();
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)); if ($exists !== $table) return 0;
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        return (int)$wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE event_time < %s", $cutoff));
    }
    public static function manual_purge() {
        if (!current_user_can('manage_options')) wp_die('Not allowed.'); check_admin_referer('bsw_p10_purge');
        self::purge_expired(); wp_safe_redirect(add_query_arg('bsw_p10_purged', '1', admin_url('options-general.php?page=bsw-phase10'))); exit;
    }
    public static function export_csv() {
        if (!current_user_can('manage_options')) wp_die('Not allowed.'); check_admin_referer('bsw_p10_export');
        global $wpdb; $rows = $wpdb->get_results('SELECT * FROM '.self::table().' ORDER BY id DESC LIMIT 10000', ARRAY_A);
        nocache_headers(); header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename=bigshor-editorial-audit.csv');
        $out = fopen('php://output', 'w'); fputcsv($out, array('ID','Date','User ID','Object ID','Object Type','Event','Old Value','New Value'));
        foreach ($rows as $row) fputcsv($out, $row); fclose($out); exit;
    }
}
BSW_Phase10_Editorial_Intelligence::init();
register_activation_hook(dirname(__FILE__, 2) . '/bigshor-wiki-core.php', array('BSW_Phase10_Editorial_Intelligence', 'activate'));
}
