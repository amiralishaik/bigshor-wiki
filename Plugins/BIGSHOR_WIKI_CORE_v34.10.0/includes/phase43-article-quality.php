<?php
/**
 * Phase 43: article quality diagnostics, taxonomy helpers and structured data.
 * Advisory editorial checks; diagnostics never block an authorised save or publication.
 */
if (!defined('ABSPATH')) exit;
final class BSW_Phase43_Article_Quality {
    public static function init() {
        add_action('add_meta_boxes_wiki_article', array(__CLASS__, 'metabox'));
        add_action('save_post_wiki_article', array(__CLASS__, 'save_schema'), 30, 2);
        add_action('wp_head', array(__CLASS__, 'schema'), 30);
        add_filter('the_content', array(__CLASS__, 'article_categories'), 25);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'admin_css'));
    }
    /** Return transparent, deterministic checks that editors can review and improve. */
    public static function quality_checks($post) {
        $body = wp_strip_all_tags(strip_shortcodes($post->post_content));
        $words = preg_split('/\s+/u', trim($body), -1, PREG_SPLIT_NO_EMPTY);
        $word_count = count($words);
        $sentences = preg_split('/(?<=[.!?])\s+/', $body);
        $seen = array(); $duplicates = array();
        foreach ($sentences as $sentence) {
            $s = strtolower(trim(preg_replace('/\W+/', ' ', $sentence)));
            if (strlen($s) < 55) continue;
            if (isset($seen[$s])) $duplicates[] = $sentence;
            $seen[$s] = true;
        }
        preg_match_all('/<h([2-6])\b/i', $post->post_content, $heads);
        $heading_levels = array_map('intval', $heads[1]);
        $heading_hierarchy_ok = true; $previous = 1;
        foreach ($heading_levels as $level) {
            if ($level > $previous + 1) $heading_hierarchy_ok = false;
            $previous = $level;
        }
        preg_match_all('/<img\b[^>]*>/i', $post->post_content, $images);
        $missing_alt = 0;
        foreach ($images[0] as $image) {
            if (!preg_match('/\balt\s*=\s*(["\'])(.*?)\1/i', $image, $alt) || trim($alt[2]) === '') $missing_alt++;
        }
        $sources = get_post_meta($post->ID, '_bsw_phase6_sources', true);
        $has_source_records = is_array($sources) && count($sources) > 0;
        $has_citations = (bool) preg_match('/\[(?:bsw|bigshor)_cite\b|\[(?:bsw|bigshor)_references\b|bsw6-references/i', $post->post_content);
        return array(
            array('Article title is descriptive', strlen(trim(get_the_title($post))) >= 10, 'Use a clear, specific title rather than a short or ambiguous label.'),
            array('Lead or summary is present', strlen(trim($post->post_excerpt)) >= 80 || $word_count >= 180, 'Add a concise lead that explains the subject and its significance.'),
            array('Article has substantive coverage', $word_count >= 300, 'Consider expanding the article with verifiable context; 300 words is a guidance threshold, not a publishing rule.'),
            array('Sections organise the article', count($heading_levels) >= 2, 'Use meaningful section headings to organise longer articles.'),
            array('Heading levels follow a logical order', $heading_hierarchy_ok, 'Avoid skipping heading levels (for example, H2 directly to H4).'),
            array('Sources and citations are documented', $has_citations && $has_source_records, 'Add source records and cite them beside the claims they support.'),
            array('Featured image is assigned', has_post_thumbnail($post->ID), 'Add a relevant featured image when suitable and rights-cleared.'),
            array('Embedded images have alternative text', $missing_alt === 0, $missing_alt ? $missing_alt . ' embedded image(s) need meaningful alt text.' : 'Provide concise alt text for informative images.'),
            array('No exact repeated long sentences', empty($duplicates), 'Review repeated passages for accidental duplication.')
        );
    }
    public static function metabox() {
        add_meta_box('bsw43_quality', 'BIGSHOR Article Quality Review', array(__CLASS__, 'render'), 'wiki_article', 'side', 'default');
    }
    public static function render($post) {
        $checks = self::quality_checks($post);
        $passed = 0;
        foreach ($checks as $check) if ($check[1]) $passed++;
        $percent = (int) round(($passed / max(1, count($checks))) * 100);
        echo '<div class="bsw43-quality-summary" role="status"><strong>Editorial checklist: '.esc_html($passed).' of '.count($checks).' checks met ('.esc_html($percent).'%)</strong><p class="description">Guidance only. This score is not an approval, fact-check or publication gate.</p></div><ul class="bsw43-checklist">';
        foreach ($checks as $c) {
            echo '<li><span class="'.($c[1]?'ok':'warn').'" aria-hidden="true">'.($c[1]?'✓':'!').'</span> <strong>'.esc_html($c[0]).'</strong><br><span class="description">'.esc_html($c[2]).'</span></li>';
        }
        echo '</ul><p class="description">Automated checks cannot determine neutrality, notability, factual accuracy, source independence, copyright status or whether a citation truly supports a claim. Editors must review these manually.</p>';
        wp_nonce_field('bsw43_schema_'.$post->ID, 'bsw43_schema_nonce');
        $type = get_post_meta($post->ID, '_bsw43_schema_type', true) ?: 'Article';
        echo '<p><label for="bsw43_schema_type">Article subject type</label><select name="bsw43_schema_type" id="bsw43_schema_type">';
        foreach (array('Article','Person','Organization','Place','Event','Book','CreativeWork') as $v) echo '<option '.selected($type,$v,false).' value="'.esc_attr($v).'">'.esc_html($v).'</option>';
        echo '</select></p>';
    }
    public static function save_schema($post_id, $post) {
        if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) return;
        if (!isset($_POST['bsw43_schema_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw43_schema_nonce'])), 'bsw43_schema_'.$post_id)) return;
        if (!current_user_can('edit_post', $post_id)) return;
        $allowed = array('Article','Person','Organization','Place','Event','Book','CreativeWork');
        $type = sanitize_text_field(wp_unslash($_POST['bsw43_schema_type'] ?? 'Article'));
        if (in_array($type,$allowed,true)) update_post_meta($post_id,'_bsw43_schema_type',$type);
    }
    public static function schema() {
        if (!is_singular('wiki_article')) return;
        $post = get_queried_object();
        if (!$post instanceof WP_Post) return;
        $subject_type = get_post_meta($post->ID, '_bsw43_schema_type', true) ?: 'Article';
        $allowed = array('Article','Person','Organization','Place','Event','Book','CreativeWork');
        if (!in_array($subject_type, $allowed, true)) $subject_type = 'Article';
        $description = wp_trim_words(wp_strip_all_tags($post->post_excerpt ?: $post->post_content), 45);
        $data = array('@context'=>'https://schema.org','@type'=>'Article','headline'=>get_the_title($post),'url'=>get_permalink($post),'datePublished'=>get_post_time('c',true,$post),'dateModified'=>get_post_modified_time('c',true,$post),'description'=>$description,'mainEntityOfPage'=>array('@type'=>'WebPage','@id'=>get_permalink($post)));
        if ($subject_type !== 'Article') {
            $subject = array('@type'=>$subject_type,'name'=>get_the_title($post),'description'=>$description);
            if ($subject_type === 'Person') $subject['url'] = get_permalink($post);
            $data['about'] = $subject;
        }
        if (has_post_thumbnail($post->ID)) $data['image'] = get_the_post_thumbnail_url($post->ID, 'full');
        $author = get_userdata($post->post_author);
        if ($author) $data['author'] = array('@type'=>'Person','name'=>$author->display_name);
        echo "\n<script type=\"application/ld+json\">" . wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
    }
    public static function article_categories($content) {
        if (!is_singular('wiki_article') || !in_the_loop() || !is_main_query()) return $content;
        $terms = get_the_terms(get_the_ID(), 'category');
        if (empty($terms) || is_wp_error($terms)) return $content;
        $html = '<div class="bsw43-categories"><strong>Categories:</strong> ';
        $links = array();
        foreach ($terms as $term) { $url = get_term_link($term); if (!is_wp_error($url)) $links[] = '<a href="'.esc_url($url).'">'.esc_html($term->name).'</a>'; }
        return $links ? $content.$html.implode(' · ',$links).'</div>' : $content;
    }
    public static function admin_css($hook) {
        if (!in_array($hook,array('post.php','post-new.php'),true)) return;
        wp_enqueue_style('wp-admin');
        wp_add_inline_style('wp-admin', '.bsw43-checklist{margin:8px 0}.bsw43-checklist li{margin:9px 0}.bsw43-checklist .ok{color:#16834a;font-weight:700}.bsw43-checklist .warn{color:#b36b00;font-weight:700}.bsw43-checklist select{width:100%}.bsw43-quality-summary{padding:8px;background:#f6f7f7;border-left:3px solid #2271b1}');
    }
}
BSW_Phase43_Article_Quality::init();
