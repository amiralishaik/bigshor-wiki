<?php
/**
 * Article-page Phase 10: search and discovery integration.
 * Reuses the existing wiki article, topic, alias and knowledge-graph records.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase10_Article_Discovery')) {
final class BSW_Phase10_Article_Discovery {
    const PER_PAGE = 20;

    public static function init() {
        add_filter('the_content', array(__CLASS__, 'append_discovery'), 85);
        add_shortcode('bsw_article_search', array(__CLASS__, 'search_shortcode'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'styles'));
    }

    public static function styles() {
        if (!is_singular('wiki_article') && !is_singular()) return;
        wp_register_style('bsw-p10-article-discovery', false, array(), '34.12.0');
        wp_enqueue_style('bsw-p10-article-discovery');
        wp_add_inline_style('bsw-p10-article-discovery',
            '.bsw-p10-discovery{clear:both;max-width:1180px;margin:24px auto;padding-top:16px;border-top:1px solid #a2a9b1}.bsw-p10-discovery h2{font-size:1.25em;margin:18px 0 10px}.bsw-p10-topics{display:flex;flex-wrap:wrap;gap:8px}.bsw-p10-topics a{display:inline-block;padding:5px 9px;border:1px solid #c8ccd1;border-radius:3px;text-decoration:none}.bsw-p10-columns{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px}.bsw-p10-columns ul{margin:0;padding-left:20px}.bsw-p10-columns li{margin:6px 0}.bsw-p10-search{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0}.bsw-p10-search label{flex:1 1 240px}.bsw-p10-search input{display:block;width:100%;min-height:40px;padding:7px}.bsw-p10-search button{padding:8px 14px;cursor:pointer}.bsw-p10-results>li{margin:12px 0}.bsw-p10-results p{margin:3px 0;color:#54595d}.bsw-p10-pagination{display:flex;flex-wrap:wrap;gap:8px;margin:16px 0}.bsw-p10-pagination a,.bsw-p10-pagination span{padding:5px 9px;border:1px solid #c8ccd1}.bsw-p10-pagination [aria-current=page]{font-weight:700;background:#eaecf0}@media(max-width:600px){.bsw-p10-columns{grid-template-columns:1fr}}');
    }

    private static function clean_query($value) {
        $value = sanitize_text_field(wp_unslash($value));
        if (function_exists('mb_substr')) return mb_substr($value, 0, 100);
        return substr($value, 0, 100);
    }

    private static function search_ids($term) {
        if (strlen($term) < 2) return array();
        $ids = get_posts(array(
            'post_type'=>'wiki_article', 'post_status'=>'publish', 's'=>$term,
            'posts_per_page'=>100, 'fields'=>'ids', 'no_found_rows'=>true,
            'orderby'=>'relevance', 'order'=>'DESC'
        ));

        // Alternative names are editor-maintained metadata from article Phase 9.
        $alias_ids = get_posts(array(
            'post_type'=>'wiki_article', 'post_status'=>'publish', 'posts_per_page'=>100,
            'fields'=>'ids', 'no_found_rows'=>true,
            'meta_query'=>array(array('key'=>'_bsw_p9_aliases','value'=>$term,'compare'=>'LIKE')),
            'orderby'=>'title', 'order'=>'ASC'
        ));
        $combined = array_values(array_unique(array_merge(array_map('absint',(array)$ids),array_map('absint',(array)$alias_ids))));
        return array_values(array_filter($combined, function($id) {
            return $id && get_post_type($id)==='wiki_article' && get_post_status($id)==='publish';
        }));
    }

    public static function search_shortcode($atts=array()) {
        $atts = shortcode_atts(array('placeholder'=>'Search BIGSHOR Wiki','title'=>'Search the encyclopedia'),$atts,'bsw_article_search');
        $term = isset($_GET['bsw_article_q']) ? self::clean_query($_GET['bsw_article_q']) : '';
        $page = isset($_GET['bsw_article_page']) ? max(1,absint($_GET['bsw_article_page'])) : 1;
        $out = '<section class="bsw-p10-search-panel" aria-label="Search BIGSHOR Wiki"><h2>'.esc_html($atts['title']).'</h2>';
        $out .= '<form class="bsw-p10-search" method="get" action="'.esc_url(get_permalink()).'"><label><span class="screen-reader-text">Search articles, topics and alternative names</span><input type="search" name="bsw_article_q" value="'.esc_attr($term).'" placeholder="'.esc_attr($atts['placeholder']).'" minlength="2" maxlength="100" required></label><button type="submit">Search</button></form>';
        if ($term==='') return $out.'</section>';
        if (strlen($term)<2) return $out.'<p>Enter at least two characters.</p></section>';

        $ids = self::search_ids($term);
        usort($ids,function($a,$b){return strcasecmp(get_the_title($a),get_the_title($b));});
        $total = count($ids);
        $pages = max(1,(int)ceil($total/self::PER_PAGE));
        $page = min($page,$pages);
        $visible = array_slice($ids,($page-1)*self::PER_PAGE,self::PER_PAGE);
        $out .= '<p>'.number_format_i18n($total).' matching article'.($total===1?'':'s').' for <strong>'.esc_html($term).'</strong>.</p>';
        if ($visible) {
            $out .= '<ol class="bsw-p10-results">';
            foreach ($visible as $id) {
                $excerpt = get_post_field('post_excerpt',$id);
                if (!$excerpt) $excerpt = get_post_field('post_content',$id);
                $out .= '<li><a href="'.esc_url(get_permalink($id)).'">'.esc_html(get_the_title($id)).'</a><p>'.esc_html(wp_trim_words(wp_strip_all_tags(strip_shortcodes($excerpt)),28)).'</p></li>';
            }
            $out .= '</ol>';
        } else $out .= '<p>No matching published articles found.</p>';
        if ($pages>1) {
            $out .= '<nav class="bsw-p10-pagination" aria-label="Search result pages">';
            for($i=1;$i<=$pages;$i++) {
                if($i===$page) $out .= '<span aria-current="page">'.(int)$i.'</span>';
                else $out .= '<a href="'.esc_url(add_query_arg(array('bsw_article_q'=>$term,'bsw_article_page'=>$i))).'">'.(int)$i.'</a>';
            }
            $out .= '</nav>';
        }
        return $out.'</section>';
    }

    private static function related_articles($post_id,$term_ids) {
        if (!$term_ids) return array();
        $query = new WP_Query(array(
            'post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>6,
            'post__not_in'=>array((int)$post_id),'tax_query'=>array(array(
                'taxonomy'=>'wiki_topic','field'=>'term_id','terms'=>$term_ids,'operator'=>'IN'
            )),
            'orderby'=>'modified','order'=>'DESC','no_found_rows'=>true
        ));
        return $query->posts;
    }

    private static function backlinks($post_id) {
        global $wpdb;
        $url = get_permalink($post_id);
        if (!$url) return array();
        $like = '%'.$wpdb->esc_like($url).'%';
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND ID != %d AND post_content LIKE %s ORDER BY post_modified_gmt DESC LIMIT 30",
            'wiki_article','publish',(int)$post_id,$like
        ));
        $matches = array();
        foreach ((array)$ids as $id) {
            $content = (string)get_post_field('post_content',(int)$id);
            if (strpos($content,$url)!==false) $matches[]=(int)$id;
            if (count($matches)>=6) break;
        }
        return $matches;
    }

    public static function append_discovery($content) {
        if (is_admin() || !is_singular('wiki_article') || !in_the_loop() || !is_main_query()) return $content;
        $id = get_the_ID();
        if (!$id || isset($_GET['bsw_edit']) || isset($_GET['bsw_history'])) return $content;

        $terms = get_the_terms($id,'wiki_topic');
        $term_ids = array();
        $out = '<section class="bsw-p10-discovery" aria-labelledby="bsw-p10-discovery-title"><h2 id="bsw-p10-discovery-title">Discover more on BIGSHOR Wiki</h2>';
        if (is_array($terms) && $terms) {
            $out .= '<h3>Explore topics</h3><div class="bsw-p10-topics">';
            foreach ($terms as $term) {
                $term_ids[]=(int)$term->term_id;
                $url=get_term_link($term);
                if (!is_wp_error($url)) $out .= '<a href="'.esc_url($url).'">'.esc_html($term->name).'</a>';
            }
            $out .= '</div>';
        }

        $related = self::related_articles($id,$term_ids);
        // Explicit editor-curated relationships take precedence and supplement topic matches.
        if (class_exists('BSW_Phase58_Knowledge_Graph') && method_exists('BSW_Phase58_Knowledge_Graph','related_articles')) {
            $curated = BSW_Phase58_Knowledge_Graph::related_articles($id,6);
            $seen = array_map(function($p){return (int)$p->ID;},$related);
            foreach ($curated as $item) if (!in_array((int)$item->ID,$seen,true) && count($related)<8) $related[]=$item;
        }
        $backlinks = self::backlinks($id);
        $out .= '<div class="bsw-p10-columns">';
        $out .= '<section><h3>Related articles</h3>';
        if ($related) {
            $out .= '<ul>';
            foreach ($related as $item) $out .= '<li><a href="'.esc_url(get_permalink($item)).'">'.esc_html(get_the_title($item)).'</a></li>';
            $out .= '</ul>';
        } else $out .= '<p>No related articles have been connected yet.</p>';
        $out .= '</section><section><h3>What links here</h3>';
        if ($backlinks) {
            $out .= '<ul>';
            foreach ($backlinks as $item_id) $out .= '<li><a href="'.esc_url(get_permalink($item_id)).'">'.esc_html(get_the_title($item_id)).'</a></li>';
            $out .= '</ul>';
        } else $out .= '<p>No incoming article links were found.</p>';
        $out .= '</section></div>';
        $out .= do_shortcode('[bsw_article_search title="Find another article"]');
        return $content.$out.'</section>';
    }
}
BSW_Phase10_Article_Discovery::init();
}
