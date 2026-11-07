<?php
/**
 * BIGSHOR Wiki Core — Phase 1 Foundation Module
 * Integrated Core module: structured encyclopedia articles, templates, infoboxes, references, redirects, disambiguation and reader tools.
 * Version: 14.0.0
 * Author: BIGSHOR
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 */
if (!defined('ABSPATH')) exit;

final class BSW_Phase1_Foundation {
    const VERSION = '1.0.0';
    const NONCE = 'bsw_p1_save';
    public static function init() {
        add_action('plugins_loaded', array(__CLASS__, 'check_core'));
        add_action('add_meta_boxes_wiki_article', array(__CLASS__, 'meta_boxes'));
        add_action('save_post_wiki_article', array(__CLASS__, 'save_meta'), 10, 2);
        add_filter('the_content', array(__CLASS__, 'render_article_extras'), 25);
        add_action('template_redirect', array(__CLASS__, 'handle_redirect'), 1);
        add_filter('manage_wiki_article_posts_columns', array(__CLASS__, 'columns'));
        add_action('manage_wiki_article_posts_custom_column', array(__CLASS__, 'column_value'), 10, 2);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'admin_assets'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'reader_assets'));
        add_shortcode('bsw_print_article', array(__CLASS__, 'print_button'));
        add_shortcode('bsw_article_references', array(__CLASS__, 'references_shortcode'));
        add_shortcode('bsw_article_infobox', array(__CLASS__, 'infobox_shortcode'));
        add_action('init', array(__CLASS__, 'register_rewrite_support'));
    }
    public static function check_core() {
        if (!defined('BSW_CORE_PLUGIN_ACTIVE')) {
            add_action('admin_notices', function () {
                if (current_user_can('activate_plugins')) echo '<div class="notice notice-warning"><p><strong>BIGSHOR Wiki Phase 1:</strong> Activate BIGSHOR Wiki Core 13.0.2 or later first.</p></div>';
            });
        }
    }
    public static function meta_boxes() {
        add_meta_box('bsw_p1_template', 'Article type & template', array(__CLASS__, 'template_box'), 'wiki_article', 'side', 'high');
        add_meta_box('bsw_p1_infobox', 'Structured infobox', array(__CLASS__, 'infobox_box'), 'wiki_article', 'normal', 'high');
        add_meta_box('bsw_p1_sources', 'References & citations', array(__CLASS__, 'sources_box'), 'wiki_article', 'normal', 'high');
        add_meta_box('bsw_p1_controls', 'Article status & navigation', array(__CLASS__, 'controls_box'), 'wiki_article', 'side', 'default');
    }
    private static function templates() {
        return array(
            'general' => array('General article', "\n\n## Overview\n\n## History\n\n## Work and activities\n\n## References\n"),
            'person' => array('Person', "\n\n## Early life and education\n\n## Career\n\n## Personal life\n\n## Awards and recognition\n\n## References\n"),
            'company' => array('Company / organisation', "\n\n## History\n\n## Operations\n\n## Products and services\n\n## Leadership\n\n## References\n"),
            'place' => array('Place / location', "\n\n## Geography\n\n## History\n\n## Demographics\n\n## Economy and infrastructure\n\n## References\n"),
            'event' => array('Event', "\n\n## Background\n\n## Event\n\n## Impact and aftermath\n\n## References\n"),
            'book' => array('Book / publication', "\n\n## Background\n\n## Synopsis\n\n## Publication history\n\n## Reception\n\n## References\n"),
            'film' => array('Film / television', "\n\n## Plot\n\n## Cast\n\n## Production\n\n## Release and reception\n\n## References\n"),
            'product' => array('Product', "\n\n## History\n\n## Design and features\n\n## Availability\n\n## Reception\n\n## References\n"),
            'education' => array('Educational institution', "\n\n## History\n\n## Campus\n\n## Academics\n\n## Governance\n\n## References\n")
        );
    }
    public static function template_box($post) {
        wp_nonce_field(self::NONCE, self::NONCE);
        $type = get_post_meta($post->ID, '_bsw_p1_article_type', true) ?: 'general';
        echo '<p><label for="bsw_p1_type">Choose an article type</label><select id="bsw_p1_type" name="bsw_p1_article_type" style="width:100%">';
        foreach (self::templates() as $key => $tpl) echo '<option value="'.esc_attr($key).'" '.selected($type,$key,false).'>'.esc_html($tpl[0]).'</option>';
        echo '</select></p><p><button type="button" class="button" id="bsw-p1-insert-template">Insert section structure</button></p><p class="description">Adds a starter outline to the editor. It does not overwrite existing article text.</p>';
    }
    public static function infobox_box($post) {
        $rows = get_post_meta($post->ID, '_bsw_p1_infobox_rows', true);
        $rows = is_array($rows) ? $rows : array();
        $title = get_post_meta($post->ID, '_bsw_p1_infobox_title', true);
        $image = get_post_meta($post->ID, '_bsw_p1_infobox_image', true);
        echo '<p><label>Infobox title</label><input class="widefat" name="bsw_p1_infobox_title" value="'.esc_attr($title).'" placeholder="e.g. Personal details"></p>';
        echo '<p><label>Image URL (optional)</label><input class="widefat" name="bsw_p1_infobox_image" value="'.esc_attr($image).'" placeholder="Use a media-library image URL"></p>';
        echo '<p class="description">Add one fact per row. Keep facts concise and verifiable.</p><table class="widefat striped" id="bsw-p1-facts"><thead><tr><th>Label</th><th>Value</th><th></th></tr></thead><tbody>';
        foreach ($rows as $row) self::fact_row($row['label'] ?? '', $row['value'] ?? '');
        if (!$rows) self::fact_row('', '');
        echo '</tbody></table><p><button type="button" class="button" id="bsw-p1-add-fact">Add fact</button></p>';
        echo '<script type="text/html" id="tmpl-bsw-p1-fact"><tr><td><input class="widefat" name="bsw_p1_fact_label[]" /></td><td><input class="widefat" name="bsw_p1_fact_value[]" /></td><td><button type="button" class="button-link-delete bsw-p1-remove">Remove</button></td></tr></script>';
    }
    private static function fact_row($label, $value) {
        echo '<tr><td><input class="widefat" name="bsw_p1_fact_label[]" value="'.esc_attr($label).'" /></td><td><input class="widefat" name="bsw_p1_fact_value[]" value="'.esc_attr($value).'" /></td><td><button type="button" class="button-link-delete bsw-p1-remove">Remove</button></td></tr>';
    }
    public static function sources_box($post) {
        $sources = get_post_meta($post->ID, '_bsw_p1_sources', true);
        $sources = is_array($sources) ? $sources : array();
        echo '<p class="description">One source per row. Add title, URL, publisher, publication date and access date. Only cite sources you have checked.</p><table class="widefat striped" id="bsw-p1-sources"><thead><tr><th>#</th><th>Source title</th><th>URL</th><th>Publisher / date</th><th></th></tr></thead><tbody>';
        foreach ($sources as $i => $s) self::source_row($i+1, $s);
        if (!$sources) self::source_row(1, array());
        echo '</tbody></table><p><button type="button" class="button" id="bsw-p1-add-source">Add reference</button></p>';
        echo '<p class="description">In article text, insert a citation using <code>[bsw_cite id="1"]</code>. The reference list is appended automatically.</p>';
        echo '<script type="text/html" id="tmpl-bsw-p1-source"><tr><td class="bsw-p1-source-num"></td><td><input class="widefat" name="bsw_p1_source_title[]" /></td><td><input class="widefat" name="bsw_p1_source_url[]" placeholder="https://" /></td><td><input class="widefat" name="bsw_p1_source_meta[]" placeholder="Publisher | YYYY-MM-DD | accessed YYYY-MM-DD" /></td><td><button type="button" class="button-link-delete bsw-p1-remove">Remove</button></td></tr></script>';
    }
    private static function source_row($n, $s) {
        $meta = implode(' | ', array_filter(array($s['publisher'] ?? '', $s['date'] ?? '', !empty($s['accessed']) ? 'accessed '.$s['accessed'] : '')));
        echo '<tr><td class="bsw-p1-source-num">'.intval($n).'</td><td><input class="widefat" name="bsw_p1_source_title[]" value="'.esc_attr($s['title'] ?? '').'" /></td><td><input class="widefat" name="bsw_p1_source_url[]" value="'.esc_attr($s['url'] ?? '').'" placeholder="https://" /></td><td><input class="widefat" name="bsw_p1_source_meta[]" value="'.esc_attr($meta).'" /></td><td><button type="button" class="button-link-delete bsw-p1-remove">Remove</button></td></tr>';
    }
    public static function controls_box($post) {
        $redirect = get_post_meta($post->ID, '_bsw_p1_redirect', true);
        $disambig = get_post_meta($post->ID, '_bsw_p1_disambiguation', true);
        $subtitle = get_post_meta($post->ID, '_bsw_p1_subtitle', true);
        echo '<p><label>Article subtitle</label><input class="widefat" name="bsw_p1_subtitle" value="'.esc_attr($subtitle).'" /></p>';
        echo '<p><label><input type="checkbox" name="bsw_p1_disambiguation" value="1" '.checked($disambig,'1',false).' /> This is a disambiguation page</label></p>';
        echo '<p><label>Redirect target URL (leave blank for normal article)</label><input class="widefat" type="url" name="bsw_p1_redirect" value="'.esc_attr($redirect).'" placeholder="https://wiki.bigshor.com/wiki/..." /></p>';
        echo '<p class="description">Redirects are applied on the public article URL. Use only for genuine title moves or duplicate-title consolidation.</p>';
    }
    public static function save_meta($post_id, $post) {
        if (!isset($_POST[self::NONCE]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE])), self::NONCE)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) return;
        $type = sanitize_key(wp_unslash($_POST['bsw_p1_article_type'] ?? 'general'));
        if (!isset(self::templates()[$type])) $type = 'general';
        update_post_meta($post_id, '_bsw_p1_article_type', $type);
        update_post_meta($post_id, '_bsw_p1_infobox_title', sanitize_text_field(wp_unslash($_POST['bsw_p1_infobox_title'] ?? '')));
        update_post_meta($post_id, '_bsw_p1_infobox_image', esc_url_raw(wp_unslash($_POST['bsw_p1_infobox_image'] ?? '')));
        update_post_meta($post_id, '_bsw_p1_subtitle', sanitize_text_field(wp_unslash($_POST['bsw_p1_subtitle'] ?? '')));
        update_post_meta($post_id, '_bsw_p1_disambiguation', isset($_POST['bsw_p1_disambiguation']) ? '1' : '0');
        $redirect = esc_url_raw(wp_unslash($_POST['bsw_p1_redirect'] ?? ''));
        update_post_meta($post_id, '_bsw_p1_redirect', $redirect);
        $labels = isset($_POST['bsw_p1_fact_label']) ? (array) wp_unslash($_POST['bsw_p1_fact_label']) : array();
        $values = isset($_POST['bsw_p1_fact_value']) ? (array) wp_unslash($_POST['bsw_p1_fact_value']) : array();
        $facts = array();
        foreach ($labels as $i => $label) { $label = sanitize_text_field($label); $value = sanitize_text_field($values[$i] ?? ''); if ($label !== '' && $value !== '') $facts[] = array('label'=>$label,'value'=>$value); }
        update_post_meta($post_id, '_bsw_p1_infobox_rows', $facts);
        $titles = isset($_POST['bsw_p1_source_title']) ? (array) wp_unslash($_POST['bsw_p1_source_title']) : array();
        $urls = isset($_POST['bsw_p1_source_url']) ? (array) wp_unslash($_POST['bsw_p1_source_url']) : array();
        $metas = isset($_POST['bsw_p1_source_meta']) ? (array) wp_unslash($_POST['bsw_p1_source_meta']) : array();
        $sources = array();
        foreach ($titles as $i => $title) {
            $title = sanitize_text_field($title); $url = esc_url_raw($urls[$i] ?? ''); $parts = array_map('trim', explode('|', sanitize_text_field($metas[$i] ?? '')));
            if ($title !== '' && $url !== '') $sources[] = array('title'=>$title,'url'=>$url,'publisher'=>$parts[0] ?? '', 'date'=>$parts[1] ?? '', 'accessed'=>preg_replace('/^accessed\s+/i','',$parts[2] ?? ''));
        }
        update_post_meta($post_id, '_bsw_p1_sources', $sources);
    }
    public static function render_article_extras($content) {
        if (!is_singular('wiki_article') || !in_the_loop() || !is_main_query()) return $content;
        $id = get_the_ID();
        $subtitle = get_post_meta($id, '_bsw_p1_subtitle', true);
        $disambig = get_post_meta($id, '_bsw_p1_disambiguation', true);
        $html = '';
        if ($subtitle) $html .= '<p class="bsw-p1-subtitle">'.esc_html($subtitle).'</p>';
        if ($disambig === '1') $html .= '<aside class="bsw-p1-disambiguation"><strong>Disambiguation:</strong> This page lists topics that share a similar name. Choose the relevant article from the links below.</aside>';
        $facts = get_post_meta($id, '_bsw_p1_infobox_rows', true);
        if (is_array($facts) && $facts) {
            $title = get_post_meta($id, '_bsw_p1_infobox_title', true) ?: 'At a glance';
            $image = get_post_meta($id, '_bsw_p1_infobox_image', true);
            $html .= '<aside class="bsw-p1-infobox"><h2>'.esc_html($title).'</h2>';
            if ($image) $html .= '<img src="'.esc_url($image).'" alt="'.esc_attr(get_the_title($id)).'" loading="lazy">';
            $html .= '<table><tbody>'; foreach ($facts as $f) $html .= '<tr><th>'.esc_html($f['label']).'</th><td>'.esc_html($f['value']).'</td></tr>';
            $html .= '</tbody></table></aside>';
        }
        $html .= '<p class="bsw-p1-print-wrap">'.self::print_button().'</p>';
        $content = $html . $content;
        $sources = get_post_meta($id, '_bsw_p1_sources', true);
        if (is_array($sources) && $sources) {
            $content = preg_replace_callback('/\[bsw_cite\s+id=["\']?(\d+)["\']?\s*\]/i', function($m) use ($sources) {
                $n = (int)$m[1]; if (!isset($sources[$n-1])) return '';
                return '<sup class="bsw-p1-cite"><a href="#bsw-reference-'.$n.'" aria-label="Reference '.$n.'">['.$n.']</a></sup>';
            }, $content);
            $content .= self::build_reference_list($sources);
        }
        return $content;
    }
    private static function build_reference_list($sources) {
        $out = '<section class="bsw-p1-reference-list" id="references"><h2>References</h2><ol>';
        foreach ($sources as $i => $s) {
            $n = $i+1; $out .= '<li id="bsw-reference-'.$n.'"><a href="'.esc_url($s['url']).'" target="_blank" rel="nofollow noopener">'.esc_html($s['title']).'</a>';
            $meta = array_filter(array($s['publisher'] ?? '', $s['date'] ?? '', !empty($s['accessed']) ? 'Accessed '.$s['accessed'] : ''));
            if ($meta) $out .= ' <span class="bsw-p1-source-meta">('.esc_html(implode('; ', $meta)).')</span>';
            $out .= '</li>';
        }
        return $out.'</ol></section>';
    }
    public static function references_shortcode() { $sources = get_post_meta(get_the_ID(), '_bsw_p1_sources', true); return is_array($sources) && $sources ? self::build_reference_list($sources) : ''; }
    public static function infobox_shortcode() { $id = get_the_ID(); if (!$id) return ''; $facts = get_post_meta($id, '_bsw_p1_infobox_rows', true); if (!is_array($facts) || !$facts) return ''; $title = get_post_meta($id, '_bsw_p1_infobox_title', true) ?: 'At a glance'; $image = get_post_meta($id, '_bsw_p1_infobox_image', true); $out = '<aside class="bsw-p1-infobox"><h2>'.esc_html($title).'</h2>'; if ($image) $out .= '<img src="'.esc_url($image).'" alt="'.esc_attr(get_the_title($id)).'" loading="lazy">'; $out .= '<table><tbody>'; foreach ($facts as $f) { if (!is_array($f)) continue; $out .= '<tr><th>'.esc_html($f['label'] ?? '').'</th><td>'.esc_html($f['value'] ?? '').'</td></tr>'; } return $out.'</tbody></table></aside>'; }
    public static function handle_redirect() {
        if (!is_singular('wiki_article')) return;
        $target = get_post_meta(get_queried_object_id(), '_bsw_p1_redirect', true);
        if ($target && wp_http_validate_url($target) && !headers_sent()) { wp_safe_redirect($target, 301, 'BIGSHOR Wiki'); exit; }
    }
    public static function columns($columns) { $columns['bsw_p1_type'] = 'Article type'; $columns['bsw_p1_refs'] = 'References'; return $columns; }
    public static function column_value($column, $post_id) {
        if ($column === 'bsw_p1_type') { $type = get_post_meta($post_id, '_bsw_p1_article_type', true) ?: 'general'; $all = self::templates(); echo esc_html($all[$type][0] ?? 'General article'); }
        if ($column === 'bsw_p1_refs') { $s = get_post_meta($post_id, '_bsw_p1_sources', true); echo is_array($s) ? intval(count($s)) : '0'; }
    }
    public static function admin_assets($hook) {
        if (!in_array($hook, array('post.php','post-new.php'), true)) return;
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== 'wiki_article') return;
        wp_add_inline_script('jquery-core', self::admin_js());
        wp_add_inline_style('common', self::admin_css());
    }
    private static function admin_js() { $templates = array(); foreach(self::templates() as $k=>$v) $templates[$k]=$v[1]; return 'jQuery(function($){var templates='.wp_json_encode($templates).';$("#bsw-p1-insert-template").on("click",function(){var type=$("#bsw_p1_type").val(),txt=templates[type]||"";if(window.wp&&wp.data&&wp.data.dispatch("core/editor")){var ed=wp.data.select("core/editor"),old=ed.getEditedPostContent()||"";if(old.trim()&& !window.confirm("Append the selected outline to the existing article?"))return;wp.data.dispatch("core/editor").editPost({content:old+txt});}else{$("#content").val(($("#content").val()||"")+txt).trigger("change");}});$("#bsw-p1-add-fact").on("click",function(){$("#bsw-p1-facts tbody").append($("#tmpl-bsw-p1-fact").html());});$("#bsw-p1-add-source").on("click",function(){$("#bsw-p1-sources tbody").append($("#tmpl-bsw-p1-source").html());renumber();});$(document).on("click",".bsw-p1-remove",function(){$(this).closest("tr").remove();renumber();});function renumber(){$("#bsw-p1-sources tbody tr").each(function(i){$(this).find(".bsw-p1-source-num").text(i+1);});}});'; }
    private static function admin_css() { return '.bsw-p1-infobox img{max-width:100%;height:auto}.bsw-p1-infobox table th{text-align:left;width:38%}#bsw-p1-sources input{min-width:100px}'; }
    public static function reader_assets() {
        if (!is_singular('wiki_article')) return;
        $css = '.bsw-p1-subtitle{font-size:1.15em;opacity:.78;margin-top:-.5em}.bsw-p1-disambiguation{padding:1em;border-left:4px solid #9a7b35;background:rgba(154,123,53,.08);margin:1em 0}.bsw-p1-infobox{float:right;clear:right;width:min(340px,42%);margin:0 0 1.5em 1.5em;padding:1em;border:1px solid #ddd;border-radius:8px;background:rgba(127,127,127,.04)}.bsw-p1-infobox h2{font-size:1.1em;margin-top:0}.bsw-p1-infobox img{display:block;max-width:100%;height:auto;margin:0 auto 1em}.bsw-p1-infobox table{width:100%;border-collapse:collapse}.bsw-p1-infobox th,.bsw-p1-infobox td{padding:.45em;border-top:1px solid #ddd;text-align:left;vertical-align:top}.bsw-p1-reference-list{clear:both;margin-top:2em;padding-top:1em;border-top:1px solid #ddd}.bsw-p1-reference-list li{margin:.45em 0}.bsw-p1-source-meta{opacity:.72;font-size:.92em}.bsw-p1-cite{font-size:.8em;margin-left:.15em}.bsw-p1-print{display:inline-block;padding:.55em .9em;border:1px solid currentColor;border-radius:4px;text-decoration:none}@media(max-width:640px){.bsw-p1-infobox{float:none;width:auto;margin:1em 0}}@media print{.bsw-p1-print,.wiki-toc{display:none!important}.bsw-p1-infobox{break-inside:avoid}}';
        wp_register_style('bsw-p1-reader', false, array(), self::VERSION); wp_enqueue_style('bsw-p1-reader'); wp_add_inline_style('bsw-p1-reader', $css);
    }
    public static function print_button() { return '<button class="bsw-p1-print" type="button" onclick="window.print()">Print / Save as PDF</button>'; }
    public static function register_rewrite_support() { /* reserved for compatible future redirect aliases */ }
}
add_action('plugins_loaded', array('BSW_Phase1_Foundation', 'init'), 20);

