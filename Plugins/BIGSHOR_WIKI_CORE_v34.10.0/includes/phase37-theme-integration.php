<?php
/**
 * BIGSHOR Wiki Core 29.2.0 — Phase 37 Theme Integration & Page Services.
 * Adds safe page tools, page metadata, incoming-link discovery and REST watchlists
 * for the Wikipedia-inspired BIGSHOR theme. PHP 7.4 compatible.
 */
if (!defined('ABSPATH')) { exit; }

if (!class_exists('BSW_Phase37_Theme_Integration')) {
final class BSW_Phase37_Theme_Integration {
    const WATCH_META = '_bsw_watchlist';

    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
        add_shortcode('bigshor_article_tools', array(__CLASS__, 'article_tools'));
        add_shortcode('bigshor_page_info', array(__CLASS__, 'page_info'));
        add_shortcode('bigshor_what_links_here', array(__CLASS__, 'what_links_here'));
        add_shortcode('bigshor_recent_changes', array(__CLASS__, 'recent_changes'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'assets'));
        add_filter('the_content', array(__CLASS__, 'append_article_tools'), 8);
    }

    public static function register_routes() {
        register_rest_route('bigshor/v1', '/page-info/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'rest_page_info'),
            'permission_callback' => '__return_true',
            'args' => array('id' => array('validate_callback' => 'is_numeric')),
        ));
        register_rest_route('bigshor/v1', '/what-links-here/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'rest_what_links_here'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route('bigshor/v1', '/watchlist', array(
            array('methods' => WP_REST_Server::READABLE, 'callback' => array(__CLASS__, 'rest_watchlist'), 'permission_callback' => array(__CLASS__, 'logged_in')),
        ));
        register_rest_route('bigshor/v1', '/watchlist/(?P<id>\d+)', array(
            array('methods' => 'POST', 'callback' => array(__CLASS__, 'rest_toggle_watch'), 'permission_callback' => array(__CLASS__, 'can_toggle_watch')),
            array('methods' => 'DELETE', 'callback' => array(__CLASS__, 'rest_remove_watch'), 'permission_callback' => array(__CLASS__, 'can_toggle_watch')),
        ));
    }

    public static function logged_in() { return is_user_logged_in(); }

    public static function can_toggle_watch($request) {
        if (!is_user_logged_in()) return new WP_Error('rest_not_logged_in', 'Please log in to manage your watchlist.', array('status' => 401));
        $id = absint($request['id']);
        if (!$id || !in_array(get_post_type($id), array('wiki_article', 'bsw_news'), true) || get_post_status($id) !== 'publish') {
            return new WP_Error('rest_invalid_article', 'Only published BIGSHOR Knowledge and News pages can be watched.', array('status' => 404));
        }
        return true;
    }

    private static function watch_ids($user_id) {
        $ids = get_user_meta($user_id, self::WATCH_META, true);
        if (!is_array($ids)) $ids = array();
        return array_values(array_unique(array_filter(array_map('absint', $ids))));
    }

    public static function rest_watchlist() {
        $ids = self::watch_ids(get_current_user_id());
        if (!$ids) return rest_ensure_response(array());
        $posts = get_posts(array('post_type' => array('wiki_article', 'bsw_news'), 'post_status' => 'publish', 'post__in' => $ids, 'orderby' => 'post__in', 'numberposts' => 100));
        $rows = array();
        foreach ($posts as $post) $rows[] = array('id' => (int) $post->ID, 'title' => get_the_title($post), 'url' => get_permalink($post), 'type' => $post->post_type, 'modified' => get_post_modified_time('c', true, $post));
        return rest_ensure_response($rows);
    }

    public static function rest_toggle_watch($request) {
        $id = absint($request['id']); $uid = get_current_user_id(); $ids = self::watch_ids($uid);
        if (in_array($id, $ids, true)) { $ids = array_values(array_diff($ids, array($id))); $watching = false; }
        else { $ids[] = $id; $ids = array_values(array_unique($ids)); $watching = true; }
        update_user_meta($uid, self::WATCH_META, $ids);
        return rest_ensure_response(array('id' => $id, 'watching' => $watching, 'count' => count($ids)));
    }

    public static function rest_remove_watch($request) {
        $id = absint($request['id']); $uid = get_current_user_id();
        $ids = array_values(array_diff(self::watch_ids($uid), array($id)));
        update_user_meta($uid, self::WATCH_META, $ids);
        return rest_ensure_response(array('id' => $id, 'watching' => false, 'count' => count($ids)));
    }

    private static function public_content_post($id) {
        $post = get_post(absint($id));
        if (!$post || !in_array($post->post_type, array('wiki_article', 'bsw_news'), true) || $post->post_status !== 'publish') return null;
        return $post;
    }

    public static function rest_page_info($request) {
        $post = self::public_content_post($request['id']);
        if (!$post) return new WP_Error('bsw_page_not_found', 'Published BIGSHOR page not found.', array('status' => 404));
        $author = get_userdata($post->post_author);
        $word_count = str_word_count(wp_strip_all_tags(strip_shortcodes($post->post_content)));
        $revisions = wp_get_post_revisions($post->ID, array('fields' => 'ids'));
        return rest_ensure_response(array(
            'id' => (int) $post->ID,
            'title' => get_the_title($post),
            'type' => $post->post_type,
            'url' => get_permalink($post),
            'published' => get_post_time('c', true, $post),
            'modified' => get_post_modified_time('c', true, $post),
            'author' => $author ? $author->display_name : '',
            'word_count' => (int) $word_count,
            'revision_count' => is_array($revisions) ? count($revisions) : 0,
            'permalink' => get_permalink($post),
        ));
    }

    public static function rest_what_links_here($request) {
        global $wpdb;
        $target = self::public_content_post($request['id']);
        if (!$target) return new WP_Error('bsw_page_not_found', 'Published BIGSHOR page not found.', array('status' => 404));
        $url = get_permalink($target); $path = wp_parse_url($url, PHP_URL_PATH); $slug = $target->post_name;
        $like_url = '%' . $wpdb->esc_like($url) . '%';
        $like_path = '%' . $wpdb->esc_like((string) $path) . '%';
        $like_slug = '%"' . $wpdb->esc_like($slug) . '"%';
        $sql = $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('wiki_article','bsw_news') AND ID<>%d AND (post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s) ORDER BY post_modified_gmt DESC LIMIT 100",
            $target->ID, $like_url, $like_path, $like_slug
        );
        $ids = $wpdb->get_col($sql); $rows = array();
        foreach ($ids as $id) $rows[] = array('id' => (int) $id, 'title' => get_the_title($id), 'url' => get_permalink($id), 'type' => get_post_type($id));
        return rest_ensure_response($rows);
    }

    public static function article_tools($atts = array()) {
        $id = get_the_ID(); if (!$id || !in_array(get_post_type($id), array('wiki_article', 'bsw_news'), true)) return '';
        $url = get_permalink($id); $title = get_the_title($id);
        $out = '<nav class="bsw-page-tools" aria-label="Page tools"><strong>Page tools</strong><ul>';
        $out .= '<li><a href="' . esc_url(add_query_arg('print', '1', $url)) . '" rel="nofollow">Printable version</a></li>';
        $out .= '<li><a href="' . esc_url(add_query_arg('oldid', 'current', $url)) . '">Permanent link</a></li>';
        $out .= '<li><a href="' . esc_url(add_query_arg('page-info', '1', $url)) . '#bsw-page-information">Page information</a></li>';
        if (get_post_type($id) === 'wiki_article') { $history = get_page_by_path('article-history'); $history_url = $history ? get_permalink($history) : $url; $out .= '<li><a href="' . esc_url(add_query_arg('article_id', $id, $history_url)) . '">View history</a></li>'; }
        $out .= '<li><a href="' . esc_url(add_query_arg('what-links-here', '1', $url)) . '#bsw-what-links-here">What links here</a></li>';
        $out .= '<li><a href="mailto:?subject=' . rawurlencode($title) . '&body=' . rawurlencode($url) . '">Share by email</a></li>';
        if (is_user_logged_in() && current_user_can('edit_post', $id)) $out .= '<li><a href="' . esc_url(get_edit_post_link($id)) . '">Edit this page</a></li>';
        $out .= '</ul></nav>';
        if (isset($_GET['page-info'])) $out .= self::page_info();
        if (isset($_GET['what-links-here'])) $out .= self::what_links_here();
        return $out;
    }

    public static function page_info() {
        $id = get_the_ID(); if (!$id) $id = absint($_GET['article_id'] ?? 0);
        $post = self::public_content_post($id); if (!$post) return '';
        $author = get_userdata($post->post_author); $revs = wp_get_post_revisions($id, array('fields' => 'ids'));
        $rows = array(
            'Page title' => get_the_title($post),
            'Content type' => $post->post_type === 'bsw_news' ? 'BIGSHOR News' : 'Knowledge article',
            'First published' => get_the_date(get_option('date_format'), $post),
            'Last updated' => get_the_modified_date(get_option('date_format') . ' ' . get_option('time_format'), $post),
            'Primary contributor' => $author ? $author->display_name : 'Not available',
            'Word count' => number_format_i18n(str_word_count(wp_strip_all_tags(strip_shortcodes($post->post_content)))),
            'Saved revisions' => number_format_i18n(is_array($revs) ? count($revs) : 0),
            'Permanent URL' => get_permalink($post),
        );
        $out = '<section id="bsw-page-information" class="bsw-page-information"><h2>Page information</h2><table><tbody>';
        foreach ($rows as $label => $value) $out .= '<tr><th scope="row">' . esc_html($label) . '</th><td>' . ($label === 'Permanent URL' ? '<a href="' . esc_url($value) . '">' . esc_html($value) . '</a>' : esc_html($value)) . '</td></tr>';
        return $out . '</tbody></table></section>';
    }

    public static function what_links_here() {
        $id = get_the_ID(); if (!$id) $id = absint($_GET['article_id'] ?? 0);
        $post = self::public_content_post($id); if (!$post) return '';
        $response = self::rest_what_links_here(new WP_REST_Request('GET', '/bigshor/v1/what-links-here/' . $id));
        $rows = is_wp_error($response) ? array() : $response->get_data();
        $out = '<section id="bsw-what-links-here" class="bsw-what-links-here"><h2>What links here</h2>';
        if (!$rows) return $out . '<p>No incoming links were found in the current published BIGSHOR content.</p></section>';
        $out .= '<ul>'; foreach ($rows as $row) $out .= '<li><a href="' . esc_url($row['url']) . '">' . esc_html($row['title']) . '</a></li>';
        return $out . '</ul></section>';
    }

    public static function recent_changes($atts) {
        $atts = shortcode_atts(array('limit' => 20), $atts, 'bigshor_recent_changes');
        $limit = max(5, min(50, absint($atts['limit'])));
        $q = new WP_Query(array('post_type' => array('wiki_article', 'bsw_news'), 'post_status' => 'publish', 'posts_per_page' => $limit, 'orderby' => 'modified', 'order' => 'DESC', 'no_found_rows' => true));
        if (!$q->have_posts()) return '<p>No published changes are available yet.</p>';
        $out = '<ol class="bsw-recent-changes">';
        while ($q->have_posts()) { $q->the_post(); $type = get_post_type() === 'bsw_news' ? 'News' : 'Knowledge'; $out .= '<li><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a> <span>' . esc_html($type) . ' · ' . esc_html(get_the_modified_date()) . '</span></li>'; }
        wp_reset_postdata(); return $out . '</ol>';
    }


    public static function append_article_tools($content) {
        if (is_admin() || !is_singular(array('wiki_article', 'bsw_news')) || !in_the_loop() || !is_main_query()) return $content;
        return self::article_tools() . $content;
    }

    public static function assets() {
        if (!is_singular(array('wiki_article', 'bsw_news')) || !is_user_logged_in()) return;
        wp_register_script('bsw-phase37-page-tools', '', array(), '29.2.0', true);
        wp_enqueue_script('bsw-phase37-page-tools');
        wp_add_inline_script('bsw-phase37-page-tools', 'window.BSWPageTools=' . wp_json_encode(array('root' => esc_url_raw(rest_url('bigshor/v1/')), 'nonce' => wp_create_nonce('wp_rest'))) . ';');
        $css = '.bsw-page-tools{border:1px solid #c8ccd1;background:#f8f9fa;padding:10px 14px;margin:0 0 1.25rem;font-size:13px}.bsw-page-tools>strong{display:block;margin-bottom:6px}.bsw-page-tools ul{display:flex;flex-wrap:wrap;gap:8px 18px;list-style:none;margin:0;padding:0}.bsw-page-tools a{text-decoration:none}.bsw-page-information,.bsw-what-links-here{margin:1.5rem 0;padding:12px;border:1px solid #c8ccd1}.bsw-page-information table{width:100%;border-collapse:collapse}.bsw-page-information th,.bsw-page-information td{padding:6px 9px;border-bottom:1px solid #eaecf0;text-align:left}.bsw-recent-changes li{margin:.5rem 0}.bsw-recent-changes span{color:#54595d;font-size:12px;margin-left:6px}';
        wp_register_style('bsw-phase37-page-tools', false, array(), '29.2.0'); wp_enqueue_style('bsw-phase37-page-tools'); wp_add_inline_style('bsw-phase37-page-tools', $css);
    }
}
BSW_Phase37_Theme_Integration::init();
}
