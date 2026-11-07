<?php
/**
 * Phase 4: structured, safer MediaWiki-style parser for BIGSHOR.
 * Supported subset: headings (2-6), paragraphs, bold/italic, internal/external links,
 * nested unordered/ordered lists, definition lists, blockquotes, fenced code,
 * simple wiki tables, named/unnamed references and categories.
 * This is deliberately a documented subset, not a claim of full MediaWiki parity.
 */
if (!defined('ABSPATH')) exit;
final class BSW_Phase47_Parser {
    private static $refs = array();
    private static $categories = array();

    public static function init() {
        add_filter('the_content', array(__CLASS__, 'append_categories'), 30);
    }
    public static function append_categories($content) {
        if (!is_singular('wiki_article') || !in_the_loop() || !is_main_query()) return $content;
        $terms = get_the_terms(get_the_ID(), 'wiki_topic');
        if (is_wp_error($terms) || empty($terms)) return $content;
        $out = '<nav class="bsw47-categories" aria-label="Article categories"><strong>Categories:</strong> ';
        $links = array();
        foreach ($terms as $term) $links[] = '<a href="'.esc_url(get_term_link($term)).'">'.esc_html($term->name).'</a>';
        return $content . $out . implode(' · ', $links) . '</nav>';
    }
    public static function render($source) {
        self::$refs = array(); self::$categories = array();
        $source = str_replace(array("\r\n", "\r"), "\n", (string) $source);
        if (class_exists('BSW_Phase54_Template_Engine')) { $budget = BSW_Phase54_Template_Engine::MAX_EXPANSIONS; $source = BSW_Phase54_Template_Engine::expand($source, 0, $budget); } elseif (class_exists('BSW_Phase50_Major_Platform')) { $budget = 100; $source = BSW_Phase50_Major_Platform::expand_wikitext($source, 0, $budget); }
        // The BIGSHOR renderer appends one consolidated references list automatically.
        // Consume common MediaWiki references-list markers so they never leak as literal text.
        $source = preg_replace('/<references\b[^>]*\/>|<references\b[^>]*>\s*<\/references>/i', '', $source);
        $source = preg_replace_callback('/\[\[Category:([^\]|]+)(?:\|[^\]]*)?\]\]/i', function($m) {
            self::$categories[] = sanitize_text_field(trim($m[1])); return '';
        }, $source);
        // Collect named reference definitions before rendering so forward reuse works.
        $source = preg_replace_callback('/<ref\s+name=["\']([^"\']+)["\']\s*>(.*?)<\/ref>/is', function($m) {
            $key = sanitize_key($m[1]); if ($key !== '') self::$refs[$key] = trim(wp_strip_all_tags($m[2]));
            return '<!--BSW47REF:'.esc_html($key).'-->';
        }, $source);
        $source = preg_replace_callback('/<ref\s*>(.*?)<\/ref>/is', function($m) {
            $key = 'ref-'.(count(self::$refs)+1); self::$refs[$key] = trim(wp_strip_all_tags($m[1]));
            return '<!--BSW47REF:'.esc_html($key).'-->';
        }, $source);
        $source = preg_replace_callback('/<ref\s+name=["\']([^"\']+)["\']\s*\/>/i', function($m) {
            return '<!--BSW47REF:'.esc_html(sanitize_key($m[1])).'-->';
        }, $source);
        // Registered, single-level BIGSHOR infobox templates. Unknown templates remain visible as source.
        $templateTokens = array();
        $source = preg_replace_callback('/\{\{Infobox\s+([a-z0-9 _-]+)\s*\|((?:(?!\{\{).)*?)\}\}/is', function($m) use (&$templateTokens) {
            $name = trim($m[1]);
            $fields = class_exists('BSW_Phase5_Template_Registry') ? BSW_Phase5_Template_Registry::parse_fields($m[2]) : array();
            $box = class_exists('BSW_Phase5_Template_Registry') ? BSW_Phase5_Template_Registry::render($name, $fields) : '';
            if ($box === '') return $m[0];
            $templateTokens[] = $box;
            return 'BSW47TEMPLATE'.(count($templateTokens)-1).'TOKEN';
        }, $source);
        $lines = explode("\n", $source); $html = ''; $paragraph = array(); $listStack = array(); $inCode = false; $code = array(); $inTable = false; $tableRows = array(); $blockquote = array();
        $flushPara = function() use (&$html, &$paragraph) { if ($paragraph) { $html .= '<p>'.self::inline(implode(' ', $paragraph)).'</p>'; $paragraph = array(); } };
        $closeLists = function($to=0) use (&$html, &$listStack) { while (count($listStack) > $to) { $tag = array_pop($listStack); $html .= '</li></'.$tag.'>'; } };
        $flushQuote = function() use (&$html, &$blockquote) { if ($blockquote) { $html .= '<blockquote><p>'.self::inline(implode(' ', $blockquote)).'</p></blockquote>'; $blockquote = array(); } };
        foreach ($lines as $line) {
            $trim = trim($line);
            if (preg_match('/^\s*```/', $trim)) { $flushPara(); $flushQuote(); $closeLists(); if ($inCode) { $html .= '<pre><code>'.esc_html(implode("\n", $code)).'</code></pre>'; $code = array(); $inCode = false; } else $inCode = true; continue; }
            if ($inCode) { $code[] = $line; continue; }
            if ($trim === '') { $flushPara(); $flushQuote(); $closeLists(); continue; }
            if (preg_match('/^BSW47TEMPLATE[0-9]+TOKEN$/', $trim)) { $flushPara(); $flushQuote(); $closeLists(); $html .= $trim; continue; }
            if (preg_match('/^(={2,6})\s*(.*?)\s*\1$/', $trim, $m)) { $flushPara(); $flushQuote(); $closeLists(); $n = strlen($m[1]); $html .= '<h'.$n.' id="'.esc_attr(sanitize_title(wp_strip_all_tags($m[2]))).'">'.self::inline($m[2]).'</h'.$n.'>'; continue; }
            if (preg_match('/^\{\|/', $trim)) { $flushPara(); $flushQuote(); $closeLists(); $inTable = true; $tableRows = array(); continue; }
            if ($inTable) {
                if (preg_match('/^\|\}/', $trim)) { $html .= self::render_table($tableRows); $inTable = false; $tableRows = array(); continue; }
                if (preg_match('/^\|-/', $trim)) { $tableRows[] = array('new_row'=>true); continue; }
                if (isset($trim[0]) && ($trim[0] === '|' || $trim[0] === '!')) { $header = $trim[0] === '!'; $delim = $header ? '!!' : '||'; $cells = preg_split('/'.preg_quote($delim, '/').'/', substr($trim, 1)); $row = array(); foreach ($cells as $cell) { $cell = preg_replace('/^[^|]*\|/', '', $cell); $row[] = array('header'=>$header, 'content'=>trim($cell)); } $tableRows[] = array('cells'=>$row); }
                continue;
            }
            if (preg_match('/^([*#]+)\s*(.*)$/', $trim, $m)) {
                $flushPara(); $flushQuote(); $path = array_map(function($c){ return $c === '*' ? 'ul' : 'ol'; }, str_split($m[1]));
                $common = 0; while ($common < count($listStack) && $common < count($path) && $listStack[$common] === $path[$common]) $common++;
                for ($i=count($listStack)-1; $i >= $common; $i--) { $html .= '</li></'.$listStack[$i].'>'; }
                if ($common > 0 && count($path) <= $common) $html .= '</li><li>';
                for ($i=$common; $i<count($path); $i++) { $html .= '<'.$path[$i].'><li>'; }
                $listStack = $path; $html .= self::inline($m[2]); continue;
            }
            if (preg_match('/^;\s*(.*?)\s*:\s*(.*)$/', $trim, $m)) { $flushPara(); $flushQuote(); $closeLists(); $html .= '<dl><dt>'.self::inline($m[1]).'</dt><dd>'.self::inline($m[2]).'</dd></dl>'; continue; }
            if (preg_match('/^:\s*(.*)$/', $trim, $m)) { $flushPara(); $closeLists(); $blockquote[] = $m[1]; continue; }
            $flushQuote(); $closeLists(); $paragraph[] = $trim;
        }
        $flushPara(); $flushQuote(); $closeLists();
        if ($inCode) $html .= '<pre><code>'.esc_html(implode("\n", $code)).'</code></pre>';
        if ($inTable) $html .= self::render_table($tableRows);
        $html .= self::reference_list();
        $html = wp_kses_post($html);
        foreach ($templateTokens as $i => $box) $html = str_replace('BSW47TEMPLATE'.$i.'TOKEN', $box, $html);
        return $html;
    }
    private static function render_table($rows) {
        $out = '<table class="bsw43-wikitable"><tbody>'; $opened = false;
        foreach ($rows as $row) { if (!empty($row['new_row'])) { if ($opened) $out .= '</tr>'; $out .= '<tr>'; $opened = true; continue; }
            if (!$opened) { $out .= '<tr>'; $opened = true; }
            foreach (($row['cells'] ?? array()) as $cell) { $tag = !empty($cell['header']) ? 'th' : 'td'; $out .= '<'.$tag.'>'.self::inline($cell['content']).'</'.$tag.'>'; }
        }
        if ($opened) $out .= '</tr>'; return $out.'</tbody></table>';
    }
    private static function inline($text) {
        $tokens = array();
        $text = preg_replace_callback('/<!--BSW47REF:([^>]*)-->/', function($m) use (&$tokens) { $key = sanitize_key($m[1]); $idx = array_search($key, array_keys(self::$refs), true); if ($idx === false) return ''; $n = $idx+1; $tokens[] = '<sup class="bsw42-ref"><a href="#bsw47-note-'.$n.'" id="bsw47-ref-'.$n.'">['.$n.']</a></sup>'; return "\x1A".(count($tokens)-1)."\x1A"; }, $text);
        $formatTokens = array();
        $text = preg_replace_callback("/'''(.*?)'''/s", function($m) use (&$formatTokens) { $formatTokens[] = '<strong>'.esc_html($m[1]).'</strong>'; return "\x1B".(count($formatTokens)-1)."\x1B"; }, $text);
        $text = preg_replace_callback("/''(.*?)''/s", function($m) use (&$formatTokens) { $formatTokens[] = '<em>'.esc_html($m[1]).'</em>'; return "\x1B".(count($formatTokens)-1)."\x1B"; }, $text);
        $text = esc_html($text);
        $text = preg_replace_callback('/\[\[File:([^\]|]+)(?:\|([^\]]*))?\]\]/i', function($m) { $name = sanitize_title(pathinfo(trim($m[1]), PATHINFO_FILENAME)); $found = get_posts(array('post_type'=>'attachment','post_status'=>'inherit','name'=>$name,'numberposts'=>1)); if (!$found) return ''; $id = (int)$found[0]->ID; $url = wp_get_attachment_image_url($id, 'large'); if (!$url) return ''; $alt = get_post_meta($id, '_wp_attachment_image_alt', true); return '<figure class="bsw42-wiki-file"><img src="'.esc_url($url).'" alt="'.esc_attr($alt).'" loading="lazy"></figure>'; }, $text);
        $text = preg_replace_callback('/\[\[([^\]|]+)(?:\|([^\]]+))?\]\]/', function($m) { $target = trim($m[1]); $slug = sanitize_title($target); $posts = get_posts(array('post_type'=>'wiki_article','name'=>$slug,'post_status'=>'publish','numberposts'=>1)); $url = $posts ? get_permalink($posts[0]) : add_query_arg('s', rawurlencode($target), home_url('/')); return '<a href="'.esc_url($url).'">'.esc_html($m[2] ?? $target).'</a>'; }, $text);
        $text = preg_replace_callback('/\[(https?:\/\/[^\s\]]+)(?:\s+([^\]]+))?\]/', function($m) { return '<a href="'.esc_url($m[1]).'" rel="nofollow noopener">'.esc_html($m[2] ?? $m[1]).'</a>'; }, $text);
        $text = preg_replace_callback('/\x1B(\d+)\x1B/', function($m) use ($formatTokens) { return $formatTokens[(int)$m[1]] ?? ''; }, $text);
        return preg_replace_callback('/\x1A(\d+)\x1A/', function($m) use ($tokens) { return $tokens[(int)$m[1]] ?? ''; }, $text);
    }
    private static function reference_list() {
        if (!self::$refs) return ''; $out = '<ol class="bsw42-references bsw47-reference-list">'; $i=0; foreach (self::$refs as $key=>$ref) { $i++; $out .= '<li id="bsw47-note-'.$i.'">'.esc_html($ref).' <a href="#bsw47-ref-'.$i.'" aria-label="Return to citation">↑</a></li>'; } return $out.'</ol>';
    }
    /**
     * Sync explicit [[Category:...]] declarations to the article's existing topic taxonomy.
     * Existing terms are reused; new terms are created only for users with manage_categories.
     * No taxonomy changes are made when the source contains no category declarations.
     */
    public static function sync_categories($post_id, $source) {
        if (!taxonomy_exists('wiki_topic')) return;
        if (!preg_match_all('/\\[\\[Category:([^\\]|]+)(?:\\|[^\\]]*)?\\]\\]/i', (string) $source, $matches)) return;
        $term_ids = array();
        foreach ($matches[1] as $raw_name) {
            $name = sanitize_text_field(trim($raw_name));
            if ($name === '') continue;
            $term = term_exists($name, 'wiki_topic');
            if (!$term && current_user_can('manage_categories')) $term = wp_insert_term($name, 'wiki_topic');
            if (is_wp_error($term) || !$term) continue;
            $term_ids[] = is_array($term) ? absint($term['term_id']) : absint($term);
        }
        wp_set_object_terms(absint($post_id), array_values(array_unique(array_filter($term_ids))), 'wiki_topic', false);
    }
    public static function diagnostics($source) {
        $warnings = array(); if (preg_match('/<ref\b(?![^>]*\/>)[^>]*>.*?<\/ref>/is', $source) && stripos($source, '<references') === false) $warnings[] = 'References are present but no references list marker was found; BIGSHOR will append a list automatically.';
        if (preg_match('/\{\{[^{}]*\{\{/', $source)) $warnings[] = 'Nested templates are outside the currently supported parser subset.';
        if (preg_match('/<ref\s+name=["\']([^"\']+)["\']\s*\/>/i', $source, $m) && !preg_match('/<ref\s+name=["\']'.preg_quote($m[1], '/').'["\']\s*>/i', $source)) $warnings[] = 'A named reference is reused without a definition in this source.';
        return $warnings;
    }
}
BSW_Phase47_Parser::init();
