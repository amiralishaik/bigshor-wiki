<?php
/**
 * BIGSHOR Wikipedia Importer.
 * Imports selected Wikipedia revisions as unpublished BIGSHOR Wiki drafts.
 * All imported material remains subject to human editorial review.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('BSW_Wikipedia_Importer')) {
class BSW_Wikipedia_Importer {
    const VERSION = '1.0.0';
    const NONCE_ACTION = 'bsw_wikipedia_import';
    const META_SOURCE = '_bsw_wikipedia_source';
    const META_PAGE_ID = '_bsw_wikipedia_page_id';
    const META_REVISION = '_bsw_wikipedia_revision_id';
    const META_IMPORTED = '_bsw_wikipedia_imported_at';
    const META_LICENSE = '_bsw_wikipedia_license';

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_post_bsw_wikipedia_import', array(__CLASS__, 'handle_import'));
        add_action('add_meta_boxes_wiki_article', array(__CLASS__, 'add_review_metabox'));
        add_filter('post_row_actions', array(__CLASS__, 'row_action'), 10, 2);
    }

    public static function menu() {
        add_submenu_page('edit.php?post_type=wiki_article', 'Wikipedia Importer', 'Wikipedia Importer', 'edit_others_posts', 'bsw-wikipedia-importer', array(__CLASS__, 'page'));
    }

    private static function language($value) {
        $value = strtolower(sanitize_text_field((string) $value));
        return in_array($value, array('en','ar','hi','ur','fr','de','es'), true) ? $value : 'en';
    }

    private static function api($language, $params) {
        $language = self::language($language);
        $url = 'https://' . $language . '.wikipedia.org/w/api.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $response = wp_remote_get($url, array(
            'timeout' => 20,
            'redirection' => 2,
            'headers' => array('Accept' => 'application/json', 'User-Agent' => 'BIGSHOR-Wiki/27.2.0 (Wikipedia importer; ' . home_url('/') . ')'),
        ));
        if (is_wp_error($response)) return $response;
        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status !== 200) return new WP_Error('bsw_wikipedia_http', 'Wikipedia returned HTTP ' . $status . '. Please try again later.');
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body)) return new WP_Error('bsw_wikipedia_json', 'Wikipedia returned an unreadable response.');
        if (!empty($body['error']['info'])) return new WP_Error('bsw_wikipedia_api', sanitize_text_field($body['error']['info']));
        return $body;
    }

    private static function search($query, $language) {
        $key = 'bsw_wiki_search_' . md5($language . '|' . $query);
        $cached = get_transient($key);
        if (is_array($cached)) return $cached;
        $result = self::api($language, array(
            'action' => 'query', 'list' => 'search', 'srsearch' => $query,
            'srlimit' => 10, 'srnamespace' => 0, 'format' => 'json', 'formatversion' => 2,
        ));
        if (is_wp_error($result)) return $result;
        $items = isset($result['query']['search']) && is_array($result['query']['search']) ? $result['query']['search'] : array();
        set_transient($key, $items, 5 * MINUTE_IN_SECONDS);
        return $items;
    }

    public static function page() {
        if (!current_user_can('edit_others_posts')) wp_die(esc_html__('You do not have permission to access this page.', 'bigshor-wiki-core'));
        $query = isset($_GET['bsw_q']) ? sanitize_text_field(wp_unslash($_GET['bsw_q'])) : '';
        $language = isset($_GET['bsw_lang']) ? self::language(wp_unslash($_GET['bsw_lang'])) : 'en';
        echo '<div class="wrap"><h1>BIGSHOR Wikipedia Importer</h1>';
        echo '<p>Search Wikipedia and import a selected revision into BIGSHOR as an <strong>unpublished draft</strong>. Every import requires editorial review before publication.</p>';
        echo '<div class="notice notice-info"><p><strong>Licensing:</strong> Wikipedia text is commonly available under CC BY-SA 4.0, but page-specific notices, embedded third-party material and exceptions can differ. Editors must verify the exact source-page licence before publication. Imported text is attributed to Wikipedia, linked to its source and revision, and marked as adapted. Review each image separately; this importer does not copy images.</p></div>';
        echo '<form method="get" action=""><input type="hidden" name="post_type" value="wiki_article"><input type="hidden" name="page" value="bsw-wikipedia-importer">';
        echo '<p><label for="bsw_q"><strong>Search Wikipedia</strong></label><br><input id="bsw_q" name="bsw_q" type="search" class="regular-text" value="' . esc_attr($query) . '" required> ';
        echo '<label for="bsw_lang">Language edition </label><select id="bsw_lang" name="bsw_lang">';
        foreach (array('en'=>'English','ar'=>'Arabic','hi'=>'Hindi','ur'=>'Urdu','fr'=>'French','de'=>'German','es'=>'Spanish') as $code=>$label) echo '<option value="' . esc_attr($code) . '" ' . selected($language, $code, false) . '>' . esc_html($label) . '</option>';
        echo '</select> <button class="button button-primary">Search</button></p></form>';
        if ($query !== '') {
            $results = self::search($query, $language);
            if (is_wp_error($results)) echo '<div class="notice notice-error"><p>' . esc_html($results->get_error_message()) . '</p></div>';
            elseif (!$results) echo '<p>No matching Wikipedia articles found.</p>';
            else {
                echo '<h2>Search results</h2><table class="widefat striped"><thead><tr><th>Article</th><th>Extract</th><th>Action</th></tr></thead><tbody>';
                foreach ($results as $item) {
                    $title = isset($item['title']) ? $item['title'] : '';
                    $pageid = isset($item['pageid']) ? absint($item['pageid']) : 0;
                    $url = 'https://' . $language . '.wikipedia.org/wiki/' . rawurlencode(str_replace(' ', '_', $title));
                    $excerpt = isset($item['snippet']) ? wp_strip_all_tags($item['snippet']) : '';
                    echo '<tr><td><strong>' . esc_html($title) . '</strong><br><a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">View on Wikipedia</a></td><td>' . esc_html(wp_trim_words($excerpt, 35)) . '</td><td>';
                    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                    wp_nonce_field(self::NONCE_ACTION, '_bsw_nonce');
                    echo '<input type="hidden" name="action" value="bsw_wikipedia_import"><input type="hidden" name="pageid" value="' . esc_attr($pageid) . '"><input type="hidden" name="title" value="' . esc_attr($title) . '"><input type="hidden" name="language" value="' . esc_attr($language) . '">';
                    echo '<button class="button button-primary" ' . disabled($pageid, 0, false) . '>Import as draft</button></form></td></tr>';
                }
                echo '</tbody></table>';
            }
        }
        echo '<hr><h2>Editorial workflow</h2><ol><li>Import creates a draft, never a published article.</li><li>Editors check accuracy, relevance, sources, neutrality and licensing.</li><li>Rewrite or supplement content with independent reliable sources; Wikipedia references are leads, not automatic verification.</li><li>Keep the attribution and licence notice for reused text, then submit for normal BIGSHOR editorial approval.</li><li>Publish only after the editor confirms the review checklist.</li></ol>';
        echo '<p><a href="' . esc_url(admin_url('edit.php?post_type=wiki_article&post_status=draft')) . '">Review imported and other draft articles</a></p></div>';
    }

    public static function handle_import() {
        if (!current_user_can('edit_others_posts')) wp_die('Permission denied.');
        check_admin_referer(self::NONCE_ACTION, '_bsw_nonce');
        $pageid = isset($_POST['pageid']) ? absint($_POST['pageid']) : 0;
        $language = isset($_POST['language']) ? self::language(wp_unslash($_POST['language'])) : 'en';
        if (!$pageid) self::redirect_error('Invalid Wikipedia page identifier.');

        $revision = self::api($language, array(
            'action'=>'query', 'pageids'=>$pageid, 'prop'=>'revisions|info', 'rvprop'=>'ids|timestamp',
            'rvlimit'=>1, 'inprop'=>'url', 'format'=>'json', 'formatversion'=>2,
        ));
        if (is_wp_error($revision)) self::redirect_error($revision->get_error_message());
        $page = isset($revision['query']['pages'][0]) ? $revision['query']['pages'][0] : array();
        if (!$page || !empty($page['missing'])) self::redirect_error('The Wikipedia article could not be found.');
        $revs = isset($page['revisions'][0]) ? $page['revisions'][0] : array();
        $revid = isset($revs['revid']) ? absint($revs['revid']) : 0;
        if (!$revid) self::redirect_error('Wikipedia did not return a revision identifier.');

        $existing = get_posts(array('post_type'=>'wiki_article','post_status'=>'any','numberposts'=>1,'fields'=>'ids','meta_query'=>array(
            'relation'=>'OR', array('key'=>self::META_PAGE_ID,'value'=>$pageid), array('key'=>self::META_REVISION,'value'=>$revid),
        )));
        if ($existing) self::redirect_error('Duplicate prevented: this Wikipedia page or revision is already imported as BIGSHOR article #' . absint($existing[0]) . '.');

        $parsed = self::api($language, array('action'=>'parse','oldid'=>$revid,'prop'=>'text','disableeditsection'=>1,'format'=>'json','formatversion'=>2));
        if (is_wp_error($parsed)) self::redirect_error($parsed->get_error_message());
        $html = isset($parsed['parse']['text']) ? $parsed['parse']['text'] : '';
        if (!is_string($html) || trim($html) === '') self::redirect_error('Wikipedia returned no article text for this revision.');
        $content = wp_kses_post($html);
        $title = isset($page['title']) ? sanitize_text_field($page['title']) : sanitize_text_field(wp_unslash($_POST['title'] ?? 'Wikipedia import'));
        $source_url = 'https://' . $language . '.wikipedia.org/w/index.php?title=' . rawurlencode(str_replace(' ', '_', $title)) . '&oldid=' . $revid;
        $article_url = 'https://' . $language . '.wikipedia.org/wiki/' . rawurlencode(str_replace(' ', '_', $title));
        $attribution = '<hr><section class="bsw-wikipedia-attribution"><h2>Source attribution and licence</h2><p>This article includes text imported from <a href="' . esc_url($article_url) . '" rel="nofollow noopener">Wikipedia: ' . esc_html($title) . '</a>, revision <a href="' . esc_url($source_url) . '" rel="nofollow noopener">' . esc_html((string)$revid) . '</a>. Wikipedia text is commonly available under the <a href="https://creativecommons.org/licenses/by-sa/4.0/" rel="license noopener">Creative Commons Attribution-ShareAlike 4.0 International licence (CC BY-SA 4.0)</a>; the editor must verify the exact licence and any exceptions on this source page before publication. BIGSHOR modifications must be identified and licensed compatibly where required.</p><p>Imported on ' . esc_html(wp_date('Y-m-d H:i:s')) . '. This draft has not been independently verified. Wikipedia references must be checked at their original sources before publication. Images and other media are not included in this import and require separate licence review.</p></section>';
        $post_id = wp_insert_post(array(
            'post_type'=>'wiki_article', 'post_status'=>'draft', 'post_title'=>$title,
            'post_content'=>$content . $attribution, 'post_excerpt'=>'Imported from Wikipedia; awaiting BIGSHOR editorial review.',
        ), true);
        if (is_wp_error($post_id)) self::redirect_error($post_id->get_error_message());
        update_post_meta($post_id, self::META_SOURCE, esc_url_raw($article_url));
        update_post_meta($post_id, self::META_PAGE_ID, $pageid);
        update_post_meta($post_id, self::META_REVISION, $revid);
        update_post_meta($post_id, self::META_IMPORTED, current_time('mysql'));
        update_post_meta($post_id, self::META_LICENSE, 'Verify source-page licence; commonly CC BY-SA 4.0');
        update_post_meta($post_id, '_bsw_editorial_review_required', '1');
        update_post_meta($post_id, '_bsw_editorial_review_status', 'pending');
        wp_safe_redirect(add_query_arg(array('post'=>$post_id,'action'=>'edit','bsw_wiki_imported'=>'1'), admin_url('post.php')));
        exit;
    }

    private static function redirect_error($message) {
        $url = add_query_arg(array('post_type'=>'wiki_article','page'=>'bsw-wikipedia-importer','bsw_import_error'=>rawurlencode(wp_strip_all_tags($message))), admin_url('edit.php'));
        wp_safe_redirect($url);
        exit;
    }

    public static function add_review_metabox() {
        add_meta_box('bsw-wikipedia-review', 'Wikipedia Import — Editorial Review', array(__CLASS__, 'review_metabox'), 'wiki_article', 'side', 'high');
    }

    public static function review_metabox($post) {
        $source = get_post_meta($post->ID, self::META_SOURCE, true);
        if (!$source) { echo '<p>This article was not imported through the BIGSHOR Wikipedia Importer.</p>'; return; }
        $revision = get_post_meta($post->ID, self::META_REVISION, true);
        echo '<p><strong>Status:</strong> ' . esc_html(get_post_meta($post->ID, '_bsw_editorial_review_status', true) ?: 'pending') . '</p>';
        echo '<p><strong>Source:</strong> <a href="' . esc_url($source) . '" target="_blank" rel="noopener noreferrer">Wikipedia article</a></p>';
        echo '<p><strong>Revision ID:</strong> ' . esc_html($revision) . '</p>';
        echo '<p><strong>Licence:</strong> ' . esc_html(get_post_meta($post->ID, self::META_LICENSE, true)) . '</p>';
        echo '<p><strong>Before publishing, confirm:</strong></p><ul><li>Facts checked against original reliable sources</li><li>Neutrality and relevance reviewed</li><li>Attribution and licence retained</li><li>Adaptations clearly identified</li><li>Images independently licence-checked</li><li>BIGSHOR editorial approval completed</li></ul>';
        echo '<p><em>Imported content remains a draft until an authorised editor publishes it.</em></p>';
    }

    public static function row_action($actions, $post) {
        if ($post instanceof WP_Post && $post->post_type === 'wiki_article' && get_post_meta($post->ID, self::META_SOURCE, true)) {
            $actions['bsw_wikipedia_source'] = '<a href="' . esc_url(get_post_meta($post->ID, self::META_SOURCE, true)) . '" target="_blank" rel="noopener noreferrer">Wikipedia source</a>';
        }
        return $actions;
    }
}
BSW_Wikipedia_Importer::init();

add_action('admin_notices', function () {
    if (!current_user_can('edit_others_posts')) return;
    if (!empty($_GET['bsw_import_error'])) echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['bsw_import_error']))) . '</p></div>';
    if (!empty($_GET['bsw_wiki_imported'])) echo '<div class="notice notice-success is-dismissible"><p>Wikipedia article imported as a draft. Complete the editorial review before publication.</p></div>';
});
}
