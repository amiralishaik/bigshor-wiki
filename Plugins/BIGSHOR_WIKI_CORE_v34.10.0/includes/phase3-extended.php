<?php
/**
 * BIGSHOR Wiki Phase 3 extension: entity quality tools, CSV exchange,
 * graph explorer, Commons collections, duplicate media signals and usage.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase3_Extended')) {
final class BSW_Phase3_Extended {
    public static function boot() {
        add_action('init', array(__CLASS__, 'register_taxonomies'), 9);
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('admin_post_bsw_p3_export_entities', array(__CLASS__, 'export_entities'));
        add_action('admin_post_bsw_p3_import_entities', array(__CLASS__, 'import_entities'));
        add_action('add_attachment', array(__CLASS__, 'record_media_fingerprint'), 20);
        add_action('admin_notices', array(__CLASS__, 'duplicate_entity_notice'));
        add_action('admin_notices', array(__CLASS__, 'duplicate_media_notice'));
        add_action('add_meta_boxes', array(__CLASS__, 'add_usage_box'));
        add_action('add_meta_boxes', array(__CLASS__, 'add_language_box'));
        add_action('save_post_bsw_entity', array(__CLASS__, 'save_language_labels'), 20, 2);
        add_shortcode('bigshor_knowledge_graph', array(__CLASS__, 'graph_shortcode'));
        add_shortcode('bigshor_entity_label', array(__CLASS__, 'entity_label_shortcode'));
        add_shortcode('bigshor_commons_collection', array(__CLASS__, 'commons_collection_shortcode'));
        add_filter('manage_upload_columns', array(__CLASS__, 'media_columns'));
        add_action('manage_media_custom_column', array(__CLASS__, 'media_column'), 10, 2);
    }
    public static function register_taxonomies() {
        if (!taxonomy_exists('bsw_commons_collection')) register_taxonomy('bsw_commons_collection', array('attachment'), array(
            'labels'=>array('name'=>'Commons Collections','singular_name'=>'Commons Collection','menu_name'=>'Commons Collections'),
            'public'=>true,'show_ui'=>true,'show_admin_column'=>true,'show_in_rest'=>true,'hierarchical'=>true,
            'rewrite'=>array('slug'=>'commons-collection')
        ));
        if (!taxonomy_exists('bsw_commons_topic')) register_taxonomy('bsw_commons_topic', array('attachment'), array(
            'labels'=>array('name'=>'Commons Topics','singular_name'=>'Commons Topic'),
            'public'=>true,'show_ui'=>true,'show_admin_column'=>true,'show_in_rest'=>true,'hierarchical'=>true
        ));
    }
    public static function admin_menu() {
        add_submenu_page('edit.php?post_type=bsw_entity', 'Knowledge Tools', 'Knowledge Tools', 'manage_options', 'bsw-knowledge-tools', array(__CLASS__, 'tools_page'));
    }
    public static function tools_page() {
        if (!current_user_can('manage_options')) return;
        $duplicates = self::find_entity_duplicates(100);
        echo '<div class="wrap"><h1>BIGSHOR Knowledge Tools</h1><p>Manage structured entity data and exchange it using CSV. Imports create new draft entities only; they do not overwrite existing records.</p>';
        echo '<h2>Entity duplicate review</h2>';
        if (!$duplicates) echo '<p>No exact-title or external-ID duplicate candidates found in the first 500 published and draft entities.</p>';
        else { echo '<table class="widefat striped"><thead><tr><th>Match reason</th><th>Entity records</th><th>Review</th></tr></thead><tbody>'; foreach ($duplicates as $d) { echo '<tr><td>'.esc_html($d['reason']).'</td><td>'; $links=array(); foreach($d['ids'] as $id) $links[]='<a href="'.esc_url(get_edit_post_link($id)).'">'.esc_html(get_the_title($id)).' (#'.absint($id).')</a>'; echo implode(' · ',$links).'</td><td>Manually compare sources and identifiers before correcting records.</td></tr>'; } echo '</tbody></table>'; }
        echo '<hr><h2>Export entities</h2><p>Exports published and draft entities with their structured fields and entity-type terms.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw_p3_export_entities">'; wp_nonce_field('bsw_p3_export_entities'); submit_button('Download entity CSV'); echo '</form>';
        echo '<hr><h2>Import entities from CSV</h2><p>Required column: <code>title</code>. Optional columns: <code>description, entity_type, aliases, external_id, official_url, same_as, language_labels</code>. For <code>language_labels</code>, use <code>en:English name|ar:Arabic name</code>. Imports are created as drafts for editorial review.</p><form method="post" enctype="multipart/form-data" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw_p3_import_entities">'; wp_nonce_field('bsw_p3_import_entities'); echo '<input type="file" name="bsw_entity_csv" accept=".csv,text/csv" required> '; submit_button('Import CSV as drafts','secondary','submit',false); echo '</form></div>';
    }
    public static function export_entities() {
        if (!current_user_can('manage_options') || !check_admin_referer('bsw_p3_export_entities')) wp_die('Not authorised.');
        $posts=get_posts(array('post_type'=>'bsw_entity','post_status'=>array('publish','draft','pending','private'),'numberposts'=>-1,'orderby'=>'ID','order'=>'ASC'));
        nocache_headers(); header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename=bigshor-knowledge-entities.csv');
        $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF"); fputcsv($out,array('title','description','entity_type','aliases','external_id','official_url','same_as','language_labels'));
        foreach($posts as $p){$terms=wp_get_post_terms($p->ID,'bsw_entity_type',array('fields'=>'names'));$labels=get_post_meta($p->ID,'_bsw_p3_language_labels',true);$label_pairs=array();if(is_array($labels))foreach($labels as $code=>$label)$label_pairs[]=$code.':'.$label;fputcsv($out,array($p->post_title,wp_strip_all_tags($p->post_content),implode('|',$terms),get_post_meta($p->ID,'_bsw_p3_aliases',true),get_post_meta($p->ID,'_bsw_p3_external_id',true),get_post_meta($p->ID,'_bsw_p3_official_url',true),get_post_meta($p->ID,'_bsw_p3_same_as',true),implode('|',$label_pairs)));}
        fclose($out); exit;
    }
    public static function import_entities() {
        if (!current_user_can('manage_options') || !check_admin_referer('bsw_p3_import_entities')) wp_die('Not authorised.');
        if (empty($_FILES['bsw_entity_csv']['tmp_name']) || !is_uploaded_file($_FILES['bsw_entity_csv']['tmp_name'])) wp_die('Please upload a CSV file.');
        $name=sanitize_file_name($_FILES['bsw_entity_csv']['name']??''); if (strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='csv') wp_die('Only CSV files are accepted.');
        $fh=fopen($_FILES['bsw_entity_csv']['tmp_name'],'r'); if(!$fh) wp_die('Could not read CSV.');
        $headers=fgetcsv($fh); if($headers && isset($headers[0])) $headers[0]=preg_replace('/^\xEF\xBB\xBF/','',$headers[0]); if(!$headers || !in_array('title',array_map('strtolower',$headers),true)){fclose($fh);wp_die('CSV must contain a title column.');}
        $headers=array_map(function($v){return sanitize_key(strtolower(trim($v)));},$headers); $created=0; $skipped=0; $row_count=0;
        while(($row=fgetcsv($fh))!==false && $row_count<1000){$row_count++;$data=array();foreach($headers as $i=>$key)$data[$key]=isset($row[$i])?trim($row[$i]):'';$title=sanitize_text_field($data['title']??'');if(!$title){$skipped++;continue;}
            $id=wp_insert_post(array('post_type'=>'bsw_entity','post_status'=>'draft','post_title'=>$title,'post_content'=>wp_kses_post($data['description']??''),'post_author'=>get_current_user_id()),true);if(is_wp_error($id)){$skipped++;continue;}
            foreach(array('aliases'=>'aliases','external_id'=>'external_id','official_url'=>'official_url','same_as'=>'same_as') as $col=>$meta){$v=$data[$col]??'';if($meta==='official_url')$v=esc_url_raw($v);else $v=sanitize_textarea_field($v);update_post_meta($id,'_bsw_p3_'.$meta,$v);}
            $labels=array();foreach(explode('|',(string)($data['language_labels']??'')) as $pair){$parts=explode(':',$pair,2);if(count($parts)===2){$lang=strtolower(trim($parts[0]));$label=sanitize_text_field(trim($parts[1]));if(preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/',$lang)&&$label)$labels[$lang]=$label;}}if($labels)update_post_meta($id,'_bsw_p3_language_labels',$labels);$type=sanitize_text_field($data['entity_type']??'');if($type)wp_set_object_terms($id,array_map('trim',explode('|',$type)),'bsw_entity_type',false);$created++;
        }
        fclose($fh); wp_safe_redirect(add_query_arg(array('post_type'=>'bsw_entity','page'=>'bsw-knowledge-tools','bsw_imported'=>$created,'bsw_skipped'=>$skipped),admin_url('edit.php')));exit;
    }
    public static function find_entity_duplicates($limit=100) {
        $posts=get_posts(array('post_type'=>'bsw_entity','post_status'=>array('publish','draft','pending','private'),'numberposts'=>500,'orderby'=>'title','order'=>'ASC','fields'=>'ids'));$groups=array();
        foreach($posts as $id){$raw_title=trim(get_the_title($id));$title=remove_accents(function_exists('mb_strtolower')?mb_strtolower($raw_title):strtolower($raw_title));$title=preg_replace('/[^a-z0-9]+/',' ',$title);if($title){$key='t:'.trim($title);$groups[$key][]=$id;}$ext=trim((string)get_post_meta($id,'_bsw_p3_external_id',true));if($ext){$key='x:'.strtolower($ext);$groups[$key][]=$id;}}
        $out=array();foreach($groups as $key=>$ids){$ids=array_values(array_unique($ids));if(count($ids)>1){$out[]=array('reason'=>strpos($key,'x:')===0?'Same external identifier: '.substr($key,2):'Same normalised title','ids'=>$ids);if(count($out)>=$limit)break;}}return $out;
    }
    public static function duplicate_entity_notice() {
        if(!current_user_can('manage_options'))return;$screen=function_exists('get_current_screen')?get_current_screen():null;if(!$screen||$screen->id!=='bsw_entity_page_bsw-knowledge-tools')return;
        if(isset($_GET['bsw_imported']))echo '<div class="notice notice-success"><p>'.sprintf(esc_html__('%1$d draft entities imported. %2$d rows skipped. Review each draft before publishing.'),absint($_GET['bsw_imported']),absint($_GET['bsw_skipped']??0)).'</p></div>';
    }
    public static function record_media_fingerprint($id) {
        $file=get_attached_file($id);if(!$file||!is_file($file)||!is_readable($file))return;$size=@filesize($file);if(!$size||$size>50*1024*1024)return;
        $hash=@hash_file('sha256',$file);if(!$hash)return;update_post_meta($id,'_bsw_commons_sha256',$hash);
        $matches=get_posts(array('post_type'=>'attachment','post_status'=>'inherit','numberposts'=>3,'post__not_in'=>array($id),'meta_key'=>'_bsw_commons_sha256','meta_value'=>$hash,'fields'=>'ids'));
        if($matches)update_post_meta($id,'_bsw_commons_duplicate_candidates',$matches);
        $mime=get_post_mime_type($id);if($mime&&strpos($mime,'image/')===0&&function_exists('imagecreatefromstring')){$raw=@file_get_contents($file);$image=$raw?@imagecreatefromstring($raw):false;if($image){$small=imagecreatetruecolor(9,8);imagecopyresampled($small,$image,0,0,0,0,9,8,imagesx($image),imagesy($image));$bits='';for($y=0;$y<8;$y++)for($x=0;$x<8;$x++){$left=imagecolorat($small,$x,$y);$right=imagecolorat($small,$x+1,$y);$l=((($left>>16)&255)*299+(($left>>8)&255)*587+($left&255)*114)/1000;$r=((($right>>16)&255)*299+(($right>>8)&255)*587+($right&255)*114)/1000;$bits.=$l>$r?'1':'0';}imagedestroy($small);imagedestroy($image);update_post_meta($id,'_bsw_commons_dhash',$bits);$candidates=get_posts(array('post_type'=>'attachment','post_status'=>'inherit','numberposts'=>150,'post__not_in'=>array($id),'meta_key'=>'_bsw_commons_dhash','fields'=>'ids'));$visual=array();foreach($candidates as $candidate){$other=(string)get_post_meta($candidate,'_bsw_commons_dhash',true);if(strlen($other)!==64)continue;$distance=substr_count(decbin(bindec(substr($bits,0,32)) ^ bindec(substr($other,0,32))),'1')+substr_count(decbin(bindec(substr($bits,32,32)) ^ bindec(substr($other,32,32))),'1');if($distance<=8)$visual[]=$candidate;if(count($visual)>=5)break;}if($visual)update_post_meta($id,'_bsw_commons_visual_candidates',$visual);}}

    }
    public static function duplicate_media_notice() {
        if(!current_user_can('upload_files'))return;$screen=function_exists('get_current_screen')?get_current_screen():null;if(!$screen||$screen->id!=='attachment')return;$id=absint($_GET['post']??0);$matches=$id?get_post_meta($id,'_bsw_commons_duplicate_candidates',true):array();$visual=$id?get_post_meta($id,'_bsw_commons_visual_candidates',true):array();if((!$matches||!is_array($matches))&&(!$visual||!is_array($visual)))return;
        echo '<div class="notice notice-info"><p><strong>BIGSHOR Commons:</strong> An identical-file match may already exist: ';$links=array();foreach($matches as $mid)$links[]='<a href="'.esc_url(get_edit_post_link($mid)).'">'.esc_html(get_the_title($mid)).' (#'.absint($mid).')</a>';echo implode(', ',$links).'. ';if($visual){$vlinks=array();foreach($visual as $mid)$vlinks[]='<a href="'.esc_url(get_edit_post_link($mid)).'">'.esc_html(get_the_title($mid)).' (#'.absint($mid).')</a>';echo 'Visually similar candidates (perceptual hash): '.implode(', ',$vlinks).'. ';}echo 'Compare the original files, licensing and attribution before deciding whether to retain both records.</p></div>';
    }
    public static function add_language_box() { add_meta_box('bsw_entity_language_labels','BIGSHOR — Multilingual entity labels',array(__CLASS__,'language_box'),'bsw_entity','normal','default'); }
    public static function language_box($post) {
        wp_nonce_field('bsw_p3_language_'.$post->ID,'bsw_p3_language_nonce');
        $value=get_post_meta($post->ID,'_bsw_p3_language_labels',true);if(is_array($value)){$pairs=array();foreach($value as $code=>$label)$pairs[]=$code.':'.$label;$value=implode("\n",$pairs);}
        echo '<p>Optional display names by language. Use one per line in <code>language-code:label</code> format, for example <code>ar:الاسم</code> or <code>hi:नाम</code>.</p><textarea class="widefat" rows="4" name="bsw_p3_language_labels">'.esc_textarea($value).'</textarea><p class="description">These labels are editorial metadata; they do not automatically translate the article.</p>';
    }
    public static function save_language_labels($post_id,$post) {
        if(wp_is_post_revision($post_id)||(defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)||!current_user_can('edit_post',$post_id))return;
        if(empty($_POST['bsw_p3_language_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_p3_language_nonce'])),'bsw_p3_language_'.$post_id))return;
        $raw=sanitize_textarea_field(wp_unslash($_POST['bsw_p3_language_labels']??''));$lines=array();foreach(preg_split('/\r\n|\r|\n/',$raw) as $line){$parts=explode(':',$line,2);if(count($parts)!==2)continue;$lang=strtolower(trim($parts[0]));$label=sanitize_text_field(trim($parts[1]));if(preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/',$lang)&&$label)$lines[$lang]=$label;}update_post_meta($post_id,'_bsw_p3_language_labels',$lines);
    }
    public static function entity_label_shortcode($atts) {
        $a=shortcode_atts(array('id'=>0,'language'=>'en'),$atts);$id=absint($a['id']);if(!$id)$id=get_the_ID();if(get_post_type($id)!=='bsw_entity'||get_post_status($id)!=='publish')return '';$lang=sanitize_key(strtolower($a['language']));$labels=get_post_meta($id,'_bsw_p3_language_labels',true);$label=is_array($labels)&&!empty($labels[$lang])?$labels[$lang]:get_the_title($id);return esc_html($label);
    }
    public static function add_usage_box() { add_meta_box('bsw_entity_usage','BIGSHOR — Entity usage',array(__CLASS__,'usage_box'),'bsw_entity','side','default'); }
    public static function usage_box($post) {
        $url=get_permalink($post->ID);$count=0;$examples=array();$articles=get_posts(array('post_type'=>array('wiki_article','post','page'),'post_status'=>'publish','numberposts'=>200,'s'=>get_the_title($post->ID),'fields'=>'ids'));
        foreach($articles as $aid){$content=get_post_field('post_content',$aid);if(stripos($content,$url)!==false||stripos($content,'post='.absint($post->ID))!==false){$count++;if(count($examples)<8)$examples[]=$aid;}}
        echo '<p>Potential published content references found: <strong>'.absint($count).'</strong></p>';if($examples){echo '<ul>';foreach($examples as $aid)echo '<li><a href="'.esc_url(get_edit_post_link($aid)).'">'.esc_html(get_the_title($aid)).'</a></li>';echo '</ul>';}echo '<p class="description">This is a title-based search sample, not a complete or guaranteed usage index.</p>';
    }
    public static function graph_shortcode($atts) {
        $a=shortcode_atts(array('id'=>0,'depth'=>1),$atts);$id=absint($a['id']);if(!$id)$id=get_the_ID();if(get_post_type($id)!=='bsw_entity'||get_post_status($id)!=='publish')return '';$rows=get_post_meta($id,'_bsw_p3_relationships',true);if(!is_array($rows))$rows=array();$depth=max(1,min(1,absint($a['depth'])));
        $out='<section class="bsw-knowledge-graph" aria-label="Knowledge graph"><h2>Knowledge connections</h2><div class="bsw-graph-center"><strong><a href="'.esc_url(get_permalink($id)).'">'.esc_html(get_the_title($id)).'</a></strong></div><ul class="bsw-graph-links">';$n=0;foreach($rows as $r){$target=absint($r['target']);if(get_post_type($target)!=='bsw_entity'||get_post_status($target)!=='publish')continue;$out.='<li><span>'.esc_html($r['label']).'</span> <span aria-hidden="true">→</span> <a href="'.esc_url(get_permalink($target)).'">'.esc_html(get_the_title($target)).'</a></li>';$n++;}if(!$n)$out.='<li>No published connections yet.</li>';$out.='</ul></section>';return $out;
    }
    public static function commons_collection_shortcode($atts) {
        $a=shortcode_atts(array('collection'=>'','limit'=>24,'rights'=>'verified'),$atts);$args=array('post_type'=>'attachment','post_status'=>'inherit','posts_per_page'=>max(1,min(60,absint($a['limit']))),'post_mime_type'=>'image','orderby'=>'date','order'=>'DESC');
        if($a['collection'])$args['tax_query']=array(array('taxonomy'=>'bsw_commons_collection','field'=>'slug','terms'=>sanitize_title($a['collection'])));
        if($a['rights']==='verified')$args['meta_query']=array(array('key'=>'_bsw_commons_rights_status','value'=>'verified'));
        $q=new WP_Query($args);if(!$q->have_posts())return '<p>No Commons media items match this collection.</p>';$out='<div class="bsw-commons-collection">';while($q->have_posts()){$q->the_post();$id=get_the_ID();$out.='<figure>'.wp_get_attachment_image($id,'medium').'<figcaption>'.esc_html(get_the_title($id));$creator=get_post_meta($id,'_bsw_commons_creator',true);$license=get_post_meta($id,'_bsw_commons_license',true);if($creator)$out.=' — '.esc_html($creator);if($license)$out.=' · '.esc_html($license);$out.='</figcaption></figure>'; }wp_reset_postdata();return $out.'</div>';
    }
    public static function media_columns($cols){$cols['bsw_commons_rights']='Rights review';$cols['bsw_commons_hash']='Duplicate check';return $cols;}
    public static function media_column($column,$id){if($column==='bsw_commons_rights'){$s=get_post_meta($id,'_bsw_commons_rights_status',true);echo $s==='verified'?'<span style="color:green">Verified by editor</span>':'<span style="color:#996800">Review pending</span>';}if($column==='bsw_commons_hash'){$m=get_post_meta($id,'_bsw_commons_duplicate_candidates',true);echo $m?'Possible duplicate':'No match recorded';}}
}
BSW_Phase3_Extended::boot();
}
