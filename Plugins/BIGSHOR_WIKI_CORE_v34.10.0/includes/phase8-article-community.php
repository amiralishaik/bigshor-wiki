<?php
/**
 * Article-page Phase 8: discussion, replies and personal watchlist controls.
 * Builds on the existing wiki_talk post type and WordPress comments.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase8_Article_Community')) {
final class BSW_Phase8_Article_Community {
    public static function init() {
        add_action('admin_post_bsw_p8_watch', array(__CLASS__, 'toggle_watch'));
        add_action('admin_post_bsw_p8_talk', array(__CLASS__, 'create_topic'));
        add_filter('the_content', array(__CLASS__, 'append_community'), 70);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'styles'));
    }
    public static function styles() {
        if (!is_singular('wiki_article')) return;
        wp_register_style('bsw-p8-community', false, array(), '34.11.0');
        wp_enqueue_style('bsw-p8-community');
        wp_add_inline_style('bsw-p8-community', '.bsw-p8-community{clear:both;border-top:1px solid #a2a9b1;margin:30px 0 20px;padding:18px 0;max-width:1180px}.bsw-p8-watch-form{margin:0 0 18px}.bsw-p8-watch-form button,.bsw-p8-topic-form button{padding:8px 14px;border:1px solid #72777d;background:#f8f9fa;border-radius:3px;cursor:pointer}.bsw-p8-topic-form label{display:block;font-weight:600;margin:10px 0}.bsw-p8-topic-form input,.bsw-p8-topic-form textarea{display:block;width:100%;max-width:680px;padding:8px;border:1px solid #72777d}.bsw-p8-topic{border:1px solid #c8ccd1;padding:14px;margin:14px 0;border-radius:3px}.bsw-p8-topic h3{margin-top:0}.bsw-p8-topic-meta{font-size:12px;color:#54595d}.bsw-p8-replies{margin-top:14px;padding-top:10px;border-top:1px solid #eaecf0}.bsw-p8-community .comment-list{padding-left:24px}.bsw-p8-honeypot{position:absolute!important;left:-10000px!important;width:1px!important;height:1px!important;overflow:hidden!important}');
    }
    private static function watched($user_id, $article_id) {
        $ids = get_user_meta($user_id, '_bsw_watchlist', true);
        if (!is_array($ids)) $ids = array();
        return in_array((int)$article_id, array_map('absint', $ids), true);
    }
    private static function watch_button($article_id) {
        if (!is_user_logged_in()) return '<p class="bsw-p8-watch-login"><a href="'.esc_url(wp_login_url(get_permalink($article_id))).'">Sign in</a> to add this article to your watchlist.</p>';
        $watching = self::watched(get_current_user_id(), $article_id);
        return '<form class="bsw-p8-watch-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw_p8_watch"><input type="hidden" name="article_id" value="'.(int)$article_id.'">'.wp_nonce_field('bsw_p8_watch_'.$article_id,'_wpnonce',true,false).'<button type="submit">'.($watching?'★ Remove from watchlist':'☆ Add to watchlist').'</button></form>';
    }
    public static function toggle_watch() {
        if (!is_user_logged_in()) wp_die('Sign in to manage your watchlist.',403);
        $id=absint($_POST['article_id']??0);
        if (!$id || get_post_type($id)!=='wiki_article') wp_die('Invalid article.',404);
        check_admin_referer('bsw_p8_watch_'.$id);
        $uid=get_current_user_id();$ids=get_user_meta($uid,'_bsw_watchlist',true);if(!is_array($ids))$ids=array();
        $ids=array_values(array_unique(array_filter(array_map('absint',$ids))));
        if(in_array($id,$ids,true))$ids=array_values(array_diff($ids,array($id)));
        else {if(count($ids)>=1000)wp_die('Your watchlist has reached its limit.');$ids[]=$id;}
        update_user_meta($uid,'_bsw_watchlist',$ids);
        wp_safe_redirect(get_permalink($id).'#bsw-article-discussion');exit;
    }
    public static function create_topic() {
        if(!is_user_logged_in())wp_die('Sign in to start a discussion.',403);
        $id=absint($_POST['article_id']??0);
        if(!$id||get_post_type($id)!=='wiki_article')wp_die('Invalid article.',404);
        check_admin_referer('bsw_p8_talk_'.$id);
        if(!empty($_POST['website']))wp_die('Discussion could not be submitted.',400);
        $last=(int)get_user_meta(get_current_user_id(),'_bsw_p8_last_topic',true);
        if($last && time()-$last<20)wp_die('Please wait before starting another discussion.',429);
        $title=sanitize_text_field(wp_unslash($_POST['topic_title']??''));
        $body=wp_kses_post(wp_unslash($_POST['topic_body']??''));
        $plain=trim(wp_strip_all_tags($body));
        if(strlen($title)<4||strlen($title)>120||strlen($plain)<20||strlen($plain)>10000)wp_die('Provide a topic title (4–120 characters) and a meaningful message (20–10,000 characters).',400);
        if(!post_type_exists('wiki_talk'))wp_die('The discussion service is not available.',503);
        $topic=wp_insert_post(array('post_type'=>'wiki_talk','post_status'=>'publish','post_title'=>$title,'post_content'=>$body,'post_author'=>get_current_user_id(),'comment_status'=>'open','ping_status'=>'closed'),true);
        if(is_wp_error($topic))wp_die(esc_html($topic->get_error_message()),500);
        update_post_meta($topic,'_bsw_talk_article',$id);
        update_user_meta(get_current_user_id(),'_bsw_p8_last_topic',time());
        wp_safe_redirect(get_permalink($id).'#bsw-talk-topic-'.(int)$topic);exit;
    }
    private static function discussions($article_id) {
        $q=new WP_Query(array('post_type'=>'wiki_talk','post_status'=>'publish','posts_per_page'=>40,'meta_key'=>'_bsw_talk_article','meta_value'=>(string)$article_id,'orderby'=>'date','order'=>'ASC','no_found_rows'=>true));
        $out='<section class="bsw-p8-discussions" aria-labelledby="bsw-p8-discussions-title"><h2 id="bsw-p8-discussions-title">Discussion topics</h2>';
        if(!$q->have_posts())$out.='<p>No discussion topics yet. Start a constructive conversation about improving this article.</p>';
        while($q->have_posts()){$q->the_post();$tid=get_the_ID();$author=get_the_author_meta('display_name');
            $out.='<article class="bsw-p8-topic" id="bsw-talk-topic-'.(int)$tid.'"><h3>'.esc_html(get_the_title()).'</h3><div>'.wp_kses_post(wpautop(get_post_field('post_content',$tid))).'</div><p class="bsw-p8-topic-meta">Started by '.esc_html($author).' · '.esc_html(get_the_date('', $tid)).'</p>';
            $comments=get_comments(array('post_id'=>$tid,'status'=>'approve','order'=>'ASC','orderby'=>'comment_date_gmt'));
            $out.='<div class="bsw-p8-replies"><h4>Replies</h4>';
            if($comments)$out.=wp_list_comments(array('style'=>'ol','short_ping'=>true,'echo'=>false),$comments);
            else $out.='<p>No replies yet.</p>';
            if(is_user_logged_in()&&comments_open($tid))$out.=get_comment_form(array('title_reply'=>'Add a reply','label_submit'=>'Post reply','logged_in_as'=>'','comment_notes_before'=>'','comment_notes_after'=>''),$tid);
            elseif(!is_user_logged_in())$out.='<p><a href="'.esc_url(wp_login_url(get_permalink($article_id))).'">Sign in</a> to reply.</p>';
            $out.='</div></article>';
        }
        wp_reset_postdata();
        if(is_user_logged_in())$out.='<form class="bsw-p8-topic-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><h3>Start a discussion</h3><input type="hidden" name="action" value="bsw_p8_talk"><input type="hidden" name="article_id" value="'.(int)$article_id.'">'.wp_nonce_field('bsw_p8_talk_'.$article_id,'_wpnonce',true,false).'<label>Topic title<input name="topic_title" maxlength="120" required></label><label>Message<textarea name="topic_body" rows="5" maxlength="12000" required></textarea></label><label class="bsw-p8-honeypot" aria-hidden="true">Leave this field empty<input name="website" tabindex="-1" autocomplete="off"></label><button type="submit">Start discussion</button></form>';
        else $out.='<p><a href="'.esc_url(wp_login_url(get_permalink($article_id))).'">Sign in</a> to start a discussion.</p>';
        return $out.'</section>';
    }
    public static function append_community($content) {
        if(is_admin()||!is_singular('wiki_article')||!in_the_loop()||!is_main_query())return $content;
        $id=get_the_ID();if(!$id||isset($_GET['bsw_edit'])||isset($_GET['bsw_history']))return $content;
        return $content.'<section class="bsw-p8-community" id="bsw-article-discussion" aria-label="Article community">'.self::watch_button($id).self::discussions($id).'</section>';
    }
}
BSW_Phase8_Article_Community::init();
}
