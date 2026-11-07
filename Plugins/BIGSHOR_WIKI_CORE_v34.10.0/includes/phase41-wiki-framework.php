<?php
/**
 * Phase 41: Structured Wikipedia-style article framework.
 * Additive extension of the existing BIGSHOR Wiki Core article model.
 */
if (!defined('ABSPATH')) exit;
final class BSW_Phase41_Wiki_Framework {
    public static function init() {
        if (class_exists('BSW_Phase40_Article_Experience')) remove_filter('the_content', array('BSW_Phase40_Article_Experience', 'article_shell'), 8);
        if (function_exists('bsw_v3_auto_toc')) remove_filter('the_content', 'bsw_v3_auto_toc', 12);
        add_action('init', array(__CLASS__, 'save'), 0);
        add_filter('the_content', array(__CLASS__, 'structure_article'), 12);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'styles'));
    }
    /** @deprecated Editor shortcode ownership moved to Phase 44. */
    public static function replace_legacy_editor() {
        // Intentionally retained as a no-op for backward compatibility.
    }
    public static function styles() {
        if (!is_singular('wiki_article')) return;
        $css = '.bsw41-wrap{max-width:1200px;margin:0 auto}.bsw41-tabs{display:flex;gap:0;flex-wrap:wrap;border-bottom:1px solid #c8ccd1;margin:12px 0 22px}.bsw41-tabs a{padding:11px 16px;color:inherit;text-decoration:none;border-bottom:3px solid transparent}.bsw41-tabs a:hover,.bsw41-tabs a.active{border-color:#202122}.bsw41-layout{display:grid;grid-template-columns:minmax(0,1fr) 290px;gap:28px;align-items:start}.bsw41-infobox{border:1px solid #a2a9b1;background:#f8f9fa;padding:14px;font-size:14px}.bsw41-infobox h2{font-size:18px;text-align:center;margin:0 0 12px}.bsw41-infobox table{width:100%;border-collapse:collapse}.bsw41-infobox th,.bsw41-infobox td{border-top:1px solid #c8ccd1;padding:7px;text-align:left;vertical-align:top}.bsw41-toc{border:1px solid #a2a9b1;background:#f8f9fa;padding:12px 16px;margin:18px 0;max-width:440px}.bsw41-toc ol{margin:8px 0 0;padding-left:24px}.bsw41-toc ol ol{margin:2px 0}.bsw41-section-tools{font-size:12px;font-weight:400;margin-left:10px;white-space:nowrap}.bsw41-section-tools a{color:#54595d;text-decoration:none}.bsw41-editor{max-width:1000px;margin:20px auto;padding:20px;border:1px solid #c8ccd1;background:#fff;color:#202122}.bsw41-editor label{display:block;font-weight:600;margin:14px 0 6px}.bsw41-editor input[type=text],.bsw41-editor textarea{width:100%;max-width:100%;padding:10px;border:1px solid #a2a9b1}.bsw41-editor textarea{min-height:320px;font-family:ui-monospace,monospace}.bsw41-actions{display:flex;gap:10px;flex-wrap:wrap;margin:16px 0}.bsw41-actions button{padding:10px 16px;background:#202122;color:white;border:0;cursor:pointer}.bsw41-note{padding:12px;background:#f8f9fa;border-left:4px solid #36c;margin:12px 0}.bsw41-history li{margin:8px 0}@media(max-width:760px){.bsw41-layout{grid-template-columns:1fr}.bsw41-infobox{order:-1}.bsw41-tabs a{padding:10px 11px;font-size:13px}.bsw41-editor{padding:12px}}';
        wp_register_style('bsw41-framework', false, array(), '30.1.0'); wp_enqueue_style('bsw41-framework'); wp_add_inline_style('bsw41-framework', $css);
    }
    private static function tabs($id) {
        $base=get_permalink($id); $edit=current_user_can('edit_post',$id);
        $items=array('Read'=>$base); if($edit){$items['Edit']=add_query_arg('bsw_edit',$id,$base);$items['Edit source']=add_query_arg(array('bsw_edit'=>$id,'bsw_source'=>1),$base);}
        $items['View history']=add_query_arg('bsw_history',1,$base); $items['Talk']=$base.'#comments';
        $active=isset($_GET['bsw_history'])?'View history':(isset($_GET['bsw_edit'])?(isset($_GET['bsw_source'])?'Edit source':'Edit'):'Read');
        $o='<nav class="bsw41-tabs" aria-label="Article actions">'; foreach($items as $label=>$url){$o.='<a class="'.($active===$label?'active':'').'" href="'.esc_url($url).'">'.esc_html($label).'</a>'; } return $o.'</nav>';
    }
    private static function headings($content) {
        $items=array(); if(preg_match_all('/<h([2-4])\b[^>]*>(.*?)<\/h\1>/is',$content,$m,PREG_OFFSET_CAPTURE)) foreach($m[0] as $i=>$whole){$label=trim(wp_strip_all_tags($m[2][$i][0]));$level=(int)$m[1][$i][0];$id='bsw-section-'.(count($items)+1);$items[]=array('level'=>$level,'title'=>$label,'id'=>$id,'offset'=>$whole[1],'length'=>strlen($whole[0]),'html'=>$whole[0]);}
        return $items;
    }
    private static function toc($items,$id) {
        if(!$items)return ''; $o='<nav class="bsw41-toc" aria-label="Contents"><strong>Contents</strong><ol>';
        foreach($items as $n=>$it){$o.='<li class="bsw41-level-'.(int)$it['level'].'"><a href="#'.$it['id'].'">'.esc_html($it['title']).'</a>';
            if(current_user_can('edit_post',$id))$o.=' <span class="bsw41-section-tools"><a href="'.esc_url(add_query_arg(array('bsw_edit'=>$id,'bsw_section'=>$n),get_permalink($id))).'">[edit]</a></span>';
            $o.='</li>'; }
        return $o.'</ol></nav>';
    }
    private static function infobox($id) {
        $d=get_post_meta($id,'_bsw_infobox',true); if(!is_array($d)||!$d){$legacy=get_post_meta($id,'_bsw_p1_infobox_rows',true);if(is_array($legacy)&&$legacy){$fields=array();foreach($legacy as $row){if(!empty($row['label']))$fields[sanitize_key($row['label'])]=$row['value']??'';}$d=array('title'=>get_post_meta($id,'_bsw_p1_infobox_title',true)?:get_the_title($id),'image'=>get_post_meta($id,'_bsw_p1_infobox_image',true),'fields'=>$fields);}} if(!is_array($d)||!$d)return '';
        $title=isset($d['title'])?$d['title']:get_the_title($id); $fields=isset($d['fields'])&&is_array($d['fields'])?$d['fields']:$d; $o='<aside class="bsw41-infobox"><h2>'.esc_html($title).'</h2>';
        if(!empty($d['image'])&&wp_http_validate_url($d['image']))$o.='<img style="width:100%;height:auto" src="'.esc_url($d['image']).'" alt="'.esc_attr($title).'">';
        $o.='<table><tbody>';foreach($fields as $k=>$v){if(in_array((string)$k,array('title','image','fields'),true)||is_array($v)||is_object($v)||$v==='')continue;$o.='<tr><th>'.esc_html(ucwords(str_replace(array('_','-'),' ',(string)$k))).'</th><td>'.esc_html(wp_strip_all_tags((string)$v)).'</td></tr>'; } return $o.'</tbody></table></aside>';
    }
    public static function structure_article($content) {
        if(is_admin()||!is_singular('wiki_article')||!in_the_loop()||!is_main_query())return $content; $id=get_the_ID();
        if(isset($_GET['bsw_edit'])&&absint($_GET['bsw_edit'])===$id){$editor=class_exists('BSW_Phase42_Wikitext')?BSW_Phase42_Wikitext::editor($id):self::editor($id);return '<div class="bsw41-wrap">'.self::tabs($id).$editor.'</div>'; }
        if(isset($_GET['bsw_history']))return '<div class="bsw41-wrap">'.self::tabs($id).self::history($id).'</div>';
        $items=self::headings($content); foreach(array_reverse($items) as $it){$content=substr_replace($content,'<h'.(int)$it['level'].' id="'.esc_attr($it['id']).'">'.wp_kses_post(wp_strip_all_tags($it['title'])).'</h'.(int)$it['level'].'>',$it['offset'],$it['length']);}
        $thumb=get_the_post_thumbnail($id,'large'); $lead='<div class="bsw41-wrap">'.self::tabs($id).'<div class="bsw41-layout"><main class="bsw41-main">';
        if(has_excerpt($id))$lead.='<p class="bsw41-lead">'.esc_html(get_the_excerpt($id)).'</p>';
        $lead.=self::toc($items,$id).$content.'</main>'.self::infobox($id).'</div></div>'; return $lead;
    }
    public static function editor($id=0) {
        if (!is_numeric($id)) $id=0; $id=$id?: (isset($_GET['bsw_edit'])?absint($_GET['bsw_edit']):get_the_ID()); $p=get_post($id);
        if(!$p||$p->post_type!=='wiki_article'||!current_user_can('edit_post',$id))return '<div class="bsw41-note">You do not have permission to edit this article.</div>';
        $source=isset($_GET['bsw_source']); $section=isset($_GET['bsw_section'])?absint($_GET['bsw_section']):null; $content=$p->post_content; $items=self::headings($content); $is_section=$section!==null&&isset($items[$section]);
        if($is_section){$start=$items[$section]['offset'];$end=isset($items[$section+1])?$items[$section+1]['offset']:strlen($content);$value=substr($content,$start,$end-$start);}
        else $value=$content;
        $plain=wp_strip_all_tags($p->post_content);$paragraphs=preg_split('/\n+|(?<=\.)\s{2,}/',$plain);$seen=array();$duplicates=array();foreach($paragraphs as $para){$norm=strtolower(trim(preg_replace('/\s+/',' ',$para)));if(strlen($norm)>80){if(isset($seen[$norm]))$duplicates[]=$norm;else $seen[$norm]=1;}}
        ob_start(); echo '<section class="bsw41-editor"><h2>'.($is_section?'Edit section: '.esc_html($items[$section]['title']):($source?'Edit source':'Edit article')).'</h2><p>Edits are checked against your WordPress capabilities and BIGSHOR editorial workflow. Saving a section preserves the rest of the article. Source mode edits the stored WordPress HTML; it is not a MediaWiki wikitext parser.</p>';
        if($duplicates){echo '<div class="bsw41-note"><strong>Possible repeated content detected.</strong> Review these repeated passages before saving; intentional summaries are allowed and nothing is removed automatically.<ul>';foreach(array_slice(array_unique($duplicates),0,5) as $dup)echo '<li>'.esc_html(wp_trim_words($dup,22)).'</li>';echo '</ul></div>'; }
        if(isset($_GET['bsw_saved']))echo '<div class="bsw41-note">Changes saved. '.(current_user_can('manage_options')?'The administrator update is published.':'Your changes have been submitted for editorial review.').'</div>';
        echo '<form method="post">'.wp_nonce_field('bsw41_save_'.$id,'bsw41_nonce',true,false).'<input type="hidden" name="bsw41_action" value="save"><input type="hidden" name="bsw41_id" value="'.esc_attr($id).'"><input type="hidden" name="bsw41_modified" value="'.esc_attr($p->post_modified_gmt).'"><input type="hidden" name="bsw41_section" value="'.($is_section?esc_attr($section):'-1').'">';
        if(!$is_section){echo '<label>Article title</label><input type="text" name="bsw41_title" required value="'.esc_attr($p->post_title).'">';echo '<label>Article summary / lead</label><textarea name="bsw41_excerpt" style="min-height:90px">'.esc_textarea($p->post_excerpt).'</textarea>';}
        echo '<label>'.($source?'Source (HTML)':'Article content').'</label>';
        if(!$source&&!$is_section){wp_editor($value,'bsw41_content',array('textarea_name'=>'bsw41_content','textarea_rows'=>24,'media_buttons'=>current_user_can('upload_files'),'tinymce'=>true,'quicktags'=>true));}
        else echo '<textarea name="bsw41_content" spellcheck="true">'.esc_textarea($value).'</textarea>';
        if(!$is_section&&current_user_can('manage_options')){ $ib=get_post_meta($id,'_bsw_infobox',true); if(!is_array($ib))$ib=array(); echo '<h3>Infobox</h3><label>Infobox title</label><input type="text" name="bsw41_box_title" value="'.esc_attr($ib['title']??'').'">';echo '<label>Image URL</label><input type="text" name="bsw41_box_image" value="'.esc_attr($ib['image']??'').'">';echo '<label>Fields (one Label: Value per line)</label><textarea name="bsw41_box_fields" style="min-height:110px">';$fs=$ib['fields']??array();foreach($fs as $k=>$v)echo esc_textarea($k.': '.$v."\n");echo '</textarea>'; }
        echo '<div class="bsw41-actions"><button type="submit">'.(current_user_can('manage_options')?'Save changes':'Submit for review').'</button><a href="'.esc_url(get_permalink($id)).'">Cancel</a></div></form></section>'; return ob_get_clean();
    }
    public static function save() {
        if(empty($_POST['bsw41_action'])||$_POST['bsw41_action']!=='save')return; $id=absint($_POST['bsw41_id']??0);$p=get_post($id);
        if(!$p||$p->post_type!=='wiki_article'||!current_user_can('edit_post',$id))wp_die('You are not allowed to edit this article.',403);
        if(!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw41_nonce']??'')),'bsw41_save_'.$id))wp_die('Security check failed.',403);
        if(!hash_equals((string)$p->post_modified_gmt,(string)sanitize_text_field(wp_unslash($_POST['bsw41_modified']??''))))wp_die('This article changed after you opened the editor. Reload the page and reapply your changes to avoid overwriting another editor.',409);
        $new=isset($_POST['bsw41_content'])?wp_kses_post(wp_unslash($_POST['bsw41_content'])):''; $sec=isset($_POST['bsw41_section'])?(int)$_POST['bsw41_section']:-1; $data=array('ID'=>$id);
        if($sec>=0){$items=self::headings($p->post_content);if(!isset($items[$sec]))wp_die('The selected section no longer exists. Reload the article.',409);$start=$items[$sec]['offset'];$end=isset($items[$sec+1])?$items[$sec+1]['offset']:strlen($p->post_content);$data['post_content']=substr($p->post_content,0,$start).$new.substr($p->post_content,$end);}
        else{$data['post_content']=$new;$data['post_title']=sanitize_text_field(wp_unslash($_POST['bsw41_title']??$p->post_title));$data['post_excerpt']=sanitize_textarea_field(wp_unslash($_POST['bsw41_excerpt']??''));}
        $admin=current_user_can('manage_options')&&current_user_can('publish_posts');$data['post_status']=$admin?'publish':'pending';$result=wp_update_post(wp_slash($data),true);if(is_wp_error($result))wp_die(esc_html($result->get_error_message()),500);
        if($sec<0&&$admin&&isset($_POST['bsw41_box_fields'])){$fields=array();$lines=preg_split('/\r\n|\r|\n/',sanitize_textarea_field(wp_unslash($_POST['bsw41_box_fields'])));foreach($lines as $line){$pair=explode(':',$line,2);if(count($pair)===2&&trim($pair[0])!=='')$fields[sanitize_key(trim($pair[0]))]=sanitize_text_field(trim($pair[1]));}$box=array('title'=>sanitize_text_field(wp_unslash($_POST['bsw41_box_title']??'')),'image'=>esc_url_raw(wp_unslash($_POST['bsw41_box_image']??'')),'fields'=>$fields);update_post_meta($id,'_bsw_infobox',$box);}
        wp_safe_redirect(add_query_arg('bsw_saved',1,get_permalink($id)));exit;
    }
    private static function history($id) {
        $revs=wp_get_post_revisions($id,array('posts_per_page'=>50));$o='<h1>Article history</h1><p>Revision history is provided by WordPress revisions.</p><ol class="bsw41-history">';if(!$revs)return $o.'<li>No revisions available.</li></ol>';
        foreach($revs as $r){$u=get_author_posts_url($r->post_author);$o.='<li>'.esc_html(get_date_from_gmt($r->post_modified_gmt,get_option('date_format').' '.get_option('time_format'))).' — <a href="'.esc_url($u).'">'.esc_html(get_the_author_meta('display_name',$r->post_author)).'</a> — '.esc_html($r->post_title);if(current_user_can('edit_post',$id))$o.=' <a href="'.esc_url(admin_url('revision.php?revision='.$r->ID)).'">Compare / restore</a>'; $o.='</li>'; }return $o.'</ol>';
    }
}
BSW_Phase41_Wiki_Framework::init();
