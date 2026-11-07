<?php
/**
 * Phase 43: article quality diagnostics, taxonomy helpers and structured data.
 * Diagnostics are advisory and never block an authorised save or publication.
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
    public static function metabox() {
        add_meta_box('bsw43_quality', 'BIGSHOR Article Quality Review', array(__CLASS__, 'render'), 'wiki_article', 'side', 'default');
    }
    public static function render($post) {
        $body = wp_strip_all_tags(strip_shortcodes($post->post_content));
        $sentences = preg_split('/(?<=[.!?])\s+/', $body);
        $seen = array(); $duplicates = array();
        foreach ($sentences as $sentence) {
            $s = strtolower(trim(preg_replace('/\W+/', ' ', $sentence)));
            if (strlen($s) < 55) continue;
            if (isset($seen[$s])) $duplicates[] = $sentence;
            $seen[$s] = true;
        }
        preg_match_all('/<h([2-6])\b/i', $post->post_content, $heads);
        $checks = array(
            array('Lead summary', strlen(trim($post->post_excerpt)) >= 80 || strlen($body) >= 180),
            array('Section headings', count($heads[0]) >= 2),
            array('References or citations', preg_match('/bsw42-ref|references|citation|source:/i', $post->post_content)),
            array('Featured image', has_post_thumbnail($post->ID)),
            array('No exact repeated long sentences', empty($duplicates))
        );
        echo '<ul class="bsw43-checklist">';
        foreach ($checks as $c) echo '<li><span class="'.($c[1]?'ok':'warn').'">'.($c[1]?'✓':'!').'</span> '.esc_html($c[0]).'</li>';
        echo '</ul><p class="description">Advisory checks only. They do not prevent publishing. Review source quality, neutrality, attribution and completeness manually.</p>';
        if ($duplicates) {
            echo '<details><summary>Potential repeated passages ('.count($duplicates).')</summary><ol>';
            foreach (array_slice($duplicates,0,8) as $d) echo '<li>'.esc_html(wp_trim_words($d,28)).'</li>';
            echo '</ol></details>';
        }
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
        $data = array(
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => get_the_title($post),
            'url' => get_permalink($post),
            'datePublished' => get_post_time('c', true, $post),
            'dateModified' => get_post_modified_time('c', true, $post),
            'description' => $description,
            'mainEntityOfPage' => array('@type' => 'WebPage', '@id' => get_permalink($post))
        );
        if ($subject_type !== 'Article') {
            $subject = array('@type' => $subject_type, 'name' => get_the_title($post), 'description' => $description);
            if ($subject_type === 'Person') {
                $subject['url'] = get_permalink($post);
            }
            $data['about'] = $subject;
        }
        if (has_post_thumbnail($post->ID)) $data['image'] = get_the_post_thumbnail_url($post->ID, 'full');
        $author = get_userdata($post->post_author);
        if ($author) $data['author'] = array('@type' => 'Person', 'name' => $author->display_name);
        echo "\n<script type=\"application/ld+json\">" . wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
    }
    public static function article_categories($content) {
        if (!is_singular('wiki_article') || !in_the_loop() || !is_main_query()) return $content;
        $terms = get_the_terms(get_the_ID(), 'category');
        if (empty($terms) || is_wp_error($terms)) return $content;
        $html = '<div class="bsw43-categories"><strong>Categories:</strong> ';
        $links = array();
        foreach ($terms as $term) $links[] = '<a href="'.esc_url(get_term_link($term)).'">'.esc_html($term->name).'</a>';
        return $content.$html.implode(' · ',$links).'</div>';
    }
    public static function admin_css($hook) {
        if (!in_array($hook,array('post.php','post-new.php'),true)) return;
        wp_enqueue_style('wp-admin');
        wp_add_inline_style('wp-admin', '.bsw43-checklist{margin:8px 0}.bsw43-checklist li{margin:7px 0}.bsw43-checklist .ok{color:#16834a;font-weight:700}.bsw43-checklist .warn{color:#b36b00;font-weight:700}.bsw43-checklist select{width:100%}');
    }
}
BSW_Phase43_Article_Quality::init();
