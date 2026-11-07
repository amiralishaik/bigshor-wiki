<?php
if (!defined('ABSPATH')) exit;
/** BIGSHOR Wiki v10: additive features. All content remains in WP tables; no destructive migrations. */

// Additional taxonomy for wiki namespaces and nested article categories.
add_action('init', function(){
    $platform=bsw_platform_settings();
    if($platform['enable_namespaces']==='1') register_taxonomy('wiki_namespace', array('wiki_article','wiki_entity'), array(
        'labels'=>array('name'=>'Wiki namespaces','singular_name'=>'Namespace'), 'public'=>true,
        'show_ui'=>true,'show_in_rest'=>true,'hierarchical'=>true,'rewrite'=>array('slug'=>'wiki-namespace')
    ));
    if($platform['enable_talk_pages']==='1') register_post_type('wiki_talk', array(
        'labels'=>array('name'=>'Talk pages','singular_name'=>'Talk page','add_new_item'=>'Create talk page'),
        'public'=>true,'show_ui'=>true,'show_in_rest'=>true,'has_archive'=>true,
        'rewrite'=>array('slug'=>'talk'),'menu_icon'=>'dashicons-format-chat',
        'supports'=>array('title','editor','author','comments','revisions')
    ));
    register_post_type('wiki_template', array(
        'labels'=>array('name'=>'Wiki templates','singular_name'=>'Wiki template'),
        'public'=>false,'show_ui'=>true,'show_in_rest'=>true,'supports'=>array('title','editor','revisions')
    ));
    register_post_type('wiki_audit', array(
        'labels'=>array('name'=>'Wiki audit log','singular_name'=>'Audit event'),
        'public'=>false,'show_ui'=>true,'show_in_menu'=>'edit.php?post_type=wiki_article',
        'supports'=>array('title','editor','author')
    ));
}, 12);

// Persistent, non-destructive settings and migration marker. Never delete or rewrite content on theme updates.
add_action('after_switch_theme', function(){
    if (!get_option('bsw_schema_version')) add_option('bsw_schema_version','1.0.0','',false);
    if (!get_option('bsw_platform_settings')) add_option('bsw_platform_settings', array(
        'enable_talk_pages'=>'1','enable_namespaces'=>'1','enable_citations'=>'1','enable_google_source'=>'1',
        'google_source_domain'=>'bigshor.com','enable_audit_log'=>'1','max_revisions'=>'100',
        'require_citations'=>'0','require_coi_disclosure'=>'1','allow_public_comments'=>'0',
        'media_storage_mode'=>'wordpress','external_storage_note'=>'Configure an approved object-storage/CDN integration separately.'
    ), '', false);
    flush_rewrite_rules();
}, 20);

function bsw_platform_settings(){
    $defaults=array('enable_talk_pages'=>'1','enable_namespaces'=>'1','enable_citations'=>'1','enable_google_source'=>'1','google_source_domain'=>'bigshor.com','enable_audit_log'=>'1','max_revisions'=>'100','require_citations'=>'0','require_coi_disclosure'=>'1','allow_public_comments'=>'0','media_storage_mode'=>'wordpress','external_storage_note'=>'Configure an approved object-storage/CDN integration separately.');
    return wp_parse_args(get_option('bsw_platform_settings',array()),$defaults);
}

add_action('admin_menu', function(){
    add_submenu_page('bigshor-wiki-settings','Advanced Platform Controls','Advanced Controls','manage_options','bsw-advanced-controls','bsw_advanced_controls_page');
    add_submenu_page('bigshor-wiki-settings','Data portability','Import / Export','manage_options','bsw-data-portability','bsw_data_portability_page');
});
function bsw_advanced_controls_page(){
    if(!current_user_can('manage_options'))return;
    $s=bsw_platform_settings();
    if(isset($_POST['bsw_save_advanced']) && check_admin_referer('bsw_save_advanced')){
        $raw=wp_unslash($_POST['bsw']??array());
        foreach(array('enable_talk_pages','enable_namespaces','enable_citations','enable_google_source','enable_audit_log','require_citations','require_coi_disclosure','allow_public_comments') as $k)$s[$k]=isset($raw[$k])?'1':'0';
        $s['google_source_domain']=sanitize_text_field($raw['google_source_domain']??'bigshor.com');
        $s['max_revisions']=min(500,max(10,absint($raw['max_revisions']??100)));
        $s['media_storage_mode']='wordpress'; // Never pretend a remote provider is connected.
        update_option('bsw_platform_settings',$s,false);
        echo '<div class="notice notice-success"><p>Advanced platform settings saved.</p></div>';
    }
    $s=bsw_platform_settings();
    echo '<div class="wrap bsw-admin"><h1>BIGSHOR Wiki — Advanced Controls</h1><p>Configure additive platform behaviour. Content and settings are stored independently of theme files.</p><form method="post">';wp_nonce_field('bsw_save_advanced');
    $fields=array('enable_talk_pages'=>'Enable article talk pages','enable_namespaces'=>'Enable wiki namespaces','enable_citations'=>'Enable structured source/citation panel','enable_google_source'=>'Show Google Preferred Source link after article lead image','enable_audit_log'=>'Record editorial audit events','require_citations'=>'Require source references on public article submissions','require_coi_disclosure'=>'Require contributor conflict-of-interest disclosure','allow_public_comments'=>'Allow public talk-page comments');
    echo '<table class="form-table">';foreach($fields as $k=>$label)echo '<tr><th>'.esc_html($label).'</th><td><label><input type="checkbox" name="bsw['.$k.']" value="1" '.checked($s[$k],'1',false).'> Enabled</label></td></tr>';
    echo '<tr><th>Google source domain</th><td><input class="regular-text" name="bsw[google_source_domain]" value="'.esc_attr($s['google_source_domain']).'"><p class="description">Used to construct the Google Preferred Sources URL. It does not guarantee Google source approval.</p></td></tr>';
    echo '<tr><th>Maximum revisions retained</th><td><input type="number" min="10" max="500" name="bsw[max_revisions]" value="'.(int)$s['max_revisions'].'"></td></tr>';
    echo '<tr><th>Media storage</th><td><strong>WordPress Media Library</strong><p class="description">Object storage/CDN is an infrastructure integration and is not activated by a theme setting alone.</p></td></tr></table><p><button class="button button-primary" name="bsw_save_advanced" value="1">Save advanced settings</button></p></form></div>';
}

// Structured citation metadata, fact-check status and conflict-of-interest disclosure.
add_action('add_meta_boxes',function(){
    add_meta_box('bsw_source_register','Sources & fact-checking','bsw_source_register_box','wiki_article','normal','high');
    add_meta_box('bsw_integrity','Editorial integrity','bsw_integrity_box','wiki_article','side','high');
    if(bsw_platform_settings()['enable_talk_pages']==='1') add_meta_box('bsw_talk_link','Article discussion','bsw_talk_link_box','wiki_article','side','default');
});
function bsw_source_register_box($post){
    wp_nonce_field('bsw_source_register_save','bsw_source_nonce');
    $sources=get_post_meta($post->ID,'_bsw_sources_json',true);
    echo '<p>Enter one JSON object per line with <code>title</code>, <code>url</code>, <code>publisher</code>, <code>date</code> and optional <code>accessed</code>. Each source should support identifiable claims in the article.</p><textarea style="width:100%;min-height:150px;font-family:monospace" name="bsw_sources_json">'.esc_textarea($sources).'</textarea>';
}
function bsw_integrity_box($post){
    wp_nonce_field('bsw_integrity_save','bsw_integrity_nonce');
    $coi=get_post_meta($post->ID,'_bsw_coi_disclosure',true);$status=get_post_meta($post->ID,'_bsw_factcheck_status',true)?:'not_checked';
    echo '<p><label>Fact-check status<br><select name="bsw_factcheck_status" style="width:100%">';foreach(array('not_checked'=>'Not checked','in_progress'=>'In progress','verified'=>'Reviewed / corroborated','needs_sources'=>'Needs sources','disputed'=>'Disputed') as $k=>$v)echo '<option value="'.esc_attr($k).'" '.selected($status,$k,false).'>'.esc_html($v).'</option>';echo '</select></label></p>';
    echo '<p><label>Conflict of interest / paid contribution disclosure<br><textarea name="bsw_coi_disclosure" rows="4" style="width:100%">'.esc_textarea($coi).'</textarea></label></p><p class="description">Do not mark content verified unless claims have been checked against reliable sources.</p>';
}
function bsw_talk_link_box($post){$id=(int)get_post_meta($post->ID,'_bsw_talk_page_id',true);$url=$id?get_permalink($id):'';echo $url?'<p><a href="'.esc_url($url).'" target="_blank" rel="noopener">Open discussion page</a></p>':'<p>Discussion page is created automatically when first requested.</p>';echo '<p><a href="'.esc_url(wp_nonce_url(add_query_arg(array('action'=>'bsw_create_talk','bsw_create_talk'=>$post->ID),admin_url('admin-post.php')),'bsw_talk_'.$post->ID)).'">Create / open talk page</a></p> '; }
add_action('save_post_wiki_article',function($id){
    if(defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE||!current_user_can('edit_post',$id))return;
    if(isset($_POST['bsw_source_nonce'])&&wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_source_nonce'])),'bsw_source_register_save'))update_post_meta($id,'_bsw_sources_json',sanitize_textarea_field(wp_unslash($_POST['bsw_sources_json']??'')));
    if(isset($_POST['bsw_integrity_nonce'])&&wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_integrity_nonce'])),'bsw_integrity_save')){update_post_meta($id,'_bsw_factcheck_status',sanitize_key(wp_unslash($_POST['bsw_factcheck_status']??'not_checked')));update_post_meta($id,'_bsw_coi_disclosure',sanitize_textarea_field(wp_unslash($_POST['bsw_coi_disclosure']??'')));}
},40);

function bsw_audit_event($event,$object_id=0,$details=''){
    $s=bsw_platform_settings();if($s['enable_audit_log']!=='1'||!post_type_exists('wiki_audit'))return;
    $user=wp_get_current_user();$title=sanitize_text_field($event.' — '.($object_id?'#'.$object_id:''));
    wp_insert_post(array('post_type'=>'wiki_audit','post_status'=>'private','post_title'=>$title,'post_content'=>sanitize_textarea_field($details),'post_author'=>get_current_user_id(),'meta_input'=>array('_bsw_event'=>$event,'_bsw_object_id'=>(int)$object_id,'_bsw_ip_hash'=>wp_hash(sanitize_text_field($_SERVER['REMOTE_ADDR']??'')),'_bsw_event_time'=>current_time('mysql'))));
}
add_action('transition_post_status',function($new,$old,$post){if($post instanceof WP_Post&&$post->post_type==='wiki_article'&&$new!==$old)bsw_audit_event('article_status_'.$old.'_to_'.$new,$post->ID,'Article status changed.');},10,3);
add_action('post_updated',function($id,$after,$before){if($after instanceof WP_Post&&$after->post_type==='wiki_article'&&$after->post_content!==$before->post_content)bsw_audit_event('article_content_updated',$id,'Article content was updated.');},10,3);

// Page protection: contributors cannot alter protected wiki articles; editors/admins retain control.
add_filter('map_meta_cap',function($caps,$cap,$user_id,$args){
    if(in_array($cap,array('edit_post','delete_post'),true)&&!empty($args[0])){
        $id=(int)$args[0];if(get_post_type($id)==='wiki_article'&&get_post_meta($id,'_bsw_protected',true)==='1'){
            $u=get_userdata($user_id);if($u&&!user_can($u,'manage_options')&&!user_can($u,'edit_others_posts'))return array('do_not_allow');
        }
    }
    return $caps;
},10,4);
// Article protection UI and nonce-checked save handler are registered in discovery.php.
add_filter('wp_revisions_to_keep',function($num,$post){if($post instanceof WP_Post&&in_array($post->post_type,array('wiki_article','wiki_entity','wiki_talk'),true))return (int)bsw_platform_settings()['max_revisions'];return $num;},10,2);

// Talk-page creation with capability + nonce validation.
add_action('admin_post_bsw_create_talk',function(){
    $id=absint($_GET['bsw_create_talk']??0);if(bsw_platform_settings()['enable_talk_pages']!=='1'||!$id||!current_user_can('edit_post',$id)||!check_admin_referer('bsw_talk_'.$id))wp_die('Not authorised.');
    $existing=(int)get_post_meta($id,'_bsw_talk_page_id',true);if(!$existing||get_post_status($existing)===false){$existing=wp_insert_post(array('post_type'=>'wiki_talk','post_status'=>'publish','post_title'=>'Talk: '.get_the_title($id),'post_content'=>'<p>Use this page to discuss improvements, sourcing, neutrality and editorial questions about this article. Keep discussion constructive and evidence-based.</p>','post_author'=>get_current_user_id()));if(!is_wp_error($existing))update_post_meta($id,'_bsw_talk_page_id',$existing);}
    wp_safe_redirect($existing?get_permalink($existing):admin_url('edit.php?post_type=wiki_article'));exit;
});

// Google Preferred Sources button directly after first article image, if enabled.
add_filter('the_content',function($content){
    if(!is_singular('wiki_article')||!in_the_loop()||!is_main_query())return $content;
    $s=bsw_platform_settings();if($s['enable_google_source']!=='1')return $content;
    $domain=preg_replace('/[^A-Za-z0-9.\-]/','',$s['google_source_domain']);if(!$domain)return $content;
    $url='https://www.google.com/preferences/source?q='.rawurlencode($domain);
    $button='<p class="bsw-google-source"><a href="'.esc_url($url).'" target="_blank" rel="noopener noreferrer">Google Preferred Source</a></p>';
    if(preg_match('/(<figure\b.*?<\/figure>)/is',$content,$m))return preg_replace('/'.preg_quote($m[1],'/').'/',$m[1].$button,$content,1);
    if(preg_match('/(<img\b[^>]*>)/i',$content,$m))return preg_replace('/'.preg_quote($m[1],'/').'/',$m[1].$button,$content,1);
    return $content;
},18);

// Source register on public article pages. The source data is escaped and URLs validated.
add_filter('the_content',function($content){
    if(!is_singular('wiki_article')||!in_the_loop()||!is_main_query())return $content;
    $raw=get_post_meta(get_the_ID(),'_bsw_sources_json',true);if(!$raw)return $content;
    $out='<section class="bsw-sources"><h2>Sources</h2><ol>';
    foreach(preg_split('/\r\n|\r|\n/',$raw) as $line){$v=json_decode($line,true);if(!is_array($v)||empty($v['url']))continue;$url=esc_url($v['url']);if(!$url)continue;$title=sanitize_text_field($v['title']??$url);$publisher=sanitize_text_field($v['publisher']??'');$date=sanitize_text_field($v['date']??'');$out.='<li><a href="'.$url.'" rel="nofollow noopener" target="_blank">'.esc_html($title).'</a>'.($publisher?' — '.esc_html($publisher):'').($date?' ('.esc_html($date).')':'').'</li>';}
    $out.='</ol></section>';return $content.$out;
},35);

// Contributor profile fields and COI declaration.
add_action('show_user_profile','bsw_contributor_profile_fields');add_action('edit_user_profile','bsw_contributor_profile_fields');
function bsw_contributor_profile_fields($user){if(!current_user_can('edit_user',$user->ID))return;echo '<h2>BIGSHOR Wiki contributor profile</h2><table class="form-table"><tr><th><label for="bsw_bio">Public contributor biography</label></th><td><textarea name="bsw_bio" id="bsw_bio" rows="4" class="regular-text">'.esc_textarea(get_user_meta($user->ID,'bsw_public_bio',true)).'</textarea></td></tr><tr><th><label for="bsw_coi">Conflict-of-interest disclosure</label></th><td><textarea name="bsw_coi" id="bsw_coi" rows="3" class="regular-text">'.esc_textarea(get_user_meta($user->ID,'bsw_user_coi',true)).'</textarea></td></tr></table>';}
add_action('personal_options_update','bsw_save_contributor_profile_fields');add_action('edit_user_profile_update','bsw_save_contributor_profile_fields');
function bsw_save_contributor_profile_fields($id){if(!current_user_can('edit_user',$id))return;if(isset($_POST['bsw_bio']))update_user_meta($id,'bsw_public_bio',sanitize_textarea_field(wp_unslash($_POST['bsw_bio'])));if(isset($_POST['bsw_coi']))update_user_meta($id,'bsw_user_coi',sanitize_textarea_field(wp_unslash($_POST['bsw_coi'])));}

// Machine-readable knowledge entity API and JSON export route.
add_action('rest_api_init',function(){
    register_rest_route('bigshor/v1','/entity/(?P<id>[0-9]+)',array(
        'methods'=>'GET',
        'permission_callback'=>'__return_true',
        'callback'=>function($req){
            $id=absint($req['id']);
            if(get_post_type($id)!=='wiki_entity'||get_post_status($id)!=='publish') return new WP_Error('not_found','Entity not found',array('status'=>404));
            $aliases=preg_split('/\r\n|\r|\n/',(string)get_post_meta($id,'_bsw_entity_aliases',true));
            $claim_lines=array_filter(preg_split('/\r\n|\r|\n/',(string)get_post_meta($id,'_bsw_entity_claims',true)));
            $claims=array_map(function($line){return json_decode($line,true);},$claim_lines);
            $references=preg_split('/\r\n|\r|\n/',(string)get_post_meta($id,'_bsw_entity_references',true));
            return rest_ensure_response(array('id'=>get_post_meta($id,'_bsw_entity_id',true),'title'=>get_the_title($id),'url'=>get_permalink($id),'aliases'=>$aliases,'claims'=>$claims,'references'=>$references));
        }
    ));
    register_rest_route('bigshor/v1','/health',array(
        'methods'=>'GET','permission_callback'=>'__return_true',
        'callback'=>function(){return array('platform'=>'BIGSHOR Wiki','theme_version'=>defined('BSW_VERSION')?BSW_VERSION:'unknown','schema_version'=>get_option('bsw_schema_version','1.0.0'),'wordpress'=>get_bloginfo('version'));}
    ));
});

// Admin export of wiki article data as JSON. No destructive import is performed.
function bsw_data_portability_page(){
    if(!current_user_can('manage_options'))return;
    echo '<div class="wrap"><h1>BIGSHOR Wiki data portability</h1><p>Export article records and metadata to a JSON file for backup or migration. This export supplements, but does not replace, a complete database and uploads backup.</p><p><a class="button button-primary" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=bsw_export_json'),'bsw_export_json')).'">Export wiki articles as JSON</a></p><h2>Safe update policy</h2><ul><li>Theme updates must not delete posts, users, terms, attachments or options.</li><li>Schema changes must be additive and versioned.</li><li>Back up the database and uploads directory before deployment.</li><li>Test updates on staging before production.</li></ul></div>';
}
add_action('admin_post_bsw_export_json',function(){if(!current_user_can('manage_options')||!check_admin_referer('bsw_export_json'))wp_die('Not authorised.');$posts=get_posts(array('post_type'=>array('wiki_article','wiki_entity','wiki_media','wiki_talk'),'post_status'=>array('publish','pending','draft','private'),'numberposts'=>-1));$data=array();foreach($posts as $p)$data[]=array('post_type'=>$p->post_type,'title'=>$p->post_title,'slug'=>$p->post_name,'content'=>$p->post_content,'status'=>$p->post_status,'date'=>$p->post_date,'modified'=>$p->post_modified,'author'=>$p->post_author,'meta'=>get_post_meta($p->ID));nocache_headers();header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="bigshor-wiki-export-'.gmdate('Ymd-His').'.json"');echo wp_json_encode(array('exported_at'=>gmdate('c'),'schema_version'=>get_option('bsw_schema_version','1.0.0'),'records'=>$data),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;});

// Add stable discussion link and editorial status to article meta.
add_filter('the_content',function($content){if(!is_singular('wiki_article')||!in_the_loop()||!is_main_query())return $content;$id=get_the_ID();$talk=(int)get_post_meta($id,'_bsw_talk_page_id',true);$status=get_post_meta($id,'_bsw_factcheck_status',true)?:get_post_meta($id,'_bsw_review_status',true);$out='<div class="bsw-article-integrity">';if($status)$out.='<span>Editorial status: '.esc_html(str_replace('_',' ',$status)).'</span> · ';$out.='<a href="'.esc_url($talk?get_permalink($talk):add_query_arg('bsw_article',$id,home_url('/talk/'))).'">Discuss this article</a></div>';return $content.$out;},38);

// Keep REST and revisions available for wiki article types.
add_filter('use_block_editor_for_post_type',function($use,$type){return in_array($type,array('wiki_article','wiki_entity','wiki_talk','wiki_template'),true)?true:$use;},20,2);

// Respect the public-discussion setting and enforce optional editorial gates for contributor publishing.
add_filter('comments_open',function($open,$post_id){if(get_post_type($post_id)==='wiki_talk'&&bsw_platform_settings()['allow_public_comments']!=='1')return false;return $open;},20,2);
add_action('save_post_wiki_article',function($id){
    static $busy=false;if($busy||wp_is_post_revision($id)||defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)return;
    $post=get_post($id);if(!$post||$post->post_status!=='publish'||user_can($post->post_author,'manage_options'))return;
    $settings=bsw_platform_settings();$missing=array();
    if($settings['require_citations']==='1'){
        $raw=(string)get_post_meta($id,'_bsw_sources_json',true);$valid=false;
        foreach(preg_split('/\r\n|\r|\n/',$raw) as $line){$v=json_decode($line,true);if(is_array($v)&&!empty($v['url'])&&wp_http_validate_url($v['url'])){$valid=true;break;}}
        if(!$valid)$missing[]='a valid source register';
    }
    if($settings['require_coi_disclosure']==='1'&&!get_user_meta($post->post_author,'bsw_user_coi',true))$missing[]='a contributor conflict-of-interest declaration';
    if($missing){$busy=true;wp_update_post(array('ID'=>$id,'post_status'=>'pending'));update_post_meta($id,'_bsw_editorial_hold_reason','Missing '.implode(' and ',$missing).'.');$busy=false;}
},70);

// Editorial quality report for source coverage, review status and maintenance backlog.
add_action('admin_menu',function(){add_submenu_page('bigshor-wiki-settings','Editorial Quality Report','Editorial Quality','edit_others_posts','bsw-editorial-quality','bsw_editorial_quality_page');});
function bsw_editorial_quality_page(){
    if(!current_user_can('edit_others_posts'))return;
    $ids=get_posts(array('post_type'=>'wiki_article','post_status'=>array('publish','pending','draft'),'numberposts'=>-1,'fields'=>'ids'));
    $counts=array('Total articles'=>count($ids),'Needs source review'=>0,'No structured source register'=>0,'Conflict disclosure missing'=>0,'Protected articles'=>0,'Disputed articles'=>0);
    foreach($ids as $id){$review=get_post_meta($id,'_bsw_factcheck_status',true)?:get_post_meta($id,'_bsw_review_status',true);if(in_array($review,array('needs_sources','not_checked','unreviewed',''),true))$counts['Needs source review']++;if(!get_post_meta($id,'_bsw_sources_json',true))$counts['No structured source register']++;if(!get_post_meta($id,'_bsw_coi_disclosure',true))$counts['Conflict disclosure missing']++;if(get_post_meta($id,'_bsw_protected',true)==='1')$counts['Protected articles']++;if($review==='disputed')$counts['Disputed articles']++;}
    echo '<div class="wrap"><h1>Editorial Quality Report</h1><p>This is a maintenance aid, not an automated determination that an article is true or false.</p><table class="widefat striped" style="max-width:850px"><thead><tr><th>Measure</th><th>Count</th></tr></thead><tbody>';foreach($counts as $label=>$n)echo '<tr><td>'.esc_html($label).'</td><td>'.number_format_i18n($n).'</td></tr>';echo '</tbody></table><h2>Recommended editorial process</h2><ol><li>Review articles marked needs sources or disputed.</li><li>Check each material claim against reliable, independent references.</li><li>Record contributor conflicts and paid relationships.</li><li>Use revision history and audit logs to document corrections.</li><li>Do not publish starter biographies as verified without corroboration.</li></ol></div>';
}

// Public author disclosure notice where contributor has declared an interest.
add_filter('the_content',function($content){
    if(!is_singular('wiki_article')||!in_the_loop()||!is_main_query())return $content;
    $author=(int)get_post_field('post_author',get_the_ID());$coi=get_user_meta($author,'bsw_user_coi',true);
    if(!$coi)return $content;
    return '<aside class="bsw-coi-notice"><strong>Contributor disclosure:</strong> '.esc_html($coi).'</aside>'.$content;
},9);

// Article protection status in the public interface.
add_filter('the_content',function($content){if(is_singular('wiki_article')&&in_the_loop()&&is_main_query()&&get_post_meta(get_the_ID(),'_bsw_protected',true)==='1')return '<p class="bsw-protected-notice">This article is protected from edits by standard contributors. Suggestions may still be submitted for editorial review.</p>'.$content;return $content;},8);
