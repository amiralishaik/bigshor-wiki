<?php
if (!defined('ABSPATH')) exit;
/** BIGSHOR Wiki Phase 2: community, roles, editorial workflow and discussions. */
if (!class_exists('BSW_Phase2_Community')) {
final class BSW_Phase2_Community {
 public static function init(){
  add_action('init',array(__CLASS__,'roles'),2);
  add_action('init',array(__CLASS__,'register_talk_type'),9);
  add_action('add_meta_boxes',array(__CLASS__,'review_box'));
  add_action('save_post_wiki_article',array(__CLASS__,'save_review_meta'),30,2);
  add_filter('manage_wiki_article_posts_columns',array(__CLASS__,'columns'));
  add_action('manage_wiki_article_posts_custom_column',array(__CLASS__,'column'),10,2);
  add_shortcode('bigshor_community_dashboard',array(__CLASS__,'dashboard'));
  add_shortcode('bigshor_contributor_profile',array(__CLASS__,'profile'));
  add_shortcode('bigshor_article_talk',array(__CLASS__,'talk'));
  add_shortcode('bigshor_editorial_queue',array(__CLASS__,'queue'));
  add_action('admin_post_bsw_phase2_review',array(__CLASS__,'review_action'));
  add_action('admin_post_bsw_phase2_profile',array(__CLASS__,'save_profile'));
  add_action('admin_post_bsw_phase2_talk',array(__CLASS__,'save_talk'));
  add_action('transition_post_status',array(__CLASS__,'notify_author'),10,3);
 }
 public static function roles(){
  if(!get_role('wiki_contributor')) add_role('wiki_contributor','Wiki Contributor',array('read'=>true,'upload_files'=>true));
  if(!get_role('wiki_reviewer')) add_role('wiki_reviewer','Wiki Reviewer',array('read'=>true,'edit_posts'=>true,'edit_published_posts'=>true,'edit_others_posts'=>true,'publish_posts'=>true,'upload_files'=>true,'moderate_comments'=>true));
  if(!get_role('wiki_moderator')) add_role('wiki_moderator','Wiki Moderator',array('read'=>true,'edit_posts'=>true,'edit_published_posts'=>true,'edit_others_posts'=>true,'publish_posts'=>true,'delete_posts'=>true,'delete_others_posts'=>true,'upload_files'=>true,'moderate_comments'=>true));
 }
 public static function register_talk_type(){
  register_post_type('wiki_talk',array('labels'=>array('name'=>'Article Discussions','singular_name'=>'Discussion'),'public'=>false,'show_ui'=>true,'show_in_menu'=>'edit.php?post_type=wiki_article','supports'=>array('title','editor','author','comments'),'capability_type'=>'post','map_meta_cap'=>true));
 }
 private static function can_review(){return current_user_can('edit_others_posts')||current_user_can('manage_options')||in_array('wiki_reviewer',(array)wp_get_current_user()->roles,true)||in_array('wiki_moderator',(array)wp_get_current_user()->roles,true);}
 public static function review_box(){add_meta_box('bsw_p2_review','BIGSHOR Review Workflow',array(__CLASS__,'render_review_box'),'wiki_article','side','high');}
 public static function render_review_box($post){
  wp_nonce_field('bsw_p2_review_'.$post->ID,'bsw_p2_review_nonce');
  $status=get_post_meta($post->ID,'_bsw_p2_workflow',true)?:'draft';$assigned=(int)get_post_meta($post->ID,'_bsw_p2_reviewer',true);$note=get_post_meta($post->ID,'_bsw_p2_review_note',true);
  echo '<p><label for="bsw_p2_workflow">Editorial status</label><select id="bsw_p2_workflow" name="bsw_p2_workflow" style="width:100%">';
  foreach(array('draft'=>'Draft','submitted'=>'Submitted for review','in_review'=>'In review','changes_requested'=>'Changes requested','approved'=>'Approved','rejected'=>'Rejected') as $k=>$v) echo '<option value="'.esc_attr($k).'" '.selected($status,$k,false).'>'.esc_html($v).'</option>';
  echo '</select></p><p><label for="bsw_p2_reviewer">Assigned reviewer</label><select id="bsw_p2_reviewer" name="bsw_p2_reviewer" style="width:100%"><option value="0">Unassigned</option>';
  $users=get_users(array('role__in'=>array('administrator','editor','wiki_reviewer','wiki_moderator')));foreach($users as $u)echo '<option value="'.(int)$u->ID.'" '.selected($assigned,$u->ID,false).'>'.esc_html($u->display_name).'</option>';
  echo '</select></p><p><label for="bsw_p2_review_note">Reviewer notes</label><textarea id="bsw_p2_review_note" name="bsw_p2_review_note" rows="4" style="width:100%">'.esc_textarea($note).'</textarea></p>';
  if(!self::can_review()) echo '<p><small>Only assigned reviewers, editors and moderators can make editorial decisions.</small></p>';
 }
 public static function save_review_meta($post_id,$post){
  if(defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)return;if(wp_is_post_revision($post_id)||!isset($_POST['bsw_p2_review_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_p2_review_nonce'])),'bsw_p2_review_'.$post_id))return;
  if(!current_user_can('edit_post',$post_id))return;
  $status=sanitize_key(wp_unslash($_POST['bsw_p2_workflow']??'draft'));$allowed=array('draft','submitted','in_review','changes_requested','approved','rejected');if(!in_array($status,$allowed,true))$status='draft';
  if(!self::can_review() && in_array($status,array('approved','rejected','in_review'),true))$status='submitted';
  update_post_meta($post_id,'_bsw_p2_workflow',$status);
  if(self::can_review()){
   update_post_meta($post_id,'_bsw_p2_reviewer',absint($_POST['bsw_p2_reviewer']??0));
   update_post_meta($post_id,'_bsw_p2_review_note',sanitize_textarea_field(wp_unslash($_POST['bsw_p2_review_note']??'')));
   update_post_meta($post_id,'_bsw_p2_reviewed_by',get_current_user_id());update_post_meta($post_id,'_bsw_p2_reviewed_at',current_time('mysql'));
  }
 }
 public static function columns($c){$c['bsw_workflow']='Editorial workflow';$c['bsw_reviewer']='Reviewer';return $c;}
 public static function column($col,$id){if($col==='bsw_workflow')echo esc_html(ucwords(str_replace('_',' ',get_post_meta($id,'_bsw_p2_workflow',true)?:'draft')));if($col==='bsw_reviewer'){$u=get_user_by('id',(int)get_post_meta($id,'_bsw_p2_reviewer',true));echo $u?esc_html($u->display_name):'—';}}
 public static function dashboard(){
  if(!is_user_logged_in())return '<p>Please <a href="'.esc_url(wp_login_url(get_permalink())).'">log in</a> to view your contributor dashboard.</p>';
  $uid=get_current_user_id();$q=new WP_Query(array('post_type'=>'wiki_article','post_status'=>array('publish','pending','draft','private'),'author'=>$uid,'posts_per_page'=>50,'orderby'=>'modified','order'=>'DESC'));
  $out='<section class="bsw-p2-dashboard"><h2>Contributor dashboard</h2><p>Welcome, '.esc_html(wp_get_current_user()->display_name).'.</p><p><strong>'.(int)$q->found_posts.'</strong> article contributions</p><h3>Your articles</h3>';
  if($q->have_posts()){$out.='<ul>';while($q->have_posts()){$q->the_post();$id=get_the_ID();$st=get_post_meta($id,'_bsw_p2_workflow',true)?:get_post_status($id);$out.='<li><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a> — '.esc_html(ucwords(str_replace('_',' ',$st))).'</li>';}$out.='</ul>';wp_reset_postdata();}else $out.='<p>No contributions yet.</p>';
  $out.='<p><a href="'.esc_url(home_url('/submit-article/')).'">Submit an article</a> · <a href="'.esc_url(home_url('/my-watchlist/')).'">Watchlist</a></p></section>';return $out;
 }
 public static function profile($atts){$a=shortcode_atts(array('user'=>'0'),$atts);$uid=absint($a['user']);if(!$uid&&isset($_GET['contributor']))$uid=absint($_GET['contributor']);if(!$uid)$uid=get_current_user_id();$u=$uid?get_user_by('id',$uid):false;if(!$u)return '<p>Contributor profile not found.</p>';
  $bio=get_user_meta($uid,'_bsw_public_bio',true);$count=count_user_posts($uid,'wiki_article',true);$out='<div class="bsw-p2-profile">'.get_avatar($uid,88).'<h2>'.esc_html($u->display_name).'</h2><p>'.esc_html($bio?:'BIGSHOR Wiki contributor').'</p><p><strong>'.number_format_i18n($count).'</strong> published articles</p>';
  if(get_current_user_id()===$uid){$out.='<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw_phase2_profile">'.wp_nonce_field('bsw_phase2_profile','bsw_profile_nonce',true,false).'<label>Public biography<textarea name="bsw_public_bio" rows="4" maxlength="500">'.esc_textarea($bio).'</textarea></label><p><button type="submit">Save profile</button></p></form>';}
  return $out.'</div>';
 }
 public static function save_profile(){if(!is_user_logged_in()||!isset($_POST['bsw_profile_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_profile_nonce'])),'bsw_phase2_profile'))wp_die('Security check failed.');update_user_meta(get_current_user_id(),'_bsw_public_bio',sanitize_textarea_field(wp_unslash($_POST['bsw_public_bio']??'')));wp_safe_redirect(wp_get_referer()?:home_url('/'));exit;}
 public static function talk($atts){$a=shortcode_atts(array('article'=>'0'),$atts);$article=absint($a['article']);if(!$article)$article=get_queried_object_id();if(get_post_type($article)!=='wiki_article')return '<p>Article discussion is available on individual wiki articles.</p>';
  $q=new WP_Query(array('post_type'=>'wiki_talk','post_status'=>'publish','posts_per_page'=>50,'meta_key'=>'_bsw_talk_article','meta_value'=>$article));$out='<section class="bsw-p2-talk"><h2>Article discussion: '.esc_html(get_the_title($article)).'</h2>';
  if($q->have_posts()){while($q->have_posts()){$q->the_post();$out.='<article><h3>'.esc_html(get_the_title()).'</h3><p>'.wp_kses_post(wpautop(get_the_content())).'</p><small>Posted by '.esc_html(get_the_author()).' · '.esc_html(get_the_date()).'</small></article>'; }wp_reset_postdata();}else $out.='<p>No discussion topics yet.</p>';
  if(is_user_logged_in())$out.='<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw_phase2_talk"><input type="hidden" name="article_id" value="'.(int)$article.'">'.wp_nonce_field('bsw_phase2_talk_'.$article,'bsw_talk_nonce',true,false).'<p><label>Topic title<input required name="talk_title" maxlength="120"></label></p><p><label>Message<textarea required name="talk_content" rows="5"></textarea></label></p><button type="submit">Start discussion</button></form>';else $out.='<p><a href="'.esc_url(wp_login_url(get_permalink($article))).'">Log in</a> to join the discussion.</p>';
  return $out.'</section>';
 }
 public static function save_talk(){ $article=absint($_POST['article_id']??0);if(!is_user_logged_in()||get_post_type($article)!=='wiki_article'||!isset($_POST['bsw_talk_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_talk_nonce'])),'bsw_phase2_talk_'.$article))wp_die('Security check failed.');$title=sanitize_text_field(wp_unslash($_POST['talk_title']??''));$content=wp_kses_post(wp_unslash($_POST['talk_content']??''));if(!$title||strlen(wp_strip_all_tags($content))<10)wp_die('Please provide a title and a meaningful discussion message.');$id=wp_insert_post(array('post_type'=>'wiki_talk','post_status'=>'publish','post_title'=>$title,'post_content'=>$content,'post_author'=>get_current_user_id()),true);if(!is_wp_error($id))update_post_meta($id,'_bsw_talk_article',$article);wp_safe_redirect(get_permalink($article).'#bsw-talk');exit; }
 public static function queue(){if(!self::can_review())return '<p>You do not have permission to view the editorial queue.</p>';$q=new WP_Query(array('post_type'=>'wiki_article','post_status'=>array('publish','pending','draft'),'posts_per_page'=>50,'meta_query'=>array(array('key'=>'_bsw_p2_workflow','value'=>array('submitted','in_review','changes_requested'),'compare'=>'IN')),'orderby'=>'modified','order'=>'ASC'));$out='<section class="bsw-p2-queue"><h2>Editorial review queue</h2>';if(!$q->have_posts())return $out.'<p>No articles are waiting for review.</p></section>';$out.='<ul>';while($q->have_posts()){$q->the_post();$id=get_the_ID();$out.='<li><a href="'.esc_url(get_edit_post_link($id)).'">'.esc_html(get_the_title()).'</a> — '.esc_html(get_post_meta($id,'_bsw_p2_workflow',true)).'</li>'; }wp_reset_postdata();return $out.'</ul></section>';}
 public static function review_action(){ $id=absint($_POST['article_id']??0);if(!self::can_review()||!$id||!isset($_POST['_wpnonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])),'bsw_phase2_decision_'.$id))wp_die('Not authorised.');$decision=sanitize_key(wp_unslash($_POST['decision']??''));if(!in_array($decision,array('in_review','changes_requested','approved','rejected'),true))wp_die('Invalid decision.');update_post_meta($id,'_bsw_p2_workflow',$decision);update_post_meta($id,'_bsw_p2_reviewed_by',get_current_user_id());update_post_meta($id,'_bsw_p2_reviewed_at',current_time('mysql'));wp_safe_redirect(get_edit_post_link($id,'url'));exit; }
 public static function notify_author($new,$old,$post){if($post->post_type!=='wiki_article'||$new===$old||!in_array($new,array('publish','pending'),true))return;$author=get_user_by('id',$post->post_author);if(!$author)return;$workflow=get_post_meta($post->ID,'_bsw_p2_workflow',true);if($new==='publish'||$workflow==='changes_requested'||$workflow==='rejected')wp_mail($author->user_email,'BIGSHOR Wiki article status: '.$post->post_title,"Your article status has changed to: ".($workflow?:$new)."\n\nView: ".get_permalink($post->ID));}
}
BSW_Phase2_Community::init();
}
