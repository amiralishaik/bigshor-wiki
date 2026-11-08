<?php
/**
 * BIGSHOR Phase 2 / Phase 45: canonical encyclopaedia article layout.
 * Presentation-only: preserves post content and all existing metadata.
 */
if (!defined('ABSPATH')) exit;
final class BSW_Phase45_Article_Layout {
    public static function init() {
        // Replace the earlier presentation callback; editing/save logic remains in Core.
        if (class_exists('BSW_Phase41_Wiki_Framework')) {
            remove_filter('the_content', array('BSW_Phase41_Wiki_Framework', 'structure_article'), 12);
        }
        add_filter('the_content', array(__CLASS__, 'render'), 12);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'styles'));
    }
    public static function styles() {
        if (!is_singular('wiki_article')) return;
        $css = '.bsw45-article{max-width:1180px;margin:0 auto;color:inherit}.bsw45-tabs{display:flex;flex-wrap:wrap;gap:0;border-bottom:1px solid #a2a9b1;margin:0 0 18px;font:14px/1.4 Arial,sans-serif}.bsw45-tabs a{padding:10px 15px;color:inherit;text-decoration:none;border-bottom:3px solid transparent}.bsw45-tabs a[aria-current="page"],.bsw45-tabs a:hover{border-color:#202122}.bsw45-lead{font-size:1.08em;line-height:1.65;margin:0 0 18px}.bsw45-lead:after{content:"";display:block;clear:both}.bsw45-infobox{float:right;clear:right;width:310px;max-width:42%;margin:0 0 20px 24px;border:1px solid #a2a9b1;background:#f8f9fa;font:14px/1.5 Arial,sans-serif;overflow:hidden}.bsw45-infobox h2{font:600 17px/1.3 Arial,sans-serif;text-align:center;margin:0;padding:10px;background:#eaecf0}.bsw45-infobox figure{margin:0;padding:10px;text-align:center}.bsw45-infobox figcaption{font-size:12px;line-height:1.45;color:#54595d;margin-top:6px}.bsw45-media-credit,.bsw45-media-source{font-size:11px;line-height:1.4;color:#54595d;margin-top:4px}.bsw45-infobox img{display:block;max-width:100%;height:auto;margin:0 auto}.bsw45-infobox table{width:100%;border-collapse:collapse}.bsw50-infobox{width:100%;border-collapse:collapse;margin:0 0 18px;background:#f8f9fa;border:1px solid #a2a9b1}.bsw50-infobox caption{font-weight:700;text-align:center;padding:8px;background:#eaecf0}.bsw50-infobox th,.bsw50-infobox td{padding:7px 9px;border-top:1px solid #c8ccd1;text-align:left;vertical-align:top}.bsw50-infobox th{width:38%}.bsw45-infobox th,.bsw45-infobox td{padding:7px 9px;border-top:1px solid #c8ccd1;text-align:left;vertical-align:top;overflow-wrap:anywhere}.bsw45-infobox th{width:38%;font-weight:600}.bsw45-toc{display:table;max-width:min(100%,520px);margin:18px 0 24px;padding:12px 18px;border:1px solid #a2a9b1;background:#f8f9fa;font:14px/1.55 Arial,sans-serif}.bsw45-toc-title{display:flex;align-items:center;justify-content:space-between;gap:18px;font-weight:700}.bsw45-toc ol{margin:8px 0 0;padding-left:24px}.bsw45-toc ol ol{margin:2px 0}.bsw45-toc li{padding:1px 0}.bsw45-toc a{color:#0645ad;text-decoration:none}.bsw45-toc a:hover{text-decoration:underline}.bsw45-content{min-width:0}.bsw45-toc-toggle{border:0;background:transparent;color:#0645ad;text-decoration:underline;cursor:pointer;font:inherit;padding:2px 4px}.bsw45-toc-body[hidden]{display:none}.bsw45-lead-summary{font-size:1.05em;line-height:1.6;margin:0 0 1em}.bsw45-preamble{min-height:1px}.bsw45-content figure{max-width:100%;margin:1rem auto;text-align:center}.bsw45-content figcaption{font-size:.9em;color:#54595d}.bsw45-content .bsw42-references,.bsw45-content .bsw47-reference-list{font-size:.92em;line-height:1.55}.bsw45-content .bsw43-wikitable{width:100%;border-collapse:collapse;overflow-x:auto}.bsw45-content .bsw43-wikitable th{background:#eaecf0}.bsw45-content .bsw43-wikitable th,.bsw45-content .bsw43-wikitable td{border:1px solid #a2a9b1;padding:7px 9px;vertical-align:top}.bsw45-content .bsw47-categories{clear:both}.bsw45-content .bsw42-wiki-file{clear:both}.bsw45-content h2,.bsw45-content h3,.bsw45-content h4,.bsw45-content h5,.bsw45-content h6{scroll-margin-top:24px}.bsw45-content h2{border-bottom:1px solid #a2a9b1;padding-bottom:5px;margin-top:30px}.bsw45-content h3{margin-top:24px}.bsw45-content .bsw45-section-anchor{margin-left:8px;font:12px Arial,sans-serif;opacity:.65;text-decoration:none}.bsw45-section-navigation{clear:both;border-top:1px solid #c8ccd1;border-bottom:1px solid #c8ccd1;padding:12px 0;margin:28px 0 18px}.bsw45-section-navigation-label{display:block;font-weight:700;margin-bottom:7px}.bsw45-section-navigation-links,.bsw45-topic-links{display:flex;flex-wrap:wrap;gap:8px 14px}.bsw45-section-navigation a,.bsw45-topic-links a{color:#0645ad;text-decoration:none}.bsw45-section-navigation a:hover,.bsw45-topic-links a:hover{text-decoration:underline}.bsw45-knowledge-navigation{clear:both;border-top:1px solid #c8ccd1;margin:22px 0;padding-top:12px}.bsw45-knowledge-navigation h2{font-size:1.2em}.bsw45-related-knowledge{columns:2;column-gap:28px}.bsw45-related-knowledge li{break-inside:avoid;margin:5px 0}.bsw45-appendices{border-top:1px solid #c8ccd1;margin-top:28px;padding-top:8px}.bsw45-article:after{content:"";display:block;clear:both}@media(max-width:760px){.bsw45-related-knowledge{columns:1}.bsw45-infobox{float:none;clear:both;width:100%;max-width:100%;margin:14px 0 20px}.bsw45-tabs a{padding:9px 10px;font-size:13px}.bsw45-toc{display:block;max-width:100%}}@media print{.bsw45-tabs,.bsw45-section-anchor{display:none!important}.bsw45-infobox{break-inside:avoid;float:right}.bsw45-toc{break-inside:avoid}.bsw45-content a{color:inherit;text-decoration:none}}
        ';
        wp_register_style('bsw45-article-layout', false, array(), '34.5.0');
        wp_enqueue_style('bsw45-article-layout');
        wp_add_inline_style('bsw45-article-layout', $css);
        wp_register_script('bsw45-article-layout', false, array(), '34.5.0', true);
        wp_enqueue_script('bsw45-article-layout');
        wp_add_inline_script('bsw45-article-layout', "document.addEventListener('click',function(e){var b=e.target.closest('.bsw45-toc-toggle');if(!b)return;var n=b.closest('.bsw45-toc').querySelector('.bsw45-toc-body');var hide=b.getAttribute('aria-expanded')==='true';b.setAttribute('aria-expanded',hide?'false':'true');b.textContent=hide?'show':'hide';n.hidden=hide;});");
    }
    private static function tabs($id) {
        $base = get_permalink($id); $items = array('Read' => $base);
        if (current_user_can('edit_post', $id)) { $items['Edit'] = add_query_arg('bsw_edit', $id, $base); $items['Edit source'] = add_query_arg(array('bsw_edit'=>$id,'bsw_source'=>1), $base); }
        $items['View history'] = add_query_arg('bsw_history', 1, $base); $items['Talk'] = $base . '#bsw-article-discussion';
        $active = isset($_GET['bsw_history']) ? 'View history' : (isset($_GET['bsw_edit']) ? (isset($_GET['bsw_source']) ? 'Edit source' : 'Edit') : 'Read');
        $out = '<nav class="bsw45-tabs" aria-label="Article actions">';
        foreach ($items as $label=>$url) $out .= '<a href="'.esc_url($url).'"'.($active===$label?' aria-current="page"':'').'>'.esc_html($label).'</a>';
        return $out.'</nav>';
    }
    private static function infobox($id) {
        $d = get_post_meta($id, '_bsw_infobox', true);
        if (!is_array($d) || !$d) {
            $legacy = get_post_meta($id, '_bsw_p1_infobox_rows', true);
            if (is_array($legacy) && $legacy) { $fields=array(); foreach($legacy as $r) if(is_array($r)&&!empty($r['label'])) $fields[$r['label']]=$r['value']??''; $d=array('title'=>get_post_meta($id,'_bsw_p1_infobox_title',true),'image'=>get_post_meta($id,'_bsw_p1_infobox_image',true),'fields'=>$fields); }
        }
        if (!is_array($d) || !$d) return '';
        $title = !empty($d['title']) ? $d['title'] : get_the_title($id); $fields = isset($d['fields']) && is_array($d['fields']) ? $d['fields'] : $d;
        $out = '<aside class="bsw45-infobox" aria-label="'.esc_attr($title).' infobox"><h2>'.esc_html($title).'</h2>';
        if (!empty($d['image'])) {
            $image_html = '';
            $alt = !empty($d['image_alt']) ? $d['image_alt'] : $title;
            if (is_numeric($d['image']) && get_post_type((int)$d['image']) === 'attachment') {
                $image_html = wp_get_attachment_image((int)$d['image'], 'medium', false, array('loading'=>'lazy', 'alt'=>$alt, 'decoding'=>'async'));
            } elseif (is_string($d['image']) && wp_http_validate_url($d['image'])) {
                $image_html = '<img src="'.esc_url($d['image']).'" alt="'.esc_attr($alt).'" loading="lazy" decoding="async">';
            }
            if ($image_html) {
                $out .= '<figure class="bsw45-infobox-media">'.$image_html;
                if (!empty($d['image_caption'])) $out .= '<figcaption>'.esc_html($d['image_caption']).'</figcaption>';
                if (!empty($d['image_credit'])) $out .= '<div class="bsw45-media-credit">'.esc_html($d['image_credit']).'</div>';
                if (!empty($d['image_source']) && wp_http_validate_url($d['image_source'])) $out .= '<div class="bsw45-media-source"><a href="'.esc_url($d['image_source']).'" rel="nofollow noopener noreferrer" target="_blank">Image source</a></div>';
                $out .= '</figure>';
            }
        }
        $rows=''; foreach($fields as $key=>$value) {
            if(in_array((string)$key,array('title','image','fields','image_alt','image_caption','image_credit','image_source'),true)||$value===''||$value===null) continue;
            if (is_array($value) && isset($value['value'])) { $cell = esc_html(wp_strip_all_tags((string)$value['value'])); if (!empty($value['url']) && wp_http_validate_url($value['url'])) $cell = '<a href="'.esc_url($value['url']).'" rel="noopener noreferrer">'.$cell.'</a>'; }
            elseif (is_scalar($value)) $cell = esc_html(wp_strip_all_tags((string)$value)); else continue;
            $rows.='<tr><th scope="row">'.esc_html(ucwords(str_replace(array('_','-'),' ',(string)$key))).'</th><td>'.$cell.'</td></tr>';
        }
        if ($rows) $out.='<table><tbody>'.$rows.'</tbody></table>';
        return $out.'</aside>';
    }
    /** Phase 5: section-to-section navigation and related knowledge links. */
    private static function knowledge_navigation($post_id, $items) {
        $out = '';
        if ($items) {
            $last = count($items) - 1;
            $out .= '<nav class="bsw45-section-navigation" aria-label="Navigate article sections"><span class="bsw45-section-navigation-label">Article sections</span><div class="bsw45-section-navigation-links">';
            $out .= '<a href="#'.esc_attr($items[0]['id']).'">First section: '.esc_html($items[0]['title']).'</a>';
            if ($last > 0) $out .= '<a href="#'.esc_attr($items[$last]['id']).'">Last section: '.esc_html($items[$last]['title']).'</a>';
            $out .= '<a href="#article-title">Back to top ↑</a></div></nav>';
        }
        $terms = get_the_terms($post_id, 'wiki_topic');
        $term_ids = array();
        if (is_array($terms) && $terms) {
            $out .= '<section class="bsw45-knowledge-navigation" aria-labelledby="bsw45-knowledge-title"><h2 id="bsw45-knowledge-title">Explore related knowledge</h2><div class="bsw45-topic-links">';
            foreach ($terms as $term) {
                $term_ids[] = (int) $term->term_id;
                $url = get_term_link($term);
                if (!is_wp_error($url)) $out .= '<a href="'.esc_url($url).'">'.esc_html($term->name).'</a>';
            }
            $out .= '</div>';
            if ($term_ids) {
                $related = get_posts(array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>4,'post__not_in'=>array((int)$post_id),'orderby'=>'date','order'=>'DESC','tax_query'=>array(array('taxonomy'=>'wiki_topic','field'=>'term_id','terms'=>$term_ids,'operator'=>'IN'))));
                if ($related) {
                    $out .= '<h3>Related articles</h3><ul class="bsw45-related-knowledge">';
                    foreach ($related as $article) $out .= '<li><a href="'.esc_url(get_permalink($article)).'">'.esc_html(get_the_title($article)).'</a></li>';
                    $out .= '</ul>';
                }
            }
            $out .= '</section>';
        }
        return $out;
    }
    private static function toc_level($items, &$index, $parent_level) {
        $nodes = array();
        $count = count($items);
        while ($index < $count) {
            $level = max(2, min(6, (int) $items[$index]['level']));
            if ($level <= $parent_level) break;
            $item = $items[$index++];
            $nodes[] = array('item' => $item, 'children' => self::toc_level($items, $index, $level));
        }
        return $nodes;
    }
    private static function toc_tree($items) {
        $index = 0;
        return self::toc_level($items, $index, 1);
    }
    private static function toc_nodes($nodes, $post_id) {
        $out = '<ol>';
        foreach ($nodes as $node) {
            $item = $node['item'];
            $out .= '<li><a href="#'.esc_attr($item['id']).'">'.esc_html($item['title']).'</a>';
            if (current_user_can('edit_post', $post_id)) $out .= ' <a class="bsw45-section-anchor" href="'.esc_url(add_query_arg(array('bsw_edit'=>$post_id,'bsw_section'=>(int)$item['index']),get_permalink($post_id))).'">[edit]</a>';
            if (!empty($node['children'])) $out .= self::toc_nodes($node['children'], $post_id);
            $out .= '</li>';
        }
        return $out.'</ol>';
    }
    private static function toc($items, $post_id) {
        if (!$items) return '';
        $tree = self::toc_tree($items);
        return '<nav class="bsw45-toc" aria-labelledby="bsw45-toc-title-'.(int)$post_id.'"><div class="bsw45-toc-title"><strong id="bsw45-toc-title-'.(int)$post_id.'">Contents</strong><button type="button" class="bsw45-toc-toggle" aria-expanded="true" aria-controls="bsw45-toc-body-'.(int)$post_id.'">hide</button></div><div class="bsw45-toc-body" id="bsw45-toc-body-'.(int)$post_id.'">'.self::toc_nodes($tree, $post_id).'</div></nav>';
    }
    private static function headings($content) {
        $items=array(); $seen=array();
        $content=preg_replace_callback('/<h([2-6])\\b([^>]*)>(.*?)<\\/h\\1>/is',function($m) use (&$items,&$seen){
            $title=trim(wp_strip_all_tags($m[3])); if($title==='') return $m[0];
            $base='bsw-section-'.(function_exists('sanitize_title')?sanitize_title($title):sanitize_key($title)); if($base==='bsw-section-') $base='bsw-section-heading';
            $id=$base; $suffix=2; while(isset($seen[$id])) { $id=$base.'-'.$suffix; $suffix++; } $seen[$id]=true;
            $items[]=array('index'=>count($items),'level'=>(int)$m[1],'title'=>$title,'id'=>$id);
            $attrs=preg_replace('/\s+id\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i','',$m[2]);
            return '<h'.(int)$m[1].$attrs.' id="'.esc_attr($id).'">'.$m[3].'<a class="bsw45-section-anchor" href="#'.esc_attr($id).'" aria-label="Link to '.esc_attr($title).'">¶</a></h'.(int)$m[1].'>';
        },$content);
        return array($content,$items);
    }
    public static function render($content) {
        if (is_admin() || !is_singular('wiki_article') || !in_the_loop() || !is_main_query()) return $content;
        $id=get_the_ID(); if(!$id) return $content;
        if (isset($_GET['bsw_edit']) && absint($_GET['bsw_edit'])===$id) return '<div class="bsw45-article">'.self::tabs($id).(class_exists('BSW_Phase42_Wikitext')?BSW_Phase42_Wikitext::editor($id):(class_exists('BSW_Phase41_Wiki_Framework')?BSW_Phase41_Wiki_Framework::editor($id):$content)).'</div>';
        if (isset($_GET['bsw_history'])) return '<div class="bsw45-article">'.self::tabs($id).'<p class="bsw45-history-note">Revision history is provided by the BIGSHOR Core revision module.</p>'.do_shortcode('[bigshor_wiki_history id="'.(int)$id.'"]').'</div>'; 
        list($content,$items)=self::headings($content);
        $out='<div class="bsw45-article">'.self::tabs($id);
        // Keep the infobox in the article flow so the opening paragraphs wrap beside it,
        // matching the conventional encyclopedia layout. Place the contents panel after
        // the lead/preamble and before the first level-two section.
        $first_section = strpos($content, '<h2');
        if ($first_section === false) { $preamble = $content; $sections = ''; }
        else { $preamble = substr($content, 0, $first_section); $sections = substr($content, $first_section); }
        $excerpt = trim((string) get_post_field('post_excerpt', $id));
        $out .= '<div class="bsw45-content">'.self::infobox($id);
        if ($excerpt) $out .= '<p class="bsw45-lead-summary">'.esc_html($excerpt).'</p>';
        $out .= '<div class="bsw45-preamble">'.$preamble.'</div>';
        $out .= self::toc($items,$id);
        $out .= '<div class="bsw45-sections">'.$sections.'</div>'.self::knowledge_navigation($id,$items).'</div></div>';
        return $out;
    }
}
add_action('init', array('BSW_Phase45_Article_Layout','init'), 50);
