<?php
/**
 * BIGSHOR Wiki Phase 4 completion — multilingual sitemap, language QA and discovery refinements.
 * All language variants remain separately authored and editorially reviewed.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase4_Completion')) {
final class BSW_Phase4_Completion {
    const QUERY_VAR = 'bsw_language_sitemap';

    public static function boot() {
        add_action('init', array(__CLASS__, 'rewrite_rules'), 20);
        add_filter('query_vars', array(__CLASS__, 'query_vars'));
        add_action('template_redirect', array(__CLASS__, 'render_sitemap'), 1);
        add_action('init', array(__CLASS__, 'flush_rules_once'), 99);
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 35);
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'accessibility_assets'), 30);
    }

    public static function rewrite_rules() {
        add_rewrite_rule('^bigshor-language-sitemap\.xml$', 'index.php?' . self::QUERY_VAR . '=1', 'top');
    }

    public static function query_vars($vars) {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    public static function flush_rules_once() {
        if (get_option('bsw_p4_flush_rewrite_pending')) {
            delete_option('bsw_p4_flush_rewrite_pending');
            flush_rewrite_rules(false);
        }
    }

    public static function activation() {
        update_option('bsw_p4_flush_rewrite_pending', 1, false);
    }

    private static function linked_ids($post_id) {
        $ids = get_post_meta($post_id, '_bsw_p4_translations', true);
        if (!is_array($ids)) return array();
        $valid = array();
        foreach ($ids as $id) {
            $id = absint($id);
            if ($id && $id !== (int)$post_id && get_post_type($id) === 'wiki_article' && get_post_status($id) === 'publish') $valid[] = $id;
        }
        return array_values(array_unique($valid));
    }

    /** Emits a standards-based sitemap with XHTML hreflang alternates for manually linked versions. */
    public static function render_sitemap() {
        if ((string)get_query_var(self::QUERY_VAR) !== '1') return;
        if (!post_type_exists('wiki_article')) status_header(404);
        else status_header(200);
        nocache_headers();
        header('Content-Type: application/xml; charset=UTF-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        if (post_type_exists('wiki_article')) {
            $page = 1;
            do {
                $query = new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>300,'paged'=>$page,'fields'=>'ids','orderby'=>'ID','order'=>'ASC','no_found_rows'=>false));
                foreach ($query->posts as $post_id) {
                    $url = get_permalink($post_id);
                    if (!$url) continue;
                    echo '<url><loc>' . esc_xml($url) . '</loc>';
                    $modified = get_post_modified_time('c', true, $post_id);
                    if ($modified) echo '<lastmod>' . esc_xml($modified) . '</lastmod>';
                    $ids = array_merge(array((int)$post_id), self::linked_ids($post_id));
                    $seen = array();
                    foreach ($ids as $alternate_id) {
                        $terms = wp_get_post_terms($alternate_id, 'wiki_language');
                        if (is_wp_error($terms) || empty($terms)) continue;
                        $code = strtolower(str_replace('_', '-', sanitize_text_field($terms[0]->slug)));
                        $alternate_url = get_permalink($alternate_id);
                        if (!$code || !$alternate_url || isset($seen[$code])) continue;
                        $seen[$code] = true;
                        echo '<xhtml:link rel="alternate" hreflang="' . esc_attr($code) . '" href="' . esc_url($alternate_url) . '" />';
                    }
                    echo "</url>\n";
                }
                $max_pages = (int)$query->max_num_pages;
                wp_reset_postdata();
                $page++;
            } while ($page <= $max_pages);
        }
        echo '</urlset>';
        exit;
    }

    public static function register_routes() {
        register_rest_route('bigshor/v1', '/search-correct', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'search_correction'),
            'permission_callback' => '__return_true',
            'args' => array('q' => array('required'=>true, 'sanitize_callback'=>'sanitize_text_field')),
        ));
    }

    /** Conservative typo hints only; it never rewrites the query or asserts factual equivalence. */
    public static function search_correction($request) {
        $term = trim((string)$request->get_param('q'));
        if (function_exists('mb_strlen')) $length = mb_strlen($term); else $length = strlen($term);
        if ($length < 4 || $length > 80) return rest_ensure_response(array('suggestions'=>array()));
        $found = new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>1,'s'=>$term,'fields'=>'ids','no_found_rows'=>true));
        if ($found->have_posts()) return rest_ensure_response(array('suggestions'=>array()));
        $candidates = new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>250,'fields'=>'ids','orderby'=>'modified','order'=>'DESC','no_found_rows'=>true));
        $needle = strtolower(remove_accents($term));
        $matches = array();
        foreach ($candidates->posts as $id) {
            $title = get_the_title($id);
            $normalized = strtolower(remove_accents(wp_strip_all_tags($title)));
            $distance = levenshtein($needle, $normalized);
            $limit = ($length >= 9) ? 2 : 1;
            if ($distance <= $limit) $matches[] = array('distance'=>$distance,'title'=>$title,'url'=>get_permalink($id));
        }
        usort($matches, function($a,$b){ return $a['distance'] <=> $b['distance']; });
        $matches = array_slice($matches, 0, 5);
        $out = array();
        foreach ($matches as $match) $out[] = array('title'=>$match['title'],'url'=>$match['url']);
        return rest_ensure_response(array('suggestions'=>$out));
    }

    public static function admin_menu() {
        add_submenu_page('edit.php?post_type=wiki_article', 'Language Coverage & Sitemap', 'Language Coverage', 'manage_options', 'bsw-language-coverage', array(__CLASS__, 'coverage_page'));
    }

    public static function coverage_page() {
        if (!current_user_can('manage_options')) return;
        $total = wp_count_posts('wiki_article');
        $published = isset($total->publish) ? (int)$total->publish : 0;
        $languages = get_terms(array('taxonomy'=>'wiki_language','hide_empty'=>false));
        $missing_language = 0;
        $missing_group = 0;
        $page = 1;
        do {
            $q = new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>250,'paged'=>$page,'fields'=>'ids','no_found_rows'=>false));
            foreach ($q->posts as $id) {
                $terms = wp_get_post_terms($id, 'wiki_language');
                if (is_wp_error($terms) || !$terms) $missing_language++;
                if (!get_post_meta($id, '_bsw_p4_translation_group', true)) $missing_group++;
            }
            $max = (int)$q->max_num_pages;
            wp_reset_postdata();
            $page++;
        } while ($page <= $max);
        echo '<div class="wrap"><h1>BIGSHOR Wiki — Language Coverage</h1>';
        echo '<p>Operational checks for multilingual publishing. A language assignment or translation link does not prove that content is complete, accurate or equivalent.</p>';
        echo '<table class="widefat striped" style="max-width:900px"><tbody>';
        echo '<tr><th>Published articles</th><td>' . number_format_i18n($published) . '</td></tr>';
        echo '<tr><th>Configured language terms</th><td>' . (is_wp_error($languages) ? '0' : number_format_i18n(count($languages))) . '</td></tr>';
        echo '<tr><th>Published articles missing a language</th><td>' . number_format_i18n($missing_language) . '</td></tr>';
        echo '<tr><th>Published articles without a translation group</th><td>' . number_format_i18n($missing_group) . '</td></tr>';
        echo '</tbody></table>';
        echo '<h2>Multilingual sitemap</h2><p><a href="' . esc_url(home_url('/bigshor-language-sitemap.xml')) . '" target="_blank" rel="noopener">Open BIGSHOR language sitemap</a></p>';
        echo '<p>The sitemap includes published wiki articles and XHTML hreflang alternates for translation links maintained in each article. Submit this sitemap in Google Search Console and Bing Webmaster Tools, then validate a sample of language pairs.</p>';
        echo '<h2>Recommended editorial checks</h2><ol><li>Assign the correct language taxonomy to each published article.</li><li>Use consistent language codes in taxonomy slugs (for example, <code>en</code>, <code>hi</code>, <code>ar</code>).</li><li>Link only true translations, not merely related articles.</li><li>Ensure every translation has its own citations and editorial review.</li><li>Check canonical URLs, hreflang reciprocity and visible language switching.</li><li>Review right-to-left layouts on mobile and desktop for each supported theme template.</li></ol>';
        echo '<p><strong>URL architecture:</strong> this release preserves each article’s existing WordPress permalink to avoid breaking indexed URLs. Language-specific article paths should be introduced only with a separately tested redirect and canonical migration plan.</p>';
        echo '</div>';
    }

    public static function accessibility_assets() {
        if (is_admin()) return;
        $css = '.bsw-reader-controls button:focus-visible,.bsw-discovery-search a:focus-visible,.bsw-discovery-search button:focus-visible,.bsw-discovery-search input:focus-visible,.bsw-discovery-search select:focus-visible{outline:3px solid currentColor;outline-offset:3px}.bsw-search-suggestions{position:relative;z-index:5;max-width:100%;background:#fff;color:#111;border:1px solid #777}.bsw-search-suggestions a{padding:8px 10px;text-decoration:underline}.bsw-search-suggestions a:focus,.bsw-search-suggestions a:hover{background:#eee;color:#111}.bsw-translation-links a:focus-visible{outline:3px solid currentColor;outline-offset:3px}html.bsw-reader-large .entry-content,html.bsw-reader-large .wiki-content{font-size:1.18em;line-height:1.75}html.bsw-reader-wide .entry-content,html.bsw-reader-wide .wiki-content{max-width:100%!important}';
        wp_register_style('bsw-phase4-completion', false, array(), '33.0.0');
        wp_enqueue_style('bsw-phase4-completion');
        wp_add_inline_style('bsw-phase4-completion', $css);
        $script = "document.addEventListener('click',function(e){var a=e.target.closest('.bsw-search-suggestions a');if(!a)return;});document.addEventListener('keydown',function(e){var box=e.target.closest('.bsw-search-suggestions');if(!box)return;var links=Array.prototype.slice.call(box.querySelectorAll('a'));var i=links.indexOf(document.activeElement);if(e.key==='ArrowDown'){e.preventDefault();if(links.length)links[Math.min(i+1,links.length-1)].focus();}if(e.key==='ArrowUp'){e.preventDefault();if(links.length){if(i<=0)e.target.focus();else links[i-1].focus();}}if(e.key==='Escape'){box.innerHTML='';e.target.focus();}});";
        wp_register_script('bsw-phase4-completion', false, array(), '33.0.0', true);
        wp_enqueue_script('bsw-phase4-completion');
        wp_add_inline_script('bsw-phase4-completion', $script);
    }
}
BSW_Phase4_Completion::boot();
}
