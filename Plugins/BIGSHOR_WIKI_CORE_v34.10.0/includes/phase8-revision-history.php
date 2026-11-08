<?php
/**
 * Article-page Phase 7: revision history, comparison and safe restoration.
 * Uses WordPress native revisions; restoration creates a new revision and never deletes history.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase8_Revision_History')) {
final class BSW_Phase8_Revision_History {
 public static function init(){
  add_action('init',array(__CLASS__,'enable_revisions'),99);
  add_action('add_meta_boxes_wiki_article',array(__CLASS__,'box'));
  add_shortcode('bsw_article_history',array(__CLASS__,'shortcode'));
  add_shortcode('bigshor_wiki_history',array(__CLASS__,'shortcode'));
  add_action('admin_post_bsw_p8_restore_revision',array(__CLASS__,'restore'));
  add_action('admin_post_bsw_p8_compare_revision',array(__CLASS__,'compare'));
 }
 public static function enable_revisions(){ if(post_type_exists('wiki_article')) add_post_type_support('wiki_article','revisions'); }
 private static function revisions($post_id,$limit=20){
  return wp_get_post_revisions($post_id,array('posts_per_page'=>max(1,min(100,(int)$limit)),'orderby'=>'date','order'=>'DESC'));
 }
 private static function revision_actions($post_id,$revision_id){
  $compare=wp_nonce_url(admin_url('admin-post.php?action=bsw_p8_compare_revision&post_id='.(int)$post_id.'&revision_id='.(int)$revision_id),'bsw_p8_compare_'.$post_id);
  $out='<a href="'.esc_url($compare).'">Compare with current</a>';
  if(current_user_can('edit_post',$post_id)){
   $restore=wp_nonce_url(admin_url('admin-post.php?action=bsw_p8_restore_revision&post_id='.(int)$post_id.'&revision_id='.(int)$revision_id),'bsw_p8_restore_'.$post_id);
   $out.=' · <a href="'.esc_url($restore).'" onclick="return confirm(\'Restore this revision as a new saved version?\')">Restore</a>';
  }
  return $out;
 }
 public static function box(){
  add_meta_box('bsw_p8_history','BIGSHOR Revision History',array(__CLASS__,'render_box'),'wiki_article','side','default');
 }
 public static function render_box($post){
  $revs=self::revisions($post->ID,12);
  echo '<p>Compare saved versions or restore an earlier version. Restoring creates a new revision; existing history is retained.</p>';
  if(!$revs){echo '<p>No saved revisions yet. WordPress creates revisions when the article is updated.</p>';return;}
  echo '<ol class="bsw-p8-list">';
  foreach($revs as $r){
   $u=get_userdata($r->post_author);
   echo '<li><strong>'.esc_html(get_date_from_gmt($r->post_modified_gmt,get_option('date_format').' '.get_option('time_format'))).'</strong><br>'.esc_html($u?$u->display_name:'Unknown contributor').'<br>'.self::revision_actions($post->ID,$r->ID).'</li>';
  }
  echo '</ol>';
 }
 public static function shortcode($atts){
  $a=shortcode_atts(array('id'=>get_the_ID(),'limit'=>20),$atts,'bsw_article_history');
  $id=absint($a['id']); $p=get_post($id);
  if(!$p||$p->post_type!=='wiki_article'||!current_user_can('read_post',$id))return '';
  $revs=self::revisions($id,$a['limit']);
  $o='<section class="bsw-p8-history" aria-labelledby="bsw-p8-history-title"><h2 id="bsw-p8-history-title">Revision history</h2>';
  if(!$revs)return $o.'<p>No saved revisions yet.</p></section>';
  $o.='<ol>';
  foreach($revs as $r){
   $u=get_userdata($r->post_author);
   $o.='<li><strong>'.esc_html(get_date_from_gmt($r->post_modified_gmt,get_option('date_format').' '.get_option('time_format'))).'</strong> — '.esc_html($u?$u->display_name:'Unknown contributor').' — '.esc_html(wp_trim_words(wp_strip_all_tags($r->post_content),18));
   if(current_user_can('edit_post',$id))$o.='<div class="bsw-p8-actions">'.self::revision_actions($id,$r->ID).'</div>';
   $o.='</li>';
  }
  return $o.'</ol></section>';
 }
 private static function validate_request($action){
  $id=isset($_GET['post_id'])?absint($_GET['post_id']):0;
  $rid=isset($_GET['revision_id'])?absint($_GET['revision_id']):0;
  $nonce=isset($_GET['_wpnonce'])&&!is_array($_GET['_wpnonce'])?sanitize_text_field(wp_unslash($_GET['_wpnonce'])):'';
  if(!$id||!$rid||!wp_verify_nonce($nonce,'bsw_p8_'.$action.'_'.$id)||!current_user_can('edit_post',$id))wp_die('Permission or security check failed.',403);
  $p=get_post($id);$r=wp_get_post_revision($rid);
  if(!$p||$p->post_type!=='wiki_article'||!$r|| (int)$r->post_parent!==$id)wp_die('Revision does not belong to this article.',404);
  return array($p,$r);
 }
 public static function compare(){
  list($p,$r)=self::validate_request('compare');
  require_once ABSPATH.'wp-admin/includes/revision.php';
  $diff=wp_text_diff($r->post_content,$p->post_content,array('title'=>'Revision comparison'));
  $html='<div class="wrap"><h1>BIGSHOR revision comparison</h1><p>'.esc_html(get_the_title($p)).'</p>'.($diff?:'<p>No content differences detected.</p>').'<p><a href="'.esc_url(get_edit_post_link($p->ID)).'">Return to article</a></p></div>';
  wp_die($html,'Revision comparison',array('response'=>200));
 }
 public static function restore(){
  list($p,$r)=self::validate_request('restore');
  require_once ABSPATH.'wp-admin/includes/revision.php';
  $result=wp_restore_post_revision($r->ID);
  if(is_wp_error($result))wp_die(esc_html($result->get_error_message()),500);
  wp_safe_redirect(add_query_arg('bsw_revision_restored','1',get_edit_post_link($p->ID,'url')));exit;
 }
}
BSW_Phase8_Revision_History::init();
}
