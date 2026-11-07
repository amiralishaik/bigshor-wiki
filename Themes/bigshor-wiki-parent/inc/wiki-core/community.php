<?php
if (!defined('ABSPATH')) exit;

/* Public revision history and side-by-side WordPress text diffs */
add_shortcode('bigshor_wiki_history',function(){
 $id=absint($_GET['article_id']??0);if(!$id||get_post_type($id)!=='wiki_article')return '<p>Select an article to view its history.</p>';
 $post=get_post($id);$revs=wp_get_post_revisions($id,array('order'=>'DESC','orderby'=>'date'));
 $out='<h2><a href="'.esc_url(get_permalink($id)).'">'.esc_html(get_the_title($id)).'</a> — revision history</h2>';
 if(!$revs)return $out.'<p>No saved revisions are available yet. WordPress creates revisions when editors update an article.</p>';
 $out.='<table class="wiki-history"><thead><tr><th>Revision date</th><th>Contributor</th><th>Compare</th></tr></thead><tbody>';
 $list=array_values($revs);
 foreach($list as $i=>$rev){
  $author=get_the_author_meta('display_name',$rev->post_author);
  $out.='<tr><td>'.esc_html(get_date_from_gmt($rev->post_date_gmt,'Y-m-d H:i')).'</td><td>'.esc_html($author).'</td><td><a href="'.esc_url(add_query_arg(array('article_id'=>$id,'old'=>$rev->ID,'new'=>isset($list[$i-1])?$list[$i-1]->ID:$id),get_permalink(get_page_by_path('article-history')))).'">Compare</a></td></tr>';
 }
 $out.='</tbody></table>';
 $old=absint($_GET['old']??0);$new=absint($_GET['new']??0);
 if($old&&$new){
  $oldpost=wp_get_post_revision($old);$newpost=wp_get_post_revision($new);
  if(!$newpost)$newpost=get_post($new);
  if($oldpost&&$newpost&&$oldpost->post_parent===$id&&($newpost->ID===$id||$newpost->post_parent===$id)){
   $diff=wp_text_diff($oldpost->post_content,$newpost->post_content,array('show_split_view'=>true));
   $out.='<h3>Revision comparison</h3><div class="wiki-diff">'.($diff?:'<p>No text differences.</p>').'</div>';
  } else $out.='<p class="wiki-alert">That revision comparison is not valid for this article.</p>';
 }
 return $out;
});

/* Public edit proposals are separate records and never overwrite the live article */
add_shortcode('bigshor_wiki_propose_edit',function(){
 $article=absint($_GET['article_id']??0);
 if(!$article||get_post_type($article)!=='wiki_article')return '<p>Open an article and choose “Suggest an edit” to start a proposal.</p>';
 if(!is_user_logged_in())return '<p>Please <a href="'.esc_url(wp_login_url(get_permalink())).'">log in</a> to suggest an edit.</p>';
 $notice='';
 if(isset($_POST['bsw_proposal_submit'])){
  if(!isset($_POST['bsw_proposal_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_proposal_nonce'])),'bsw_proposal_'.$article))$notice='<p class="wiki-alert">Security validation failed.</p>';
  else{
   $content=wp_kses_post(wp_unslash($_POST['bsw_proposal_content']??''));$reason=sanitize_textarea_field(wp_unslash($_POST['bsw_proposal_reason']??''));
   $recent=get_transient('bsw_proposal_rate_'.get_current_user_id());
   if($recent)$notice='<p class="wiki-alert">Please wait before submitting another proposal.</p>';
   elseif(strlen(wp_strip_all_tags($content))<40)$notice='<p class="wiki-alert">Please provide a meaningful suggested revision.</p>';
   else{
    $pid=wp_insert_post(array('post_type'=>'wiki_edit_proposal','post_status'=>'pending','post_title'=>'Edit proposal: '.get_the_title($article),'post_content'=>$content,'post_author'=>get_current_user_id()),true);
    if(!is_wp_error($pid)){update_post_meta($pid,'_bsw_target_article',$article);update_post_meta($pid,'_bsw_proposal_reason',$reason);set_transient('bsw_proposal_rate_'.get_current_user_id(),1,60);
    wp_mail(get_option('admin_email'),'BIGSHOR Wiki: edit proposal submitted','A new edit proposal is waiting for review: '.admin_url('post.php?post='.$pid.'&action=edit'));
    $notice='<p class="bsw-success">Your proposal has been sent to the editorial team. It does not change the published article.</p>';}
    else $notice='<p class="wiki-alert">Could not save your proposal. Please try again.</p>';
   }
  }
 }
 $out=$notice.'<div class="bsw-v3-card"><h2>Suggest an improvement to: '.esc_html(get_the_title($article)).'</h2><form class="bsw-v3-form" method="post">';
 $out.=wp_nonce_field('bsw_proposal_'.$article,'bsw_proposal_nonce',true,false);
 $out.='<p><label><strong>Suggested replacement or revised article text</strong><textarea required name="bsw_proposal_content" rows="12"></textarea></label></p><p><label><strong>Reason and sources</strong><textarea name="bsw_proposal_reason" rows="4" placeholder="Explain what should change and provide reliable sources."></textarea></label></p><button class="wiki-button" type="submit" name="bsw_proposal_submit" value="1">Submit proposal for review</button></form></div>';
 return $out;
});

/* Admin proposal actions: accept proposal content as a new revision, or reject it */
add_filter('post_row_actions',function($actions,$post){
 if($post->post_type!=='wiki_edit_proposal'||!current_user_can('edit_others_posts'))return $actions;
 $actions['bsw_accept']='<a href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=bsw_accept_proposal&proposal_id='.$post->ID),'bsw_accept_'.$post->ID)).'">Approve and apply</a>';
 $actions['bsw_reject']='<a href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=bsw_reject_proposal&proposal_id='.$post->ID),'bsw_reject_'.$post->ID)).'">Reject</a>';
 return $actions;
},10,2);
add_action('admin_post_bsw_accept_proposal',function(){
 $pid=absint($_GET['proposal_id']??0);if(!$pid||!current_user_can('edit_others_posts')||!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce']??'')),'bsw_accept_'.$pid))wp_die('Not authorised.');
 $p=get_post($pid);$target=absint(get_post_meta($pid,'_bsw_target_article',true));if(!$p||$p->post_type!=='wiki_edit_proposal'||get_post_type($target)!=='wiki_article')wp_die('Invalid proposal.');
 wp_update_post(array('ID'=>$target,'post_content'=>$p->post_content));update_post_meta($pid,'_bsw_proposal_state','approved');wp_update_post(array('ID'=>$pid,'post_status'=>'private'));wp_safe_redirect(admin_url('edit.php?post_type=wiki_edit_proposal&bsw_notice=approved'));exit;
});
add_action('admin_post_bsw_reject_proposal',function(){
 $pid=absint($_GET['proposal_id']??0);if(!$pid||!current_user_can('edit_others_posts')||!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce']??'')),'bsw_reject_'.$pid))wp_die('Not authorised.');
 update_post_meta($pid,'_bsw_proposal_state','rejected');wp_update_post(array('ID'=>$pid,'post_status'=>'private'));wp_safe_redirect(admin_url('edit.php?post_type=wiki_edit_proposal&bsw_notice=rejected'));exit;
});

/* Watchlist controls and email notification when a watched article changes */
add_action('wp',function(){
 $settings=function_exists('bsw_get_settings')?bsw_get_settings():array('show_watchlist'=>'1');if(($settings['show_watchlist']??'1')!=='1'||!is_singular('wiki_article')||!is_user_logged_in()||!isset($_GET['bsw_watch']))return;
 $id=get_queried_object_id();$nonce=sanitize_text_field(wp_unslash($_GET['_wpnonce']??''));
 if(!wp_verify_nonce($nonce,'bsw_watch_'.$id))return;
 $uid=get_current_user_id();$list=get_user_meta($uid,'_bsw_watchlist',true);if(!is_array($list))$list=array();
 if(in_array($id,$list))$list=array_diff($list,array($id));else $list[]=$id;
 update_user_meta($uid,'_bsw_watchlist',array_values(array_unique(array_map('intval',$list))));
 wp_safe_redirect(remove_query_arg(array('bsw_watch','_wpnonce')));exit;
});
add_shortcode('bigshor_wiki_watchlist',function(){
 $settings=function_exists('bsw_get_settings')?bsw_get_settings():array('show_watchlist'=>'1');if(($settings['show_watchlist']??'1')!=='1')return '<p>Watchlists are currently disabled.</p>';
 if(!is_user_logged_in())return '<p>Please log in to view your watchlist.</p>';
 $ids=get_user_meta(get_current_user_id(),'_bsw_watchlist',true);if(!is_array($ids)||!$ids)return '<p>Your watchlist is empty. Visit an article and choose Add to watchlist.</p>';
 $q=new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','post__in'=>array_map('intval',$ids),'orderby'=>'post__in','posts_per_page'=>100));
 $o='<ul>';while($q->have_posts()){$q->the_post();$o.='<li><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a></li>';};wp_reset_postdata();return $o.'</ul>';
});
add_filter('the_content',function($content){
 $settings=function_exists('bsw_get_settings')?bsw_get_settings():array('show_watchlist'=>'1');if(($settings['show_watchlist']??'1')!=='1'||!is_singular('wiki_article')||!is_user_logged_in()||!in_the_loop()||!is_main_query())return $content;
 $id=get_the_ID();$list=get_user_meta(get_current_user_id(),'_bsw_watchlist',true);if(!is_array($list))$list=array();$watch=in_array($id,$list);
 $url=wp_nonce_url(add_query_arg('bsw_watch',$id,get_permalink()),'bsw_watch_'.$id);
 return '<p class="bsw-watch"><a class="wiki-button" href="'.esc_url($url).'">'.($watch?'Remove from watchlist':'Add to watchlist').'</a></p>'.$content;
},11);
function bsw_v3_notify_watchers($post_id,$post,$update){
 $settings=function_exists('bsw_get_settings')?bsw_get_settings():array('email_notifications'=>'1');if(($settings['email_notifications']??'1')!=='1')return;
 if(wp_is_post_revision($post_id)||wp_is_post_autosave($post_id)||$post->post_type!=='wiki_article'||$post->post_status!=='publish')return;
 $old=get_post_meta($post_id,'_bsw_last_notified_hash',true);$hash=md5($post->post_modified_gmt.'|'.$post->post_content);
 if($old===$hash)return;
 update_post_meta($post_id,'_bsw_last_notified_hash',$hash);
 $users=get_users(array('fields'=>array('ID','user_email','display_name')));
 foreach($users as $u){
  $watchlist=get_user_meta($u->ID,'_bsw_watchlist',true);
  if(!is_array($watchlist)||!in_array((int)$post_id,array_map('intval',$watchlist),true))continue;
  if((int)$u->ID===(int)$post->post_author)continue;
  $subject='BIGSHOR Wiki update: '.$post->post_title;
  $body="An article on your BIGSHOR Wiki watchlist has been updated.\n\nArticle: ".$post->post_title."\nView: ".get_permalink($post_id)."\n\nYou are receiving this because you watch this article. Manage your watchlist: ".home_url('/my-watchlist/');
  wp_mail($u->user_email,$subject,$body,array('Content-Type: text/plain; charset=UTF-8'));
 }
}
add_action('wp_after_insert_post','bsw_v3_notify_watchers',20,3);

/* Article issue reporting with nonce, basic rate limiting and private moderation queue */
add_shortcode('bigshor_wiki_report',function(){
 $article=absint($_GET['article_id']??0);if(!$article||get_post_type($article)!=='wiki_article')return '<p>Choose an article to report.</p>';
 $notice='';
 if(isset($_POST['bsw_report_submit'])){
  if(!isset($_POST['bsw_report_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_report_nonce'])),'bsw_report_'.$article))$notice='<p class="wiki-alert">Security check failed.</p>';
  elseif(get_transient('bsw_report_rate_'.(is_user_logged_in()?get_current_user_id():sanitize_text_field($_SERVER['REMOTE_ADDR']??'guest'))))$notice='<p class="wiki-alert">Please wait before sending another report.</p>';
  else{
   $reason=sanitize_key(wp_unslash($_POST['bsw_report_reason']??'other'));$details=sanitize_textarea_field(wp_unslash($_POST['bsw_report_details']??''));
   $rid=wp_insert_post(array('post_type'=>'wiki_moderation_report','post_status'=>'private','post_title'=>'Report: '.get_the_title($article).' ('.$reason.')','post_content'=>$details,'post_author'=>get_current_user_id()));
   if($rid){update_post_meta($rid,'_bsw_report_article',$article);update_post_meta($rid,'_bsw_report_reason',$reason);set_transient('bsw_report_rate_'.(is_user_logged_in()?get_current_user_id():sanitize_text_field($_SERVER['REMOTE_ADDR']??'guest')),1,3600);
    wp_mail(get_option('admin_email'),'BIGSHOR Wiki: moderation report received','A reader submitted a report. Review it here: '.admin_url('post.php?post='.$rid.'&action=edit'));
    $notice='<p class="bsw-success">Thank you. Your report has been sent to moderators.</p>';}
  }
 }
 $out=$notice.'<div class="bsw-report-form"><h2>Report an issue with '.esc_html(get_the_title($article)).'</h2><form method="post">'.wp_nonce_field('bsw_report_'.$article,'bsw_report_nonce',true,false);
 $out.='<p><select name="bsw_report_reason"><option value="inaccuracy">Factual inaccuracy</option><option value="unsourced">Missing or unreliable sources</option><option value="copyright">Copyright concern</option><option value="privacy">Privacy or personal information</option><option value="defamation">Potentially defamatory content</option><option value="vandalism">Vandalism or abuse</option><option value="other">Other</option></select></p><p><textarea name="bsw_report_details" rows="5" style="width:100%" placeholder="Describe the concern and relevant location in the article."></textarea></p><button class="wiki-button" name="bsw_report_submit" value="1">Send report</button></form></div>';
 return $out;
});

/* Multilingual article directory and language selector */
add_shortcode('bigshor_wiki_languages',function(){
 $terms=get_terms(array('taxonomy'=>'wiki_language','hide_empty'=>true));if(is_wp_error($terms)||!$terms)return '<p>No language tags have been added yet. Editors can assign a language code to each article.</p>';
 $o='<ul>';foreach($terms as $t)$o.='<li><a href="'.esc_url(get_term_link($t)).'">'.esc_html($t->name).'</a> ('.(int)$t->count.')</li>';return $o.'</ul>';
});
add_shortcode('bigshor_wiki_language_switcher',function(){
 $group=get_post_meta(get_the_ID(),'_bsw_translation_group',true);if(!$group)return '';
 $q=new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>30,'meta_key'=>'_bsw_translation_group','meta_value'=>$group,'post__not_in'=>array(get_the_ID())));
 if(!$q->have_posts())return '';
 $o='<div class="wiki-community-tools"><strong>Available languages:</strong> ';while($q->have_posts()){$q->the_post();$code=get_post_meta(get_the_ID(),'_bsw_language_code',true)?:'language';$o.='<a href="'.esc_url(get_permalink()).'">'.esc_html(strtoupper($code)).'</a> ';}wp_reset_postdata();return $o.'</div>';
});

add_action('add_meta_boxes',function(){add_meta_box('bsw_report_status','Moderation case status','bsw_report_status_box','wiki_moderation_report','side','high');});
function bsw_report_status_box($post){
 wp_nonce_field('bsw_report_status_save','bsw_report_status_nonce');
 $status=get_post_meta($post->ID,'_bsw_report_status',true)?:'open';
 echo '<p><select name="bsw_report_status" style="width:100%">';
 foreach(array('open'=>'Open','investigating'=>'Investigating','resolved'=>'Resolved','dismissed'=>'Dismissed') as $k=>$v)echo '<option value="'.esc_attr($k).'" '.selected($status,$k,false).'>'.esc_html($v).'</option>';
 echo '</select></p>';
 $article=absint(get_post_meta($post->ID,'_bsw_report_article',true));
 if($article)echo '<p><a href="'.esc_url(get_edit_post_link($article)).'">Open reported article</a></p>';
}
add_action('save_post_wiki_moderation_report',function($id){
 if(!isset($_POST['bsw_report_status_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_report_status_nonce'])),'bsw_report_status_save'))return;
 if(!current_user_can('edit_post',$id))return;
 update_post_meta($id,'_bsw_report_status',sanitize_key(wp_unslash($_POST['bsw_report_status']??'open')));
});
