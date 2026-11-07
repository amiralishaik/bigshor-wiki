<?php
if (!defined('ABSPATH')) exit;
function bsw_v3_register_roles(){
 if(!get_role('wiki_contributor')) add_role('wiki_contributor','Wiki Contributor',array('read'=>true,'upload_files'=>true));
 if(!get_role('wiki_moderator')) add_role('wiki_moderator','Wiki Moderator',array('read'=>true,'upload_files'=>true,'edit_posts'=>true,'edit_others_posts'=>true,'moderate_comments'=>true));
}
function bsw_v3_register_types(){
 $labels=array('name'=>'Wiki Articles','singular_name'=>'Wiki Article','add_new_item'=>'Add New Wiki Article','edit_item'=>'Edit Wiki Article','view_item'=>'View Article','search_items'=>'Search Wiki Articles','menu_name'=>'Wiki Articles');
 register_post_type('wiki_article',array('labels'=>$labels,'public'=>true,'show_in_rest'=>true,'has_archive'=>true,'rewrite'=>array('slug'=>'wiki','with_front'=>false),'menu_icon'=>'dashicons-book-alt','supports'=>array('title','editor','author','thumbnail','excerpt','revisions','comments','custom-fields'),'capability_type'=>'post','map_meta_cap'=>true));
 register_taxonomy('wiki_topic','wiki_article',array('labels'=>array('name'=>'Wiki Topics','singular_name'=>'Wiki Topic'),'public'=>true,'hierarchical'=>true,'show_in_rest'=>true,'rewrite'=>array('slug'=>'wiki-topic')));
 register_taxonomy('wiki_language','wiki_article',array('labels'=>array('name'=>'Article Languages','singular_name'=>'Article Language'),'public'=>true,'hierarchical'=>false,'show_in_rest'=>true,'rewrite'=>array('slug'=>'wiki-language')));
 register_post_type('wiki_edit_proposal',array('labels'=>array('name'=>'Edit Proposals','singular_name'=>'Edit Proposal'),'public'=>false,'show_ui'=>true,'show_in_menu'=>'edit.php?post_type=wiki_article','supports'=>array('title','editor','author'),'capability_type'=>'post','map_meta_cap'=>true));
 register_post_type('wiki_media',array('labels'=>array('name'=>'Wiki Media Repository','singular_name'=>'Wiki Media'),'public'=>true,'show_ui'=>true,'show_in_rest'=>true,'has_archive'=>true,'rewrite'=>array('slug'=>'wiki-media'),'menu_icon'=>'dashicons-format-gallery','supports'=>array('title','editor','author','thumbnail','custom-fields')));
 register_post_type('wiki_moderation_report',array('labels'=>array('name'=>'Moderation Reports','singular_name'=>'Moderation Report'),'public'=>false,'show_ui'=>true,'show_in_menu'=>'edit.php?post_type=wiki_article','supports'=>array('title','editor','author'),'capability_type'=>'post','map_meta_cap'=>true));
}
add_action('init','bsw_v3_register_roles',1);
function bsw_v3_activate(){
 bsw_v3_register_roles(); bsw_v3_register_types();
 $pages=array(
 'submit-article'=>array('Submit an Article','[bigshor_wiki_submit]'),
 'recent-changes'=>array('Recent Changes','[bigshor_wiki_recent limit="30"]'),
 'my-watchlist'=>array('My Watchlist','[bigshor_wiki_watchlist]'),
 'contributor-guide'=>array('Contributor Guide','[bigshor_wiki_contributor_guide]'),
 'article-history'=>array('Article History','[bigshor_wiki_history]'),
 'propose-edit'=>array('Propose an Edit','[bigshor_wiki_propose_edit]'),
 'report-article'=>array('Report an Article','[bigshor_wiki_report]'),
 'media-library'=>array('Wiki Media Library','[bigshor_wiki_media]'),
 'contribute-media'=>array('Contribute Media','[bigshor_wiki_media_submit]'),
 'wiki-languages'=>array('Browse by Language','[bigshor_wiki_languages]'),
 'all-pages'=>array('All Articles','[bigshor_wiki_all_pages]'),
 'wiki-statistics'=>array('Wiki Statistics','[bigshor_wiki_stats]'),
 'wiki-contributors'=>array('Contributors','[bigshor_wiki_contributors] [bigshor_contributor_reputation]'),
 'contributor-reputation'=>array('Contributor Reputation','[bigshor_contributor_reputation]'),
 'recent-uploads'=>array('Recent Uploads','[bigshor_wiki_recent_uploads]'),
 'orphaned-articles'=>array('Orphaned Articles','[bigshor_wiki_orphaned]'),
 'community-portal'=>array('Community Portal','<h2>Welcome to the BIGSHOR Wiki community</h2><p>Coordinate article creation, sourcing, review and maintenance.</p>[bigshor_wiki_recent limit=10]'),
 'help-center'=>array('Help Center','[bigshor_wiki_contributor_guide]'),
 'about'=>array('About BIGSHOR Wiki','<p>BIGSHOR Wiki is an independently edited knowledge resource. Articles should be verifiable, neutral and supported by reliable sources.</p>'),
 'editorial-policy'=>array('Editorial Policy','<h2>Reliable sourcing</h2><p>Use independent, reputable sources and cite claims inline. Disclose conflicts of interest and correct errors transparently.</p>'),
 'style-guide'=>array('Style Guide','<p>Write in a neutral, clear, encyclopedic style. Avoid promotional language and unsupported claims.</p>'),
 'reliable-sources'=>array('Reliable Sources','<p>Prefer independent reporting, academic publications, official filings and primary documents used with care.</p>'),
 'knowledge-graph'=>array('Knowledge Graph','<p>Explore structured people, organisations, places and their cited relationships.</p>[bigshor_entity_directory]'),
 'on-this-day'=>array('On This Day','[bigshor_on_this_day]'),
 'research-assistant'=>array('Research Assistant','[bigshor_ai_research_assistant]'),
 'contact'=>array('Contact','<p>Contact the BIGSHOR Wiki editorial team through the site administrator.</p>')
 );
 foreach($pages as $slug=>$data) if(!get_page_by_path($slug)) wp_insert_post(array('post_title'=>$data[0],'post_name'=>$slug,'post_content'=>$data[1],'post_status'=>'publish','post_type'=>'page'));
 if(function_exists('bsw_import_founder_profiles')) bsw_import_founder_profiles();
 flush_rewrite_rules();
}
function bsw_v3_deactivate(){flush_rewrite_rules();}
add_action('add_meta_boxes',function(){
 add_meta_box('bsw_v3_review','BIGSHOR Editorial Controls','bsw_v3_review_box','wiki_article','side','high');
});
function bsw_v3_review_box($post){
 wp_nonce_field('bsw_v3_review_save','bsw_v3_review_nonce');
 $status=get_post_meta($post->ID,'_bsw_review_status',true)?:'unreviewed';
 $lang=get_post_meta($post->ID,'_bsw_language_code',true)?:'en';
 $group=get_post_meta($post->ID,'_bsw_translation_group',true);
 echo '<p><label>Review status</label><select name="bsw_v3_status" style="width:100%">';
 foreach(array('unreviewed'=>'Unreviewed','in_review'=>'In review','reviewed'=>'Reviewed','needs_sources'=>'Needs sources','disputed'=>'Disputed') as $k=>$v) echo '<option value="'.esc_attr($k).'" '.selected($status,$k,false).'>'.esc_html($v).'</option>';
 echo '</select></p><p><label>Language code (e.g. en, ar, hi)</label><input style="width:100%" name="bsw_v3_language" value="'.esc_attr($lang).'"></p><p><label>Translation group ID</label><input style="width:100%" name="bsw_v3_group" value="'.esc_attr($group).'"><small>Use the same ID to associate translations of this article.</small></p>';
}
add_action('save_post_wiki_article',function($id){
 if(!isset($_POST['bsw_v3_review_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_v3_review_nonce'])),'bsw_v3_review_save'))return;
 if(defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)return;if(!current_user_can('edit_post',$id))return;
 update_post_meta($id,'_bsw_review_status',sanitize_key(wp_unslash($_POST['bsw_v3_status']??'unreviewed')));
 update_post_meta($id,'_bsw_language_code',sanitize_text_field(wp_unslash($_POST['bsw_v3_language']??'en')));
 update_post_meta($id,'_bsw_translation_group',sanitize_text_field(wp_unslash($_POST['bsw_v3_group']??'')));
 $code=sanitize_text_field(wp_unslash($_POST['bsw_v3_language']??'en'));
 if($code){$term=get_term_by('slug',sanitize_title($code),'wiki_language');if(!$term){$created=wp_insert_term(strtoupper($code), 'wiki_language',array('slug'=>sanitize_title($code)));if(!is_wp_error($created))$term=get_term($created['term_id'],'wiki_language');}if($term&&!is_wp_error($term))wp_set_object_terms($id,array((int)$term->term_id),'wiki_language',false);}
});
add_filter('manage_wiki_article_posts_columns',function($c){$c['bsw_status']='Review';$c['bsw_lang']='Language';return $c;});
add_action('manage_wiki_article_posts_custom_column',function($col,$id){if($col==='bsw_status')echo esc_html(get_post_meta($id,'_bsw_review_status',true)?:'Unreviewed');if($col==='bsw_lang')echo esc_html(get_post_meta($id,'_bsw_language_code',true)?:'en');},10,2);
add_filter('pre_get_posts',function($q){if(!is_admin()&&$q->is_main_query()&&$q->is_search())$q->set('post_type',array('post','page','wiki_article','wiki_media'));});
add_filter('the_content','bsw_v3_auto_toc',12);
function bsw_v3_auto_toc($content){
 if(!is_singular('wiki_article')||!in_the_loop()||!is_main_query())return $content;
 preg_match_all('/<h([2-3])([^>]*)>(.*?)<\\/h[2-3]>/is',$content,$ms,PREG_SET_ORDER);if(count($ms)<3)return $content;
 $items=array();$i=0;
 foreach($ms as $m){$label=trim(wp_strip_all_tags($m[3]));$id='bsw-section-'.(++$i);
 if(preg_match("/id=\"([^\"]+)\"/i",$m[2],$f))$id=$f[1];else $content=str_replace($m[0],'<h'.$m[1].$m[2].' id="'.esc_attr($id).'">'.$m[3].'</h'.$m[1].'>',$content);
 $items[]='<li class="toc-level-'.$m[1].'"><a href="#'.esc_attr($id).'">'.esc_html($label).'</a></li>';}
 return '<nav class="wiki-toc" aria-label="Contents"><div class="wiki-toc-title">Contents</div><ol>'.implode('',$items).'</ol></nav>'.$content;
}
function bsw_v3_citation($atts){$a=shortcode_atts(array('url'=>'','title'=>'Source','accessed'=>''),$atts);if(!$a['url'])return '';return '<sup class="bsw-citation"><a href="'.esc_url($a['url']).'" rel="nofollow noopener" target="_blank">['.esc_html($a['title']).']</a></sup>';}
add_shortcode('wiki_ref','bsw_v3_citation');
add_shortcode('wiki_infobox',function($atts,$content=null){$a=shortcode_atts(array('title'=>'Article facts','image'=>''),$atts);$img=$a['image']?'<img src="'.esc_url($a['image']).'" alt="'.esc_attr($a['title']).'">':'';return '<aside class="wiki-infobox"><div class="wiki-infobox-title">'.esc_html($a['title']).'</div><div class="wiki-infobox-body">'.$img.do_shortcode($content).'</div></aside>';});
add_shortcode('wiki_fact',function($atts){$a=shortcode_atts(array('label'=>'','value'=>''),$atts);return '<table><tr><td>'.esc_html($a['label']).'</td><td>'.wp_kses_post($a['value']).'</td></tr></table>';});
add_action('admin_notices',function(){if(current_user_can('manage_options'))echo '<div class="notice notice-info"><p><strong>BIGSHOR Wiki v4:</strong> Configure backups, HTTPS, SMTP, security, anti-spam and editorial policies. Test all community workflows before inviting public contributors.</p></div>';});

add_filter('wp_revisions_to_keep',function($num,$post){
 if($post instanceof WP_Post && $post->post_type==='wiki_article')return 100;
 return $num;
},10,2);
