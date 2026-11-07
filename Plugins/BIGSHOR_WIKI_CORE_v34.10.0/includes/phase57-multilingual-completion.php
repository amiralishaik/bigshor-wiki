<?php
/**
 * BIGSHOR Wiki Phase 4 completion (Core 33.0.0).
 * Language-aware routes, search relevance, translation QA and accessibility diagnostics.
 * Human-authored translations only; no machine translation or automatic publication.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase57_Multilingual_Completion')) {
final class BSW_Phase57_Multilingual_Completion {
    const OPTION_URLS = 'bsw_p4_language_urls_enabled';
    const QUERY_VAR = 'bsw_lang_route';

    public static function boot() {
        add_action('init', array(__CLASS__, 'rewrite_rules'), 25);
        add_filter('query_vars', array(__CLASS__, 'query_vars'));
        add_action('pre_get_posts', array(__CLASS__, 'constrain_language_route'), 20);
        add_filter('post_type_link', array(__CLASS__, 'language_permalink'), 20, 2);
        add_filter('redirect_canonical', array(__CLASS__, 'preserve_language_route'), 10, 2);
        add_filter('language_attributes', array(__CLASS__, 'language_attributes'), 20);
        add_filter('get_canonical_url', array(__CLASS__, 'canonical_url'), 20, 2);
        add_action('wp_head', array(__CLASS__, 'canonical_and_xdefault'), 4);
        add_action('wp_head', array(__CLASS__, 'rtl_accessibility_css'), 30);
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 40);
        add_action('admin_init', array(__CLASS__, 'save_settings'));
        add_filter('posts_clauses', array(__CLASS__, 'search_relevance_order'), 20, 2);
        add_filter('manage_wiki_article_posts_columns', array(__CLASS__, 'quality_column'));
        add_action('manage_wiki_article_posts_custom_column', array(__CLASS__, 'render_quality_column'), 20, 2);
        add_action('admin_notices', array(__CLASS__, 'assignment_notice'));
        add_shortcode('bsw_language_switcher', array(__CLASS__, 'language_switcher'));
        add_shortcode('bsw_translation_quality', array(__CLASS__, 'quality_shortcode'));
    }

    public static function rewrite_rules() {
        add_rewrite_rule('^wiki-language/([a-zA-Z]{2,3}(?:-[a-zA-Z0-9]{2,8})?)/([^/]+)/?$', 'index.php?post_type=wiki_article&name=$matches[2]&' . self::QUERY_VAR . '=$matches[1]', 'top');
    }

    public static function query_vars($vars) { $vars[] = self::QUERY_VAR; return $vars; }

    private static function normalise_code($code) {
        $code = str_replace('_', '-', trim((string)$code));
        if (!preg_match('/^[a-zA-Z]{2,3}(?:-[a-zA-Z0-9]{2,8})?$/', $code)) return '';
        $parts = explode('-', $code); $parts[0] = strtolower($parts[0]);
        for ($i=1; $i<count($parts); $i++) $parts[$i] = (strlen($parts[$i]) === 2) ? strtoupper($parts[$i]) : ucfirst(strtolower($parts[$i]));
        return implode('-', $parts);
    }

    private static function post_language($post_id) {
        $terms = wp_get_post_terms($post_id, 'wiki_language');
        if (is_wp_error($terms) || empty($terms)) {
            $meta = get_post_meta($post_id, '_bsw_language_code', true);
            return self::normalise_code($meta);
        }
        return self::normalise_code($terms[0]->slug);
    }

    public static function language_permalink($url, $post) {
        if (!get_option(self::OPTION_URLS, 0) || !is_object($post) || $post->post_type !== 'wiki_article' || $post->post_status === 'auto-draft') return $url;
        $lang = self::post_language($post->ID);
        if (!$lang || !$post->post_name) return $url;
        return home_url(user_trailingslashit('wiki-language/' . rawurlencode(strtolower($lang)) . '/' . rawurlencode($post->post_name)));
    }

    public static function constrain_language_route($query) {
        if (is_admin() || !$query->is_main_query()) return;
        $lang = self::normalise_code($query->get(self::QUERY_VAR));
        if (!$lang) return;
        $slug = strtolower($lang);
        $query->set('post_type', 'wiki_article');
        $query->set('tax_query', array(array('taxonomy'=>'wiki_language','field'=>'slug','terms'=>array($slug))));
    }

    public static function preserve_language_route($redirect, $requested) {
        if (get_query_var(self::QUERY_VAR)) return false;
        return $redirect;
    }

    public static function language_attributes($output) {
        if (!is_singular('wiki_article')) return $output;
        $lang = self::post_language(get_queried_object_id());
        if (!$lang) return $output;
        $rtl = in_array(strtolower(substr($lang, 0, 2)), array('ar','fa','he','ur'), true);
        $output = preg_replace('/\s(?:lang|dir)=("|\').*?\1/i', '', $output);
        $output .= ' lang="' . esc_attr($lang) . '" dir="' . ($rtl ? 'rtl' : 'ltr') . '"';
        return trim($output);
    }

    public static function canonical_url($canonical, $post) {
        if ($post instanceof WP_Post && $post->post_type === 'wiki_article') return get_permalink($post->ID);
        return $canonical;
    }

    public static function canonical_and_xdefault() {
        if (!is_singular('wiki_article')) return;
        $id = get_queried_object_id();
        $links = get_post_meta($id, '_bsw_p4_translations', true);
        if (!is_array($links)) $links = array();
        $ids = array_merge(array($id), array_map('absint', $links));
        $seen = array();
        foreach ($ids as $other) {
            if (get_post_type($other) !== 'wiki_article' || get_post_status($other) !== 'publish') continue;
            $lang = self::post_language($other); $url = get_permalink($other);
            if (!$lang || !$url || isset($seen[strtolower($lang)])) continue;
            $seen[strtolower($lang)] = true;
        }
        // x-default is emitted only when an English variant exists; otherwise no arbitrary default is invented.
        foreach ($ids as $other) {
            if (get_post_type($other) !== 'wiki_article' || get_post_status($other) !== 'publish') continue;
            if (strtolower(self::post_language($other)) === 'en') {
                echo '<link rel="alternate" hreflang="x-default" href="' . esc_url(get_permalink($other)) . '" />' . "\n";
                break;
            }
        }
    }

    public static function rtl_accessibility_css() {
        if (!is_singular('wiki_article')) return;
        $lang = self::post_language(get_queried_object_id());
        if (!$lang || !in_array(strtolower(substr($lang,0,2)), array('ar','fa','he','ur'), true)) return;
        echo '<style id="bsw-phase4-rtl">.bsw-article-rtl,.bsw-article-rtl .entry-content,.bsw-article-rtl .wiki-content{direction:rtl;text-align:start}.bsw-article-rtl img,.bsw-article-rtl table{max-width:100%}.bsw-article-rtl th,.bsw-article-rtl td{text-align:start}.bsw-article-rtl blockquote{border-inline-start:3px solid currentColor;border-inline-end:0;margin-inline:0;padding-inline:1rem}.bsw-article-rtl input,.bsw-article-rtl textarea,.bsw-article-rtl select{direction:rtl;text-align:start}.bsw-article-rtl .bsw-translation-links{direction:rtl;text-align:start}</style>' . "\n";
    }

    public static function language_switcher($atts) {
        if (!is_singular('wiki_article')) return '';
        $id = absint(get_queried_object_id());
        $ids = get_post_meta($id, '_bsw_p4_translations', true);
        if (!is_array($ids)) $ids = array();
        $ids = array_merge(array($id), array_map('absint', $ids));
        $rows = array();
        foreach (array_unique($ids) as $other) {
            if (get_post_type($other) !== 'wiki_article' || get_post_status($other) !== 'publish') continue;
            $lang = self::post_language($other); if (!$lang) continue;
            $terms = wp_get_post_terms($other, 'wiki_language');
            $label = (!is_wp_error($terms) && !empty($terms)) ? $terms[0]->name : strtoupper($lang);
            $rows[] = '<li><a hreflang="' . esc_attr($lang) . '" lang="' . esc_attr($lang) . '" href="' . esc_url(get_permalink($other)) . '">' . esc_html($label) . '</a></li>';
        }
        if (!$rows) return '';
        return '<nav class="bsw-language-switcher" aria-label="' . esc_attr__('Article languages','bigshor-wiki') . '"><ul>' . implode('', $rows) . '</ul></nav>';
    }

    /** Returns advisory, deterministic checks; never labels a translation as factually correct. */
    public static function quality_report($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'wiki_article') return array('score'=>0,'checks'=>array());
        $content = (string)$post->post_content;
        $plain = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($content)));
        $words = preg_match_all('/[\p{L}\p{N}]+/u', $plain, $m);
        $checks = array(
            'language' => (bool) self::post_language($post_id),
            'title' => trim($post->post_title) !== '',
            'summary' => trim($post->post_excerpt) !== '',
            'body' => $words >= 80,
            'headings' => (bool) preg_match('/<h[2-6]\b|^={2,6}\s/m', $content),
            'references' => (bool) preg_match('/<ref\b|class=["\'][^"\']*(?:references|citation)|\[\d+\]/i', $content),
            'image_alt' => self::images_have_alt($content),
            'link_text' => self::links_have_text($content),
            'reviewer' => (bool) get_post_meta($post_id, BSW_Phase4_Workflow::META_REVIEWER, true),
            'review_approved' => get_post_meta($post_id, BSW_Phase4_Workflow::META_STATUS, true) === 'approved',
        );
        $passed = count(array_filter($checks));
        return array('score'=>(int)round(($passed / count($checks))*100),'passed'=>$passed,'total'=>count($checks),'words'=>(int)$words,'checks'=>$checks);
    }

    private static function images_have_alt($content) {
        if (!preg_match_all('/<img\b[^>]*>/i', $content, $matches)) return true;
        foreach ($matches[0] as $tag) if (!preg_match('/\balt\s*=\s*(["\']).*?\1/i', $tag)) return false;
        return true;
    }
    private static function links_have_text($content) {
        if (!preg_match_all('/<a\b[^>]*>(.*?)<\/a>/is', $content, $matches)) return true;
        foreach ($matches[1] as $label) if (trim(wp_strip_all_tags($label)) === '' && stripos($label, '<img') === false) return false;
        return true;
    }

    public static function quality_column($columns) { $columns['bsw_p4_quality'] = 'Translation QA'; return $columns; }
    public static function render_quality_column($column, $post_id) {
        if ($column !== 'bsw_p4_quality') return;
        $r = self::quality_report($post_id);
        echo esc_html($r['score'] . '%');
        echo '<br><small>' . esc_html($r['passed'] . '/' . $r['total'] . ' checks') . '</small>';
    }

    public static function quality_shortcode($atts) {
        if (!is_user_logged_in() || !current_user_can('edit_posts')) return '';
        $id = absint($atts['id'] ?? get_queried_object_id());
        $r = self::quality_report($id);
        if (!$r['total']) return '';
        $labels = array('language'=>'Language assigned','title'=>'Title present','summary'=>'Summary present','body'=>'At least 80 words','headings'=>'Section headings present','references'=>'Reference/citation marker present','image_alt'=>'Images have alt attributes','link_text'=>'Links have discernible text','reviewer'=>'Reviewer assigned','review_approved'=>'Editorial review approved');
        $out = '<section class="bsw-translation-quality"><h2>Translation completeness checks</h2><p>Automated structural checks only. This score does not assess translation accuracy, source reliability or factual equivalence.</p><p><strong>' . esc_html($r['score']) . '%</strong> (' . esc_html($r['passed']) . '/' . esc_html($r['total']) . ' checks passed; ' . esc_html($r['words']) . ' words)</p><ul>';
        foreach ($r['checks'] as $key=>$passed) $out .= '<li>' . ($passed ? '&#10003; ' : '&#9888; ') . esc_html($labels[$key] ?? $key) . '</li>';
        return $out . '</ul></section>';
    }

    public static function search_relevance_order($clauses, $query) {
        global $wpdb;
        if (is_admin() || !$query->is_search() || $query->get('post_type') !== 'wiki_article' || !$query->get('s')) return $clauses;
        $needle = trim((string)$query->get('s'));
        if ($needle === '' || strlen($needle) > 160) return $clauses;
        $like = '%' . $wpdb->esc_like($needle) . '%';
        $exact = $wpdb->prepare("CASE WHEN {$wpdb->posts}.post_title = %s THEN 0 WHEN {$wpdb->posts}.post_title LIKE %s THEN 1 WHEN {$wpdb->posts}.post_excerpt LIKE %s THEN 2 ELSE 3 END", $needle, $like, $like);
        $clauses['orderby'] = $exact . ', ' . $clauses['orderby'];
        return $clauses;
    }

    public static function admin_menu() {
        add_submenu_page('edit.php?post_type=wiki_article', 'Multilingual Quality & URLs', 'Multilingual QA', 'manage_options', 'bsw-multilingual-qa', array(__CLASS__, 'admin_page'));
    }

    public static function save_settings() {
        if (!is_admin() || !current_user_can('manage_options') || empty($_POST['bsw_p4_settings_submit'])) return;
        check_admin_referer('bsw_p4_settings');
        $enabled = !empty($_POST['bsw_p4_language_urls']) ? 1 : 0;
        update_option(self::OPTION_URLS, $enabled, false);
        update_option('bsw_p4_flush_rewrite_pending', 1, false);
        wp_safe_redirect(add_query_arg('settings-updated','true',admin_url('edit.php?post_type=wiki_article&page=bsw-multilingual-qa')));
        exit;
    }

    public static function admin_page() {
        if (!current_user_can('manage_options')) return;
        $enabled = (bool)get_option(self::OPTION_URLS, 0);
        $total = 0; $missing = 0; $incomplete = 0; $rtl = 0; $page = 1;
        do {
            $q = new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>200,'paged'=>$page,'fields'=>'ids','no_found_rows'=>false));
            foreach ($q->posts as $id) {
                $total++; $lang = self::post_language($id); if (!$lang) $missing++;
                if ($lang && in_array(strtolower(substr($lang,0,2)), array('ar','fa','he','ur'), true)) $rtl++;
                $r = self::quality_report($id); if ($r['score'] < 80) $incomplete++;
            }
            $max = (int)$q->max_num_pages; $page++; wp_reset_postdata();
        } while ($page <= $max);
        echo '<div class="wrap"><h1>BIGSHOR Wiki — Multilingual QA & URL settings</h1>';
        echo '<p>Structural completeness and accessibility indicators are advisory. Human reviewers remain responsible for language quality, factual accuracy and source validation.</p>';
        echo '<table class="widefat striped" style="max-width:760px"><tbody><tr><th>Published wiki articles</th><td>' . number_format_i18n($total) . '</td></tr><tr><th>Missing language assignment</th><td>' . number_format_i18n($missing) . '</td></tr><tr><th>Below 80% structural checks</th><td>' . number_format_i18n($incomplete) . '</td></tr><tr><th>RTL language articles</th><td>' . number_format_i18n($rtl) . '</td></tr></tbody></table>';
        echo '<h2>Language-specific URL routing</h2><p>Optional route format: <code>/wiki-language/ar/article-slug/</code>. Existing URLs remain unchanged when this option is off. Enable only after testing rewrite rules and redirects on staging.</p>';
        echo '<form method="post">'; wp_nonce_field('bsw_p4_settings');
        echo '<input type="hidden" name="bsw_p4_settings_submit" value="1"><label><input type="checkbox" name="bsw_p4_language_urls" value="1" ' . checked($enabled, true, false) . '> Enable language-specific routes for language-assigned wiki articles</label><p><button class="button button-primary">Save multilingual settings</button></p></form>';
        echo '<h2>Recommended validation</h2><ol><li>Test at least one article in each configured language, including Arabic, Urdu, Persian or Hebrew where enabled.</li><li>Confirm reciprocal hreflang links and one canonical URL per article.</li><li>Check search relevance and language filters with real multilingual queries.</li><li>Run keyboard-only checks, screen-reader review and a full WCAG 2.2 AA audit using specialist tools and manual testing.</li><li>Submit <code>/bigshor-language-sitemap.xml</code> to search engine webmaster tools after staging verification.</li></ol>';
        echo '<p><strong>WCAG status:</strong> this release adds targeted semantics, keyboard focus and RTL layout improvements; it does not claim full WCAG conformance.</p></div>';
    }

    public static function assignment_notice() {
        if (!is_admin() || !current_user_can('manage_options') || empty($_GET['bsw_p4_assignment_saved'])) return;
        echo '<div class="notice notice-success is-dismissible"><p>Translation reviewer assignment saved.</p></div>';
    }
}
BSW_Phase57_Multilingual_Completion::boot();
}
