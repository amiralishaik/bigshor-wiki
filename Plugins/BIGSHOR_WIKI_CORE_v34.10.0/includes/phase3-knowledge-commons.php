<?php
/**
 * BIGSHOR Wiki Phase 3: Knowledge Graph and Commons foundations.
 * Entity records, typed relationships, structured metadata and media rights records.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase3_Knowledge_Commons')) {
final class BSW_Phase3_Knowledge_Commons {
    const VERSION = '16.0.0';
    public static function boot() {
        add_action('init', array(__CLASS__, 'register_content'), 8);
        add_action('add_meta_boxes', array(__CLASS__, 'meta_boxes'));
        add_action('save_post_bsw_entity', array(__CLASS__, 'save_entity'), 10, 2);
        add_action('add_attachment', array(__CLASS__, 'attachment_defaults'));
        add_action('add_meta_boxes_attachment', array(__CLASS__, 'media_box'));
        add_action('edit_attachment', array(__CLASS__, 'save_media'));
        add_action('admin_notices', array(__CLASS__, 'rights_notice'));
        add_action('wp_head', array(__CLASS__, 'entity_jsonld'), 30);
        add_shortcode('bigshor_entity', array(__CLASS__, 'entity_shortcode'));
        add_shortcode('bigshor_entity_relationships', array(__CLASS__, 'relationships_shortcode'));
        add_shortcode('bigshor_entity_explorer', array(__CLASS__, 'explorer_shortcode'));
        add_shortcode('bigshor_commons_item', array(__CLASS__, 'commons_shortcode'));
        add_filter('manage_bsw_entity_posts_columns', array(__CLASS__, 'entity_columns'));
        add_action('manage_bsw_entity_posts_custom_column', array(__CLASS__, 'entity_column'), 10, 2);
    }
    public static function register_content() {
        register_post_type('bsw_entity', array(
            'labels'=>array('name'=>'Knowledge Entities','singular_name'=>'Knowledge Entity','add_new_item'=>'Add Knowledge Entity','edit_item'=>'Edit Knowledge Entity'),
            'public'=>true,'show_ui'=>true,'show_in_rest'=>true,'has_archive'=>true,'rewrite'=>array('slug'=>'knowledge'),
            'menu_icon'=>'dashicons-networking','supports'=>array('title','editor','excerpt','thumbnail','author','revisions'),
            'capability_type'=>'post','map_meta_cap'=>true
        ));
        register_taxonomy('bsw_entity_type', array('bsw_entity'), array(
            'labels'=>array('name'=>'Entity Types','singular_name'=>'Entity Type'),'public'=>true,'show_ui'=>true,'show_in_rest'=>true,'hierarchical'=>true,'rewrite'=>array('slug'=>'knowledge-type')
        ));
        register_taxonomy('bsw_entity_topic', array('bsw_entity'), array(
            'labels'=>array('name'=>'Knowledge Topics','singular_name'=>'Knowledge Topic'),'public'=>true,'show_ui'=>true,'show_in_rest'=>true,'hierarchical'=>true
        ));
        register_post_type('bsw_relation', array(
            'labels'=>array('name'=>'Knowledge Relationships','singular_name'=>'Knowledge Relationship'),
            'public'=>false,'show_ui'=>true,'show_in_menu'=>'edit.php?post_type=bsw_entity','show_in_rest'=>false,
            'supports'=>array('title','author'),'capability_type'=>'post','map_meta_cap'=>true
        ));
    }
    public static function meta_boxes() {
        add_meta_box('bsw_entity_data','Structured entity data',array(__CLASS__,'entity_box'),'bsw_entity','normal','high');
        add_meta_box('bsw_entity_links','Entity relationships',array(__CLASS__,'relationship_box'),'bsw_entity','normal','default');
    }
    public static function entity_box($post) {
        wp_nonce_field('bsw_p3_entity_'.$post->ID,'bsw_p3_entity_nonce');
        $fields=array('aliases'=>'Alternative names / aliases (one per line)','external_id'=>'External identifier (Wikidata QID, ISNI, etc.)','official_url'=>'Official website URL','same_as'=>'Other authoritative URLs (one per line)');
        echo '<p>Use reliable, verifiable identifiers. Do not add private or sensitive personal information.</p>';
        foreach($fields as $key=>$label){$v=get_post_meta($post->ID,'_bsw_p3_'.$key,true);echo '<p><label for="bsw_p3_'.esc_attr($key).'"><strong>'.esc_html($label).'</strong></label><br>';if(in_array($key,array('aliases','same_as'),true))echo '<textarea class="widefat" rows="3" id="bsw_p3_'.esc_attr($key).'" name="bsw_p3_'.esc_attr($key).'">'.esc_textarea($v).'</textarea>';else echo '<input class="widefat" id="bsw_p3_'.esc_attr($key).'" name="bsw_p3_'.esc_attr($key).'" value="'.esc_attr($v).'">';echo '</p>';}
    }
    public static function relationship_box($post) {
        wp_nonce_field('bsw_p3_rel_'.$post->ID,'bsw_p3_rel_nonce');
        $rows=get_post_meta($post->ID,'_bsw_p3_relationships',true);if(!is_array($rows))$rows=array();
        echo '<p>Connect this entity to another BIGSHOR knowledge entity. Enter one relationship per line using: <code>relationship label | target entity ID | optional source URL</code>. The target ID must be an existing Knowledge Entity.</p><textarea class="widefat" rows="6" name="bsw_p3_relationships">'.esc_textarea(self::format_relationships($rows)).'</textarea>';
        echo '<p class="description">Examples: Founder of | 123 — Headquartered in | 456 — Author of | 789. Relationships are stored as structured records and displayed on the public entity page.</p>';
    }
    private static function format_relationships($rows){$out=array();foreach($rows as $r){$out[]=sanitize_text_field($r['label']).' | '.absint($r['target']).(!empty($r['source'])?' | '.esc_url_raw($r['source']):'');}return implode("\n",$out);}
    public static function save_entity($post_id,$post) {
        if(wp_is_post_revision($post_id)||defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE||!current_user_can('edit_post',$post_id))return;
        if(isset($_POST['bsw_p3_entity_nonce'])&&wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_p3_entity_nonce'])),'bsw_p3_entity_'.$post_id)){
            foreach(array('aliases','external_id','official_url','same_as') as $key){$raw=isset($_POST['bsw_p3_'.$key])?wp_unslash($_POST['bsw_p3_'.$key]):'';$value=($key==='official_url')?esc_url_raw($raw):sanitize_textarea_field($raw);update_post_meta($post_id,'_bsw_p3_'.$key,$value);}
        }
        if(isset($_POST['bsw_p3_rel_nonce'])&&wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_p3_rel_nonce'])),'bsw_p3_rel_'.$post_id)){
            $raw=sanitize_textarea_field(wp_unslash($_POST['bsw_p3_relationships']??''));$rows=array();foreach(preg_split('/\r\n|\r|\n/',$raw) as $line){$parts=array_map('trim',explode('|',$line));if(count($parts)<2)continue;$label=sanitize_text_field($parts[0]);$target=absint($parts[1]);$source=isset($parts[2])?esc_url_raw(trim($parts[2])):'';if(!$label||!$target||$target===$post_id||get_post_type($target)!=='bsw_entity')continue;$rows[]=array('label'=>$label,'target'=>$target,'source'=>$source);}
            update_post_meta($post_id,'_bsw_p3_relationships',$rows);
            self::sync_relation_records($post_id,$rows);
        }
    }
    private static function sync_relation_records($source,$rows){
        $old=get_posts(array('post_type'=>'bsw_relation','post_status'=>'private','numberposts'=>-1,'meta_key'=>'_bsw_rel_source','meta_value'=>$source,'fields'=>'ids'));
        foreach($old as $id)wp_delete_post($id,true);
        foreach($rows as $r){$target=get_post($r['target']);if(!$target)continue;$id=wp_insert_post(array('post_type'=>'bsw_relation','post_status'=>'private','post_title'=>get_the_title($source).' — '.$r['label'].' — '.$target->post_title,'post_author'=>get_current_user_id()?:1));if(!is_wp_error($id)){update_post_meta($id,'_bsw_rel_source',$source);update_post_meta($id,'_bsw_rel_target',$r['target']);update_post_meta($id,'_bsw_rel_label',$r['label']);}}
    }
    public static function entity_shortcode($atts){$a=shortcode_atts(array('id'=>0,'show_image'=>'yes'),$atts);$id=absint($a['id']);if(!$id)$id=get_the_ID();if(get_post_type($id)!=='bsw_entity')return ''; $out='<article class="bsw-entity-card">';if($a['show_image']==='yes'&&has_post_thumbnail($id))$out.='<a href="'.esc_url(get_permalink($id)).'">'.get_the_post_thumbnail($id,'medium').'</a>';$out.='<h3><a href="'.esc_url(get_permalink($id)).'">'.esc_html(get_the_title($id)).'</a></h3>';$out.='<p>'.esc_html(get_the_excerpt($id)).'</p></article>';return $out;}
    public static function relationships_shortcode($atts){$a=shortcode_atts(array('id'=>0),$atts);$id=absint($a['id']);if(!$id)$id=get_the_ID();if(get_post_type($id)!=='bsw_entity')return '';$rows=get_post_meta($id,'_bsw_p3_relationships',true);if(!is_array($rows)||!$rows)return '<p>No published relationships have been recorded.</p>';$out='<section class="bsw-entity-relationships"><h2>Related entities</h2><ul>';foreach($rows as $r){$target=absint($r['target']);if(get_post_status($target)!=='publish')continue;$out.='<li><strong>'.esc_html($r['label']).':</strong> <a href="'.esc_url(get_permalink($target)).'">'.esc_html(get_the_title($target)).'</a>'.(!empty($r['source'])?' <a rel="nofollow noopener" target="_blank" href="'.esc_url($r['source']).'">[source]</a>':'').'</li>';}$out.='</ul></section>';return $out;}
    public static function explorer_shortcode($atts){$a=shortcode_atts(array('limit'=>24,'type'=>''),$atts);$args=array('post_type'=>'bsw_entity','post_status'=>'publish','posts_per_page'=>max(1,min(100,absint($a['limit']))),'orderby'=>'title','order'=>'ASC');if($a['type'])$args['tax_query']=array(array('taxonomy'=>'bsw_entity_type','field'=>'slug','terms'=>sanitize_title($a['type'])));$q=new WP_Query($args);if(!$q->have_posts())return '<p>No knowledge entities have been published yet.</p>';$out='<div class="bsw-entity-explorer">';while($q->have_posts()){$q->the_post();$out.=self::entity_shortcode(array('id'=>get_the_ID()));}wp_reset_postdata();return $out.'</div>';}
    public static function entity_jsonld(){if(!is_singular('bsw_entity'))return;$id=get_queried_object_id();$type=wp_get_post_terms($id,'bsw_entity_type',array('fields'=>'names'));$same=get_post_meta($id,'_bsw_p3_same_as',true);$same_urls=array_filter(array_map('esc_url_raw',preg_split('/\r\n|\r|\n/',(string)$same)));$url=get_post_meta($id,'_bsw_p3_official_url',true);$data=array('@context'=>'https://schema.org','@type'=>'Thing','name'=>get_the_title($id),'description'=>wp_strip_all_tags(get_the_excerpt($id)),'url'=>get_permalink($id));if($type)$data['additionalType']=$type[0];if($url)$data['sameAs']=array_merge(array($url),$same_urls);elseif($same_urls)$data['sameAs']=$same_urls;$ext=get_post_meta($id,'_bsw_p3_external_id',true);if($ext)$data['identifier']=$ext;echo "\n<script type=\"application/ld+json\">".wp_json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."</script>\n";}
    public static function attachment_defaults($id){if(!get_post_meta($id,'_bsw_commons_license',true))update_post_meta($id,'_bsw_commons_rights_status','needs_review');}
    public static function media_box($post){add_meta_box('bsw_commons_rights','BIGSHOR Commons — Rights & attribution',array(__CLASS__,'media_box_html'),'attachment','side','high');}
    public static function media_box_html($post){wp_nonce_field('bsw_commons_'.$post->ID,'bsw_commons_nonce');$fields=array('creator'=>'Creator / photographer','source'=>'Source URL or origin','license'=>'License (e.g. CC BY 4.0, public domain)','attribution'=>'Required attribution text');foreach($fields as $k=>$label){$v=get_post_meta($post->ID,'_bsw_commons_'.$k,true);echo '<p><label><strong>'.esc_html($label).'</strong></label><input class="widefat" name="bsw_commons_'.esc_attr($k).'" value="'.esc_attr($v).'" /></p>';}$status=get_post_meta($post->ID,'_bsw_commons_rights_status',true);echo '<p><label><input type="checkbox" name="bsw_commons_verified" value="1" '.checked($status,'verified',false).' /> Rights and license checked by an authorised editor</label></p><p class="description">Only mark verified after checking the license and that it permits the intended use. Uploading an image does not establish ownership or permission.</p>';}
    public static function save_media($id){if(!isset($_POST['bsw_commons_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_commons_nonce'])),'bsw_commons_'.$id)||!current_user_can('edit_post',$id))return;foreach(array('creator','source','license','attribution') as $k){$v=sanitize_text_field(wp_unslash($_POST['bsw_commons_'.$k]??''));if($k==='source')$v=esc_url_raw(wp_unslash($_POST['bsw_commons_'.$k]??''));update_post_meta($id,'_bsw_commons_'.$k,$v);} $complete=true;foreach(array('creator','license') as $k){if(!get_post_meta($id,'_bsw_commons_'.$k,true))$complete=false;}$verified=!empty($_POST['bsw_commons_verified'])&&$complete&&current_user_can('manage_options');update_post_meta($id,'_bsw_commons_rights_status',$verified?'verified':'needs_review');}
    public static function rights_notice(){if(!current_user_can('upload_files'))return;$screen=function_exists('get_current_screen')?get_current_screen():null;if(!$screen||$screen->id!=='attachment')return;$id=isset($_GET['post'])?absint($_GET['post']):0;if($id&&get_post_meta($id,'_bsw_commons_rights_status',true)!=='verified')echo '<div class="notice notice-warning"><p><strong>BIGSHOR Commons:</strong> This media item is not rights-verified. Confirm the creator, source and license before using or redistributing it.</p></div>';}
    public static function commons_shortcode($atts){$a=shortcode_atts(array('id'=>0),$atts);$id=absint($a['id']);if(!$id||get_post_type($id)!=='attachment')return '';$status=get_post_meta($id,'_bsw_commons_rights_status',true);$creator=get_post_meta($id,'_bsw_commons_creator',true);$license=get_post_meta($id,'_bsw_commons_license',true);$attr=get_post_meta($id,'_bsw_commons_attribution',true);$out='<figure class="bsw-commons-item">'.wp_get_attachment_image($id,'large');$out.='<figcaption>'.esc_html(get_the_title($id));if($creator)$out.=' — '.esc_html($creator);if($license)$out.=' · '.esc_html($license);if($attr)$out.='<br>'.esc_html($attr);if($status!=='verified')$out.='<br><strong>Rights review pending</strong>';$out.='</figcaption></figure>';return $out;}
    public static function entity_columns($cols){$cols['bsw_entity_type']='Entity type';$cols['bsw_entity_id']='External ID';return $cols;}
    public static function entity_column($col,$id){if($col==='bsw_entity_type'){$terms=wp_get_post_terms($id,'bsw_entity_type',array('fields'=>'names'));echo esc_html(implode(', ',$terms));}if($col==='bsw_entity_id')echo esc_html(get_post_meta($id,'_bsw_p3_external_id',true));}
}
BSW_Phase3_Knowledge_Commons::boot();
}
