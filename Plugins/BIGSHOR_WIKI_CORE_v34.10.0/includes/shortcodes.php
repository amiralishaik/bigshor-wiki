<?php
if (!defined('ABSPATH')) exit;
function bsw_v3_list($atts){
 $a=shortcode_atts(array('limit'=>10,'orderby'=>'modified','topic'=>''),$atts);
 $args=array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>max(1,min(50,(int)$a['limit'])),'orderby'=>sanitize_key($a['orderby']),'order'=>'DESC');
 if($a['topic'])$args['tax_query']=array(array('taxonomy'=>'wiki_topic','field'=>'slug','terms'=>sanitize_title($a['topic'])));
 $q=new WP_Query($args);if(!$q->have_posts())return '<p>No published articles yet.</p>';
 $o='<ul class="wiki-list">';while($q->have_posts()){$q->the_post();$o.='<li><h3><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a></h3><div class="wiki-meta">Updated '.esc_html(get_the_modified_date()).'</div><div class="excerpt">'.esc_html(get_the_excerpt()).'</div></li>';}wp_reset_postdata();return $o.'</ul>';
}
add_shortcode('bigshor_wiki_recent','bsw_v3_list');
add_shortcode('bigshor_wiki_featured',function($atts){$a=shortcode_atts(array('limit'=>5),$atts);$q=new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>max(1,min(20,(int)$a['limit'])),'meta_key'=>'_bsw_featured','meta_value'=>'1'));if(!$q->have_posts())return bsw_v3_list($a);$o='<ul>';while($q->have_posts()){$q->the_post();$o.='<li><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a></li>';}wp_reset_postdata();return $o.'</ul>';});
add_shortcode('bigshor_wiki_topics',function(){$terms=get_terms(array('taxonomy'=>'wiki_topic','hide_empty'=>true));if(is_wp_error($terms)||!$terms)return '<p>No topics yet.</p>';$o='<ul>';foreach($terms as $t)$o.='<li><a href="'.esc_url(get_term_link($t)).'">'.esc_html($t->name).'</a> ('.(int)$t->count.')</li>';return $o.'</ul>';});
add_shortcode('bigshor_wiki_random',function(){$q=new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>1,'orderby'=>'rand'));if(!$q->have_posts())return ''; $q->the_post();$o='<a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a>';wp_reset_postdata();return $o;});
add_shortcode('bigshor_wiki_related',function($atts){if(!is_singular('wiki_article'))return '';$terms=wp_get_object_terms(get_the_ID(),'wiki_topic',array('fields'=>'ids'));if(is_wp_error($terms)||!$terms)return '';$a=shortcode_atts(array('limit'=>5),$atts);$q=new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>max(1,min(10,(int)$a['limit'])),'post__not_in'=>array(get_the_ID()),'tax_query'=>array(array('taxonomy'=>'wiki_topic','field'=>'term_id','terms'=>$terms))));if(!$q->have_posts())return '';$o='<section class="wiki-panel"><h2>Related articles</h2><ul>';while($q->have_posts()){$q->the_post();$o.='<li><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a></li>';}wp_reset_postdata();return $o.'</ul></section>';});
add_shortcode('bigshor_wiki_contributor_guide',function(){return '<div class="bsw-guide"><h2>How to contribute</h2><ol><li>Search first to avoid duplicate topics.</li><li>Write neutrally and use reliable independent sources.</li><li>Cite claims, dates and quotations.</li><li>Do not publish private personal information or defamatory claims.</li><li>Submit new articles and edits for review.</li><li>Only upload media you own or are licensed to use.</li></ol></div>';});
add_shortcode('bigshor_wiki_submit',function(){
 if(!is_user_logged_in())return '<p>Please <a href="'.esc_url(wp_login_url(get_permalink())).'">log in</a> to submit an article.</p>';
 $notice='';
 if(isset($_POST['bsw_submit_article'])){
  if(!isset($_POST['bsw_submit_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_submit_nonce'])),'bsw_submit_article'))$notice='<p class="wiki-alert">Security check failed.</p>';
  else{
   $title=sanitize_text_field(wp_unslash($_POST['bsw_title']??''));$body=wp_kses_post(wp_unslash($_POST['bsw_body']??''));$summary=sanitize_textarea_field(wp_unslash($_POST['bsw_summary']??''));
   if(strlen($title)<5||strlen(wp_strip_all_tags($body))<100)$notice='<p class="wiki-alert">Provide a descriptive title and at least 100 characters of article content.</p>';
   elseif(get_transient('bsw_submit_rate_'.get_current_user_id()))$notice='<p class="wiki-alert">Please wait before submitting another article.</p>';
   else{$id=wp_insert_post(array('post_type'=>'wiki_article','post_title'=>$title,'post_content'=>$body,'post_excerpt'=>$summary,'post_status'=>'pending','post_author'=>get_current_user_id()),true);if(!is_wp_error($id)){set_transient('bsw_submit_rate_'.get_current_user_id(),1,60);$notice='<p class="bsw-success">Your article has been submitted for editorial review. Reference #'.(int)$id.'.</p>';}else $notice='<p class="wiki-alert">Submission failed. Contact the site editor.</p>';}
  }
 }
 $o=$notice.'<form class="bsw-v3-form" method="post">'.wp_nonce_field('bsw_submit_article','bsw_submit_nonce',true,false);
 $o.='<p><label>Article title<input required minlength="5" name="bsw_title"></label></p><p><label>Summary<textarea name="bsw_summary" rows="2"></textarea></label></p><p><label>Article content<textarea required minlength="100" name="bsw_body" rows="16" placeholder="Use headings, neutral language and source citations."></textarea></label></p><p class="wiki-meta">Submissions remain private until approved by an editor.</p><button class="wiki-button" name="bsw_submit_article" value="1">Submit for editorial review</button></form>';
 return $o;
});
