<?php
/**
 * BIGSHOR Wiki Phase 52 — indexed discovery, namespace-aware canonical routes and bot API helpers.
 * Additive implementation. The WordPress post model remains authoritative.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase52_Search_Routing')) {
final class BSW_Phase52_Search_Routing {
    const VERSION = '52.0.0';
    private static $table = '';

    public static function boot() {
        global $wpdb;
        self::$table = $wpdb->prefix . 'bsw_wiki_search';
        add_action('init', array(__CLASS__, 'register_routes'), 30);
        add_filter('query_vars', array(__CLASS__, 'query_vars'));
        add_action('pre_get_posts', array(__CLASS__, 'resolve_namespace_route'), 20);
        add_filter('post_type_link', array(__CLASS__, 'namespace_permalink'), 20, 2);
        add_action('save_post_wiki_article', array(__CLASS__, 'index_article'), 110, 3);
        add_action('before_delete_post', array(__CLASS__, 'remove_article'), 10, 2);
        add_action('init', array(__CLASS__, 'maybe_upgrade'), 40);
        add_action('bsw52_index_batch', array(__CLASS__, 'index_batch'));
        if (!wp_next_scheduled('bsw52_index_batch')) wp_schedule_event(time()+180, 'hourly', 'bsw52_index_batch');
        add_shortcode('bsw52_search', array(__CLASS__, 'search_shortcode'));
        add_action('rest_api_init', array(__CLASS__, 'register_api'));
    }

    public static function activate() {
        self::create_table();
        update_option('bsw52_schema_version', self::VERSION, false);
        update_option('bsw52_index_complete', false, false);
        flush_rewrite_rules();
    }

    public static function maybe_upgrade() {
        if (get_option('bsw52_schema_version') !== self::VERSION) {
            self::create_table();
            update_option('bsw52_schema_version', self::VERSION, false);
            update_option('bsw52_index_complete', false, false);
            flush_rewrite_rules();
        }
    }

    private static function create_table() {
        global $wpdb;
        self::$table = $wpdb->prefix . 'bsw_wiki_search';
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE " . self::$table . " (
            post_id bigint(20) unsigned NOT NULL,
            title text NOT NULL,
            content longtext NOT NULL,
            language varchar(16) NOT NULL DEFAULT 'und',
            namespace varchar(100) NOT NULL DEFAULT '',
            modified datetime NOT NULL,
            PRIMARY KEY  (post_id),
            KEY language (language),
            KEY namespace (namespace),
            KEY modified (modified),
            FULLTEXT KEY title_content (title,content)
        ) $charset;";
        dbDelta($sql);
    }

    public static function index_article($post_id, $post, $update) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || !$post || $post->post_type !== 'wiki_article') return;
        global $wpdb;
        if (!self::$table) self::$table = $wpdb->prefix . 'bsw_wiki_search';
        if ($post->post_status !== 'publish' || $post->post_password !== '') { self::remove_article($post_id); return; }
        $terms = wp_get_object_terms($post_id, 'wiki_namespace', array('fields'=>'slugs'));
        $namespace = (!is_wp_error($terms) && $terms) ? sanitize_title($terms[0]) : '';
        $lang = sanitize_key(get_post_meta($post_id, '_bsw_language_code', true) ?: 'und');
        $title = wp_strip_all_tags($post->post_title);
        $content = wp_strip_all_tags(strip_shortcodes($post->post_content));
        $wpdb->replace(self::$table, array('post_id'=>absint($post_id),'title'=>$title,'content'=>$content,'language'=>$lang,'namespace'=>$namespace,'modified'=>current_time('mysql', true)), array('%d','%s','%s','%s','%s','%s'));
    }

    public static function remove_article($post_id, $post = null) {
        if ($post && $post->post_type !== 'wiki_article') return;
        global $wpdb;
        if (!self::$table) self::$table = $wpdb->prefix . 'bsw_wiki_search';
        $wpdb->delete(self::$table, array('post_id'=>absint($post_id)), array('%d'));
    }

    /** Index existing published articles gradually to avoid activation-time load spikes. */
    public static function index_batch() {
        if (get_option('bsw52_index_complete')) return;
        $cursor = absint(get_option('bsw52_index_cursor', 0));
        global $wpdb;
        $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND ID > %d ORDER BY ID ASC LIMIT 100", 'wiki_article', $cursor));
        foreach ((array)$ids as $id) { $post = get_post($id); if ($post) self::index_article($id, $post, true); }
        if (count((array)$ids) < 100) { update_option('bsw52_index_cursor', 0, false); update_option('bsw52_index_complete', true, false); }
        elseif ($ids) update_option('bsw52_index_cursor', (int)end($ids), false);
    }

    public static function register_routes() {
        add_rewrite_tag('%bsw_ns%', '([^&]+)');
        add_rewrite_tag('%bsw_article%', '([^&]+)');
        add_rewrite_rule('^wiki/([^/]+)/([^/]+)/?$', 'index.php?post_type=wiki_article&bsw_ns=$matches[1]&bsw_article=$matches[2]', 'top');
    }

    public static function query_vars($vars) {
        $vars[] = 'bsw_ns'; $vars[] = 'bsw_article'; return $vars;
    }

    public static function resolve_namespace_route($query) {
        if (is_admin() || !$query->is_main_query()) return;
        $slug = $query->get('bsw_article');
        $namespace = $query->get('bsw_ns');
        if (!$slug || !$namespace) return;
        $query->set('post_type', 'wiki_article');
        $query->set('name', sanitize_title($slug));
        $query->set('tax_query', array(array('taxonomy'=>'wiki_namespace','field'=>'slug','terms'=>sanitize_title($namespace))));
    }

    public static function namespace_permalink($url, $post) {
        if (!$post || $post->post_type !== 'wiki_article' || !taxonomy_exists('wiki_namespace')) return $url;
        $terms = wp_get_object_terms($post->ID, 'wiki_namespace');
        if (is_wp_error($terms) || count($terms) !== 1 || !$terms[0]->slug) return $url;
        return home_url(user_trailingslashit('wiki/' . rawurlencode($terms[0]->slug) . '/' . $post->post_name));
    }

    private static function indexed_ids($term, $namespace, $language, $limit) {
        global $wpdb;
        if (!self::$table) self::$table = $wpdb->prefix . 'bsw_wiki_search';
        $like = '%' . $wpdb->esc_like($term) . '%';
        $where = array('1=1'); $params = array();
        if ($namespace !== '') { $where[] = 'namespace = %s'; $params[] = $namespace; }
        if ($language !== '') { $where[] = 'language = %s'; $params[] = $language; }
        $params[] = $term; $params[] = $like; $params[] = $like; $params[] = $limit;
        $sql = "SELECT post_id FROM " . self::$table . " WHERE " . implode(' AND ', $where) . " AND (MATCH(title,content) AGAINST (%s IN BOOLEAN MODE) OR title LIKE %s OR content LIKE %s) ORDER BY (title = %s) DESC, MATCH(title,content) AGAINST (%s IN NATURAL LANGUAGE MODE) DESC, modified DESC LIMIT %d";
        // Exact title and relevance ordering; fallback to LIKE if FULLTEXT is unavailable on the host database.
        $params2 = array();
        if ($namespace !== '') { $params2[] = $namespace; }
        if ($language !== '') { $params2[] = $language; }
        $params2[] = $term; $params2[] = $like; $params2[] = $like; $params2[] = $term; $params2[] = $term; $params2[] = $limit;
        $prepared = $wpdb->prepare($sql, $params2);
        $ids = $wpdb->get_col($prepared);
        if ($wpdb->last_error) {
            $fallback = "SELECT post_id FROM " . self::$table . " WHERE " . implode(' AND ', $where) . " AND (title LIKE %s OR content LIKE %s) ORDER BY (title = %s) DESC, modified DESC LIMIT %d";
            $fp = array(); if ($namespace !== '') $fp[]=$namespace; if ($language !== '') $fp[]=$language;
            $fp[]=$like; $fp[]=$like; $fp[]=$term; $fp[]=$limit;
            $ids = $wpdb->get_col($wpdb->prepare($fallback, $fp));
        }
        return array_values(array_filter(array_map('absint', (array)$ids)));
    }

    public static function search_shortcode($atts) {
        $atts = shortcode_atts(array('placeholder'=>'Search BIGSHOR Wiki','per_page'=>20), $atts, 'bsw52_search');
        $term = isset($_GET['bswq']) ? sanitize_text_field(wp_unslash($_GET['bswq'])) : '';
        $ns = isset($_GET['bsw_namespace']) ? sanitize_title(wp_unslash($_GET['bsw_namespace'])) : '';
        $lang = isset($_GET['bsw_language']) ? sanitize_key(wp_unslash($_GET['bsw_language'])) : '';
        $out = '<form method="get" class="bsw52-search"><label for="bsw52-q">Search articles</label><input id="bsw52-q" name="bswq" value="'.esc_attr($term).'" placeholder="'.esc_attr($atts['placeholder']).'">';
        if (taxonomy_exists('wiki_namespace')) {
            $out .= '<label>Namespace <select name="bsw_namespace"><option value="">All namespaces</option>';
            $terms = get_terms(array('taxonomy'=>'wiki_namespace','hide_empty'=>false));
            if (!is_wp_error($terms)) foreach ($terms as $t) $out .= '<option value="'.esc_attr($t->slug).'" '.selected($ns,$t->slug,false).'>'.esc_html($t->name).'</option>';
            $out .= '</select></label>';
        }
        $out .= '<label>Language <input name="bsw_language" value="'.esc_attr($lang).'" maxlength="16" placeholder="en"></label><button type="submit">Search</button></form>';
        if ($term === '') return $out;
        if (strlen($term) < 2) return $out . '<p>Enter at least two characters.</p>';
        $ids = self::indexed_ids($term, $ns, $lang, min(100,max(1,absint($atts['per_page']))));
        if (!$ids) {
            // A bounded fallback keeps newly published articles discoverable before the index catches up.
            $fallback = new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','s'=>$term,'posts_per_page'=>min(30,max(1,absint($atts['per_page']))),'no_found_rows'=>true));
            $ids = wp_list_pluck($fallback->posts, 'ID');
        }
        if ($ids) {
            $out .= '<h2>Search results</h2><ol class="bsw52-search-results">';
            foreach ($ids as $id) { $p=get_post($id); if (!$p || $p->post_status !== 'publish') continue; $out .= '<li><a href="'.esc_url(get_permalink($id)).'">'.esc_html(get_the_title($id)).'</a><p>'.esc_html(wp_trim_words(wp_strip_all_tags(get_the_excerpt($id)),30)).'</p></li>'; }
            $out .= '</ol>';
        } else $out .= '<p>No matching articles found.</p>';
        return $out;
    }

    public static function register_api() {
        register_rest_route('bigshor/v1', '/bot/schema', array('methods'=>'GET','callback'=>array(__CLASS__,'api_schema'),'permission_callback'=>'__return_true'));
        register_rest_route('bigshor/v1', '/bot/articles/(?P<id>\d+)', array('methods'=>'DELETE','callback'=>array(__CLASS__,'api_delete_draft'),'permission_callback'=>function($r){ return self::bot_auth($r,'articles:update'); }));
    }

    public static function api_schema($request) {
        return rest_ensure_response(array(
            'name'=>'BIGSHOR Wiki Bot API','version'=>'1.0','authentication'=>'Bearer token created by a BIGSHOR Wiki administrator',
            'base'=>rest_url('bigshor/v1'),'endpoints'=>array(
                array('method'=>'GET','path'=>'/bot/articles','scope'=>'articles:read','description'=>'List published wiki articles. Supports page and per_page.'),
                array('method'=>'POST','path'=>'/bot/articles','scope'=>'articles:create','description'=>'Create an article draft. Required: title; optional: content.'),
                array('method'=>'PATCH','path'=>'/bot/articles/{id}','scope'=>'articles:update','description'=>'Update a draft owned by the bot creator.'),
                array('method'=>'DELETE','path'=>'/bot/articles/{id}','scope'=>'articles:update','description'=>'Move an owned draft to the WordPress Trash; published content is never deleted by bots.')
            ),'limits'=>array('requests_per_token_per_hour'=>600,'writes'=>'draft-only; editorial publication remains a human workflow')
        ));
    }

    private static function bot_auth($request, $scope) {
        if (!class_exists('BSW_Phase51_Advanced_Platform')) return new WP_Error('bsw52_api_unavailable','Bot API is unavailable.',array('status'=>503));
        return BSW_Phase51_Advanced_Platform::bot_auth_for_phase($request,$scope);
    }

    public static function api_delete_draft($request) {
        $id=absint($request['id']); $post=get_post($id);
        if (!$post || $post->post_type !== 'wiki_article') return new WP_Error('bsw52_not_found','Article not found.',array('status'=>404));
        if ($post->post_status !== 'draft' && $post->post_status !== 'pending') return new WP_Error('bsw52_not_draft','Bots may only trash drafts or pending articles.',array('status'=>409));
        $owner=BSW_Phase51_Advanced_Platform::bot_context_for_phase(); $owner_id=absint($owner['created_by']??0);
        if (!$owner_id || ((int)$post->post_author !== $owner_id && !user_can($owner_id,'edit_others_posts')) || !user_can($owner_id,'delete_post',$id)) return new WP_Error('bsw52_owner','This bot cannot delete this draft with the creator account’s current permissions.',array('status'=>403));
        $trashed=wp_trash_post($id); if (!$trashed) return new WP_Error('bsw52_trash_failed','Draft could not be moved to Trash.',array('status'=>500));
        return rest_ensure_response(array('id'=>$id,'status'=>'trash'));
    }
}
BSW_Phase52_Search_Routing::boot();
register_activation_hook(dirname(__DIR__) . '/bigshor-wiki-core.php', array('BSW_Phase52_Search_Routing','activate'));
}
