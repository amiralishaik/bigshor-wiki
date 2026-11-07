<?php
/**
 * BIGSHOR Wiki Phase 35 — Editorial operations, source trust and safe Wikipedia updates.
 * All changes are human-reviewed. Imported drafts are never silently overwritten.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase35_Governance_Trust')) {
class BSW_Phase35_Governance_Trust {
    const VERSION = '1.0.0';
    const META_STATUS = '_bsw_p35_workflow_status';
    const META_CHECKS = '_bsw_p35_review_checks';
    const META_COI = '_bsw_p35_coi_disclosure';
    const META_SOURCE_CLASS = '_bsw_p35_source_class';
    const META_REVIEW_DATE = '_bsw_p35_next_review';
    const META_PROPOSAL_FOR = '_bsw_p35_wikipedia_update_for';
    const META_PROPOSAL_REV = '_bsw_p35_wikipedia_update_revision';
    const AUDIT_OPTION = 'bsw_p35_audit_log';

    public static function init() {
        add_action('add_meta_boxes_wiki_article', array(__CLASS__, 'article_box'));
        add_action('save_post_wiki_article', array(__CLASS__, 'save_article'), 30, 2);
        add_action('transition_post_status', array(__CLASS__, 'publish_guard'), 10, 3);
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('admin_post_bsw_p35_wikipedia_check', array(__CLASS__, 'check_update'));
        add_action('admin_post_bsw_p35_proposal_status', array(__CLASS__, 'proposal_status'));
        add_filter('post_row_actions', array(__CLASS__, 'row_actions'), 20, 2);
        add_action('admin_notices', array(__CLASS__, 'notices'));
    }

    public static function audit($event, $post_id = 0, $details = '') {
        $rows = get_option(self::AUDIT_OPTION, array());
        if (!is_array($rows)) $rows = array();
        array_unshift($rows, array(
            'time' => current_time('mysql'), 'user' => get_current_user_id(),
            'event' => sanitize_key($event), 'post_id' => absint($post_id),
            'details' => sanitize_text_field($details),
        ));
        update_option(self::AUDIT_OPTION, array_slice($rows, 0, 300), false);
    }

    public static function article_box($post) {
        if (!current_user_can('edit_post', $post->ID)) return;
        add_meta_box('bsw-p35-editorial', 'BIGSHOR Editorial Governance & Trust', array(__CLASS__, 'render_box'), 'wiki_article', 'side', 'high');
    }

    public static function render_box($post) {
        wp_nonce_field('bsw_p35_save_' . $post->ID, 'bsw_p35_nonce');
        $status = get_post_meta($post->ID, self::META_STATUS, true) ?: (get_post_meta($post->ID, '_bsw_wikipedia_source', true) ? 'in_review' : 'draft');
        $checks = get_post_meta($post->ID, self::META_CHECKS, true);
        if (!is_array($checks)) $checks = array();
        $coi = get_post_meta($post->ID, self::META_COI, true);
        $source_class = get_post_meta($post->ID, self::META_SOURCE_CLASS, true) ?: 'not_assessed';
        $review_date = get_post_meta($post->ID, self::META_REVIEW_DATE, true);
        echo '<p><label for="bsw-p35-status"><strong>Editorial workflow</strong></label><select class="widefat" id="bsw-p35-status" name="bsw_p35_status">';
        foreach (array('draft'=>'Draft','in_review'=>'In Review','changes_requested'=>'Changes Requested','approved'=>'Approved','published'=>'Published','disputed'=>'Disputed') as $key=>$label) echo '<option value="'.esc_attr($key).'" '.selected($status,$key,false).'>'.esc_html($label).'</option>';
        echo '</select></p><p><strong>Required review checks</strong></p>';
        $items = array('facts'=>'Key claims checked against original sources','sources'=>'Reliable independent sources reviewed','neutrality'=>'Neutrality and conflicts of interest reviewed','rights'=>'Copyright, attribution and licence checked','media'=>'Images and media rights checked separately');
        foreach ($items as $key=>$label) echo '<p><label><input type="checkbox" name="bsw_p35_checks[]" value="'.esc_attr($key).'" '.checked(in_array($key,$checks,true),true,false).'> '.esc_html($label).'</label></p>';
        echo '<p><label for="bsw-p35-coi"><strong>Conflict-of-interest disclosure</strong></label><textarea class="widefat" rows="3" id="bsw-p35-coi" name="bsw_p35_coi">'.esc_textarea($coi).'</textarea></p>';
        echo '<p><label for="bsw-p35-source"><strong>Source assessment</strong></label><select class="widefat" id="bsw-p35-source" name="bsw_p35_source">';
        foreach (array('not_assessed'=>'Not assessed','primary'=>'Primary source','independent_secondary'=>'Independent secondary','official'=>'Official / institutional','user_generated'=>'User-generated','disputed'=>'Disputed / unreliable') as $key=>$label) echo '<option value="'.esc_attr($key).'" '.selected($source_class,$key,false).'>'.esc_html($label).'</option>';
        echo '</select></p><p><label for="bsw-p35-date"><strong>Next review date</strong></label><input class="widefat" type="date" id="bsw-p35-date" name="bsw_p35_review_date" value="'.esc_attr($review_date).'" /></p>';
        if (get_post_meta($post->ID, '_bsw_wikipedia_source', true)) {
            echo '<hr><p><strong>Wikipedia source update</strong><br>New revisions are fetched into a separate private proposal. Existing editorial content is never overwritten.</p>';
            $url = wp_nonce_url(admin_url('admin-post.php?action=bsw_p35_wikipedia_check&post_id='.$post->ID), 'bsw_p35_update_'.$post->ID);
            echo '<p><a class="button" href="'.esc_url($url).'">Check for newer Wikipedia revision</a></p>';
        }
        echo '<p class="description">Approval is an editorial status, not a guarantee of factual accuracy. Publication permissions remain governed by WordPress roles.</p>';
    }

    public static function save_article($post_id, $post) {
        if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) return;
        if (!isset($_POST['bsw_p35_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_p35_nonce'])), 'bsw_p35_save_'.$post_id)) return;
        if (!current_user_can('edit_post', $post_id)) return;
        $old = get_post_meta($post_id, self::META_STATUS, true);
        $status = isset($_POST['bsw_p35_status']) ? sanitize_key(wp_unslash($_POST['bsw_p35_status'])) : 'draft';
        $allowed = array('draft','in_review','changes_requested','approved','published','disputed');
        if (!in_array($status,$allowed,true)) $status='draft';
        // Only editors with broad editorial permissions can approve or mark published.
        if (in_array($status,array('approved','published'),true) && !current_user_can('edit_others_posts')) $status = in_array($old,$allowed,true) ? $old : 'in_review';
        $checks = isset($_POST['bsw_p35_checks']) && is_array($_POST['bsw_p35_checks']) ? array_map('sanitize_key',wp_unslash($_POST['bsw_p35_checks'])) : array();
        $valid = array('facts','sources','neutrality','rights','media');
        $checks = array_values(array_intersect($valid,$checks));
        update_post_meta($post_id,self::META_STATUS,$status);
        update_post_meta($post_id,self::META_CHECKS,$checks);
        update_post_meta($post_id,self::META_COI,isset($_POST['bsw_p35_coi']) ? sanitize_textarea_field(wp_unslash($_POST['bsw_p35_coi'])) : '');
        $source = isset($_POST['bsw_p35_source']) ? sanitize_key(wp_unslash($_POST['bsw_p35_source'])) : 'not_assessed';
        $source_values = array('not_assessed','primary','independent_secondary','official','user_generated','disputed');
        update_post_meta($post_id,self::META_SOURCE_CLASS,in_array($source,$source_values,true)?$source:'not_assessed');
        $date = isset($_POST['bsw_p35_review_date']) ? sanitize_text_field(wp_unslash($_POST['bsw_p35_review_date'])) : '';
        update_post_meta($post_id,self::META_REVIEW_DATE,preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)?$date:'');
        if ($old !== $status) self::audit('workflow_'.$status,$post_id,'Editorial status changed from '.($old?:'unset').' to '.$status);
    }

    public static function publish_guard($new_status, $old_status, $post) {
        if (!$post instanceof WP_Post || $post->post_type !== 'wiki_article' || $new_status !== 'publish' || $old_status === 'publish') return;

        // Administrators can publish directly, including imported Wikipedia
        // records, without completing the ordinary editorial checklist.
        if (current_user_can('manage_options') && current_user_can('publish_posts')) {
            update_post_meta($post->ID, self::META_STATUS, 'published');
            self::audit('administrator_publication_override', $post->ID, 'Administrator published directly; Phase 35 checklist bypassed.');
            return;
        }

        if (!get_post_meta($post->ID, '_bsw_wikipedia_source', true)) return;
        $checks = get_post_meta($post->ID,self::META_CHECKS,true);
        $required = array('facts','sources','neutrality','rights','media');
        if (!is_array($checks) || count(array_intersect($required,$checks)) !== count($required)) {
            // Revert to draft without recursion; editors can publish after saving every check.
            remove_action('transition_post_status',array(__CLASS__,'publish_guard'),10);
            wp_update_post(array('ID'=>$post->ID,'post_status'=>'draft'));
            add_action('transition_post_status',array(__CLASS__,'publish_guard'),10,3);
            self::audit('publish_blocked',$post->ID,'Wikipedia import missing required editorial checks');
            set_transient('bsw_p35_blocked_'.get_current_user_id(),$post->ID,60);
        } else {
            update_post_meta($post->ID,self::META_STATUS,'published');
            self::audit('published',$post->ID,'Imported article passed required checklist');
        }
    }

    public static function check_update() {
        $id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        if (!$id || get_post_type($id)!=='wiki_article' || !current_user_can('edit_post',$id)) wp_die('Permission denied.');
        check_admin_referer('bsw_p35_update_'.$id);
        $source = get_post_meta($id,'_bsw_wikipedia_source',true);
        $page_id = absint(get_post_meta($id,'_bsw_wikipedia_page_id',true));
        $language = 'en';
        if (preg_match('~^https://([a-z-]+)\.wikipedia\.org/~',$source,$m)) $language = sanitize_key($m[1]);
        if (!preg_match('/^[a-z-]{2,12}$/',$language)) $language='en';
        $response = wp_remote_get('https://'.rawurlencode($language).'.wikipedia.org/w/api.php?'.http_build_query(array('action'=>'query','pageids'=>$page_id,'prop'=>'revisions','rvprop'=>'ids|timestamp','rvlimit'=>1,'format'=>'json','formatversion'=>2),'','&',PHP_QUERY_RFC3986),array('timeout'=>15,'redirection'=>1,'headers'=>array('Accept'=>'application/json','User-Agent'=>'BIGSHOR-Wiki/28.0.0; '.home_url('/'))));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response)!==200) self::finish_redirect($id,'update_error');
        $data=json_decode(wp_remote_retrieve_body($response),true);
        $rev=isset($data['query']['pages'][0]['revisions'][0]['revid'])?absint($data['query']['pages'][0]['revisions'][0]['revid']):0;
        $current=absint(get_post_meta($id,'_bsw_wikipedia_revision_id',true));
        if (!$rev || $rev===$current) self::finish_redirect($id,'no_update');
        $parse=wp_remote_get('https://'.rawurlencode($language).'.wikipedia.org/w/api.php?'.http_build_query(array('action'=>'parse','oldid'=>$rev,'prop'=>'text','disableeditsection'=>1,'format'=>'json','formatversion'=>2),'','&',PHP_QUERY_RFC3986),array('timeout'=>20,'redirection'=>1,'headers'=>array('Accept'=>'application/json','User-Agent'=>'BIGSHOR-Wiki/28.0.0; '.home_url('/'))));
        if (is_wp_error($parse) || wp_remote_retrieve_response_code($parse)!==200) self::finish_redirect($id,'update_error');
        $parsed=json_decode(wp_remote_retrieve_body($parse),true);
        $html=isset($parsed['parse']['text'])?$parsed['parse']['text']:'';
        if (!is_string($html) || trim($html)==='') self::finish_redirect($id,'update_error');
        $proposal=wp_insert_post(array('post_type'=>'wiki_edit_proposal','post_status'=>'private','post_title'=>'Wikipedia revision update proposal: '.get_the_title($id),'post_content'=>wp_kses_post($html)),true);
        if (is_wp_error($proposal)) self::finish_redirect($id,'update_error');
        update_post_meta($proposal,self::META_PROPOSAL_FOR,$id);
        update_post_meta($proposal,self::META_PROPOSAL_REV,$rev);
        update_post_meta($proposal,'_bsw_p35_previous_revision',$current);
        update_post_meta($proposal,'_bsw_p35_source_url',esc_url_raw($source));
        update_post_meta($proposal,'_bsw_p35_review_status','pending');
        self::audit('wikipedia_update_proposal_created',$id,'New revision '.$rev.' stored as private proposal #'.$proposal);
        wp_safe_redirect(add_query_arg(array('post'=>$proposal,'action'=>'edit','bsw_p35_notice'=>'proposal'),admin_url('post.php'))); exit;
    }

    private static function finish_redirect($id,$notice) {
        wp_safe_redirect(add_query_arg(array('post'=>$id,'action'=>'edit','bsw_p35_notice'=>$notice),admin_url('post.php'))); exit;
    }

    public static function proposal_status() {
        $proposal=isset($_GET['proposal_id'])?absint($_GET['proposal_id']):0;
        $status=isset($_GET['status'])?sanitize_key(wp_unslash($_GET['status'])):'';
        if (!$proposal || get_post_type($proposal)!=='wiki_edit_proposal' || !current_user_can('edit_post',$proposal)) wp_die('Permission denied.');
        check_admin_referer('bsw_p35_proposal_'.$proposal);
        if (!in_array($status,array('reviewing','accepted','rejected'),true)) wp_die('Invalid status.');
        update_post_meta($proposal,'_bsw_p35_review_status',$status);
        self::audit('wikipedia_proposal_'.$status,$proposal,'Editorial decision on proposed Wikipedia revision');
        wp_safe_redirect(get_edit_post_link($proposal,'url')); exit;
    }

    public static function row_actions($actions,$post) {
        if ($post instanceof WP_Post && $post->post_type==='wiki_edit_proposal' && get_post_meta($post->ID,self::META_PROPOSAL_FOR,true)) {
            $status=get_post_meta($post->ID,'_bsw_p35_review_status',true)?:'pending';
            $actions['bsw_p35_status']='<span>Review: '.esc_html(ucfirst($status)).'</span>';
            foreach(array('reviewing'=>'Mark reviewing','accepted'=>'Accept proposal','rejected'=>'Reject proposal') as $key=>$label) {
                $url=wp_nonce_url(admin_url('admin-post.php?action=bsw_p35_proposal_status&proposal_id='.$post->ID.'&status='.$key),'bsw_p35_proposal_'.$post->ID);
                $actions['bsw_p35_'.$key]='<a href="'.esc_url($url).'">'.esc_html($label).'</a>';
            }
        }
        return $actions;
    }

    public static function admin_menu() {
        add_submenu_page('edit.php?post_type=wiki_article','Editorial Operations','Editorial Operations','edit_others_posts','bsw-p35-operations',array(__CLASS__,'dashboard'));
    }

    public static function dashboard() {
        if (!current_user_can('edit_others_posts')) return;
        $statuses=array('draft'=>'Draft','in_review'=>'In Review','changes_requested'=>'Changes Requested','approved'=>'Approved','disputed'=>'Disputed');
        echo '<div class="wrap"><h1>BIGSHOR Editorial Operations</h1><p>Central overview of review workload, trust assessments, source checks and recent editorial actions.</p><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px">';
        foreach($statuses as $key=>$label) {
            $q=new WP_Query(array('post_type'=>'wiki_article','post_status'=>array('draft','pending','publish'),'posts_per_page'=>1,'fields'=>'ids','meta_key'=>self::META_STATUS,'meta_value'=>$key));
            echo '<div style="background:#fff;border:1px solid #dcdcde;padding:16px"><strong>'.esc_html($label).'</strong><h2>'.absint($q->found_posts).'</h2></div>'; wp_reset_postdata();
        }
        echo '</div><h2>Recent editorial audit trail</h2><table class="widefat striped"><thead><tr><th>Date</th><th>User ID</th><th>Event</th><th>Article / record</th><th>Details</th></tr></thead><tbody>';
        $rows=get_option(self::AUDIT_OPTION,array()); if (!is_array($rows)) $rows=array();
        foreach(array_slice($rows,0,50) as $row) { $pid=absint($row['post_id']??0); echo '<tr><td>'.esc_html($row['time']??'').'</td><td>'.absint($row['user']??0).'</td><td>'.esc_html($row['event']??'').'</td><td>'.($pid&&get_post($pid)?'<a href="'.esc_url(get_edit_post_link($pid)).'">#'.$pid.' '.esc_html(get_the_title($pid)).'</a>':'—').'</td><td>'.esc_html($row['details']??'').'</td></tr>'; }
        if (!$rows) echo '<tr><td colspan="5">No editorial actions logged yet.</td></tr>';
        echo '</tbody></table><p class="description">Audit history is capped at the latest 300 events and is not a tamper-proof compliance log. Configure independent backups and access monitoring for production.</p></div>';
    }

    public static function notices() {
        if (!is_admin() || !current_user_can('edit_others_posts')) return;
        $id=get_transient('bsw_p35_blocked_'.get_current_user_id());
        if ($id) { delete_transient('bsw_p35_blocked_'.get_current_user_id()); echo '<div class="notice notice-error is-dismissible"><p>BIGSHOR blocked publication of imported article #'.absint($id).'. Complete and save all five editorial checks before publishing.</p></div>'; }
        $notice=isset($_GET['bsw_p35_notice'])?sanitize_key(wp_unslash($_GET['bsw_p35_notice'])):'';
        $messages=array('proposal'=>'A newer Wikipedia revision was saved as a separate private proposal. The existing article was not changed.','no_update'=>'The imported Wikipedia revision is already current.','update_error'=>'Could not retrieve a newer revision. Please try again later.');
        if (isset($messages[$notice])) echo '<div class="notice notice-info is-dismissible"><p>'.esc_html($messages[$notice]).'</p></div>';
    }
}
BSW_Phase35_Governance_Trust::init();
}
