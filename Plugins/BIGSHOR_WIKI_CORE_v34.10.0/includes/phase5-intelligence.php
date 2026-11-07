<?php
/**
 * BIGSHOR Wiki Phase 5 — Editorial Intelligence & Visibility Foundation.
 *
 * Deterministic checks only. No external AI calls, fabricated citations,
 * automatic fact verification, or automatic publication are performed.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase5_Intelligence')) {
final class BSW_Phase5_Intelligence {
    const META_FACT_STATUS = '_bsw_p5_fact_status';
    const META_REVIEW_DATE = '_bsw_p5_review_date';
    const META_REVIEW_NOTES = '_bsw_p5_review_notes';
    const META_SOURCE_NOTES = '_bsw_p5_source_provenance';
    const META_SEO_TITLE = '_bsw_p5_seo_title';
    const META_SEO_DESC = '_bsw_p5_seo_description';

    public static function boot() {
        add_action('add_meta_boxes_wiki_article', array(__CLASS__, 'add_metabox'));
        add_action('save_post_wiki_article', array(__CLASS__, 'save_metabox'), 60, 2);
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 45);
        add_filter('manage_wiki_article_posts_columns', array(__CLASS__, 'columns'));
        add_action('manage_wiki_article_posts_custom_column', array(__CLASS__, 'column_value'), 10, 2);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'admin_assets'));
        add_action('wp_head', array(__CLASS__, 'seo_meta'), 8);
    }

    public static function add_metabox() {
        add_meta_box('bsw-p5-intelligence', 'BIGSHOR Editorial Intelligence', array(__CLASS__, 'metabox_html'), 'wiki_article', 'normal', 'high');
    }

    private static function get_meta($post_id, $key, $default = '') {
        $value = get_post_meta($post_id, $key, true);
        return $value === '' ? $default : $value;
    }

    private static function count_words($text) {
        $plain = trim(wp_strip_all_tags(strip_shortcodes($text)));
        if ($plain === '') return 0;
        $words = preg_split('/\s+/u', $plain);
        return is_array($words) ? count($words) : 0;
    }

    public static function audit($post) {
        $content = (string) $post->post_content;
        $plain = wp_strip_all_tags(strip_shortcodes($content));
        $words = self::count_words($content);
        $headings = preg_match_all('/<h[2-6][^>]*>/i', $content, $m);
        $links = preg_match_all('/<a\s[^>]*href\s*=/i', $content, $m2);
        $images = preg_match_all('/<img\s[^>]*>/i', $content, $m3);
        $citation_markers = preg_match_all('/<sup[^>]*>.*?<\/sup>|\[\s*\d+\s*\]/is', $content, $m4);
        $has_refs = (bool) preg_match('/references|bibliography|sources|citations/i', $plain);
        $checks = array(
            'substantial_content' => array('label'=>'Article length', 'pass'=>$words >= 300, 'detail'=>$words . ' words (target: at least 300)'),
            'section_structure' => array('label'=>'Section structure', 'pass'=>$headings >= 2, 'detail'=>$headings . ' section headings (target: at least 2)'),
            'citations' => array('label'=>'Citation markers', 'pass'=>$citation_markers >= 1, 'detail'=>$citation_markers . ' possible citation markers detected'),
            'references_section' => array('label'=>'References section', 'pass'=>$has_refs, 'detail'=>$has_refs ? 'A references/source heading appears in the article text' : 'No references/source heading detected'),
            'internal_or_external_links' => array('label'=>'Links', 'pass'=>$links >= 1, 'detail'=>$links . ' links detected'),
            'images' => array('label'=>'Images', 'pass'=>has_post_thumbnail($post->ID) || $images >= 1, 'detail'=>(has_post_thumbnail($post->ID) || $images >= 1) ? 'Featured image or inline image present' : 'No image detected'),
            'excerpt' => array('label'=>'Summary / excerpt', 'pass'=>!empty($post->post_excerpt), 'detail'=>!empty($post->post_excerpt) ? 'Excerpt provided' : 'Add a concise article excerpt'),
            'review_date' => array('label'=>'Editorial review date', 'pass'=>!empty(self::get_meta($post->ID, self::META_REVIEW_DATE)), 'detail'=>!empty(self::get_meta($post->ID, self::META_REVIEW_DATE)) ? 'Review date recorded' : 'Record the date of the latest human review'),
        );
        $passed = 0;
        foreach ($checks as $check) if ($check['pass']) $passed++;
        return array('score'=>(int) round(($passed / max(1, count($checks))) * 100), 'words'=>$words, 'checks'=>$checks);
    }

    public static function metabox_html($post) {
        wp_nonce_field('bsw_p5_save', 'bsw_p5_nonce');
        $audit = self::audit($post);
        $fact = self::get_meta($post->ID, self::META_FACT_STATUS, 'not_reviewed');
        $review_date = self::get_meta($post->ID, self::META_REVIEW_DATE);
        $notes = self::get_meta($post->ID, self::META_REVIEW_NOTES);
        $sources = self::get_meta($post->ID, self::META_SOURCE_NOTES);
        $seo_title = self::get_meta($post->ID, self::META_SEO_TITLE);
        $seo_desc = self::get_meta($post->ID, self::META_SEO_DESC);
        echo '<div class="bsw-p5-wrap">';
        echo '<p><strong>Deterministic article quality score: ' . esc_html($audit['score']) . '/100</strong> — this checks editorial completeness, not factual truth.</p>';
        echo '<ul class="bsw-p5-checks">';
        foreach ($audit['checks'] as $check) {
            echo '<li><span class="' . ($check['pass'] ? 'bsw-p5-pass' : 'bsw-p5-warn') . '">' . ($check['pass'] ? '✓' : '○') . '</span> <strong>' . esc_html($check['label']) . ':</strong> ' . esc_html($check['detail']) . '</li>';
        }
        echo '</ul><hr/><p><label for="bsw_p5_fact"><strong>Human fact-check status</strong></label><br/><select id="bsw_p5_fact" name="bsw_p5_fact">';
        $statuses = array('not_reviewed'=>'Not reviewed','in_review'=>'In review','sources_checked'=>'Sources checked by editor','disputed'=>'Disputed / needs attention');
        foreach ($statuses as $value=>$label) echo '<option value="' . esc_attr($value) . '" ' . selected($fact, $value, false) . '>' . esc_html($label) . '</option>';
        echo '</select></p>';
        echo '<p><label for="bsw_p5_review_date"><strong>Last human review date</strong></label><br/><input type="date" id="bsw_p5_review_date" name="bsw_p5_review_date" value="' . esc_attr($review_date) . '"/></p>';
        echo '<p><label for="bsw_p5_source_notes"><strong>Source provenance / source quality notes</strong></label><br/><textarea class="widefat" rows="3" id="bsw_p5_source_notes" name="bsw_p5_source_notes">' . esc_textarea($sources) . '</textarea><span class="description">Record source types, independence, primary/secondary status and any limitations. Do not paste confidential information.</span></p>';
        echo '<p><label for="bsw_p5_review_notes"><strong>Editorial review notes</strong></label><br/><textarea class="widefat" rows="3" id="bsw_p5_review_notes" name="bsw_p5_review_notes">' . esc_textarea($notes) . '</textarea></p>';
        echo '<hr/><h4>Search visibility metadata</h4>';
        echo '<p><label for="bsw_p5_seo_title"><strong>SEO title override</strong></label><br/><input class="widefat" maxlength="180" id="bsw_p5_seo_title" name="bsw_p5_seo_title" value="' . esc_attr($seo_title) . '"/></p>';
        echo '<p><label for="bsw_p5_seo_desc"><strong>SEO description override</strong></label><br/><textarea class="widefat" rows="2" maxlength="320" id="bsw_p5_seo_desc" name="bsw_p5_seo_desc">' . esc_textarea($seo_desc) . '</textarea></p>';
        echo '<p><button type="button" class="button" id="bsw-p5-prompt">Generate editorial AI prompt</button> <span class="description">Creates a prompt for an external AI assistant; no AI service is called from WordPress.</span></p>';
        echo '<textarea readonly class="widefat" rows="8" id="bsw-p5-prompt-output" style="display:none"></textarea>';
        echo '</div>';
    }

    public static function save_metabox($post_id, $post) {
        if (!isset($_POST['bsw_p5_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_p5_nonce'])), 'bsw_p5_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) return;
        $fact = isset($_POST['bsw_p5_fact']) ? sanitize_key(wp_unslash($_POST['bsw_p5_fact'])) : 'not_reviewed';
        $allowed = array('not_reviewed','in_review','sources_checked','disputed');
        if (!in_array($fact, $allowed, true)) $fact = 'not_reviewed';
        update_post_meta($post_id, self::META_FACT_STATUS, $fact);
        $date = isset($_POST['bsw_p5_review_date']) ? sanitize_text_field(wp_unslash($_POST['bsw_p5_review_date'])) : '';
        if ($date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = '';
        update_post_meta($post_id, self::META_REVIEW_DATE, $date);
        update_post_meta($post_id, self::META_SOURCE_NOTES, isset($_POST['bsw_p5_source_notes']) ? sanitize_textarea_field(wp_unslash($_POST['bsw_p5_source_notes'])) : '');
        update_post_meta($post_id, self::META_REVIEW_NOTES, isset($_POST['bsw_p5_review_notes']) ? sanitize_textarea_field(wp_unslash($_POST['bsw_p5_review_notes'])) : '');
        update_post_meta($post_id, self::META_SEO_TITLE, isset($_POST['bsw_p5_seo_title']) ? sanitize_text_field(wp_unslash($_POST['bsw_p5_seo_title'])) : '');
        update_post_meta($post_id, self::META_SEO_DESC, isset($_POST['bsw_p5_seo_desc']) ? sanitize_textarea_field(wp_unslash($_POST['bsw_p5_seo_desc'])) : '');
    }

    public static function columns($columns) {
        $columns['bsw_p5_quality'] = 'Quality';
        $columns['bsw_p5_fact'] = 'Fact-check';
        return $columns;
    }

    public static function column_value($column, $post_id) {
        if ($column === 'bsw_p5_quality') {
            $post = get_post($post_id);
            if ($post) { $audit = self::audit($post); echo esc_html($audit['score'] . '/100'); }
        }
        if ($column === 'bsw_p5_fact') {
            $fact = self::get_meta($post_id, self::META_FACT_STATUS, 'not_reviewed');
            $labels = array('not_reviewed'=>'Not reviewed','in_review'=>'In review','sources_checked'=>'Sources checked','disputed'=>'Disputed');
            echo esc_html(isset($labels[$fact]) ? $labels[$fact] : 'Not reviewed');
        }
    }

    public static function admin_menu() {
        add_submenu_page('edit.php?post_type=wiki_article', 'Editorial Intelligence', 'Editorial Intelligence', 'edit_others_posts', 'bsw-editorial-intelligence', array(__CLASS__, 'dashboard'));
    }

    public static function dashboard() {
        if (!current_user_can('edit_others_posts')) return;
        $query = new WP_Query(array('post_type'=>'wiki_article','post_status'=>array('publish','draft','pending'),'posts_per_page'=>30,'orderby'=>'modified','order'=>'ASC','no_found_rows'=>true));
        echo '<div class="wrap"><h1>BIGSHOR Editorial Intelligence</h1><p>Editorial completeness indicators are automated; factual verification always requires human review.</p>';
        echo '<table class="widefat striped"><thead><tr><th>Article</th><th>Status</th><th>Quality</th><th>Fact-check</th><th>Last review</th><th>Action</th></tr></thead><tbody>';
        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                $audit = self::audit($post);
                $fact = self::get_meta($post->ID, self::META_FACT_STATUS, 'not_reviewed');
                $date = self::get_meta($post->ID, self::META_REVIEW_DATE, 'Not recorded');
                echo '<tr><td>' . esc_html(get_the_title($post)) . '</td><td>' . esc_html($post->post_status) . '</td><td>' . esc_html($audit['score'] . '/100') . '</td><td>' . esc_html($fact) . '</td><td>' . esc_html($date) . '</td><td><a href="' . esc_url(get_edit_post_link($post->ID)) . '">Edit / review</a></td></tr>';
            }
        } else echo '<tr><td colspan="6">No articles found.</td></tr>';
        echo '</tbody></table><p class="description">Showing up to 30 articles, oldest recently modified first. Scores are not claims of accuracy or neutrality.</p></div>';
        wp_reset_postdata();
    }

    public static function admin_assets($hook) {
        if (!in_array($hook, array('post.php','post-new.php'), true)) return;
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== 'wiki_article') return;
        wp_register_script('bsw-p5-admin', '', array(), '19.0.0', true);
        wp_enqueue_script('bsw-p5-admin');
        $js = "document.addEventListener('click',function(e){if(e.target&&e.target.id==='bsw-p5-prompt'){var title=document.getElementById('title')?document.getElementById('title').value:'';var body=document.getElementById('content')?document.getElementById('content').value:'';var box=document.getElementById('bsw-p5-prompt-output');var prompt='You are an editorial research assistant for BIGSHOR Wiki. Review the draft below. Do not invent facts, citations, URLs, quotes, dates or sources. Separate verifiable claims from claims needing evidence. Suggest improvements and clearly mark uncertainty. Do not publish. Return: (1) neutral summary, (2) claims requiring verification, (3) source gaps, (4) structure and readability suggestions, (5) possible bias or conflict-of-interest concerns.\\n\\nTITLE: '+title+'\\n\\nDRAFT:\\n'+body;box.style.display='block';box.value=prompt;box.focus();box.select();}});";
        wp_add_inline_script('bsw-p5-admin', $js);
        wp_register_style('bsw-p5-admin-style', false, array(), '19.0.0');
        wp_enqueue_style('bsw-p5-admin-style');
        wp_add_inline_style('bsw-p5-admin-style', '.bsw-p5-checks{list-style:none;margin-left:0}.bsw-p5-checks li{margin:6px 0}.bsw-p5-pass{color:#16803c;font-weight:bold}.bsw-p5-warn{color:#9a6700;font-weight:bold}.bsw-p5-wrap textarea{max-width:900px}');
    }

    public static function seo_meta() {
        if (!is_singular('wiki_article')) return;
        $post_id = get_queried_object_id();
        if (!$post_id) return;
        $title = self::get_meta($post_id, self::META_SEO_TITLE);
        $desc = self::get_meta($post_id, self::META_SEO_DESC);
        if ($title) echo '<meta name="twitter:title" content="' . esc_attr($title) . "\" />\n";
        if ($desc) {
            echo '<meta name="twitter:description" content="' . esc_attr($desc) . "\" />\n";
            $seo_plugin_active = defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('AIOSEO_VERSION');
            if (!$seo_plugin_active) echo '<meta name="description" content="' . esc_attr($desc) . "\" />\n";
        }
    }
}
BSW_Phase5_Intelligence::boot();
}
