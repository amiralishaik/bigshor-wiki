<?php
/**
 * Phase 49: editorial maintenance reports, recent-change patrol and safe revision rollback.
 * Additive module. Uses native WordPress revisions, trash and capabilities.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase49_Maintenance_Patrol')) {
final class BSW_Phase49_Maintenance_Patrol {
    const VERSION = '49.0.0';
    const PATROL_META = '_bsw_patrolled_at';
    const PATROL_USER_META = '_bsw_patrolled_by';

    public static function boot() {
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('admin_post_bsw_phase49_patrol', array(__CLASS__, 'mark_patrolled'));
        add_action('admin_post_bsw_phase49_rollback', array(__CLASS__, 'rollback_revision'));
        add_action('trashed_post', array(__CLASS__, 'log_trash'));
        add_action('untrashed_post', array(__CLASS__, 'log_untrash'));
        add_action('before_delete_post', array(__CLASS__, 'log_delete'), 10, 2);
        add_shortcode('bsw_maintenance_reports', array(__CLASS__, 'maintenance_shortcode'));
    }

    public static function admin_menu() {
        add_submenu_page('edit.php?post_type=wiki_article', 'Wiki maintenance', 'Wiki maintenance', 'edit_others_posts', 'bsw-wiki-maintenance', array(__CLASS__, 'render_admin'));
    }

    private static function article_query($args = array()) {
        $defaults = array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>50,'orderby'=>'modified','order'=>'DESC','no_found_rows'=>true);
        return new WP_Query(wp_parse_args($args, $defaults));
    }

    private static function get_report_rows() {
        $q = self::article_query(array('posts_per_page'=>200));
        $rows = array('unpatrolled'=>array(),'no_references'=>array(),'short'=>array(),'orphaned'=>array(),'redirects'=>array());
        foreach ($q->posts as $post) {
            $content = (string)$post->post_content;
            $patrolled = get_post_meta($post->ID, self::PATROL_META, true);
            if (!$patrolled || strtotime($patrolled) < strtotime($post->post_modified_gmt)) $rows['unpatrolled'][] = $post;
            if (stripos($content, '[bsw_cite') === false && !preg_match('/<ref\b/i', $content) && stripos($content, '[bsw_references') === false) $rows['no_references'][] = $post;
            if (str_word_count(wp_strip_all_tags($content)) < 150) $rows['short'][] = $post;
            if (get_post_meta($post->ID, '_bsw_redirect_target', true)) $rows['redirects'][] = $post;
            $links = get_post_meta($post->ID, '_bsw_outgoing_article_ids', true);
            if (empty($links) && !preg_match('/\[\[[^\]]+\]\]/', $content)) $rows['orphaned'][] = $post;
        }
        wp_reset_postdata();
        return $rows;
    }

    public static function render_admin() {
        if (!current_user_can('edit_others_posts')) wp_die(esc_html__('You are not allowed to view maintenance reports.', 'bigshor-wiki-core'));
        $rows = self::get_report_rows();
        echo '<div class="wrap"><h1>BIGSHOR Wiki maintenance</h1><p>Actionable editorial checks for published articles. Reports are advisory and inspect up to 200 recently modified articles per category.</p>';
        echo '<div class="notice notice-info"><p>Patrolling records that a reviewer inspected the current article revision; it does not certify factual accuracy or source reliability.</p></div>';
        foreach ($rows as $key=>$posts) {
            $labels = array('unpatrolled'=>'Unpatrolled / changed since patrol','no_references'=>'Articles without detected references','short'=>'Short articles (under 150 words)','orphaned'=>'Potential orphan articles','redirects'=>'Redirect articles');
            echo '<h2>'.esc_html($labels[$key]).' <span class="count">('.count($posts).')</span></h2>';
            if (!$posts) { echo '<p>None found in this report sample.</p>'; continue; }
            echo '<table class="widefat striped"><thead><tr><th>Article</th><th>Last modified</th><th>Actions</th></tr></thead><tbody>';
            foreach ($posts as $post) {
                echo '<tr><td><a href="'.esc_url(get_edit_post_link($post->ID)).'">'.esc_html(get_the_title($post)).'</a></td><td>'.esc_html(get_post_modified_time('Y-m-d H:i', false, $post)).'</td><td>';
                if ($key === 'unpatrolled') echo self::action_link('bsw_phase49_patrol', $post->ID, 'Mark reviewed');
                echo ' <a href="'.esc_url(get_permalink($post)).'" target="_blank" rel="noopener">View</a></td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '<h2>Recent article revisions</h2>';
        $recent = self::article_query(array('posts_per_page'=>25));
        echo '<table class="widefat striped"><thead><tr><th>Article</th><th>Revision</th><th>Author</th><th>Actions</th></tr></thead><tbody>';
        foreach ($recent->posts as $post) {
            $revisions = wp_get_post_revisions($post->ID, array('posts_per_page'=>2, 'orderby'=>'date', 'order'=>'DESC'));
            foreach ($revisions as $revision) {
                $author = get_the_author_meta('display_name', $revision->post_author);
                echo '<tr><td>'.esc_html(get_the_title($post)).'</td><td>'.esc_html(get_date_from_gmt($revision->post_date_gmt, 'Y-m-d H:i')).'</td><td>'.esc_html($author).'</td><td><a href="'.esc_url(get_edit_post_link($revision->ID)).'">Inspect</a> ';
                if (current_user_can('edit_post', $post->ID)) echo self::action_link('bsw_phase49_rollback', $revision->ID, 'Restore this revision', 'Are you sure? The current version will remain in history.');
                echo '</td></tr>';
            }
        }
        echo '</tbody></table></div>';
    }

    private static function action_link($action, $id, $label, $confirm = '') {
        $url = wp_nonce_url(admin_url('admin-post.php?action='.$action.'&id='.(int)$id), $action.'_'.$id);
        return '<a class="button button-small" '.($confirm ? 'onclick="return confirm(\''.esc_js($confirm).'\')" ' : '').'href="'.esc_url($url).'">'.esc_html($label).'</a>';
    }

    public static function mark_patrolled() {
        $revision_or_post = absint($_GET['id'] ?? 0);
        check_admin_referer('bsw_phase49_patrol_'.$revision_or_post);
        if (!$revision_or_post || get_post_type($revision_or_post) !== 'wiki_article' || !current_user_can('edit_others_posts')) wp_die('Not permitted.');
        update_post_meta($revision_or_post, self::PATROL_META, current_time('mysql', true));
        update_post_meta($revision_or_post, self::PATROL_USER_META, get_current_user_id());
        self::log('Article marked as reviewed', $revision_or_post);
        wp_safe_redirect(admin_url('edit.php?post_type=wiki_article&page=bsw-wiki-maintenance&patrolled=1')); exit;
    }

    public static function rollback_revision() {
        $revision_id = absint($_GET['id'] ?? 0);
        check_admin_referer('bsw_phase49_rollback_'.$revision_id);
        $revision = wp_get_post_revision($revision_id);
        if (!$revision) wp_die('Revision not found.');
        $parent_id = (int)$revision->post_parent;
        if (get_post_type($parent_id) !== 'wiki_article' || !current_user_can('edit_post', $parent_id) || !current_user_can('edit_others_posts')) wp_die('You are not allowed to restore this revision.');
        if (wp_is_post_revision($revision_id) === false) wp_die('Invalid revision.');
        // Preserve the current published state as a revision before restoring the selected content.
        if (function_exists('wp_save_post_revision')) wp_save_post_revision($parent_id);
        $result = wp_update_post(array('ID'=>$parent_id,'post_title'=>$revision->post_title,'post_content'=>$revision->post_content,'post_excerpt'=>$revision->post_excerpt), true);
        if (is_wp_error($result)) wp_die(esc_html($result->get_error_message()));
        self::log('Revision restored', $parent_id, array('revision_id'=>$revision_id));
        wp_safe_redirect(add_query_arg('bsw_rollback', 'success', get_edit_post_link($parent_id, 'url'))); exit;
    }

    public static function log_trash($post_id) { if (get_post_type($post_id)==='wiki_article') self::log('Article moved to trash', $post_id); }
    public static function log_untrash($post_id) { if (get_post_type($post_id)==='wiki_article') self::log('Article restored from trash', $post_id); }
    public static function log_delete($post_id, $post) { if ($post instanceof WP_Post && $post->post_type==='wiki_article') self::log('Article permanently deleted', $post_id, array('title'=>$post->post_title)); }

    private static function log($action, $post_id, $details = array()) {
        $entry = array('time'=>current_time('mysql'),'user'=>get_current_user_id(),'action'=>sanitize_text_field($action),'post_id'=>(int)$post_id,'details'=>$details);
        $logs = get_option('bsw_phase49_editorial_log', array()); if (!is_array($logs)) $logs = array();
        $logs[] = $entry; update_option('bsw_phase49_editorial_log', array_slice($logs, -500), false);
    }

    public static function maintenance_shortcode() {
        if (!current_user_can('edit_others_posts')) return '<p>You do not have permission to view editorial maintenance reports.</p>';
        $rows = self::get_report_rows();
        $labels = array('unpatrolled'=>'Unreviewed or changed since review','no_references'=>'No detected references','short'=>'Short articles','orphaned'=>'Potential orphan articles','redirects'=>'Redirects');
        $out = '<section class="bsw-maintenance-reports"><h2>Editorial maintenance</h2><ul>';
        foreach ($labels as $key=>$label) $out .= '<li>'.esc_html($label).': <strong>'.count($rows[$key]).'</strong></li>';
        return $out.'</ul><p><a href="'.esc_url(admin_url('edit.php?post_type=wiki_article&page=bsw-wiki-maintenance')).'">Open full maintenance dashboard</a></p></section>';
    }
}
BSW_Phase49_Maintenance_Patrol::boot();
}
