<?php
/**
 * Phase 3 completion — deletion discussions, editorial approval queue and trash restoration.
 * Builds on native WordPress article statuses, comments, revisions and Trash.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase56_Editorial_Workflows')) {
final class BSW_Phase56_Editorial_Workflows {
    const DISCUSSION_TYPE = 'bsw_deletion_discussion';
    const AUDIT_OPTION = 'bsw_phase56_editorial_audit';

    public static function boot() {
        add_action('init', array(__CLASS__, 'register_discussion_type'), 27);
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 60);
        add_action('admin_post_bsw56_submit_deletion', array(__CLASS__, 'submit_deletion'));
        add_action('admin_post_bsw56_resolve_deletion', array(__CLASS__, 'resolve_deletion'));
        add_action('admin_post_bsw56_review_article', array(__CLASS__, 'review_article'));
        add_action('admin_post_bsw56_restore_article', array(__CLASS__, 'restore_article'));
        add_shortcode('bsw_deletion_discussion', array(__CLASS__, 'discussion_shortcode'));
        add_filter('preprocess_comment', array(__CLASS__, 'guard_discussion_comments'));
        add_action('transition_post_status', array(__CLASS__, 'audit_article_status'), 50, 3);
    }

    public static function register_discussion_type() {
        if (post_type_exists(self::DISCUSSION_TYPE)) return;
        register_post_type(self::DISCUSSION_TYPE, array(
            'labels'=>array('name'=>'Deletion Discussions','singular_name'=>'Deletion Discussion'),
            'public'=>false,'publicly_queryable'=>false,'show_ui'=>true,
            'show_in_menu'=>'edit.php?post_type=wiki_article','show_in_rest'=>false,
            'supports'=>array('title','editor','author','comments'),
            'capability_type'=>'post','map_meta_cap'=>true
        ));
    }

    public static function admin_menu() {
        add_submenu_page('edit.php?post_type=wiki_article','Editorial Workflow','Editorial Workflow',
            'edit_others_posts','bsw-phase56-workflow',array(__CLASS__,'dashboard'));
    }

    public static function submit_deletion() {
        if (!is_user_logged_in()) wp_die('Sign in to propose deletion.',403);
        check_admin_referer('bsw56_submit_deletion');
        $article_id=absint($_POST['article_id']??0);
        if (!$article_id || get_post_type($article_id)!=='wiki_article' || get_post_status($article_id)!=='publish') wp_die('Select a published BIGSHOR article.',400);
        $reason=sanitize_textarea_field(wp_unslash($_POST['reason']??''));
        if (strlen($reason)<20 || strlen($reason)>5000) wp_die('Explain the proposal in 20–5,000 characters.',400);
        $existing=get_posts(array('post_type'=>self::DISCUSSION_TYPE,'post_status'=>'private','numberposts'=>1,
            'meta_query'=>array(array('key'=>'_bsw56_article_id','value'=>$article_id),array('key'=>'_bsw56_state','value'=>'open'))));
        if ($existing) wp_die('An open deletion discussion already exists for this article.',409);
        $title='Deletion discussion: '.get_the_title($article_id);
        $id=wp_insert_post(array('post_type'=>self::DISCUSSION_TYPE,'post_status'=>'private','post_title'=>$title,
            'post_content'=>$reason,'post_author'=>get_current_user_id(),'comment_status'=>'open'),true);
        if (is_wp_error($id)) wp_die(esc_html($id->get_error_message()),500);
        update_post_meta($id,'_bsw56_article_id',$article_id);
        update_post_meta($id,'_bsw56_state','open');
        update_post_meta($id,'_bsw56_opened_at',current_time('mysql'));
        self::audit('deletion_discussion_opened',$article_id,array('discussion_id'=>$id));
        wp_safe_redirect(add_query_arg('bsw_deletion_proposed','1',wp_get_referer()?:get_permalink($article_id))); exit;
    }

    public static function discussion_shortcode($atts=array()) {
        $atts=shortcode_atts(array('id'=>0),$atts,'bsw_deletion_discussion');
        $article_id=absint($atts['id']?:get_the_ID());
        if (!$article_id || get_post_type($article_id)!=='wiki_article') return '';
        $out='';
        if (is_user_logged_in() && current_user_can('edit_others_posts')) {
            $out.='<form class="bsw-deletion-proposal" method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
            $out.='<input type="hidden" name="action" value="bsw56_submit_deletion"><input type="hidden" name="article_id" value="'.$article_id.'">';
            $out.=wp_nonce_field('bsw56_submit_deletion','_wpnonce',true,false);
            $out.='<p><label>Reason for proposing deletion<br><textarea name="reason" rows="4" minlength="20" maxlength="5000" required></textarea></label></p><button type="submit">Open deletion discussion</button></form>';
        }
        $items=get_posts(array('post_type'=>self::DISCUSSION_TYPE,'post_status'=>'private','numberposts'=>10,
            'meta_query'=>array(array('key'=>'_bsw56_article_id','value'=>$article_id)), 'orderby'=>'date','order'=>'DESC'));
        if (!$items) return $out.'<p>No deletion discussions have been recorded for this article.</p>';
        foreach ($items as $discussion) {
            $state=get_post_meta($discussion->ID,'_bsw56_state',true)?:'open';
            $out.='<section class="bsw-deletion-discussion"><h3>'.esc_html($discussion->post_title).'</h3><p><strong>Status:</strong> '.esc_html(ucfirst($state)).'</p>';
            $out.=wpautop(esc_html($discussion->post_content));
            $out.='<p><strong>Discussion comments</strong></p>';
            if ($state==='open' && is_user_logged_in()) $out.=get_comment_form(array('title_reply'=>'Add to this deletion discussion','label_submit'=>'Post comment','logged_in_as'=>'','comment_notes_before'=>'','comment_notes_after'=>'','comment_field'=>'<p class="comment-form-comment"><label for="comment">Your contribution</label><textarea id="comment" name="comment" rows="4" required></textarea></p>'),$discussion->ID);
            $comments=get_comments(array('post_id'=>$discussion->ID,'status'=>'approve','order'=>'ASC','orderby'=>'comment_date_gmt'));
            if ($comments) { $out.='<ol>'; foreach($comments as $comment) $out.='<li><strong>'.esc_html($comment->comment_author).'</strong> — '.esc_html($comment->comment_date).wpautop(esc_html($comment->comment_content)).'</li>'; $out.='</ol>'; }
            $out.='</section>';
        }
        return $out;
    }

    public static function guard_discussion_comments($commentdata) {
        $post_id=absint($commentdata['comment_post_ID']??0);
        if (get_post_type($post_id)===self::DISCUSSION_TYPE) {
            if (!is_user_logged_in()) wp_die('Sign in to participate in deletion discussions.',403);
            if (get_post_meta($post_id,'_bsw56_state',true)!=='open') wp_die('This deletion discussion is closed.',409);
        }
        return $commentdata;
    }

    public static function resolve_deletion() {
        if (!current_user_can('edit_others_posts')) wp_die('Editorial permission required.',403);
        $id=absint($_POST['discussion_id']??0);
        check_admin_referer('bsw56_resolve_'.$id);
        $discussion=get_post($id);
        if (!$discussion || $discussion->post_type!==self::DISCUSSION_TYPE || get_post_meta($id,'_bsw56_state',true)!=='open') wp_die('Open deletion discussion not found.',404);
        $article_id=absint(get_post_meta($id,'_bsw56_article_id',true));
        $article=get_post($article_id);
        if (!$article || $article->post_type!=='wiki_article') wp_die('Related article no longer exists.',404);
        $outcome=sanitize_key($_POST['outcome']??'');
        if (!in_array($outcome,array('keep','delete','merge'),true)) wp_die('Invalid resolution.',400);
        $note=sanitize_textarea_field(wp_unslash($_POST['resolution_note']??''));
        if ($outcome==='merge') {
            $target_id=absint($_POST['merge_target']??0);
            if (!$target_id || $target_id===$article_id || get_post_type($target_id)!=='wiki_article' || get_post_status($target_id)!=='publish') wp_die('Choose another published BIGSHOR article as the merge destination.',400);
            $target_url=get_permalink($target_id);
            update_post_meta($article_id,'_bsw_redirect_target',$target_url);
            update_post_meta($article_id,'_bsw56_merged_into',$target_id);
            wp_update_post(array('ID'=>$article_id,'post_content'=>'This article has been merged into <a href="'.esc_url($target_url).'">'.esc_html(get_the_title($target_id)).'</a>.'));
        } elseif ($outcome==='delete') {
            $trashed=wp_trash_post($article_id);
            if (!$trashed) wp_die('The article could not be moved to Trash.',500);
        }
        update_post_meta($id,'_bsw56_state','closed');
        update_post_meta($id,'_bsw56_outcome',$outcome);
        update_post_meta($id,'_bsw56_resolution_note',$note);
        update_post_meta($id,'_bsw56_closed_by',get_current_user_id());
        update_post_meta($id,'_bsw56_closed_at',current_time('mysql'));
        self::audit('deletion_discussion_resolved',$article_id,array('discussion_id'=>$id,'outcome'=>$outcome,'note'=>$note));
        wp_safe_redirect(admin_url('edit.php?post_type=wiki_article&page=bsw-phase56-workflow&tab=deletion&updated=1')); exit;
    }

    public static function review_article() {
        if (!current_user_can('edit_others_posts')) wp_die('Editorial permission required.',403);
        $id=absint($_POST['article_id']??0);
        check_admin_referer('bsw56_review_'.$id);
        $post=get_post($id);
        if (!$post || $post->post_type!=='wiki_article' || $post->post_status!=='pending') wp_die('Pending article not found.',404);
        $decision=sanitize_key($_POST['decision']??'');
        if (!in_array($decision,array('approve','return'),true)) wp_die('Invalid editorial decision.',400);
        $note=sanitize_textarea_field(wp_unslash($_POST['review_note']??''));
        update_post_meta($id,'_bsw56_review_note',$note);
        update_post_meta($id,'_bsw56_reviewed_by',get_current_user_id());
        update_post_meta($id,'_bsw56_reviewed_at',current_time('mysql'));
        $target=$decision==='approve'?'publish':'draft';
        $result=wp_update_post(array('ID'=>$id,'post_status'=>$target),true);
        if (is_wp_error($result)) wp_die(esc_html($result->get_error_message()),500);
        self::audit($decision==='approve'?'article_approved':'article_returned_for_revision',$id,array('note'=>$note));
        wp_safe_redirect(admin_url('edit.php?post_type=wiki_article&page=bsw-phase56-workflow&tab=queue&updated=1')); exit;
    }

    public static function restore_article() {
        if (!current_user_can('edit_others_posts')) wp_die('Editorial permission required.',403);
        $id=absint($_POST['article_id']??0);
        check_admin_referer('bsw56_restore_'.$id);
        $post=get_post($id);
        if (!$post || $post->post_type!=='wiki_article' || $post->post_status!=='trash') wp_die('Trashed article not found.',404);
        if (!current_user_can('delete_post',$id)) wp_die('You cannot restore this article.',403);
        $result=wp_untrash_post($id);
        if (!$result) wp_die('WordPress could not restore this article.',500);
        self::audit('article_restored_from_trash',$id);
        wp_safe_redirect(admin_url('edit.php?post_type=wiki_article&page=bsw-phase56-workflow&tab=trash&updated=1')); exit;
    }

    public static function audit_article_status($new,$old,$post) {
        if (!$post || $post->post_type!=='wiki_article' || $new===$old || wp_is_post_revision($post->ID) || wp_is_post_autosave($post->ID)) return;
        self::audit('article_status_changed',(int)$post->ID,array('from'=>$old,'to'=>$new));
    }

    private static function audit($action,$post_id=0,$details=array()) {
        $rows=get_option(self::AUDIT_OPTION,array());
        if (!is_array($rows)) $rows=array();
        $rows[]=array('time'=>current_time('mysql'),'user'=>get_current_user_id(),'action'=>sanitize_key($action),'post_id'=>absint($post_id),'details'=>$details);
        update_option(self::AUDIT_OPTION,array_slice($rows,-1500),false);
        // Mirror events into the Phase 55 governance audit stream when available.
        $existing=get_option('bsw_phase55_moderation_audit',array());
        if (is_array($existing)) {
            $existing[]=array('time'=>current_time('mysql'),'user'=>get_current_user_id(),'action'=>sanitize_key($action),'post_id'=>absint($post_id),'details'=>$details);
            update_option('bsw_phase55_moderation_audit',array_slice($existing,-1000),false);
        }
    }

    public static function dashboard() {
        if (!current_user_can('edit_others_posts')) wp_die('Not authorised.',403);
        $tab=sanitize_key($_GET['tab']??'queue');
        echo '<div class="wrap"><h1>BIGSHOR Editorial Workflow</h1><nav class="nav-tab-wrapper">';
        foreach(array('queue'=>'Approval queue','deletion'=>'Deletion discussions','trash'=>'Restore deleted articles') as $key=>$label) {
            echo '<a class="nav-tab '.($tab===$key?'nav-tab-active':'').'" href="'.esc_url(add_query_arg(array('page'=>'bsw-phase56-workflow','tab'=>$key),admin_url('edit.php?post_type=wiki_article'))).'">'.esc_html($label).'</a>';
        }
        echo '</nav>';
        if ($tab==='deletion') self::render_deletion();
        elseif ($tab==='trash') self::render_trash();
        else self::render_queue();
        echo '</div>';
    }

    private static function render_queue() {
        $items=get_posts(array('post_type'=>'wiki_article','post_status'=>'pending','numberposts'=>100,'orderby'=>'date','order'=>'ASC'));
        echo '<h2>Articles awaiting editorial approval</h2>';
        if (!$items) { echo '<p>No articles are waiting for review.</p>'; return; }
        echo '<table class="widefat striped"><thead><tr><th>Article</th><th>Author</th><th>Submitted</th><th>Review decision</th></tr></thead><tbody>';
        foreach($items as $p) {
            echo '<tr><td><a href="'.esc_url(get_edit_post_link($p->ID)).'">'.esc_html(get_the_title($p)).'</a></td><td>'.esc_html(get_the_author_meta('display_name',$p->post_author)).'</td><td>'.esc_html($p->post_date).'</td><td>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw56_review_article"><input type="hidden" name="article_id" value="'.(int)$p->ID.'">'.wp_nonce_field('bsw56_review_'.$p->ID,'_wpnonce',true,false);
            echo '<select name="decision"><option value="approve">Approve and publish</option><option value="return">Return to draft</option></select><input type="text" name="review_note" placeholder="Reviewer note"><button class="button button-primary">Record decision</button></form></td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_deletion() {
        $items=get_posts(array('post_type'=>self::DISCUSSION_TYPE,'post_status'=>'private','numberposts'=>100,'orderby'=>'date','order'=>'DESC'));
        echo '<h2>Deletion discussions</h2>';
        if (!$items) { echo '<p>No deletion proposals have been submitted.</p>'; return; }
        echo '<table class="widefat striped"><thead><tr><th>Discussion</th><th>Article</th><th>State</th><th>Comments</th><th>Resolution</th></tr></thead><tbody>';
        foreach($items as $d) {
            $aid=absint(get_post_meta($d->ID,'_bsw56_article_id',true));$state=get_post_meta($d->ID,'_bsw56_state',true)?:'open';
            $article=get_post($aid);$comments=get_comments(array('post_id'=>$d->ID,'count'=>true,'status'=>'approve'));
            echo '<tr><td>'.esc_html($d->post_title).'<details><summary>Proposal details</summary>'.wpautop(esc_html($d->post_content)).'</details></td><td>'.($article?'<a href="'.esc_url(get_edit_post_link($aid)).'">'.esc_html(get_the_title($aid)).'</a>':'Unavailable').'</td><td>'.esc_html($state).'</td><td>'.absint($comments).'</td><td>';
            if ($state==='open' && $article) {
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw56_resolve_deletion"><input type="hidden" name="discussion_id" value="'.(int)$d->ID.'">'.wp_nonce_field('bsw56_resolve_'.$d->ID,'_wpnonce',true,false);
                echo '<select name="outcome"><option value="keep">Keep article</option><option value="delete">Delete (move to Trash)</option><option value="merge">Merge into another article</option></select><input type="number" min="1" name="merge_target" placeholder="Merge destination ID"><input type="text" name="resolution_note" placeholder="Resolution note"><button class="button">Close discussion</button></form>';
            } else echo esc_html(get_post_meta($d->ID,'_bsw56_outcome',true)?:'—').' '.esc_html(get_post_meta($d->ID,'_bsw56_resolution_note',true));
            echo '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_trash() {
        $items=get_posts(array('post_type'=>'wiki_article','post_status'=>'trash','numberposts'=>100,'orderby'=>'date','order'=>'DESC'));
        echo '<h2>Trashed articles — restoration</h2><p>Restoration returns the article to its prior status when WordPress retains that status in Trash metadata.</p>';
        if (!$items) { echo '<p>The article Trash is empty.</p>'; return; }
        echo '<table class="widefat striped"><thead><tr><th>Article</th><th>Deleted</th><th>Restore</th></tr></thead><tbody>';
        foreach($items as $p) {
            echo '<tr><td>'.esc_html(get_the_title($p)).'</td><td>'.esc_html($p->post_modified).'</td><td><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw56_restore_article"><input type="hidden" name="article_id" value="'.(int)$p->ID.'">'.wp_nonce_field('bsw56_restore_'.$p->ID,'_wpnonce',true,false).'<button class="button">Restore article</button></form></td></tr>';
        }
        echo '</tbody></table>';
    }
}
BSW_Phase56_Editorial_Workflows::boot();
}
