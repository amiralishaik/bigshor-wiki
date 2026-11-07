<?php
/**
 * Phase 3 — Editorial governance and moderation workflow.
 * Adds protection tiers, moderation appeals, revision oversight and an audit dashboard.
 * Uses native WordPress posts, revisions, capabilities and nonce-protected admin actions.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase55_Editorial_Governance')) {
final class BSW_Phase55_Editorial_Governance {
    const APPEAL_TYPE = 'bsw_moderation_appeal';
    const AUDIT_OPTION = 'bsw_phase55_moderation_audit';
    const PROTECTION_META = '_bsw_phase55_protection';

    public static function boot() {
        add_action('init', array(__CLASS__, 'register_types'), 26);
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('add_meta_boxes_wiki_article', array(__CLASS__, 'protection_box'));
        add_action('save_post_wiki_article', array(__CLASS__, 'save_protection'), 30, 2);
        add_filter('map_meta_cap', array(__CLASS__, 'enforce_protection_tier'), 40, 4);
        add_filter('get_post', array(__CLASS__, 'filter_suppressed_revision'), 20, 2);
        add_action('admin_post_bsw55_submit_appeal', array(__CLASS__, 'submit_appeal'));
        add_action('admin_post_bsw55_appeal_decision', array(__CLASS__, 'appeal_decision'));
        add_action('admin_post_bsw55_revision_visibility', array(__CLASS__, 'revision_visibility'));
        add_action('admin_post_bsw55_protection_change', array(__CLASS__, 'protection_change'));
        add_action('trashed_post', array(__CLASS__, 'audit_trash'));
        add_action('untrashed_post', array(__CLASS__, 'audit_restore'));
        add_action('before_delete_post', array(__CLASS__, 'audit_permanent_delete'), 20, 2);
        add_shortcode('bsw_moderation_appeal', array(__CLASS__, 'appeal_shortcode'));
    }

    public static function register_types() {
        if (!post_type_exists(self::APPEAL_TYPE)) {
            register_post_type(self::APPEAL_TYPE, array(
                'labels'=>array('name'=>'Moderation Appeals','singular_name'=>'Moderation Appeal'),
                'public'=>false,'show_ui'=>true,'show_in_menu'=>'edit.php?post_type=wiki_article',
                'show_in_rest'=>false,'supports'=>array('title','editor','author'),
                'capability_type'=>'post','map_meta_cap'=>true
            ));
        }
    }

    public static function admin_menu() {
        add_submenu_page('edit.php?post_type=wiki_article','Governance & Moderation','Governance & Moderation',
            'edit_others_posts','bsw-phase55-governance',array(__CLASS__,'dashboard'));
    }

    public static function protection_box() {
        add_meta_box('bsw_phase55_protection','BIGSHOR Protection Tier',array(__CLASS__,'render_protection_box'),
            'wiki_article','side','high');
    }

    public static function render_protection_box($post) {
        if (!current_user_can('edit_post',$post->ID)) { echo '<p>You cannot change this page protection.</p>'; return; }
        $level=get_post_meta($post->ID,self::PROTECTION_META,true);
        if (!$level) $level='open';
        wp_nonce_field('bsw55_protect_'.$post->ID,'bsw55_protection_nonce');
        echo '<label for="bsw55_protection"><strong>Who may edit this page?</strong></label>';
        echo '<select name="bsw55_protection" id="bsw55_protection" style="width:100%">';
        foreach (array('open'=>'Open — any user with normal edit rights','registered'=>'Registered editors','editor'=>'Editors and administrators','admin'=>'Administrators only') as $key=>$label) {
            echo '<option value="'.esc_attr($key).'" '.selected($level,$key,false).'>'.esc_html($label).'</option>';
        }
        echo '</select><p class="description">Protection is enforced server-side for normal WordPress edit capabilities.</p>';
    }

    public static function save_protection($post_id,$post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id) || !isset($_POST['bsw55_protection_nonce'])) return;
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw55_protection_nonce'])),'bsw55_protect_'.$post_id)) return;
        if (!current_user_can('edit_post',$post_id) || !current_user_can('edit_others_posts')) return;
        $level=sanitize_key(wp_unslash($_POST['bsw55_protection']??'open'));
        if (!in_array($level,array('open','registered','editor','admin'),true)) $level='open';
        update_post_meta($post_id,self::PROTECTION_META,$level);
        self::audit('protection_changed',$post_id,array('level'=>$level));
    }

    public static function enforce_protection_tier($caps,$cap,$user_id,$args) {
        if ($cap!=='edit_post' || empty($args[0])) return $caps;
        $post_id=absint($args[0]);
        if (get_post_type($post_id)!=='wiki_article') return $caps;
        $level=get_post_meta($post_id,self::PROTECTION_META,true);
        if (!$level || $level==='open') return $caps;
        $user=get_userdata($user_id);
        if (!$user) return array('do_not_allow');
        $all=(array)$user->allcaps;
        $allowed=false;
        if ($level==='registered') $allowed=!empty($all['edit_posts']) || !empty($all['edit_others_posts']) || !empty($all['manage_options']);
        if ($level==='editor') $allowed=!empty($all['edit_others_posts']) || !empty($all['manage_options']);
        if ($level==='admin') $allowed=!empty($all['manage_options']);
        return $allowed ? $caps : array('do_not_allow');
    }

    public static function appeal_shortcode($atts=array()) {
        if (!is_user_logged_in()) return '<p><a href="'.esc_url(wp_login_url(get_permalink())).'">Sign in</a> to submit a moderation appeal.</p>';
        $url=esc_url(admin_url('admin-post.php'));
        $html='<form class="bsw-moderation-appeal" method="post" action="'.$url.'">';
        $html.='<input type="hidden" name="action" value="bsw55_submit_appeal">';
        $html.=wp_nonce_field('bsw55_submit_appeal','_wpnonce',true,false);
        $html.='<p><label>Related article ID (optional)<br><input type="number" min="1" name="article_id"></label></p>';
        $html.='<p><label>Appeal category<br><select name="category"><option value="account_restriction">Account restriction</option><option value="content_action">Content moderation action</option><option value="page_protection">Page protection</option><option value="other">Other</option></select></label></p>';
        $html.='<p><label>Explain your appeal<br><textarea name="reason" rows="6" maxlength="5000" required></textarea></label></p>';
        $html.='<button type="submit">Submit appeal</button></form>';
        return $html;
    }

    public static function submit_appeal() {
        if (!is_user_logged_in()) wp_die('Sign in to submit an appeal.',403);
        check_admin_referer('bsw55_submit_appeal');
        $article=absint($_POST['article_id']??0);
        if ($article && get_post_type($article)!=='wiki_article') wp_die('Invalid article reference.',400);
        $category=sanitize_key(wp_unslash($_POST['category']??'other'));
        if (!in_array($category,array('account_restriction','content_action','page_protection','other'),true)) $category='other';
        $reason=sanitize_textarea_field(wp_unslash($_POST['reason']??''));
        if ($reason==='') wp_die('An appeal explanation is required.',400);
        $id=wp_insert_post(array('post_type'=>self::APPEAL_TYPE,'post_status'=>'pending',
            'post_title'=>'Appeal — '.$category.' — user #'.get_current_user_id(),
            'post_content'=>$reason,'post_author'=>get_current_user_id()),true);
        if (is_wp_error($id)) wp_die(esc_html($id->get_error_message()),500);
        update_post_meta($id,'_bsw55_category',$category);
        update_post_meta($id,'_bsw55_article_id',$article);
        update_post_meta($id,'_bsw55_decision','pending');
        self::audit('appeal_submitted',$article,array('appeal_id'=>$id,'category'=>$category));
        wp_safe_redirect(wp_get_referer() ?: home_url('/')); exit;
    }

    public static function appeal_decision() {
        if (!current_user_can('edit_others_posts')) wp_die('Not authorised.',403);
        $id=absint($_POST['appeal_id']??0);
        check_admin_referer('bsw55_appeal_'.$id);
        if (get_post_type($id)!==self::APPEAL_TYPE) wp_die('Appeal not found.',404);
        $decision=sanitize_key($_POST['decision']??'');
        if (!in_array($decision,array('upheld','rejected','more_information'),true)) wp_die('Invalid decision.',400);
        $note=sanitize_textarea_field(wp_unslash($_POST['review_note']??''));
        update_post_meta($id,'_bsw55_decision',$decision);
        update_post_meta($id,'_bsw55_review_note',$note);
        update_post_meta($id,'_bsw55_reviewed_by',get_current_user_id());
        update_post_meta($id,'_bsw55_reviewed_at',current_time('mysql'));
        wp_update_post(array('ID'=>$id,'post_status'=>'private'));
        self::audit('appeal_'.$decision,absint(get_post_meta($id,'_bsw55_article_id',true)),array('appeal_id'=>$id));
        wp_safe_redirect(admin_url('edit.php?post_type=wiki_article&page=bsw-phase55-governance&tab=appeals&updated=1')); exit;
    }

    public static function revision_visibility() {
        if (!current_user_can('manage_options')) wp_die('Oversight permission required.',403);
        $revision_id=absint($_POST['revision_id']??0);
        check_admin_referer('bsw55_revision_'.$revision_id);
        $revision=wp_get_post_revision($revision_id);
        if (!$revision || get_post_type($revision->post_parent)!=='wiki_article') wp_die('Invalid revision.',404);
        $suppress=!empty($_POST['suppress']);
        update_post_meta($revision_id,'_bsw55_suppressed',$suppress?'1':'0');
        update_post_meta($revision_id,'_bsw55_suppressed_by',$suppress?get_current_user_id():0);
        update_post_meta($revision_id,'_bsw55_suppressed_at',$suppress?current_time('mysql'):'');
        self::audit($suppress?'revision_suppressed':'revision_revealed',$revision->post_parent,array('revision_id'=>$revision_id));
        wp_safe_redirect(admin_url('edit.php?post_type=wiki_article&page=bsw-phase55-governance&tab=revisions&updated=1')); exit;
    }

    public static function filter_suppressed_revision($post,$post_id) {
        if (!$post || !($post instanceof WP_Post) || $post->post_type!=='revision') return $post;
        if (get_post_meta($post->ID,'_bsw55_suppressed',true)!=='1' || current_user_can('manage_options')) return $post;
        $copy=clone $post;
        $copy->post_title='[Revision hidden by an oversight administrator]';
        $copy->post_content='';
        $copy->post_excerpt='';
        return $copy;
    }

    public static function protection_change() {
        if (!current_user_can('manage_options')) wp_die('Not authorised.',403);
        $id=absint($_POST['article_id']??0);
        check_admin_referer('bsw55_change_protection_'.$id);
        if (get_post_type($id)!=='wiki_article') wp_die('Article not found.',404);
        $level=sanitize_key($_POST['level']??'open');
        if (!in_array($level,array('open','registered','editor','admin'),true)) wp_die('Invalid protection level.',400);
        update_post_meta($id,self::PROTECTION_META,$level);
        self::audit('protection_changed',$id,array('level'=>$level));
        wp_safe_redirect(admin_url('edit.php?post_type=wiki_article&page=bsw-phase55-governance&updated=1')); exit;
    }

    public static function dashboard() {
        if (!current_user_can('edit_others_posts')) wp_die('Not authorised.',403);
        $tab=sanitize_key($_GET['tab']??'overview');
        echo '<div class="wrap"><h1>BIGSHOR Governance & Moderation</h1><nav class="nav-tab-wrapper">';
        foreach(array('overview'=>'Overview','appeals'=>'Appeals','revisions'=>'Revision oversight','protected'=>'Protected pages','audit'=>'Audit log') as $key=>$label) {
            echo '<a class="nav-tab '.($tab===$key?'nav-tab-active':'').'" href="'.esc_url(add_query_arg(array('page'=>'bsw-phase55-governance','tab'=>$key),admin_url('edit.php?post_type=wiki_article'))).'">'.esc_html($label).'</a>';
        }
        echo '</nav>';
        if ($tab==='appeals') self::render_appeals();
        elseif ($tab==='revisions') self::render_revisions();
        elseif ($tab==='protected') self::render_protected();
        elseif ($tab==='audit') self::render_audit();
        else self::render_overview();
        echo '</div>';
    }

    private static function render_overview() {
        $appeals=wp_count_posts(self::APPEAL_TYPE);
        $pending=isset($appeals->pending)?(int)$appeals->pending:0;
        $q=new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>1,'meta_query'=>array(array('key'=>self::PROTECTION_META,'value'=>array('registered','editor','admin'),'compare'=>'IN'))));
        echo '<h2>Editorial control centre</h2><p>Pending appeals: <strong>'.esc_html($pending).'</strong> · Protected published pages (sample query): <strong>'.esc_html($q->found_posts).'</strong></p>';
        echo '<p>Use the tabs to review appeals, hide sensitive revision snapshots from non-oversight users, inspect protected pages and audit moderation actions.</p>';
        echo '<p>Oversight suppression is reversible and does not delete the underlying WordPress revision.</p>';
    }

    private static function render_appeals() {
        $items=get_posts(array('post_type'=>self::APPEAL_TYPE,'post_status'=>array('pending','private'),'numberposts'=>100,'orderby'=>'date','order'=>'DESC'));
        echo '<h2>Moderation appeals</h2>';
        if (!$items) { echo '<p>No appeals found.</p>'; return; }
        echo '<table class="widefat striped"><thead><tr><th>Appeal</th><th>Category</th><th>Article</th><th>Decision</th><th>Submitted</th><th>Review</th></tr></thead><tbody>';
        foreach($items as $item) {
            $article=absint(get_post_meta($item->ID,'_bsw55_article_id',true));
            echo '<tr><td>'.esc_html($item->post_title).'<details><summary>Read explanation</summary>'.wpautop(esc_html($item->post_content)).'</details></td>';
            echo '<td>'.esc_html(get_post_meta($item->ID,'_bsw55_category',true)).'</td><td>'.($article?'<a href="'.esc_url(get_edit_post_link($article)).'">#'.(int)$article.'</a>':'—').'</td>';
            echo '<td>'.esc_html(get_post_meta($item->ID,'_bsw55_decision',true)?:'pending').'</td><td>'.esc_html($item->post_date).'</td><td>';
            if (get_post_meta($item->ID,'_bsw55_decision',true)==='pending') {
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw55_appeal_decision"><input type="hidden" name="appeal_id" value="'.(int)$item->ID.'">'.wp_nonce_field('bsw55_appeal_'.$item->ID,'_wpnonce',true,false);
                echo '<select name="decision"><option value="upheld">Uphold</option><option value="rejected">Reject</option><option value="more_information">Request more information</option></select><input type="text" name="review_note" placeholder="Reviewer note"><button class="button">Save decision</button></form>';
            } else echo esc_html(get_post_meta($item->ID,'_bsw55_review_note',true));
            echo '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_revisions() {
        $posts=get_posts(array('post_type'=>'wiki_article','post_status'=>array('publish','draft','pending','private'),'numberposts'=>50,'orderby'=>'modified','order'=>'DESC'));
        echo '<h2>Revision oversight</h2><p>Suppression hides revision title and content from users without oversight capability. The original revision remains stored.</p>';
        echo '<table class="widefat striped"><thead><tr><th>Article</th><th>Revision date</th><th>Author</th><th>Visibility</th><th>Action</th></tr></thead><tbody>';
        foreach($posts as $post) foreach(wp_get_post_revisions($post->ID,array('posts_per_page'=>3,'orderby'=>'date','order'=>'DESC')) as $rev) {
            $hidden=get_post_meta($rev->ID,'_bsw55_suppressed',true)==='1';
            echo '<tr><td><a href="'.esc_url(get_edit_post_link($post->ID)).'">'.esc_html(get_the_title($post)).'</a></td><td>'.esc_html($rev->post_date).'</td><td>'.esc_html(get_the_author_meta('display_name',$rev->post_author)).'</td><td>'.($hidden?'Suppressed':'Visible').'</td><td>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw55_revision_visibility"><input type="hidden" name="revision_id" value="'.(int)$rev->ID.'">'.wp_nonce_field('bsw55_revision_'.$rev->ID,'_wpnonce',true,false);
            if ($hidden) echo '<button class="button" name="suppress" value="0">Reveal</button>'; else echo '<button class="button" name="suppress" value="1">Suppress</button>';
            echo '</form></td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_protected() {
        $posts=get_posts(array('post_type'=>'wiki_article','post_status'=>array('publish','draft','pending'),'numberposts'=>100,'meta_query'=>array(array('key'=>self::PROTECTION_META,'value'=>array('registered','editor','admin'),'compare'=>'IN'))));
        echo '<h2>Protected pages</h2><table class="widefat striped"><thead><tr><th>Article</th><th>Protection</th><th>Change</th></tr></thead><tbody>';
        foreach($posts as $post) {
            $level=get_post_meta($post->ID,self::PROTECTION_META,true);
            echo '<tr><td><a href="'.esc_url(get_edit_post_link($post->ID)).'">'.esc_html(get_the_title($post)).'</a></td><td>'.esc_html($level).'</td><td><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw55_protection_change"><input type="hidden" name="article_id" value="'.(int)$post->ID.'">'.wp_nonce_field('bsw55_change_protection_'.$post->ID,'_wpnonce',true,false).'<select name="level"><option value="open">Open</option><option value="registered">Registered editors</option><option value="editor">Editors</option><option value="admin">Administrators</option></select><button class="button">Update</button></form></td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_audit() {
        $items=get_option(self::AUDIT_OPTION,array());
        echo '<h2>Moderation audit history</h2>';
        if (!$items) { echo '<p>No Phase 3 moderation events recorded yet.</p>'; return; }
        echo '<table class="widefat striped"><thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Article</th><th>Details</th></tr></thead><tbody>';
        foreach(array_reverse($items) as $row) {
            $user=get_userdata((int)($row['user']??0));
            echo '<tr><td>'.esc_html($row['time']??'').'</td><td>'.esc_html($user?$user->display_name:'System / unavailable').'</td><td>'.esc_html($row['action']??'').'</td><td>'.(!empty($row['post_id'])?'#'.(int)$row['post_id']:'—').'</td><td><code>'.esc_html(wp_json_encode($row['details']??array())).'</code></td></tr>';
        }
        echo '</tbody></table>';
    }

    public static function audit_trash($id) { if (get_post_type($id)==='wiki_article') self::audit('article_trashed',$id); }
    public static function audit_restore($id) { if (get_post_type($id)==='wiki_article') self::audit('article_restored',$id); }
    public static function audit_permanent_delete($id,$post) { if ($post instanceof WP_Post && $post->post_type==='wiki_article') self::audit('article_permanently_deleted',$id); }

    private static function audit($action,$post_id=0,$details=array()) {
        $rows=get_option(self::AUDIT_OPTION,array());
        if (!is_array($rows)) $rows=array();
        $rows[]=array('time'=>current_time('mysql'),'user'=>get_current_user_id(),'action'=>sanitize_key($action),
            'post_id'=>absint($post_id),'details'=>$details);
        update_option(self::AUDIT_OPTION,array_slice($rows,-1000),false);
    }
}
BSW_Phase55_Editorial_Governance::boot();
}
