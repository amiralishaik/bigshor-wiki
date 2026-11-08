<?php
/**
 * Article-page Phase 9: advanced article metadata, page information and citation tools.
 * All metadata is optional; existing articles render without migration.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase9_Advanced_Article')) {
final class BSW_Phase9_Advanced_Article {
    public static function init() {
        add_action('add_meta_boxes_wiki_article',array(__CLASS__,'add_box'));
        add_action('save_post_wiki_article',array(__CLASS__,'save_meta'),45,2);
        add_filter('the_content',array(__CLASS__,'append_tools'),80);
        add_action('wp_enqueue_scripts',array(__CLASS__,'styles'));
        add_shortcode('bsw_article_information',array(__CLASS__,'information_shortcode'));
    }
    public static function add_box() {
        add_meta_box('bsw_p9_advanced_article','Advanced Article Information',array(__CLASS__,'render_box'),'wiki_article','normal','default');
    }
    public static function render_box($post) {
        if(!current_user_can('edit_post',$post->ID))return;
        wp_nonce_field('bsw_p9_meta_'.$post->ID,'bsw_p9_nonce');
        $aliases=get_post_meta($post->ID,'_bsw_p9_aliases',true);
        $links=get_post_meta($post->ID,'_bsw_p9_external_links',true);
        echo '<p><label for="bsw_p9_aliases"><strong>Alternative names</strong></label><textarea class="widefat" rows="3" id="bsw_p9_aliases" name="bsw_p9_aliases" placeholder="One alternative name per line">'.esc_textarea(is_array($aliases)?implode("\n",$aliases):'').'</textarea><span class="description">Use recognised alternate spellings, former names or common aliases. Do not add promotional keywords.</span></p>';
        $lines=array();if(is_array($links))foreach($links as $row)$lines[]=(isset($row['label'])?$row['label']:'').' | '.(isset($row['url'])?$row['url']:'');
        echo '<p><label for="bsw_p9_external_links"><strong>External identifiers and authoritative links</strong></label><textarea class="widefat" rows="4" id="bsw_p9_external_links" name="bsw_p9_external_links" placeholder="Wikidata | https://www.wikidata.org/wiki/Q...">'.esc_textarea(implode("\n",$lines)).'</textarea><span class="description">One entry per line: label | full HTTP(S) URL. Only stable, relevant reference destinations.</span></p>';
    }
    public static function save_meta($post_id,$post) {
        if(wp_is_post_revision($post_id)||(defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE))return;
        if(!isset($_POST['bsw_p9_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_p9_nonce'])),'bsw_p9_meta_'.$post_id))return;
        if(!current_user_can('edit_post',$post_id))return;
        $raw=sanitize_textarea_field(wp_unslash($_POST['bsw_p9_aliases']??''));
        $aliases=array();foreach(preg_split('/\r\n|\r|\n/',$raw) as $line){$line=trim($line);if($line!==''&&strlen($line)<=160)$aliases[]=$line;}
        update_post_meta($post_id,'_bsw_p9_aliases',array_slice(array_values(array_unique($aliases)),0,30));
        $raw_links=sanitize_textarea_field(wp_unslash($_POST['bsw_p9_external_links']??''));
        $links=array();foreach(preg_split('/\r\n|\r|\n/',$raw_links) as $line){$parts=array_map('trim',explode('|',$line,2));if(count($parts)!==2||$parts[0]===''||!wp_http_validate_url($parts[1]))continue;$scheme=strtolower((string)wp_parse_url($parts[1],PHP_URL_SCHEME));if(!in_array($scheme,array('http','https'),true))continue;$links[]=array('label'=>sanitize_text_field($parts[0]),'url'=>esc_url_raw($parts[1]));}
        update_post_meta($post_id,'_bsw_p9_external_links',array_slice($links,0,20));
    }
    private static function citation_data($post_id) {
        $post=get_post($post_id);$author=get_userdata($post->post_author);
        $name=$author?$author->display_name:get_bloginfo('name');
        $date=get_the_date('Y-m-d',$post);
        $url=get_permalink($post_id);
        $title=wp_strip_all_tags(get_the_title($post_id));
        $site=wp_strip_all_tags(get_bloginfo('name'));
        $plain=$name.'. "'.$title.'." '.$site.'. '.$date.'. '.$url;
        $key=sanitize_title($title).'-'.substr($date,0,4);
        $bib='@article{'.$key.",\n  author = {".str_replace(array('{','}'),'', $name)."},\n  title = {".str_replace(array('{','}'),'', $title)."},\n  journal = {".str_replace(array('{','}'),'', $site)."},\n  year = {".substr($date,0,4)."},\n  url = {".$url."}\n}";
        return array('plain'=>$plain,'bibtex'=>$bib,'url'=>$url);
    }
    public static function information_shortcode($atts=array()) {
        $a=shortcode_atts(array('id'=>0),$atts,'bsw_article_information');
        $id=absint($a['id']);if(!$id)$id=get_queried_object_id();
        if(!$id||get_post_type($id)!=='wiki_article'||!current_user_can('read_post',$id))return '';
        return self::render_information($id);
    }
    private static function render_information($id) {
        $post=get_post($id);if(!$post)return '';
        $body=wp_strip_all_tags(strip_shortcodes($post->post_content));
        $word_parts=preg_split('/\\s+/u',trim($body),-1,PREG_SPLIT_NO_EMPTY);
        $words=is_array($word_parts)?count($word_parts):0;
        $minutes=max(1,(int)ceil($words/200));
        $author=get_userdata($post->post_author);
        $aliases=get_post_meta($id,'_bsw_p9_aliases',true);
        $links=get_post_meta($id,'_bsw_p9_external_links',true);
        $citation=self::citation_data($id);
        $out='<section class="bsw-p9-article-tools" aria-label="Advanced article information"><details class="bsw-p9-info"><summary>Article information</summary><dl><dt>Article ID</dt><dd>'.(int)$id.'</dd><dt>Author</dt><dd>'.esc_html($author?$author->display_name:'Unknown').'</dd><dt>Published</dt><dd><time datetime="'.esc_attr(get_post_time('c',true,$post)).'">'.esc_html(get_the_date('', $post)).'</time></dd><dt>Last updated</dt><dd><time datetime="'.esc_attr(get_post_modified_time('c',true,$post)).'">'.esc_html(get_the_modified_date('', $post)).'</time></dd><dt>Length</dt><dd>'.number_format_i18n($words).' words · about '.(int)$minutes.' min read</dd></dl>';
        if(is_array($aliases)&&$aliases){$out.='<h3>Alternative names</h3><ul>';foreach($aliases as $alias)$out.='<li>'.esc_html($alias).'</li>';$out.='</ul>';}
        if(is_array($links)&&$links){$out.='<h3>External identifiers and authoritative links</h3><ul>';foreach($links as $link)if(!empty($link['label'])&&!empty($link['url'])&&wp_http_validate_url($link['url']))$out.='<li><a href="'.esc_url($link['url']).'" rel="nofollow noopener noreferrer" target="_blank">'.esc_html($link['label']).'</a></li>';$out.='</ul>';}
        $out.='</details><details class="bsw-p9-citation"><summary>Cite this article</summary><p>Copy a citation for reuse. Verify the citation style required by your publisher or institution.</p><label>Plain citation<textarea readonly rows="3">'.esc_textarea($citation['plain']).'</textarea></label><button type="button" class="bsw-p9-copy" data-copy-text="'.esc_attr($citation['plain']).'">Copy plain citation</button><label>BibTeX<textarea readonly rows="6">'.esc_textarea($citation['bibtex']).'</textarea></label><button type="button" class="bsw-p9-copy" data-copy-text="'.esc_attr($citation['bibtex']).'">Copy BibTeX</button><p><a href="'.esc_url($citation['url']).'">Canonical article URL</a></p></details></section>';
        return $out;
    }
    public static function append_tools($content) {
        if(is_admin()||!is_singular('wiki_article')||!in_the_loop()||!is_main_query())return $content;
        $id=get_the_ID();if(!$id||isset($_GET['bsw_edit'])||isset($_GET['bsw_history']))return $content;
        return $content.self::render_information($id);
    }
    public static function styles() {
        if(!is_singular('wiki_article'))return;
        wp_register_style('bsw-p9-article-tools',false,array(),'34.11.0');wp_enqueue_style('bsw-p9-article-tools');
        wp_add_inline_style('bsw-p9-article-tools','.bsw-p9-article-tools{clear:both;max-width:1180px;margin:20px auto;border-top:1px solid #a2a9b1;padding-top:14px}.bsw-p9-article-tools details{border:1px solid #c8ccd1;margin:10px 0;padding:10px 14px;background:#f8f9fa}.bsw-p9-article-tools summary{font-weight:700;cursor:pointer}.bsw-p9-article-tools dl{display:grid;grid-template-columns:minmax(110px,180px) 1fr;gap:7px 14px}.bsw-p9-article-tools dt{font-weight:700}.bsw-p9-article-tools dd{margin:0;overflow-wrap:anywhere}.bsw-p9-article-tools label{display:block;font-weight:600;margin:12px 0 6px}.bsw-p9-article-tools textarea{display:block;width:100%;max-width:850px;margin:5px 0;padding:8px;background:#fff}.bsw-p9-article-tools button{padding:7px 12px;cursor:pointer}@media(max-width:600px){.bsw-p9-article-tools dl{grid-template-columns:1fr;gap:3px}.bsw-p9-article-tools dd{margin-bottom:8px}}');
        wp_register_script('bsw-p9-article-tools',false,array(),'34.11.0',true);wp_enqueue_script('bsw-p9-article-tools');
        wp_add_inline_script('bsw-p9-article-tools',"document.addEventListener('click',function(e){var b=e.target.closest('.bsw-p9-copy');if(!b)return;var value=b.getAttribute('data-copy-text')||'';if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(value).then(function(){var old=b.textContent;b.textContent='Copied';setTimeout(function(){b.textContent=old;},1600);}).catch(function(){window.prompt('Copy citation',value);});}else{window.prompt('Copy citation',value);}});");
    }
}
BSW_Phase9_Advanced_Article::init();
}
