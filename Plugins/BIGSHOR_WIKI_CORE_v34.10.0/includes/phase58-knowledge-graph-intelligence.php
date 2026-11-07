<?php
/**
 * BIGSHOR Wiki Phase 5.4 — structured knowledge graph and topic discovery.
 * Entity and relationship records are editor supplied. This module does not
 * infer facts, fetch external entity data, or alter/publish articles.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase58_Knowledge_Graph')) {
final class BSW_Phase58_Knowledge_Graph {
    const META_ENTITIES = '_bsw_p5_knowledge_entities';
    const META_RELATIONS = '_bsw_p5_knowledge_relations';
    const ENTITY_TYPES = array('person','organisation','place','event','work','concept','other');
    const RELATIONS = array('related_to','part_of','located_in','founded_by','created_by','member_of','occurred_at','works_for');

    public static function boot() {
        add_action('add_meta_boxes_wiki_article', array(__CLASS__, 'metabox'));
        add_action('save_post_wiki_article', array(__CLASS__, 'save'), 90, 2);
        add_action('admin_menu', array(__CLASS__, 'menu'), 75);
        add_action('rest_api_init', array(__CLASS__, 'routes'));
        add_action('wp_head', array(__CLASS__, 'schema'), 30);
        add_shortcode('bsw_knowledge_graph', array(__CLASS__, 'shortcode'));
    }

    public static function metabox() {
        add_meta_box('bsw-p5-knowledge-graph', 'BIGSHOR Knowledge Graph & Topic Relationships', array(__CLASS__, 'render_metabox'), 'wiki_article', 'normal', 'default');
    }

    private static function entities($post_id) {
        $rows = get_post_meta($post_id, self::META_ENTITIES, true);
        return is_array($rows) ? $rows : array();
    }

    private static function relations($post_id) {
        $rows = get_post_meta($post_id, self::META_RELATIONS, true);
        return is_array($rows) ? $rows : array();
    }

    public static function render_metabox($post) {
        wp_nonce_field('bsw_p5_knowledge_save', 'bsw_p5_knowledge_nonce');
        $entity_lines = array();
        foreach (self::entities($post->ID) as $row) {
            $entity_lines[] = implode(' | ', array($row['type'] ?? 'other', $row['label'] ?? '', $row['url'] ?? ''));
        }
        $relation_lines = array();
        foreach (self::relations($post->ID) as $row) {
            $relation_lines[] = implode(' | ', array($row['type'] ?? 'related_to', (int)($row['target_id'] ?? 0), $row['note'] ?? ''));
        }
        echo '<p>Connect this article to clearly identified people, organisations, places, events, works and concepts. Records are editor-supplied and should be supported by the article citations.</p>';
        echo '<p><strong>Entities</strong> — one per line: <code>type | name | reference URL (optional)</code></p>';
        echo '<textarea class="widefat" rows="6" name="bsw_p5_kg_entities" placeholder="person | Example Person | https://example.org/person">' . esc_textarea(implode("\n", $entity_lines)) . '</textarea>';
        echo '<p class="description">Allowed types: ' . esc_html(implode(', ', self::ENTITY_TYPES)) . '. Reference URLs are stored as supplied; BIGSHOR does not crawl or certify them.</p>';
        echo '<hr/><p><strong>Article relationships</strong> — one per line: <code>relation | target article ID | editorial note (optional)</code></p>';
        echo '<textarea class="widefat" rows="5" name="bsw_p5_kg_relations" placeholder="related_to | 123 | Shares historical context">' . esc_textarea(implode("\n", $relation_lines)) . '</textarea>';
        echo '<p class="description">Allowed relations: ' . esc_html(implode(', ', self::RELATIONS)) . '. Targets must be published BIGSHOR wiki articles. Do not create a relationship unless the article supports it.</p>';
        echo '<p><a class="button" href="' . esc_url(rest_url('bigshor/v1/knowledge-graph/' . absint($post->ID))) . '" target="_blank" rel="noopener">Preview public graph data</a></p>';
        $related = self::related_articles($post->ID, 6);
        if ($related) {
            echo '<h4>Potential topic connections</h4><ul>';
            foreach ($related as $item) echo '<li><a href="' . esc_url(get_edit_post_link($item->ID)) . '">' . esc_html(get_the_title($item)) . '</a></li>';
            echo '</ul><p class="description">Suggestions are based on exact shared entity labels, not semantic or factual verification.</p>';
        }
    }

    public static function save($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id) || !isset($_POST['bsw_p5_knowledge_nonce'])) return;
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_p5_knowledge_nonce'])), 'bsw_p5_knowledge_save')) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $entities = array();
        $raw_entities = isset($_POST['bsw_p5_kg_entities']) ? sanitize_textarea_field(wp_unslash($_POST['bsw_p5_kg_entities'])) : '';
        foreach (array_slice(preg_split('/\r\n|\r|\n/', $raw_entities), 0, 30) as $line) {
            $parts = array_map('trim', explode('|', $line, 3));
            if (count($parts) < 2 || $parts[1] === '') continue;
            $type = sanitize_key($parts[0]);
            if (!in_array($type, self::ENTITY_TYPES, true)) $type = 'other';
            $label = sanitize_text_field($parts[1]);
            if ($label === '' || strlen($label) > 180) continue;
            $url = isset($parts[2]) ? esc_url_raw($parts[2], array('https')) : '';
            if ($url) {
                $parsed = wp_parse_url($url);
                if (!wp_http_validate_url($url) || empty($parsed['host']) || isset($parsed['user']) || isset($parsed['pass'])) $url = '';
            }
            $key = strtolower(remove_accents($label));
            if (!isset($entities[$key])) $entities[$key] = array('type'=>$type,'label'=>$label,'url'=>$url);
        }
        update_post_meta($post_id, self::META_ENTITIES, array_values($entities));

        $relations = array();
        $raw_relations = isset($_POST['bsw_p5_kg_relations']) ? sanitize_textarea_field(wp_unslash($_POST['bsw_p5_kg_relations'])) : '';
        foreach (array_slice(preg_split('/\r\n|\r|\n/', $raw_relations), 0, 20) as $line) {
            $parts = array_map('trim', explode('|', $line, 3));
            if (count($parts) < 2) continue;
            $type = sanitize_key($parts[0]);
            $target = absint($parts[1]);
            if (!in_array($type, self::RELATIONS, true) || !$target || $target === (int)$post_id) continue;
            $target_post = get_post($target);
            if (!$target_post || $target_post->post_type !== 'wiki_article' || $target_post->post_status !== 'publish') continue;
            $relations[$type . ':' . $target] = array('type'=>$type,'target_id'=>$target,'note'=>isset($parts[2]) ? sanitize_text_field($parts[2]) : '');
        }
        update_post_meta($post_id, self::META_RELATIONS, array_values($relations));
    }

    public static function related_articles($post_id, $limit = 6) {
        $entities = self::entities($post_id);
        if (!$entities) return array();
        $labels = array();
        foreach ($entities as $entity) if (!empty($entity['label'])) $labels[] = sanitize_text_field($entity['label']);
        $labels = array_slice(array_unique($labels), 0, 8);
        if (!$labels) return array();
        $meta_query = array('relation'=>'OR');
        foreach ($labels as $label) $meta_query[] = array('key'=>self::META_ENTITIES,'value'=>'"label";s:' . strlen($label) . ':"' . $label . '"','compare'=>'LIKE');
        $query = new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>max(1,min(12,(int)$limit)),'post__not_in'=>array(absint($post_id)),'meta_query'=>$meta_query,'orderby'=>'modified','order'=>'DESC','no_found_rows'=>true));
        return $query->posts;
    }

    public static function graph_data($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'wiki_article' || $post->post_status !== 'publish') return null;
        $relations = array();
        foreach (self::relations($post_id) as $row) {
            $target = get_post(absint($row['target_id'] ?? 0));
            if (!$target || $target->post_type !== 'wiki_article' || $target->post_status !== 'publish') continue;
            $relations[] = array('type'=>$row['type'],'note'=>$row['note'],'id'=>(int)$target->ID,'title'=>get_the_title($target),'url'=>get_permalink($target));
        }
        $entities = array();
        foreach (self::entities($post_id) as $row) $entities[] = array('type'=>$row['type'],'label'=>$row['label'],'url'=>$row['url']);
        return array('article'=>array('id'=>(int)$post->ID,'title'=>get_the_title($post),'url'=>get_permalink($post)),'entities'=>$entities,'relations'=>$relations,'related_articles'=>array_map(function($p){return array('id'=>(int)$p->ID,'title'=>get_the_title($p),'url'=>get_permalink($p));},self::related_articles($post_id,6)),'notice'=>'Editor-supplied knowledge relationships and exact-label topic suggestions. Not independently verified.');
    }

    public static function routes() {
        register_rest_route('bigshor/v1','/knowledge-graph/(?P<id>\d+)',array(
            'methods'=>'GET','callback'=>function($request){$data=self::graph_data(absint($request['id']));return $data ? rest_ensure_response($data) : new WP_Error('bsw_graph_not_found','Published wiki article not found.',array('status'=>404));},
            'permission_callback'=>'__return_true','args'=>array('id'=>array('validate_callback'=>function($v){return absint($v)>0;}))
        ));
    }

    public static function schema() {
        if (!is_singular('wiki_article') || is_admin()) return;
        $id = absint(get_queried_object_id());
        if (!$id || get_post_status($id) !== 'publish') return;
        $entities = self::entities($id);
        if (!$entities) return;
        $nodes = array();
        foreach ($entities as $index=>$entity) {
            $node = array('@type'=>'Thing','@id'=>get_permalink($id) . '#entity-' . ($index+1),'name'=>$entity['label']);
            if (!empty($entity['url']) && wp_http_validate_url($entity['url'])) $node['sameAs'] = $entity['url'];
            $nodes[] = $node;
        }
        $json = wp_json_encode(array('@context'=>'https://schema.org','@graph'=>$nodes),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        if ($json) echo "\n<script type=\"application/ld+json\">" . $json . "</script>\n";
    }

    public static function shortcode($atts) {
        $atts = shortcode_atts(array('id'=>0,'title'=>'Related topics and articles'),$atts,'bsw_knowledge_graph');
        $id = absint($atts['id']) ?: absint(get_queried_object_id());
        if (get_post_type($id) !== 'wiki_article' || get_post_status($id) !== 'publish') return '';
        $entities = self::entities($id); $relations = self::relations($id); $related = self::related_articles($id,6);
        if (!$entities && !$relations && !$related) return '';
        $out = '<section class="bsw-knowledge-graph" aria-label="' . esc_attr__('Related topics and articles','bigshor-wiki') . '"><h2>' . esc_html($atts['title']) . '</h2>';
        if ($entities) { $out .= '<ul class="bsw-knowledge-entities">'; foreach ($entities as $entity) { $label=esc_html($entity['label']); $out .= '<li>' . ($entity['url'] ? '<a href="'.esc_url($entity['url']).'" rel="nofollow noopener" target="_blank">'.$label.'</a>' : $label) . ' <span>(' . esc_html($entity['type']) . ')</span></li>'; } $out .= '</ul>'; }
        if ($relations) { $out .= '<h3>Article relationships</h3><ul>'; foreach ($relations as $row) { $target=get_post(absint($row['target_id'])); if (!$target || $target->post_status!=='publish' || $target->post_type!=='wiki_article') continue; $out .= '<li>' . esc_html(str_replace('_',' ',$row['type'])) . ': <a href="'.esc_url(get_permalink($target)).'">'.esc_html(get_the_title($target)).'</a>' . (!empty($row['note']) ? ' — '.esc_html($row['note']) : '') . '</li>'; } $out .= '</ul>'; }
        if ($related) { $out .= '<h3>Related articles</h3><ul>'; foreach ($related as $item) $out .= '<li><a href="'.esc_url(get_permalink($item)).'">'.esc_html(get_the_title($item)).'</a></li>'; $out .= '</ul>'; }
        return $out . '</section>';
    }

    public static function menu() {
        add_submenu_page('edit.php?post_type=wiki_article','Knowledge Graph','Knowledge Graph','edit_others_posts','bsw-p5-knowledge-graph',array(__CLASS__,'dashboard'));
    }

    public static function dashboard() {
        if (!current_user_can('edit_others_posts')) return;
        $posts = get_posts(array('post_type'=>'wiki_article','post_status'=>array('publish','draft','pending'),'numberposts'=>500,'orderby'=>'modified','order'=>'DESC'));
        $with_entities=0; $with_relations=0; $without=0; $rows=array();
        foreach ($posts as $post) {
            $entities=self::entities($post->ID); $relations=self::relations($post->ID);
            if ($entities) $with_entities++; else $without++;
            if ($relations) $with_relations++;
            if (count($rows)<60) $rows[]=array($post,$entities,$relations);
        }
        echo '<div class="wrap"><h1>BIGSHOR Knowledge Graph</h1><p>Structured, editor-maintained entities and relationships connect wiki articles and improve topic discovery. These records are not automatically inferred or fact-checked.</p>';
        echo '<div class="card" style="padding:12px;display:inline-block;min-width:140px"><strong>Articles scanned</strong><p style="font-size:22px">'.number_format_i18n(count($posts)).'</p></div> <div class="card" style="padding:12px;display:inline-block;min-width:140px"><strong>With entities</strong><p style="font-size:22px">'.number_format_i18n($with_entities).'</p></div> <div class="card" style="padding:12px;display:inline-block;min-width:140px"><strong>With relationships</strong><p style="font-size:22px">'.number_format_i18n($with_relations).'</p></div> <div class="card" style="padding:12px;display:inline-block;min-width:140px"><strong>Without entities</strong><p style="font-size:22px">'.number_format_i18n($without).'</p></div>';
        echo '<h2>Article graph coverage</h2><table class="widefat striped"><thead><tr><th>Article</th><th>Status</th><th>Entities</th><th>Relationships</th><th>Action</th></tr></thead><tbody>';
        foreach ($rows as $row) echo '<tr><td>'.esc_html(get_the_title($row[0])).'</td><td>'.esc_html($row[0]->post_status).'</td><td>'.count($row[1]).'</td><td>'.count($row[2]).'</td><td><a href="'.esc_url(get_edit_post_link($row[0]->ID)).'">Edit graph</a></td></tr>';
        if (!$rows) echo '<tr><td colspan="5">No wiki articles found.</td></tr>';
        echo '</tbody></table><p class="description">Showing up to 500 articles in the scan and the 60 most recently modified in the table. For frontend display, add <code>[bsw_knowledge_graph]</code> to the wiki article template.</p></div>';
    }
}
BSW_Phase58_Knowledge_Graph::boot();
}
