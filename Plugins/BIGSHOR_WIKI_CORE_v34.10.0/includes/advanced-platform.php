<?php
if (!defined('ABSPATH')) exit;
/**
 * BIGSHOR advanced platform layer.
 * WordPress-native equivalents for structured entities, visual editing, basic abuse rules,
 * and Commons-style media metadata. This is not the upstream Wikibase/MediaWiki stack.
 */

add_action('init', function () {
    register_post_type('wiki_entity', array(
        'labels' => array('name'=>'Knowledge entities','singular_name'=>'Knowledge entity','add_new_item'=>'Add knowledge entity','edit_item'=>'Edit knowledge entity'),
        'public'=>true, 'show_in_rest'=>true, 'has_archive'=>true,
        'rewrite'=>array('slug'=>'entity'), 'menu_icon'=>'dashicons-networking',
        'supports'=>array('title','editor','excerpt','thumbnail','revisions','author'),
    ));
    register_taxonomy('wiki_entity_type', array('wiki_entity'), array(
        'labels'=>array('name'=>'Entity types','singular_name'=>'Entity type'),
        'public'=>true,'show_in_rest'=>true,'hierarchical'=>true,'rewrite'=>array('slug'=>'entity-type')
    ));
    register_taxonomy('wiki_media_license', array('attachment'), array(
        'labels'=>array('name'=>'Media licences','singular_name'=>'Media licence'),
        'public'=>true,'show_ui'=>true,'show_in_rest'=>true,'hierarchical'=>false
    ));
}, 8);

// Gutenberg is WordPress's built-in visual editor. Explicitly expose wiki entities to it.
add_filter('use_block_editor_for_post_type', function($use, $type){
    return in_array($type, array('wiki_article','wiki_entity'), true) ? true : $use;
}, 10, 2);

add_action('add_meta_boxes', function(){
    add_meta_box('bsw_entity_data','Structured entity data', 'bsw_entity_data_box', 'wiki_entity', 'normal', 'high');
    add_meta_box('bsw_media_rights','Media source & rights', 'bsw_media_rights_box', 'attachment', 'normal', 'high');
});
function bsw_entity_data_box($post){
    wp_nonce_field('bsw_save_entity_data','bsw_entity_nonce');
    $qid=get_post_meta($post->ID,'_bsw_entity_id',true);
    $aliases=get_post_meta($post->ID,'_bsw_entity_aliases',true);
    $refs=get_post_meta($post->ID,'_bsw_entity_references',true);
    $claims=get_post_meta($post->ID,'_bsw_entity_claims',true);
    echo '<p><label>Stable entity identifier (e.g. BIGSHOR-E0001)<br><input style="width:100%" name="bsw_entity_id" value="'.esc_attr($qid).'" /></label></p>';
    echo '<p><label>Alternative names (one per line)<br><textarea style="width:100%" rows="3" name="bsw_entity_aliases">'.esc_textarea($aliases).'</textarea></label></p>';
    echo '<p><label>Claims / statements (one JSON object per line; include property, value and source URL)<br><textarea style="width:100%" rows="7" name="bsw_entity_claims">'.esc_textarea($claims).'</textarea></label></p>';
    echo '<p><label>External identifiers and references (one URL or identifier per line)<br><textarea style="width:100%" rows="4" name="bsw_entity_references">'.esc_textarea($refs).'</textarea></label></p>';
    echo '<p class="description">Structured editorial metadata for BIGSHOR entity pages. Validate claims and cite reliable sources. This is not a full RDF/Wikibase statement store.</p>';
}
function bsw_media_rights_box($post){
    $id=$post->ID;
    foreach(array('creator'=>'Creator / photographer','source'=>'Original source URL','licence'=>'Licence name or identifier','attribution'=>'Required attribution','permission'=>'Permission / rights notes') as $key=>$label){
        $val=get_post_meta($id,'_bsw_media_'.$key,true);
        echo '<p><label>'.esc_html($label).'<br><input style="width:100%" name="bsw_media_'.esc_attr($key).'" value="'.esc_attr($val).'" /></label></p>';
    }
    wp_nonce_field('bsw_save_media_rights','bsw_media_nonce');
}
add_action('save_post_wiki_entity',function($id){
    if(!isset($_POST['bsw_entity_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_entity_nonce'])),'bsw_save_entity_data')||defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE||!current_user_can('edit_post',$id))return;
    foreach(array('entity_id'=>'_bsw_entity_id','entity_aliases'=>'_bsw_entity_aliases','entity_claims'=>'_bsw_entity_claims','entity_references'=>'_bsw_entity_references') as $field=>$meta){
        if(isset($_POST['bsw_'.$field])) update_post_meta($id,$meta,sanitize_textarea_field(wp_unslash($_POST['bsw_'.$field])));
    }
},10,1);
add_action('edit_attachment',function($id){
    if(!isset($_POST['bsw_media_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_media_nonce'])),'bsw_save_media_rights')||!current_user_can('edit_post',$id))return;
    foreach(array('creator','source','licence','attribution','permission') as $key)if(isset($_POST['bsw_media_'.$key]))update_post_meta($id,'_bsw_media_'.$key,sanitize_text_field(wp_unslash($_POST['bsw_media_'.$key])));
});

// Simple configurable abuse checks: suspicious submissions are held for review, never silently deleted.
add_action('admin_menu',function(){
    add_submenu_page('edit.php?post_type=wiki_article','Abuse rules','Abuse rules','manage_options','bsw-abuse-rules','bsw_abuse_rules_page');
});
function bsw_abuse_rules_page(){
    if(!current_user_can('manage_options'))return;
    if(isset($_POST['bsw_save_rules'])&&check_admin_referer('bsw_save_abuse_rules')){
        $raw=sanitize_textarea_field(wp_unslash($_POST['bsw_abuse_terms']??''));
        update_option('bsw_abuse_terms',$raw,false);
        echo '<div class="updated notice"><p>Abuse rules saved.</p></div>';
    }
    $terms=get_option('bsw_abuse_terms','');
    echo '<div class="wrap"><h1>BIGSHOR Abuse Rules</h1><p>Enter one suspicious phrase or spam pattern per line. Matching new contributor submissions are held as pending for human review. Avoid broad terms that create false positives.</p><form method="post">';
    wp_nonce_field('bsw_save_abuse_rules');
    echo '<textarea name="bsw_abuse_terms" rows="12" style="width:100%;max-width:850px">'.esc_textarea($terms).'</textarea><p><button class="button button-primary" name="bsw_save_rules" value="1">Save rules</button></p></form><p><strong>Important:</strong> This is a basic phrase filter, not MediaWiki AbuseFilter. It does not execute arbitrary rule expressions or replace specialist anti-spam/security services.</p></div>';
}
add_filter('wp_insert_post_data',function($data,$postarr){
    if(($data['post_type']??'')!=='wiki_article'||!in_array($data['post_status']??'',array('publish','pending','draft'),true)||is_admin()&&current_user_can('publish_posts'))return $data;
    $rules=preg_split('/\r\n|\r|\n/',(string)get_option('bsw_abuse_terms',''));
    $hay=strtolower(wp_strip_all_tags(($data['post_title']??'').' '.($data['post_content']??'')));
    foreach($rules as $rule){$rule=strtolower(trim($rule));if($rule!==''&&strpos($hay,$rule)!==false){$data['post_status']='pending';break;}}
    return $data;
},20,2);

add_shortcode('bigshor_entity',function($atts){
    $atts=shortcode_atts(array('id'=>''),$atts);$id=sanitize_text_field($atts['id']);
    $q=new WP_Query(array('post_type'=>'wiki_entity','post_status'=>'publish','meta_key'=>'_bsw_entity_id','meta_value'=>$id,'posts_per_page'=>1));
    if(!$q->have_posts())return '<p>Entity not found.</p>';
    $q->the_post();$pid=get_the_ID();$out='<article class="bsw-entity"><h2><a href="'.esc_url(get_permalink($pid)).'">'.esc_html(get_the_title($pid)).'</a></h2>'.apply_filters('the_content',get_post_field('post_content',$pid));
    $aliases=get_post_meta($pid,'_bsw_entity_aliases',true);if($aliases)$out.='<p><strong>Also known as:</strong> '.esc_html(str_replace("\n",', ',$aliases)).'</p>';
    $claims=get_post_meta($pid,'_bsw_entity_claims',true);if($claims){$out.='<h3>Statements</h3><ul>';foreach(preg_split('/\r\n|\r|\n/',$claims) as $line){$v=json_decode($line,true);if(is_array($v))$out.='<li>'.esc_html(($v['property']??'Property').': '.($v['value']??'')).(empty($v['source'])?'':' — <a rel="nofollow" href="'.esc_url($v['source']).'">source</a>').'</li>';}$out.='</ul>';}
    $out.='</article>';wp_reset_postdata();return $out;
});
add_shortcode('bigshor_entity_directory',function(){
    $q=new WP_Query(array('post_type'=>'wiki_entity','post_status'=>'publish','posts_per_page'=>100,'orderby'=>'title','order'=>'ASC'));
    if(!$q->have_posts())return '<p>No knowledge entities have been published yet.</p>';
    $out='<ul class="wiki-list">';while($q->have_posts()){$q->the_post();$out.='<li><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a></li>';}$out.='</ul>';wp_reset_postdata();return $out;
});
add_shortcode('bigshor_commons_gallery',function($atts){
    $atts=shortcode_atts(array('limit'=>24,'category'=>''),$atts);$args=array('post_type'=>'attachment','post_status'=>'inherit','post_mime_type'=>'image','posts_per_page'=>min(100,max(1,absint($atts['limit']))));
    if($atts['category'])$args['tax_query']=array(array('taxonomy'=>'wiki_media_license','field'=>'slug','terms'=>sanitize_title($atts['category'])));
    $q=new WP_Query($args);if(!$q->have_posts())return '<p>No media items found.</p>';$out='<div class="bsw-media-gallery">';
    while($q->have_posts()){$q->the_post();$id=get_the_ID();$src=wp_get_attachment_image_url($id,'large');if(!$src)continue;$out.='<figure><a href="'.esc_url(wp_get_attachment_url($id)).'"><img loading="lazy" src="'.esc_url($src).'" alt="'.esc_attr(get_post_meta($id,'_wp_attachment_image_alt',true)).'"></a><figcaption>'.esc_html(get_the_title($id)).'<br><small>'.esc_html(get_post_meta($id,'_bsw_media_creator',true)).' · '.esc_html(get_post_meta($id,'_bsw_media_licence',true)).'</small></figcaption></figure>';}
    $out.='</div>';wp_reset_postdata();return $out;
});
add_action('rest_api_init',function(){
    register_rest_route('bigshor/v1','/entities',array('methods'=>'GET','permission_callback'=>'__return_true','callback'=>function($request){
        $q=new WP_Query(array('post_type'=>'wiki_entity','post_status'=>'publish','posts_per_page'=>min(100,max(1,(int)$request->get_param('per_page'))),'s'=>sanitize_text_field((string)$request->get_param('search'))));$items=array();
        foreach($q->posts as $p)$items[]=array('id'=>get_post_meta($p->ID,'_bsw_entity_id',true),'title'=>get_the_title($p),'url'=>get_permalink($p),'description'=>get_the_excerpt($p));
        return rest_ensure_response($items);
    }));
});
