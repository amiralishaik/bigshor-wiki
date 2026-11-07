<?php
/** Phase 42: safe MediaWiki-style source editing for BIGSHOR Wiki. */
if (!defined('ABSPATH')) exit;
final class BSW_Phase42_Wikitext {
    private static $refs = array();
    public static function init() { add_action('init', array(__CLASS__, 'save'), 0); }
    /** @deprecated Editor shortcode ownership moved to Phase 44. */
    public static function register() {
        // Intentionally retained as a no-op for backward compatibility.
    }
    private static function headings($content) {
        $items = array();
        if (preg_match_all('/<h([2-4])\b[^>]*>(.*?)<\/h\1>/is', $content, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as $i => $whole) $items[] = array('title'=>trim(wp_strip_all_tags($m[2][$i][0])), 'level'=>(int)$m[1][$i][0], 'offset'=>$whole[1]);
        }
        return $items;
    }
    public static function editor($id = 0) {
        $id = absint($id ?: (isset($_GET['bsw_edit']) ? $_GET['bsw_edit'] : get_the_ID())); $p = get_post($id);
        if (!$p || $p->post_type !== 'wiki_article' || !is_user_logged_in() || !current_user_can('edit_post', $id)) return '<div class="bsw41-note">You do not have permission to edit this article.</div>';
        $source = isset($_GET['bsw_source']); $section = isset($_GET['bsw_section']) ? absint($_GET['bsw_section']) : null;
        $heads = self::headings($p->post_content); $is_section = $section !== null && isset($heads[$section]); $value = $p->post_content;
        if ($is_section) { $start=$heads[$section]['offset']; $end=strlen($value); $level=$heads[$section]['level']; for($j=$section+1;$j<count($heads);$j++){if($heads[$j]['level'] <= $level){$end=$heads[$j]['offset'];break;}} $value=substr($value,$start,$end-$start); }
        if ($source) $value=self::html_to_wikitext($value);
        ob_start();
        echo '<section class="bsw41-editor bsw42-editor"><h2>'.esc_html($is_section?'Edit section: '.$heads[$section]['title']:($source?'Edit source (wikitext)':'Edit article')).'</h2>';
        echo '<p>Source mode supports headings, bold and italic text, internal and external links, lists and references. Content is converted to safe HTML and saved as a normal WordPress revision.</p>';
        if(isset($_GET['bsw_saved'])) echo '<div class="bsw41-note">Changes saved. '.(current_user_can('manage_options')?'The administrator update is published.':'Your changes have been submitted for editorial review.').'</div>';
        echo '<form method="post">'.wp_nonce_field('bsw42_save_'.$id,'bsw42_nonce',true,false);
        echo '<input type="hidden" name="bsw42_action" value="save"><input type="hidden" name="bsw42_id" value="'.esc_attr($id).'">';
        echo '<input type="hidden" name="bsw42_modified" value="'.esc_attr($p->post_modified_gmt).'"><input type="hidden" name="bsw42_section" value="'.($is_section?esc_attr($section):'-1').'">';
        echo '<input type="hidden" name="bsw42_mode" value="'.($source?'wikitext':'visual').'">';
        if(!$is_section){ echo '<label>Article title</label><input type="text" name="bsw42_title" required value="'.esc_attr($p->post_title).'">'; echo '<label>Article summary / lead</label><textarea name="bsw42_excerpt" rows="3">'.esc_textarea($p->post_excerpt).'</textarea>'; }
        echo '<label>'.($source?'Wikitext source':'Visual article content').'</label>';
        if(!$source&&!$is_section) wp_editor($value,'bsw42_content',array('textarea_name'=>'bsw42_content','textarea_rows'=>24,'media_buttons'=>current_user_can('upload_files'),'tinymce'=>true,'quicktags'=>true));
        else echo '<textarea name="bsw42_content" spellcheck="true" style="min-height:420px">'.esc_textarea($value).'</textarea>';
        if($source) echo '<p>Examples: <code>== Career ==</code>, <code>\'\'\'bold\'\'\'</code>, <code>[[Article|label]]</code>, <code>[https://example.com source]</code>, <code>&lt;ref&gt;Source&lt;/ref&gt;</code>, <code>&lt;references /&gt;</code>.</p>';
        if(!$is_section && current_user_can('edit_post', $id)) {
            $ib = get_post_meta($id, '_bsw_infobox', true);
            if (!is_array($ib)) $ib = array();
            $ib_fields = isset($ib['fields']) && is_array($ib['fields']) ? $ib['fields'] : array();
            $ib_lines = array();
            foreach ($ib_fields as $ib_key=>$ib_value) if (is_scalar($ib_value) && (string)$ib_value !== '') $ib_lines[] = ucwords(str_replace(array('_','-'),' ',(string)$ib_key)).': '.(string)$ib_value;
            echo '<h3>Article infobox</h3><label>Infobox heading</label><input type="text" name="bsw42_box_title" value="'.esc_attr($ib['title']??'').'">';
            echo '<label>Image URL</label><input type="url" name="bsw42_box_image" value="'.esc_attr(is_string($ib['image']??'')?$ib['image']:'').'">';
            echo '<label>Fields (one Label: Value per line)</label><textarea name="bsw42_box_fields" rows="8">'.esc_textarea(implode("\n",$ib_lines)).'</textarea>';
        }
        echo '<label for="bsw42_edit_summary">Edit summary</label><input id="bsw42_edit_summary" type="text" name="bsw42_edit_summary" maxlength="240" placeholder="Briefly describe what you changed">';
        echo '<div class="bsw46-editor-tools"><button type="button" class="bsw46-preview-toggle">Preview</button><button type="button" class="bsw46-recovery-clear">Clear local recovery</button><span class="bsw46-save-state" aria-live="polite">Local recovery is enabled.</span></div><div class="bsw46-preview" hidden></div>';
        echo '<div class="bsw41-actions"><button type="submit">'.(current_user_can('manage_options')?'Save changes':'Submit for review').'</button><a href="'.esc_url(get_permalink($id)).'">Cancel</a></div></form></section>';
        return ob_get_clean();
    }
    public static function save() {
        if(empty($_POST['bsw42_action'])||$_POST['bsw42_action']!=='save') return;
        $id=absint($_POST['bsw42_id']??0); $p=get_post($id);
        if(!$p||$p->post_type!=='wiki_article'||!current_user_can('edit_post',$id)) wp_die('You are not allowed to edit this article.',403);
        if(!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw42_nonce']??'')),'bsw42_save_'.$id)) wp_die('Security check failed.',403);
        $modified=sanitize_text_field(wp_unslash($_POST['bsw42_modified']??''));
        if(!hash_equals((string)$p->post_modified_gmt,(string)$modified)) wp_die('This article changed after you opened the editor. Reload and reapply your changes to avoid overwriting another editor.',409);
        $raw=wp_unslash($_POST['bsw42_content']??''); $mode=sanitize_key(wp_unslash($_POST['bsw42_mode']??'visual'));
        $new=$mode==='wikitext'?self::wikitext_to_html($raw):wp_kses_post($raw); $sec=(int)($_POST['bsw42_section']??-1); $data=array('ID'=>$id);
        if($sec>=0){ $heads=self::headings($p->post_content); if(!isset($heads[$sec])) wp_die('The selected section no longer exists. Reload the article.',409); $start=$heads[$sec]['offset']; $end=strlen($p->post_content); $level=$heads[$sec]['level']; for($j=$sec+1;$j<count($heads);$j++){if($heads[$j]['level'] <= $level){$end=$heads[$j]['offset'];break;}} $data['post_content']=substr($p->post_content,0,$start).$new.substr($p->post_content,$end); }
        else { $data['post_content']=$new; $data['post_title']=sanitize_text_field(wp_unslash($_POST['bsw42_title']??$p->post_title)); $data['post_excerpt']=sanitize_textarea_field(wp_unslash($_POST['bsw42_excerpt']??'')); }
        $admin=current_user_can('manage_options')&&current_user_can('publish_posts'); $data['post_status']=$admin?'publish':'pending';
        $result=wp_update_post(wp_slash($data),true); if(is_wp_error($result)) wp_die(esc_html($result->get_error_message()),500);
        // Keep [[Category:...]] declarations and the BIGSHOR topic taxonomy in sync.
        if ($mode === 'wikitext' && class_exists('BSW_Phase47_Parser') && method_exists('BSW_Phase47_Parser', 'sync_categories')) {
            BSW_Phase47_Parser::sync_categories($id, $raw);
        }
        if($sec < 0 && current_user_can('edit_post', $id) && isset($_POST['bsw42_box_fields'])) {
            $box_fields = array();
            $box_raw = sanitize_textarea_field(wp_unslash($_POST['bsw42_box_fields']));
            foreach (preg_split('/\r\n|\r|\n/', $box_raw) as $box_line) {
                if (strpos($box_line, ':') === false) continue;
                list($box_label, $box_value) = array_map('trim', explode(':', $box_line, 2));
                if ($box_label === '' || $box_value === '') continue;
                $box_key = sanitize_key(str_replace(' ', '_', $box_label));
                if ($box_key !== '') $box_fields[$box_key] = sanitize_text_field($box_value);
            }
            $box_title = sanitize_text_field(wp_unslash($_POST['bsw42_box_title'] ?? ''));
            $box_image = esc_url_raw(wp_unslash($_POST['bsw42_box_image'] ?? ''));
            if ($box_title === '' && $box_image === '' && !$box_fields) delete_post_meta($id, '_bsw_infobox');
            else update_post_meta($id, '_bsw_infobox', array('title'=>$box_title,'image'=>$box_image,'fields'=>$box_fields));
        }
        $edit_summary=sanitize_text_field(wp_unslash($_POST['bsw42_edit_summary']??'')); if($edit_summary!==''){ update_post_meta($id,'_bsw_last_edit_summary',$edit_summary); update_post_meta($id,'_bsw_last_edit_summary_user',get_current_user_id()); update_post_meta($id,'_bsw_last_edit_summary_time',current_time('mysql',true)); }
        wp_safe_redirect(add_query_arg('bsw_saved','1',get_permalink($id))); exit;
    }
    public static function wikitext_to_html($source) {
        // Phase 4 parser is the canonical renderer; retain the legacy implementation below as a compatibility fallback.
        if (class_exists('BSW_Phase47_Parser')) return BSW_Phase47_Parser::render($source);
        self::$refs=array(); $source=str_replace(array("\r\n","\r"),"\n",(string)$source);
        // First collect complete definitions, then replace every reference call.
        $source=preg_replace_callback('/<ref\s+name=["\']([^"\']+)["\']\s*>(.*?)<\/ref>/is',function($m){
            $key=sanitize_key($m[1]); if($key!=='') self::$refs[$key]=trim(wp_strip_all_tags($m[2])); return '@@BSWREFDEF_'.rawurlencode($key).'@@';
        },$source);
        $source=preg_replace_callback('/<ref\s+name=["\']([^"\']+)["\']\s*\/>/i',function($m){
            $key=sanitize_key($m[1]); return isset(self::$refs[$key])?'@@BSWREF_'.(array_search($key,array_keys(self::$refs),true)+1).'@@':'';
        },$source);
        $source=preg_replace_callback('/<ref\s*>(.*?)<\/ref>/is',function($m){
            $key='ref-'.(count(self::$refs)+1); self::$refs[$key]=trim(wp_strip_all_tags($m[1])); return '@@BSWREF_'.count(self::$refs).'@@';
        },$source);
        $source=preg_replace_callback('/@@BSWREFDEF_([^@]+)@@/',function($m){
            $key=rawurldecode($m[1]); return isset(self::$refs[$key])?'@@BSWREF_'.(array_search($key,array_keys(self::$refs),true)+1).'@@':'';
        },$source);
        $source=preg_replace_callback('/\{\{Infobox\s+([a-z0-9 _-]+)\s*\|(.*?)\}\}/is',function($m){$rows=preg_split('/\s*\|\s*(?=[a-z0-9 _-]+\s*=)/i',$m[2]);$out='<table class="bsw43-infobox"><caption>'.esc_html(ucwords($m[1])).'</caption><tbody>';foreach($rows as $row){if(strpos($row,'=')===false)continue;list($k,$v)=array_map('trim',explode('=',$row,2));if($k==='')continue;$out.='<tr><th>'.esc_html(ucwords(str_replace('_',' ',$k))).'</th><td>'.esc_html(wp_strip_all_tags($v)).'</td></tr>';}return $out.'</tbody></table>';},$source);
        $source=preg_replace('/<references\s*\/?\s*>/i','@@BSWREFLIST@@',$source);
        $lines=explode("\n",$source);$html='';$para=array();$list='';$in_table=false;
        $flush=function()use(&$html,&$para){if($para){$html.='<p>'.self::inline_markup(implode(' ',$para)).'</p>';$para=array();}};
        foreach($lines as $line){$t=trim($line);if($t==='@@BSWREFLIST@@'){$flush();if($list){$html.='</'.$list.'>';$list='';}$html.='<ol class="bsw42-references">';foreach(self::$refs as $ref)$html.='<li>'.esc_html($ref).'</li>';$html.='</ol>';continue;}if($t===''){$flush();if($list){$html.='</'.$list.'>';$list='';}continue;}
            if(preg_match('/^\{\|/',$t)){$flush();if($list){$html.='</'.$list.'>';$list='';}$html.='<table class="bsw43-wikitable"><tbody>';$in_table=true;continue;}
            if(preg_match('/^\|\}/',$t)){$html.='</tbody></table>'; $in_table=false; continue;}
            if(strpos($t,'|}')===0)continue;
            if($in_table && preg_match('/^\\|[-+]/', $t)) continue;
            if(isset($in_table) && $in_table && ($t[0]==='|' || $t[0]==='!')){$header=$t[0]==='!';$delimiter=$header?'!!':'||';$cells=preg_split('/'.preg_quote($delimiter,'/').'/',substr($t,1));$tag=$header?'th':'td';$html.='<tr>';foreach($cells as $cell){$cell=preg_replace('/^[^|]*\|/','',$cell);$html.='<'.$tag.'>'.self::inline_markup(trim($cell)).'</'.$tag.'>';}$html.='</tr>';continue;}
            if(preg_match('/^(={2,4})\s*(.*?)\s*\1$/',$t,$m)){$flush();if($list){$html.='</'.$list.'>';$list='';}$n=min(4,strlen($m[1]));$html.='<h'.$n.'>'.self::inline_markup($m[2]).'</h'.$n.'>';continue;}
            if(preg_match('/^([*#]+)\s*(.*)$/',$t,$m)){$flush();$tag=$m[1][0]==='*'?'ul':'ol';if($list&&$list!==$tag)$html.='</'.$list.'>';if(!$list||$list!==$tag)$html.='<'.$tag.'>';$list=$tag;$html.='<li>'.self::inline_markup($m[2]).'</li>';continue;}
            if($list){$html.='</'.$list.'>';$list='';}$para[]=$t;
        }
        $flush();if($list)$html.='</'.$list.'>';if($in_table)$html.='</tbody></table>'; $html=wp_kses_post($html); foreach(array_keys(self::$refs) as $i=>$key){$n=$i+1;$html=str_replace('@@BSWREF_'.$n.'@@','<sup class="bsw42-ref">['.$n.']</sup>',$html);} if(strpos($html,'@@BSWREFLIST@@')!==false){$list_html='<ol class="bsw42-references">';foreach(self::$refs as $ref)$list_html.='<li>'.esc_html($ref).'</li>';$list_html.='</ol>';$html=str_replace('@@BSWREFLIST@@',$list_html,$html);} return $html;
    }
    private static function inline_markup($text) {
        // Resolve wiki file links only to existing WordPress attachment records.
        $text=preg_replace_callback('/\[\[File:([^\]|]+)(?:\|([^\]]*))?\]\]/i',function($m){
            $file=trim($m[1]); $opts=isset($m[2])?array_map('trim',explode('|',$m[2])):array();
            $attachment=0;
            if(ctype_digit($file)) $attachment=absint($file);
            else {
                $base=sanitize_title(pathinfo($file,PATHINFO_FILENAME));
                $found=get_posts(array('post_type'=>'attachment','post_status'=>'inherit','name'=>$base,'numberposts'=>1));
                if($found) $attachment=(int)$found[0]->ID;
            }
            if(!$attachment || get_post_type($attachment)!=='attachment') return '';
            $url=wp_get_attachment_image_url($attachment,'large');
            if(!$url) return '';
            $alt=get_post_meta($attachment,'_wp_attachment_image_alt',true);
            $caption=wp_get_attachment_caption($attachment);
            foreach($opts as $opt) if(strpos($opt,'alt=')===0) $alt=substr($opt,4);
            foreach($opts as $opt) if($opt!=='' && !in_array(strtolower($opt),array('thumb','thumbnail','right','left','center','frameless'),true) && strpos($opt,'alt=')!==0) $caption=$opt;
            return '<figure class="bsw42-wiki-file"><img src="'.esc_url($url).'" alt="'.esc_attr($alt).'" loading="lazy">'.($caption!==''?'<figcaption>'.esc_html($caption).'</figcaption>':'').'</figure>';
        },$text);
        $text=esc_html($text);
        $text=preg_replace_callback('/\[\[([^\]|]+)(?:\|([^\]]+))?\]\]/',function($m){$slug=sanitize_title($m[1]);$posts=get_posts(array('post_type'=>'wiki_article','name'=>$slug,'post_status'=>'publish','numberposts'=>1));$url=$posts?get_permalink($posts[0]):add_query_arg('s',rawurlencode($m[1]),home_url('/'));return '<a href="'.esc_url($url).'">'.esc_html($m[2]??$m[1]).'</a>';},$text);
        $text=preg_replace_callback('/\[(https?:\/\/[^\s\]]+)(?:\s+([^\]]+))?\]/',function($m){return '<a href="'.esc_url($m[1]).'" rel="nofollow noopener">'.esc_html($m[2]??$m[1]).'</a>';},$text);
        $text=preg_replace("/'''(.*?)'''/s",'<strong>$1</strong>',$text);$text=preg_replace("/''(.*?)''/s",'<em>$1</em>',$text);return $text;
    }
    public static function html_to_wikitext($html) {
        $html=preg_replace('/<h([2-4])\b[^>]*>(.*?)<\/h\1>/is',"\n\n==$2==\n\n",$html);
        $html=preg_replace('/<\/(p|div|li|blockquote)>/i',"\n\n",$html);
        $html=preg_replace('/<strong\b[^>]*>(.*?)<\/strong>|<b\b[^>]*>(.*?)<\/b>/is',"'''$1$2'''",$html);
        $html=preg_replace('/<em\b[^>]*>(.*?)<\/em>|<i\b[^>]*>(.*?)<\/i>/is',"''$1$2''",$html);
        $html=preg_replace_callback('/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is',function($m){$slug=basename((string)parse_url($m[1],PHP_URL_PATH));return '[['.rawurldecode($slug).'|'.wp_strip_all_tags($m[2]).']]';},$html);
        $text=html_entity_decode(wp_strip_all_tags($html),ENT_QUOTES,get_bloginfo('charset')?:'UTF-8');return trim(preg_replace("/\n{3,}/","\n\n",$text));
    }
}
BSW_Phase42_Wikitext::init();
