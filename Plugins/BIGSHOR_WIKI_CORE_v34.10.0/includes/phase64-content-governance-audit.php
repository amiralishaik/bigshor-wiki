<?php
/**
 * Phase 64 — Content governance and editorial health audit.
 * Read-only, administrator-only inventory of content states, stale drafts,
 * media accessibility metadata and editorial ownership. Does not mutate content.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase64_Content_Governance_Audit')) {
final class BSW_Phase64_Content_Governance_Audit {
    const REPORT_OPTION = 'bsw_p64_governance_report';
    const MAX_ITEMS = 500;
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'), 100);
        add_action('admin_post_bsw_p64_run_audit', array(__CLASS__, 'run_audit'));
        add_action('admin_post_bsw_p64_export_audit', array(__CLASS__, 'export_audit'));
    }
    public static function menu() {
        add_management_page('BIGSHOR Content Governance', 'BIGSHOR Content Audit', 'manage_options', 'bsw-phase64-audit', array(__CLASS__, 'render'));
    }
    private static function count_posts($status) {
        $counts = wp_count_posts('wiki_article');
        return isset($counts->{$status}) ? (int)$counts->{$status} : 0;
    }
    public static function audit() {
        $now = current_time('timestamp', true);
        $stale_cutoff = gmdate('Y-m-d H:i:s', $now - (90 * DAY_IN_SECONDS));
        $stale = get_posts(array('post_type'=>'wiki_article','post_status'=>array('draft','pending','private'),'posts_per_page'=>self::MAX_ITEMS,'orderby'=>'modified','order'=>'ASC','date_query'=>array(array('column'=>'post_modified_gmt','before'=>$stale_cutoff,'inclusive'=>true)),'fields'=>'ids','no_found_rows'=>true));
        $stale_items = array();
        foreach ($stale as $id) $stale_items[] = array('id'=>(int)$id,'title'=>get_the_title($id),'status'=>get_post_status($id),'modified_gmt'=>get_post_field('post_modified_gmt',$id),'edit_url'=>get_edit_post_link($id,'raw'));
        $media = get_posts(array('post_type'=>'attachment','post_status'=>'inherit','post_mime_type'=>'image','posts_per_page'=>self::MAX_ITEMS,'orderby'=>'date','order'=>'DESC','fields'=>'ids','no_found_rows'=>true));
        $missing_alt = array();
        foreach ($media as $id) {
            $alt = trim((string)get_post_meta($id,'_wp_attachment_image_alt',true));
            if ($alt === '') $missing_alt[] = array('id'=>(int)$id,'title'=>get_the_title($id),'edit_url'=>get_edit_post_link($id,'raw'));
        }
        $authors = array();
        $author_rows = get_users(array('role__in'=>array('administrator','editor','author','wiki_editor','wiki_reviewer','wiki_contributor'),'fields'=>array('ID','display_name','user_email'),'number'=>500));
        foreach ($author_rows as $u) {
            $authored = count_user_posts($u->ID, 'wiki_article', true);
            $authors[] = array('id'=>(int)$u->ID,'name'=>$u->display_name,'email'=>$u->user_email,'article_count'=>(int)$authored);
        }
        usort($authors, function($a,$b){ return $b['article_count'] <=> $a['article_count']; });
        $published = self::count_posts('publish');
        $draft = self::count_posts('draft');
        $pending = self::count_posts('pending');
        $private = self::count_posts('private');
        $trash = self::count_posts('trash');
        return array(
            'generated_at'=>gmdate('c'), 'scope'=>'Read-only audit; maximum 500 stale records, 500 recent image attachments and 500 contributor accounts. No content or user data is changed.',
            'content'=>array('published'=>$published,'draft'=>$draft,'pending'=>$pending,'private'=>$private,'trash'=>$trash),
            'stale_cutoff_gmt'=>$stale_cutoff,'stale_items'=>$stale_items,'stale_count'=>count($stale),'stale_truncated'=>count($stale)>=self::MAX_ITEMS,
            'media'=>array('images_scanned'=>count($media),'missing_alt_count'=>count($missing_alt),'missing_alt'=>$missing_alt,'truncated'=>count($media)>=self::MAX_ITEMS),
            'contributors'=>$authors,'contributors_truncated'=>count($author_rows)>=500,
            'limits'=>array('max_stale_items'=>self::MAX_ITEMS,'max_media_items'=>self::MAX_ITEMS,'max_contributors'=>500)
        );
    }
    public static function run_audit() {
        if (!current_user_can('manage_options')) wp_die('Permission denied.',403);
        check_admin_referer('bsw_p64_run_audit');
        update_option(self::REPORT_OPTION, self::audit(), false);
        wp_safe_redirect(add_query_arg('bsw_p64_result','complete',admin_url('tools.php?page=bsw-phase64-audit'))); exit;
    }
    public static function export_audit() {
        if (!current_user_can('manage_options')) wp_die('Permission denied.',403);
        check_admin_referer('bsw_p64_export_audit');
        $report=get_option(self::REPORT_OPTION,array());
        if (!is_array($report) || empty($report['generated_at'])) $report=self::audit();
        nocache_headers(); header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="bigshor-content-governance-'.gmdate('Ymd-His').'.json"');
        echo wp_json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES); exit;
    }
    public static function render() {
        if (!current_user_can('manage_options')) return;
        $r=get_option(self::REPORT_OPTION,array());
        echo '<div class="wrap"><h1>BIGSHOR Content Governance Audit</h1><p>Read-only editorial inventory covering article states, stale work, image alternative text and contributor activity. Run after major editorial cycles and before releases.</p>';
        if (isset($_GET['bsw_p64_result'])) echo '<div class="notice notice-success"><p>Audit completed. Review findings before taking action.</p></div>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw_p64_run_audit">'; wp_nonce_field('bsw_p64_run_audit'); submit_button('Run content governance audit','primary','submit',false); echo '</form>';
        if (empty($r['generated_at'])) { echo '<p>No report has been generated yet.</p></div>'; return; }
        echo '<p><strong>Generated:</strong> '.esc_html($r['generated_at']).'</p><h2>Article inventory</h2><table class="widefat striped"><tbody>';
        foreach ($r['content'] as $k=>$v) echo '<tr><th>'.esc_html(ucfirst($k)).'</th><td>'.(int)$v.'</td></tr>';
        echo '</tbody></table><h2>Stale editorial work</h2><p>Draft, pending and private articles not modified in 90 days: <strong>'.(int)$r['stale_count'].'</strong>. Listed below up to '.self::MAX_ITEMS.' records.</p>';
        if (!empty($r['stale_items'])) { echo '<table class="widefat striped"><thead><tr><th>Article</th><th>Status</th><th>Last modified (GMT)</th></tr></thead><tbody>'; foreach ($r['stale_items'] as $x) echo '<tr><td><a href="'.esc_url($x['edit_url']).'">'.esc_html($x['title']).'</a></td><td>'.esc_html($x['status']).'</td><td>'.esc_html($x['modified_gmt']).'</td></tr>'; echo '</tbody></table>'; } else echo '<p>No stale editorial items found within the scan limit.</p>';
        echo '<h2>Media accessibility</h2><p>Image attachments scanned: '.(int)$r['media']['images_scanned'].' · Missing alt text: <strong>'.(int)$r['media']['missing_alt_count'].'</strong></p>';
        if (!empty($r['media']['missing_alt'])) { echo '<table class="widefat striped"><thead><tr><th>Image</th><th>Attachment</th></tr></thead><tbody>'; foreach ($r['media']['missing_alt'] as $x) echo '<tr><td>'.esc_html($x['title']).'</td><td><a href="'.esc_url($x['edit_url']).'">Edit media</a></td></tr>'; echo '</tbody></table>'; }
        echo '<h2>Contributor activity inventory</h2><table class="widefat striped"><thead><tr><th>Contributor</th><th>Published wiki articles</th></tr></thead><tbody>'; foreach ($r['contributors'] as $u) echo '<tr><td>'.esc_html($u['name']).'</td><td>'.(int)$u['article_count'].'</td></tr>'; echo '</tbody></table>';
        echo '<p class="description">'.esc_html($r['scope']).' Contributor emails are included in the administrator-only JSON export; handle the export as personal data. This audit does not judge article quality, factual accuracy, source reliability or contributor performance.</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw_p64_export_audit">'; wp_nonce_field('bsw_p64_export_audit'); submit_button('Export report as JSON','secondary','submit',false); echo '</form></div>';
    }
}
BSW_Phase64_Content_Governance_Audit::init();
}
