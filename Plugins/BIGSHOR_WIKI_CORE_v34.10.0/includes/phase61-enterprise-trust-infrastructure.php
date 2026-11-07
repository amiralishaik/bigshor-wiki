<?php
/**
 * Phase 8: Enterprise trust, operational resilience and observability.
 * Conservative defaults: no automatic blocking, deletion or publication.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase61_Enterprise_Trust')) {
final class BSW_Phase61_Enterprise_Trust {
    const AUDIT_KEY = 'bsw_phase61_audit';
    const FLAGS_KEY = 'bsw_phase61_review_queue';
    const ANALYTICS_KEY = 'bsw_phase61_analytics';
    const CRON = 'bsw_phase61_daily_maintenance';
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'), 90);
        add_action('admin_post_bsw_phase61_backup', array(__CLASS__, 'download_backup'));
        add_action('save_post_wiki_article', array(__CLASS__, 'inspect_article'), 30, 3);
        add_action('transition_post_status', array(__CLASS__, 'status_audit'), 10, 3);
        add_action('before_delete_post', array(__CLASS__, 'deletion_audit'), 10, 2);
        add_action('user_register', array(__CLASS__, 'user_registered'), 10, 1);
        add_action('set_user_role', array(__CLASS__, 'role_changed'), 10, 3);
        add_action('wp_login', array(__CLASS__, 'login_success'), 10, 2);
        add_action('wp_login_failed', array(__CLASS__, 'login_failed'), 10, 2);
        add_filter('authenticate', array(__CLASS__, 'login_guard'), 1, 3);
        add_action('comment_post', array(__CLASS__, 'comment_created'), 10, 3);
        add_action('template_redirect', array(__CLASS__, 'track_view'), 20);
        add_action(self::CRON, array(__CLASS__, 'maintenance'));
        add_action('save_post_wiki_article', array(__CLASS__, 'purge_article_cache'), 40, 1);
        add_action('deleted_post', array(__CLASS__, 'purge_article_cache'), 40, 1);
        if (!wp_next_scheduled(self::CRON)) wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON);
    }
    private static function audit($event, $subject = '', $details = array()) {
        $rows = get_option(self::AUDIT_KEY, array()); if (!is_array($rows)) $rows = array();
        $rows[] = array('time'=>gmdate('c'), 'event'=>sanitize_key($event), 'actor'=>get_current_user_id(), 'subject'=>sanitize_text_field((string)$subject), 'details'=>self::safe_details($details));
        if (count($rows) > 1000) $rows = array_slice($rows, -1000);
        update_option(self::AUDIT_KEY, $rows, false);
    }
    private static function safe_details($details) {
        $out = array(); foreach ((array)$details as $k=>$v) { $key = sanitize_key($k); if (preg_match('/password|secret|token|authorization|cookie/i', $key)) continue; if (is_scalar($v)) $out[$key] = sanitize_text_field((string)$v); }
        return $out;
    }
    public static function status_audit($new, $old, $post) {
        if ($post && $post->post_type === 'wiki_article' && $new !== $old) self::audit('article_status', $post->ID, array('from'=>$old,'to'=>$new));
    }
    public static function deletion_audit($id, $post) { if ($post && $post->post_type === 'wiki_article') self::audit('article_delete', $id, array('status'=>$post->post_status)); }
    public static function user_registered($id) { self::audit('user_registered', $id); }
    public static function role_changed($id, $role, $old) { self::audit('user_role_changed', $id, array('new_role'=>$role,'old_roles'=>implode(',', (array)$old))); }
    public static function login_success($login, $user) { $ip=self::ip_hash(); delete_transient('bsw_p61_login_fail_' . substr($ip,0,32)); delete_transient('bsw_p61_login_lock_' . substr($ip,0,32)); self::audit('login_success', $user->ID); }
    public static function login_failed($username, $error = null) {
        $ip = self::ip_hash(); $key = 'bsw_p61_login_fail_' . substr($ip, 0, 32); $count = (int)get_transient($key) + 1;
        set_transient($key, $count, 15 * MINUTE_IN_SECONDS);
        if ($count === 20) { set_transient('bsw_p61_login_lock_' . substr($ip, 0, 32), 1, 10 * MINUTE_IN_SECONDS); self::audit('login_rate_limited', 'ip:' . substr($ip,0,12), array('attempts'=>$count)); }
        self::audit('login_failed', substr(sanitize_user($username), 0, 80), array('ip_hash'=>substr($ip,0,16),'attempts'=>$count));
    }
    public static function login_guard($user, $username, $password) {
        $ip = self::ip_hash(); if ($ip && get_transient('bsw_p61_login_lock_' . substr($ip,0,32))) return new WP_Error('bsw_login_rate_limited', __('Too many sign-in attempts. Please wait before trying again.','bigshor-wiki'));
        return $user;
    }
    public static function comment_created($comment_id, $approved, $data) { self::audit('comment_created', $comment_id, array('approved'=>$approved)); }
    private static function ip_hash() { $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : ''; return $ip ? hash_hmac('sha256', $ip, wp_salt('auth')) : ''; }
    public static function inspect_article($post_id, $post, $update) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || !$post || $post->post_type !== 'wiki_article' || !current_user_can('edit_post', $post_id)) return;
        $content = (string)$post->post_content; $score = 0; $signals = array();
        if (preg_match_all('~https?://~i', $content) > 12) { $score += 2; $signals[] = 'high_link_count'; }
        if (preg_match_all('~<a\b[^>]*>~i', $content) > 20) { $score += 2; $signals[] = 'high_anchor_count'; }
        if (preg_match('/(.)\1{19,}/u', wp_strip_all_tags($content))) { $score += 2; $signals[] = 'repeated_character_sequence'; }
        if (strlen($content) > 1000 && preg_match_all('/[A-Z]{12,}/', wp_strip_all_tags($content)) > 5) { $score += 1; $signals[] = 'excessive_uppercase'; }
        if ($score < 2) return;
        $flags = get_option(self::FLAGS_KEY, array()); if (!is_array($flags)) $flags = array();
        $flags[$post_id] = array('post_id'=>$post_id,'score'=>min(10,$score),'signals'=>$signals,'updated'=>gmdate('c'),'reviewed'=>false);
        update_option(self::FLAGS_KEY, $flags, false); self::audit('article_flagged_for_review', $post_id, array('score'=>$score,'signals'=>implode(',', $signals)));
    }
    public static function track_view() {
        if (is_admin() || is_user_logged_in() || !is_singular('wiki_article')) return;
        $post_id = get_queried_object_id(); if (!$post_id || get_post_status($post_id) !== 'publish') return;
        $key = 'bsw_p61_view_' . $post_id . '_' . substr(self::ip_hash(), 0, 20);
        if (get_transient($key)) return; set_transient($key, 1, DAY_IN_SECONDS);
        $data = get_option(self::ANALYTICS_KEY, array()); if (!is_array($data)) $data = array(); $day = gmdate('Y-m-d');
        if (!isset($data[$day])) $data[$day] = array(); if (!isset($data[$day][$post_id])) $data[$day][$post_id] = 0;
        $data[$day][$post_id]++; if (count($data) > 90) { ksort($data); $data = array_slice($data, -90, null, true); }
        update_option(self::ANALYTICS_KEY, $data, false);
    }
    public static function purge_article_cache($post_id) { if (get_post_type($post_id) === 'wiki_article') { delete_transient('bsw_p61_article_' . absint($post_id)); clean_post_cache($post_id); } }
    public static function get_article_summary($post_id) {
        $key = 'bsw_p61_article_' . absint($post_id); $cached = get_transient($key); if (false !== $cached) return $cached;
        $post = get_post($post_id); if (!$post || $post->post_type !== 'wiki_article' || $post->post_status !== 'publish') return false;
        $value = array('id'=>(int)$post->ID,'title'=>get_the_title($post),'url'=>get_permalink($post),'modified'=>get_post_modified_time('c', true, $post),'excerpt'=>wp_trim_words(wp_strip_all_tags($post->post_content), 45));
        set_transient($key, $value, 15 * MINUTE_IN_SECONDS); return $value;
    }
    public static function maintenance() {
        $flags = get_option(self::FLAGS_KEY, array()); if (is_array($flags)) { foreach ($flags as $id=>$flag) if (empty($flag['reviewed']) && get_post_status($id) === false) unset($flags[$id]); update_option(self::FLAGS_KEY, $flags, false); }
        $data = get_option(self::ANALYTICS_KEY, array()); if (is_array($data)) { ksort($data); if (count($data)>90) $data=array_slice($data,-90,null,true); update_option(self::ANALYTICS_KEY,$data,false); }
    }
    public static function menu() { add_management_page('BIGSHOR Trust & Operations','BIGSHOR Trust','manage_options','bsw-phase61',array(__CLASS__,'page')); }
    public static function page() {
        if (!current_user_can('manage_options')) return;
        $audit = get_option(self::AUDIT_KEY,array()); $flags = get_option(self::FLAGS_KEY,array()); $analytics=get_option(self::ANALYTICS_KEY,array());
        echo '<div class="wrap"><h1>BIGSHOR Trust & Operations</h1><p>Security signals are advisory. This module does not automatically block users or alter article publication status.</p>';
        echo '<h2>Operational overview</h2><ul><li>Audit events retained: '.(int)count((array)$audit).'</li><li>Articles flagged for human review: '.(int)count((array)$flags).'</li><li>Analytics days retained: '.(int)count((array)$analytics).'</li><li>Scheduled maintenance: '.(wp_next_scheduled(self::CRON)?'Active':'Not scheduled').'</li></ul>';
        echo '<h2>Human review queue</h2><table class="widefat striped"><thead><tr><th>Article</th><th>Risk score</th><th>Signals</th><th>Last flagged</th></tr></thead><tbody>';
        if (is_array($flags)) foreach (array_reverse($flags) as $flag) { $p=get_post((int)$flag['post_id']); if (!$p) continue; echo '<tr><td><a href="'.esc_url(get_edit_post_link($p->ID)).'">'.esc_html(get_the_title($p)).'</a></td><td>'.(int)$flag['score'].'/10</td><td>'.esc_html(implode(', ',(array)$flag['signals'])).'</td><td>'.esc_html($flag['updated']).'</td></tr>'; }
        echo '</tbody></table><h2>Backup export</h2><p>Download a JSON snapshot of published wiki articles and their basic metadata. Store it off-site; this export is not a full database/files backup.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw_phase61_backup">'; wp_nonce_field('bsw_phase61_backup'); submit_button('Download published-article backup'); echo '</form>';
        echo '<h2>Recent audit events</h2><table class="widefat striped"><thead><tr><th>UTC time</th><th>Event</th><th>Actor</th><th>Subject</th></tr></thead><tbody>';
        foreach (array_slice(array_reverse((array)$audit),0,50) as $row) echo '<tr><td>'.esc_html($row['time']).'</td><td>'.esc_html($row['event']).'</td><td>'.(int)$row['actor'].'</td><td>'.esc_html($row['subject']).'</td></tr>';
        echo '</tbody></table></div>';
    }
    public static function download_backup() {
        if (!current_user_can('manage_options')) wp_die(esc_html__('Not permitted.','bigshor-wiki'));
        check_admin_referer('bsw_phase61_backup');
        $posts = get_posts(array('post_type'=>'wiki_article','post_status'=>'publish','numberposts'=>-1,'orderby'=>'ID','order'=>'ASC'));
        $items = array(); foreach ($posts as $p) { $items[] = array('id'=>(int)$p->ID,'title'=>$p->post_title,'content'=>$p->post_content,'excerpt'=>$p->post_excerpt,'slug'=>$p->post_name,'date_gmt'=>$p->post_date_gmt,'modified_gmt'=>$p->post_modified_gmt,'author'=>(int)$p->post_author,'permalink'=>get_permalink($p),'meta'=>array('_bsw_language'=>get_post_meta($p->ID,'_bsw_language',true))); }
        $payload = array('format'=>'bigshor-wiki-content-backup','version'=>1,'generated_utc'=>gmdate('c'),'site'=>home_url('/'),'count'=>count($items),'articles'=>$items);
        self::audit('backup_export', count($items)); nocache_headers(); header('Content-Type: application/json; charset=utf-8'); header('Content-Disposition: attachment; filename="bigshor-wiki-backup-'.gmdate('Ymd-His').'.json"'); echo wp_json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES); exit;
    }
}
BSW_Phase61_Enterprise_Trust::init();
}
