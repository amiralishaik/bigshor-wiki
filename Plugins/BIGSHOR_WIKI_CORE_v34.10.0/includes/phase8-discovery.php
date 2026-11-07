<?php
/**
 * BIGSHOR Wiki Phase 8: Unified Knowledge Discovery.
 * Published-content discovery across the platform's existing knowledge types.
 * Compatible with PHP 7.4+ and WordPress 6.4+.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase8_Discovery')) {
final class BSW_Phase8_Discovery {
    const VERSION = '22.1.0';
    const OPTION = 'bsw_phase8_settings';

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 40);
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('rest_api_init', array(__CLASS__, 'register_rest'));
        add_shortcode('bigshor_discover', array(__CLASS__, 'shortcode'));
    }

    public static function defaults() { return array('enabled' => 1, 'include_events' => 1); }
    public static function settings() {
        $saved = get_option(self::OPTION, array());
        return wp_parse_args(is_array($saved) ? $saved : array(), self::defaults());
    }
    public static function enabled() { $s = self::settings(); return !empty($s['enabled']); }

    private static function searchable_types() {
        $types = array();
        foreach (array('wiki_article', 'bsw_entity', 'bsw_knowledge_entity', 'bsw_profile') as $type) {
            if (post_type_exists($type) && !in_array($type, $types, true)) $types[] = $type;
        }
        $s = self::settings();
        if (!empty($s['include_events']) && post_type_exists('bsw_knowledge_event')) $types[] = 'bsw_knowledge_event';
        return $types;
    }

    private static function type_label($type) {
        $labels = array(
            'wiki_article' => 'Article', 'bsw_entity' => 'Knowledge Entity',
            'bsw_knowledge_entity' => 'Knowledge Record', 'bsw_profile' => 'Profile',
            'bsw_knowledge_event' => 'Timeline Event'
        );
        return isset($labels[$type]) ? $labels[$type] : 'Knowledge';
    }

    private static function query_args($term, $type, $page, $per_page, $sort) {
        $types = self::searchable_types();
        if ($type && in_array($type, $types, true)) $types = array($type);
        $orderby = 'relevance';
        $order = 'DESC';
        if ($sort === 'newest') { $orderby = 'date'; $order = 'DESC'; }
        elseif ($sort === 'oldest') { $orderby = 'date'; $order = 'ASC'; }
        elseif ($sort === 'updated') { $orderby = 'modified'; $order = 'DESC'; }
        return array(
            'post_type' => $types ? $types : array('none'),
            'post_status' => 'publish',
            's' => $term,
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => $orderby,
            'order' => $order,
            'ignore_sticky_posts' => true,
            'no_found_rows' => false,
            'suppress_filters' => false
        );
    }

    public static function shortcode($atts) {
        if (!self::enabled()) return '';
        $a = shortcode_atts(array('limit' => 10, 'show_filters' => 'yes'), $atts, 'bigshor_discover');
        $limit = max(1, min(30, absint($a['limit'])));
        $term = isset($_GET['bsw_discover_q']) && !is_array($_GET['bsw_discover_q']) ? sanitize_text_field(wp_unslash($_GET['bsw_discover_q'])) : '';
        $type = isset($_GET['bsw_discover_type']) && !is_array($_GET['bsw_discover_type']) ? sanitize_key(wp_unslash($_GET['bsw_discover_type'])) : '';
        $sort = isset($_GET['bsw_discover_sort']) && !is_array($_GET['bsw_discover_sort']) ? sanitize_key(wp_unslash($_GET['bsw_discover_sort'])) : 'relevance';
        if (!in_array($sort, array('relevance','newest','oldest','updated'), true)) $sort = 'relevance';
        $page = isset($_GET['bsw_discover_page']) && !is_array($_GET['bsw_discover_page']) ? max(1, absint($_GET['bsw_discover_page'])) : 1;
        $types = self::searchable_types();
        $valid_type = in_array($type, $types, true) ? $type : '';
        $action = remove_query_arg(array('bsw_discover_q', 'bsw_discover_type', 'bsw_discover_sort', 'bsw_discover_page'));
        ob_start();
        echo '<section class="bsw-p8-discovery" aria-label="BIGSHOR knowledge discovery">';
        echo '<form class="bsw-p8-search" method="get" action="'.esc_url($action).'">';
        foreach ($_GET as $key => $value) {
            if (in_array($key, array('bsw_discover_q','bsw_discover_type','bsw_discover_sort','bsw_discover_page'), true) || is_array($value) || is_object($value)) continue;
            echo '<input type="hidden" name="'.esc_attr(sanitize_key($key)).'" value="'.esc_attr(sanitize_text_field(wp_unslash($value))).'">';
        }
        echo '<label class="screen-reader-text" for="bsw-p8-query">Search BIGSHOR knowledge</label><input id="bsw-p8-query" type="search" minlength="2" name="bsw_discover_q" value="'.esc_attr($term).'" placeholder="Search articles, people, organisations and events">';
        if ($a['show_filters'] === 'yes') {
            echo '<label class="screen-reader-text" for="bsw-p8-type">Content type</label><select id="bsw-p8-type" name="bsw_discover_type"><option value="">All content types</option>';
            foreach ($types as $pt) echo '<option value="'.esc_attr($pt).'" '.selected($valid_type, $pt, false).'>'.esc_html(self::type_label($pt)).'</option>';
            echo '</select><label class="screen-reader-text" for="bsw-p8-sort">Sort results</label><select id="bsw-p8-sort" name="bsw_discover_sort"><option value="relevance" '.selected($sort,'relevance',false).'>Most relevant</option><option value="newest" '.selected($sort,'newest',false).'>Newest first</option><option value="oldest" '.selected($sort,'oldest',false).'>Oldest first</option><option value="updated" '.selected($sort,'updated',false).'>Recently updated</option></select>';
        }
        echo '<button type="submit">Search</button></form>';
        if ($term !== '') {
            if (strlen($term) < 2) {
                echo '<p class="bsw-p8-intro">Please enter at least two characters to search.</p>';
            } else {
                $q = new WP_Query(self::query_args($term, $valid_type, $page, $limit, $sort));
                echo '<p class="bsw-p8-count">'.esc_html(number_format_i18n((int)$q->found_posts)).' published result(s) for <strong>'.esc_html($term).'</strong></p>';
                if ($q->have_posts()) {
                    echo '<div class="bsw-p8-results">';
                    while ($q->have_posts()) { $q->the_post(); $id = get_the_ID();
                        $date = get_the_date('', $id);
                        echo '<article class="bsw-p8-result"><div class="bsw-p8-result-meta">'.esc_html(self::type_label(get_post_type($id))).' <span aria-hidden="true">·</span> '.esc_html($date).'</div><h3><a href="'.esc_url(get_permalink($id)).'">'.esc_html(get_the_title($id)).'</a></h3><p>'.esc_html(wp_trim_words(get_the_excerpt($id), 34)).'</p></article>';
                    }
                    echo '</div>';
                    $pages = (int)$q->max_num_pages;
                    if ($pages > 1) {
                        echo '<nav class="bsw-p8-pagination" aria-label="Search result pages">';
                        $start = max(1, $page - 2); $end = min($pages, $page + 2);
                        if ($start > 1) echo '<a href="'.esc_url(add_query_arg(array('bsw_discover_q'=>$term,'bsw_discover_type'=>$valid_type,'bsw_discover_sort'=>$sort,'bsw_discover_page'=>1),$action)).'">1</a> ';
                        if ($start > 2) echo '<span aria-hidden="true">…</span> ';
                        for ($i = $start; $i <= $end; $i++) {
                            $url = add_query_arg(array('bsw_discover_q'=>$term,'bsw_discover_type'=>$valid_type,'bsw_discover_sort'=>$sort,'bsw_discover_page'=>$i),$action);
                            if ($i === $page) echo '<span aria-current="page">'.absint($i).'</span> ';
                            else echo '<a href="'.esc_url($url).'">'.absint($i).'</a> ';
                        }
                        if ($end < $pages - 1) echo '<span aria-hidden="true">…</span> ';
                        if ($end < $pages) echo '<a href="'.esc_url(add_query_arg(array('bsw_discover_q'=>$term,'bsw_discover_type'=>$valid_type,'bsw_discover_sort'=>$sort,'bsw_discover_page'=>$pages),$action)).'">'.absint($pages).'</a>';
                        echo '</nav>';
                    }
                } else echo '<p>No matching published content was found. Try another keyword or content type.</p>';
                wp_reset_postdata();
            }
        } else echo '<p class="bsw-p8-intro">Search across BIGSHOR articles, knowledge entities, profiles and timeline events.</p>';
        echo '</section>';
        return ob_get_clean();
    }

    public static function register_rest() {
        register_rest_route('bigshor/v1', '/discover', array(
            'methods' => 'GET', 'callback' => array(__CLASS__, 'rest_discover'),
            'permission_callback' => '__return_true',
            'args' => array(
                'q' => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
                'type' => array('required' => false, 'sanitize_callback' => 'sanitize_key'),
                'sort' => array('required' => false, 'default' => 'relevance', 'sanitize_callback' => 'sanitize_key'),
                'page' => array('required' => false, 'default' => 1, 'sanitize_callback' => 'absint'),
                'per_page' => array('required' => false, 'default' => 10, 'sanitize_callback' => 'absint')
            )
        ));
    }

    public static function rest_discover($request) {
        if (!self::enabled()) return new WP_Error('bsw_discovery_disabled', 'Knowledge discovery is disabled.', array('status' => 404));
        $term = trim((string)$request->get_param('q'));
        if (strlen($term) < 2) return new WP_Error('bsw_discovery_query', 'Enter at least two characters to search.', array('status' => 400));
        $types = self::searchable_types();
        $type = sanitize_key($request->get_param('type'));
        if ($type !== '' && !in_array($type, $types, true)) return new WP_Error('bsw_discovery_type', 'The requested content type is not searchable.', array('status' => 400));
        $sort = sanitize_key($request->get_param('sort'));
        if (!in_array($sort, array('relevance','newest','oldest','updated'), true)) return new WP_Error('bsw_discovery_sort', 'Unsupported sort order.', array('status' => 400));
        $page = max(1, absint($request->get_param('page')));
        $per_page = max(1, min(30, absint($request->get_param('per_page')) ?: 10));
        $query = new WP_Query(self::query_args($term, $type, $page, $per_page, $sort));
        $items = array();
        foreach ($query->posts as $post) {
            $items[] = array(
                'id' => (int)$post->ID,
                'type' => get_post_type($post),
                'type_label' => self::type_label(get_post_type($post)),
                'title' => get_the_title($post),
                'url' => get_permalink($post),
                'excerpt' => wp_trim_words(get_the_excerpt($post), 40),
                'date' => get_post_time('c', true, $post),
                'modified' => get_post_modified_time('c', true, $post)
            );
        }
        return rest_ensure_response(array(
            'query' => $term, 'type' => $type, 'sort' => $sort, 'page' => $page, 'per_page' => $per_page,
            'total' => (int)$query->found_posts, 'total_pages' => (int)$query->max_num_pages,
            'items' => $items
        ));
    }

    public static function admin_menu() {
        add_submenu_page('options-general.php', 'BIGSHOR Discovery', 'BIGSHOR Discovery', 'manage_options', 'bsw-phase8', array(__CLASS__, 'settings_page'));
    }
    public static function register_settings() {
        register_setting('bsw_phase8_group', self::OPTION, array('sanitize_callback' => array(__CLASS__, 'sanitize_settings')));
    }
    public static function sanitize_settings($input) {
        $input = is_array($input) ? $input : array();
        return array('enabled' => empty($input['enabled']) ? 0 : 1, 'include_events' => empty($input['include_events']) ? 0 : 1);
    }
    public static function settings_page() {
        if (!current_user_can('manage_options')) return;
        $s = self::settings();
        echo '<div class="wrap"><h1>BIGSHOR Unified Knowledge Discovery — Phase 8</h1><p>Searches published BIGSHOR content across supported content types. Disabling discovery does not delete content or metadata.</p><form method="post" action="options.php">';
        settings_fields('bsw_phase8_group');
        echo '<p><label><input type="checkbox" name="'.esc_attr(self::OPTION).'[enabled]" value="1" '.checked($s['enabled'], 1, false).'> Enable unified knowledge discovery</label></p>';
        echo '<p><label><input type="checkbox" name="'.esc_attr(self::OPTION).'[include_events]" value="1" '.checked($s['include_events'], 1, false).'> Include published timeline events in search</label></p>';
        submit_button('Save discovery settings');
        echo '</form><hr><h2>Shortcode</h2><p><code>[bigshor_discover]</code> or <code>[bigshor_discover limit="15" show_filters="yes"]</code></p><h2>Read-only REST endpoint</h2><p><code>/wp-json/bigshor/v1/discover?q=keyword&amp;per_page=10&amp;page=1&amp;sort=relevance</code></p><p>Sort options: relevance, newest, oldest, updated. Only published records are returned. Invalid content types and sort values are rejected.</p></div>';
    }
}
BSW_Phase8_Discovery::init();

add_action('wp_enqueue_scripts', function () {
    if (!BSW_Phase8_Discovery::enabled()) return;
    $css = '.bsw-p8-discovery{margin:1.5em 0}.bsw-p8-search{display:flex;gap:10px;flex-wrap:wrap}.bsw-p8-search input[type=search]{flex:1;min-width:220px;padding:12px;border:1px solid #cbd2da;border-radius:6px}.bsw-p8-search select,.bsw-p8-search button{padding:11px 14px;border:1px solid #cbd2da;border-radius:6px}.bsw-p8-search button{cursor:pointer}.bsw-p8-results{display:grid;gap:14px;margin-top:18px}.bsw-p8-result{padding:16px;border:1px solid #e0e4e8;border-radius:9px}.bsw-p8-result h3{margin:5px 0}.bsw-p8-result-meta{font-size:12px;text-transform:uppercase;letter-spacing:.04em;opacity:.7}.bsw-p8-pagination{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:18px}.bsw-p8-pagination span[aria-current],.bsw-p8-pagination a{padding:5px 9px;border:1px solid #d7dce2;border-radius:5px}';
    wp_register_style('bsw-phase8-inline', false); wp_enqueue_style('bsw-phase8-inline'); wp_add_inline_style('bsw-phase8-inline', $css);
});
}
