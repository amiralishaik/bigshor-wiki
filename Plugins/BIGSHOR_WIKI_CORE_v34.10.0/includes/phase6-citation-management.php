<?php
/**
 * BIGSHOR Wiki Core — Phase 6: structured citation and reference management.
 * Stores source records as post meta; no database tables or destructive migrations.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('BSW_Phase6_Citation_Manager')) {
final class BSW_Phase6_Citation_Manager {
    const META_KEY = '_bsw_phase6_sources';
    const NONCE_ACTION = 'bsw_phase6_save_sources';
    private static $used = array();
    private static $occurrences = array();
    private static $rendered = array();

    public static function init() {
        add_action('add_meta_boxes_wiki_article', array(__CLASS__, 'add_metabox'));
        add_action('save_post_wiki_article', array(__CLASS__, 'save_sources'), 10, 2);
        add_shortcode('bsw_cite', array(__CLASS__, 'citation_shortcode'));
        add_shortcode('bigshor_cite', array(__CLASS__, 'citation_shortcode'));
        add_shortcode('bsw_references', array(__CLASS__, 'references_shortcode'));
        add_shortcode('bigshor_references', array(__CLASS__, 'references_shortcode'));
        add_filter('the_content', array(__CLASS__, 'append_references_if_needed'), 12);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_styles'));
        add_action('admin_notices', array(__CLASS__, 'admin_notice_if_missing'));
    }

    public static function add_metabox() {
        add_meta_box('bsw-phase6-sources', 'BIGSHOR — Sources & Citations', array(__CLASS__, 'metabox_html'), 'wiki_article', 'normal', 'high');
    }

    public static function metabox_html($post) {
        if (!current_user_can('edit_post', $post->ID)) return;
        wp_nonce_field(self::NONCE_ACTION, 'bsw_phase6_nonce');
        $sources = get_post_meta($post->ID, self::META_KEY, true);
        if (!is_array($sources)) $sources = array();
        $json = wp_json_encode($sources, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        echo '<p>Maintain reusable source records for this article. Cite a source in the article body with <code>[bsw_cite id="source-id"]</code> and insert <code>[bsw_references]</code> where the reference list should appear.</p>';
        echo '<p>Each source needs a unique <code>id</code> and a <code>title</code>. Use the other fields when available. The saved records are stored with the article and retained with revisions/backups as post metadata.</p>';
        echo '<textarea name="bsw_phase6_sources_json" rows="14" spellcheck="false" style="width:100%;font-family:ui-monospace,monospace" aria-label="Structured source records JSON">'.esc_textarea($json ? $json : '[]').'</textarea>';
        echo '<details style="margin-top:8px"><summary>View source record example</summary><pre style="white-space:pre-wrap">'.esc_html("[\n  {\n    \"id\": \"source-1\",\n    \"author\": \"Author or organisation\",\n    \"title\": \"Title of the source\",\n    \"publisher\": \"Publisher / journal\",\n    \"date\": \"2026-01-15\",\n    \"url\": \"https://example.com/article\",\n    \"accessed\": \"2026-11-05\",\n    \"doi\": \"10.xxxx/example\",\n    \"page\": \"p. 12\",\n    \"note\": \"Optional editorial note; not displayed publicly\"\n  }\n]").'</pre></details>';
        echo '<p><strong>Important:</strong> a citation record documents a source; it does not independently verify that the source supports the associated claim.</p>';
    }

    public static function save_sources($post_id, $post) {
        if (!isset($_POST['bsw_phase6_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_phase6_nonce'])), self::NONCE_ACTION)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id) || (defined('DOING_CRON') && DOING_CRON)) return;
        if (!current_user_can('edit_post', $post_id) || !isset($_POST['bsw_phase6_sources_json'])) return;
        $raw = wp_unslash($_POST['bsw_phase6_sources_json']);
        if (strlen($raw) > 200000) { self::set_admin_error('Source data was not saved: the JSON exceeds the 200 KB safety limit.'); return; }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) { self::set_admin_error('Source data was not saved: please correct the JSON syntax.'); return; }
        $clean = array(); $seen = array();
        foreach (array_slice($decoded, 0, 200) as $row) {
            if (!is_array($row)) continue;
            $id = sanitize_key(isset($row['id']) ? $row['id'] : '');
            $title = sanitize_text_field(isset($row['title']) ? $row['title'] : '');
            if ($id === '' || $title === '' || isset($seen[$id])) continue;
            $seen[$id] = true;
            $url = isset($row['url']) ? esc_url_raw(trim((string)$row['url']), array('http','https')) : '';
            $clean[] = array(
                'id' => $id,
                'author' => sanitize_text_field(isset($row['author']) ? $row['author'] : ''),
                'title' => $title,
                'publisher' => sanitize_text_field(isset($row['publisher']) ? $row['publisher'] : ''),
                'date' => sanitize_text_field(isset($row['date']) ? $row['date'] : ''),
                'url' => $url,
                'accessed' => sanitize_text_field(isset($row['accessed']) ? $row['accessed'] : ''),
                'doi' => sanitize_text_field(isset($row['doi']) ? $row['doi'] : ''),
                'page' => sanitize_text_field(isset($row['page']) ? $row['page'] : ''),
                'note' => sanitize_textarea_field(isset($row['note']) ? $row['note'] : ''),
            );
        }
        if ($clean) update_post_meta($post_id, self::META_KEY, $clean);
        else delete_post_meta($post_id, self::META_KEY);
        delete_transient('bsw_phase6_source_error_' . get_current_user_id());
    }

    private static function sources_for($post_id) {
        $sources = get_post_meta((int)$post_id, self::META_KEY, true);
        if (!is_array($sources)) return array();
        $map = array();
        foreach ($sources as $source) if (is_array($source) && !empty($source['id']) && !empty($source['title'])) $map[sanitize_key($source['id'])] = $source;
        return $map;
    }

    public static function citation_shortcode($atts) {
        $atts = shortcode_atts(array('id' => '', 'label' => ''), $atts, 'bsw_cite');
        $id = sanitize_key($atts['id']); $post_id = get_the_ID();
        if (!$id || !$post_id) return '';
        $sources = self::sources_for($post_id);
        if (!isset($sources[$id])) return current_user_can('edit_post', $post_id) ? '<span class="bsw6-citation-missing">[Missing source: '.esc_html($id).']</span>' : '';
        if (!isset(self::$used[$post_id])) self::$used[$post_id] = array();
        if (!isset(self::$used[$post_id][$id])) self::$used[$post_id][$id] = count(self::$used[$post_id]) + 1;
        if (!isset(self::$occurrences[$post_id])) self::$occurrences[$post_id] = array();
        if (!isset(self::$occurrences[$post_id][$id])) self::$occurrences[$post_id][$id] = array();
        $number = self::$used[$post_id][$id];
        $occurrence = count(self::$occurrences[$post_id][$id]) + 1;
        $citation_id = 'bsw6-cite-' . $id . '-' . $occurrence;
        self::$occurrences[$post_id][$id][] = $citation_id;
        $label = trim((string)$atts['label']);
        return '<sup class="bsw6-citation" id="'.esc_attr($citation_id).'"><a href="#bsw6-ref-'.esc_attr($id).'" aria-label="Reference '.$number.', citation '.$occurrence.'">'.($label !== '' ? esc_html($label) : '['.$number.']').'</a></sup>';
    }

    public static function references_shortcode($atts = array()) {
        $post_id = get_the_ID();
        if (!$post_id) return '';
        $sources = self::sources_for($post_id);
        $used = isset(self::$used[$post_id]) ? self::$used[$post_id] : array();
        if (!$used) return '';
        self::$rendered[$post_id] = true;
        asort($used);
        $html = '<section class="bsw6-references" aria-label="References"><h2>References</h2><ol>';
        foreach ($used as $id => $number) {
            if (!isset($sources[$id])) continue;
            $s = $sources[$id];
            $parts = array();
            if (!empty($s['author'])) $parts[] = esc_html($s['author']).'.';
            $title = esc_html($s['title']);
            if (!empty($s['url'])) $title = '<a href="'.esc_url($s['url']).'" rel="nofollow noopener noreferrer" target="_blank">'.$title.'</a>';
            $parts[] = $title.'.';
            if (!empty($s['publisher'])) $parts[] = esc_html($s['publisher']).'.';
            if (!empty($s['date'])) $parts[] = esc_html($s['date']).'.';
            if (!empty($s['page'])) $parts[] = esc_html($s['page']).'.';
            if (!empty($s['doi'])) $parts[] = 'DOI: '.esc_html($s['doi']).'.';
            if (!empty($s['accessed'])) $parts[] = 'Accessed '.esc_html($s['accessed']).'.';
            $html .= '<li id="bsw6-ref-'.esc_attr($id).'" value="'.(int)$number.'"><span>'.implode(' ', $parts).'</span>';
            $citation_ids = isset(self::$occurrences[$post_id][$id]) ? self::$occurrences[$post_id][$id] : array();
            foreach ($citation_ids as $occurrence => $citation_id) {
                $html .= ' <a class="bsw6-backlink" href="#'.esc_attr($citation_id).'" aria-label="Return to citation '.(int)($occurrence + 1).'">↩'.(count($citation_ids) > 1 ? (int)($occurrence + 1) : '').'</a>';
            }
            $html .= '</li>';
        }
        return $html.'</ol></section>';
    }

    public static function append_references_if_needed($content) {
        if (!is_singular('wiki_article') || !in_the_loop() || !is_main_query()) return $content;
        $post_id = get_the_ID();
        if (!$post_id || !empty(self::$rendered[$post_id]) || strpos($content, 'bsw6-references') !== false) return $content;
        $references = self::references_shortcode();
        return $references ? $content . "\n\n" . $references : $content;
    }

    public static function enqueue_styles() {
        if (!is_singular('wiki_article')) return;
        $css = '.bsw6-citation{font-size:.78em;vertical-align:super;line-height:0}.bsw6-citation a{text-decoration:none}.bsw6-references{clear:both;margin:2rem 0 1rem;padding-top:1rem;border-top:1px solid #a2a9b1;font-size:.92em;overflow-wrap:anywhere}.bsw6-references h2{font-size:1.35em;margin:0 0 .75rem}.bsw6-references ol{padding-left:2rem}.bsw6-references li{padding:.25rem 0;scroll-margin-top:1rem}.bsw6-backlink{display:inline-block;margin-left:.35rem;text-decoration:none}.bsw6-citation-missing{color:#b32d2e;font-size:.85em}';
        wp_register_style('bsw-phase6-citations', false, array(), '31.1.0');
        wp_enqueue_style('bsw-phase6-citations');
        wp_add_inline_style('bsw-phase6-citations', $css);
    }

    private static function set_admin_error($message) {
        set_transient('bsw_phase6_source_error_' . get_current_user_id(), $message, 60);
    }
    public static function admin_notice_if_missing() {
        $message = get_transient('bsw_phase6_source_error_' . get_current_user_id());
        if ($message && current_user_can('edit_posts')) echo '<div class="notice notice-error"><p>'.esc_html($message).'</p></div>';
    }
}
BSW_Phase6_Citation_Manager::init();
}
