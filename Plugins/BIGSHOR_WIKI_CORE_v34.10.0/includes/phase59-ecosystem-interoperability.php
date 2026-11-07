<?php
/**
 * BIGSHOR Wiki Phase 6 completion: interoperability, controlled import/export and API v2.
 * All imported articles are created as pending editorial drafts; no remote content is auto-published.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase59_Ecosystem_Interoperability')) {
final class BSW_Phase59_Ecosystem_Interoperability {
    const VERSION = '1.0.0';
    const MAX_IMPORT_BYTES = 2097152;
    const MAX_IMPORT_ROWS = 100;
    const API_TRANSIENT = 'bsw_p6_exchange_';

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('admin_post_bsw_p6_exchange_import', array(__CLASS__, 'handle_import'));
        add_action('admin_post_bsw_p6_exchange_export', array(__CLASS__, 'handle_export'));
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    public static function admin_menu() {
        add_management_page('BIGSHOR Data Exchange', 'BIGSHOR Data Exchange', 'manage_options', 'bsw-data-exchange', array(__CLASS__, 'render_admin'));
    }

    public static function render_admin() {
        if (!current_user_can('manage_options')) return;
        $result = isset($_GET['bsw_exchange_result']) ? sanitize_key(wp_unslash($_GET['bsw_exchange_result'])) : '';
        $count = isset($_GET['count']) ? absint($_GET['count']) : 0;
        echo '<div class="wrap"><h1>BIGSHOR Data Exchange</h1><p>Exchange published wiki articles using the versioned BIGSHOR JSON format. Imports are validated and saved as pending articles for editorial review. Existing content is not overwritten.</p>';
        if ($result === 'imported') echo '<div class="notice notice-success"><p>Import processed. '.esc_html((string)$count).' article record(s) were created as pending editorial drafts.</p></div>';
        if ($result === 'preview') echo '<div class="notice notice-info"><p>Dry run passed. '.esc_html((string)$count).' article record(s) are valid and ready for a reviewed import.</p></div>';
        if ($result === 'error') echo '<div class="notice notice-error"><p>The import could not be processed. Check the JSON format, record limits and required fields.</p></div>';
        echo '<h2>Export</h2><p>Export published wiki articles with stable IDs, titles, canonical URLs, summaries, content, language metadata and update timestamps.</p>';
        echo '<p><a class="button button-primary" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=bsw_p6_exchange_export&format=json'),'bsw_p6_exchange_export')).'">Download JSON export</a> ';
        echo '<a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=bsw_p6_exchange_export&format=csv'),'bsw_p6_exchange_export')).'">Download CSV export</a></p>';
        echo '<hr><h2>Import JSON</h2><p>Use a BIGSHOR exchange JSON file or paste its contents below. The import is limited to 100 records and 2 MB per request. Imported articles are never published automatically.</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('bsw_p6_exchange_import');
        echo '<input type="hidden" name="action" value="bsw_p6_exchange_import"><textarea name="bsw_exchange_json" class="large-text code" rows="14" required placeholder="Paste the JSON exchange document here"></textarea>';
        echo '<p><label><input type="checkbox" name="dry_run" value="1"> Validate only (dry run; do not create drafts)</label></p>';
        submit_button('Validate and import as pending drafts');
        echo '</form><h2>API v2</h2><p>Authenticated, read-only endpoints are available at <code>/wp-json/bigshor/v2/exchange/articles</code> and <code>/wp-json/bigshor/v2/exchange/articles/{id}</code>. Send the configured key in the <code>X-BSW-API-Key</code> header. Use pagination parameters <code>page</code> and <code>per_page</code> (maximum 50).</p>';
        echo '<p>For external systems, map their own identifiers into the optional <code>external_id</code> field. Imports preserve that identifier as provenance metadata.</p></div>';
    }

    private static function valid_document($raw) {
        if (!is_string($raw) || strlen($raw) > self::MAX_IMPORT_BYTES) return new WP_Error('bsw_exchange_size', 'Import exceeds the 2 MB limit.');
        $doc = json_decode($raw, true);
        if (!is_array($doc) || json_last_error() !== JSON_ERROR_NONE || empty($doc['format']) || $doc['format'] !== 'bigshor-wiki-exchange' || empty($doc['version']) || !isset($doc['articles']) || !is_array($doc['articles'])) return new WP_Error('bsw_exchange_format', 'Invalid BIGSHOR exchange document.');
        if (count($doc['articles']) > self::MAX_IMPORT_ROWS) return new WP_Error('bsw_exchange_count', 'Import contains more than 100 article records.');
        $clean = array();
        foreach ($doc['articles'] as $row) {
            if (!is_array($row)) return new WP_Error('bsw_exchange_record', 'An article record is malformed.');
            $title = isset($row['title']) ? sanitize_text_field($row['title']) : '';
            $content = isset($row['content']) ? wp_kses_post($row['content']) : '';
            if ($title === '' || strlen($title) > 250 || trim(wp_strip_all_tags($content)) === '' || strlen($content) > 250000) return new WP_Error('bsw_exchange_fields', 'Each record needs a title and content within the allowed limits.');
            $external_id = isset($row['external_id']) ? sanitize_text_field($row['external_id']) : '';
            if (strlen($external_id) > 190) return new WP_Error('bsw_exchange_external_id', 'An external identifier is too long.');
            $clean[] = array(
                'title' => $title,
                'content' => $content,
                'excerpt' => isset($row['excerpt']) ? sanitize_textarea_field($row['excerpt']) : '',
                'language' => isset($row['language']) ? sanitize_key($row['language']) : '',
                'source_url' => isset($row['url']) ? esc_url_raw($row['url']) : '',
                'external_id' => $external_id,
            );
        }
        return $clean;
    }

    public static function handle_import() {
        if (!current_user_can('manage_options')) wp_die('Not authorised.', 403);
        check_admin_referer('bsw_p6_exchange_import');
        $raw = isset($_POST['bsw_exchange_json']) ? wp_unslash($_POST['bsw_exchange_json']) : '';
        $rows = self::valid_document($raw);
        if (is_wp_error($rows)) self::redirect_result('error', 0);
        if (!empty($_POST['dry_run'])) self::redirect_result('preview', count($rows));
        $created = 0;
        foreach ($rows as $row) {
            if ($row['external_id']) {
                $existing = get_posts(array('post_type'=>'wiki_article','post_status'=>array('publish','pending','draft','private'),'numberposts'=>1,'fields'=>'ids','meta_key'=>'_bsw_p6_external_id','meta_value'=>$row['external_id']));
                if ($existing) continue;
            }
            $post_id = wp_insert_post(array('post_type'=>'wiki_article','post_status'=>'pending','post_title'=>$row['title'],'post_content'=>$row['content'],'post_excerpt'=>$row['excerpt'],'post_author'=>get_current_user_id()), true);
            if (is_wp_error($post_id)) continue;
            if ($row['external_id']) update_post_meta($post_id, '_bsw_p6_external_id', $row['external_id']);
            if ($row['source_url']) update_post_meta($post_id, '_bsw_p6_import_source_url', $row['source_url']);
            if ($row['language']) update_post_meta($post_id, '_bsw_language_code', $row['language']);
            update_post_meta($post_id, '_bsw_p6_imported_at_gmt', gmdate('c'));
            update_post_meta($post_id, '_bsw_p6_import_review_required', 1);
            $created++;
        }
        self::redirect_result('imported', $created);
    }

    private static function redirect_result($result, $count) {
        wp_safe_redirect(add_query_arg(array('page'=>'bsw-data-exchange','bsw_exchange_result'=>$result,'count'=>absint($count)), admin_url('tools.php')));
        exit;
    }

    private static function export_rows($limit = 0) {
        $args = array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>$limit ? min(5000,absint($limit)) : 5000,'orderby'=>'ID','order'=>'ASC');
        $posts = get_posts($args); $rows = array();
        foreach ($posts as $p) {
            $rows[] = array(
                'id'=>(int)$p->ID,
                'external_id'=>(string)get_post_meta($p->ID,'_bsw_p6_external_id',true),
                'title'=>get_the_title($p),
                'url'=>get_permalink($p),
                'excerpt'=>get_the_excerpt($p),
                'content'=>apply_filters('the_content',$p->post_content),
                'language'=>(string)get_post_meta($p->ID,'_bsw_language_code',true),
                'modified_gmt'=>$p->post_modified_gmt,
            );
        }
        return $rows;
    }

    public static function handle_export() {
        if (!current_user_can('manage_options')) wp_die('Not authorised.', 403);
        check_admin_referer('bsw_p6_exchange_export');
        $format = isset($_GET['format']) ? sanitize_key(wp_unslash($_GET['format'])) : 'json';
        $rows = self::export_rows();
        nocache_headers();
        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="bigshor-wiki-export-'.gmdate('Ymd-His').'.csv"');
            $out = fopen('php://output','w');
            fputcsv($out,array('id','external_id','title','url','excerpt','content','language','modified_gmt'));
            foreach ($rows as $row) {
                foreach ($row as $key=>$value) {
                    if (is_string($value) && preg_match('/^[\s]*[=+\-@]/u', $value)) $row[$key] = "'" . $value;
                }
                fputcsv($out,$row);
            }
            fclose($out); exit;
        }
        $doc = array('format'=>'bigshor-wiki-exchange','version'=>self::VERSION,'generated_at_gmt'=>gmdate('c'),'articles'=>$rows);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="bigshor-wiki-export-'.gmdate('Ymd-His').'.json"');
        echo wp_json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); exit;
    }

    public static function register_routes() {
        register_rest_route('bigshor/v2','/exchange/articles',array(
            'methods'=>WP_REST_Server::READABLE,
            'callback'=>array(__CLASS__,'api_articles'),
            'permission_callback'=>array('BSW_Phase6_Ecosystem','api_permission'),
            'args'=>array('page'=>array('default'=>1,'sanitize_callback'=>'absint'),'per_page'=>array('default'=>20,'sanitize_callback'=>'absint')),
        ));
        register_rest_route('bigshor/v2','/exchange/articles/(?P<id>\d+)',array(
            'methods'=>WP_REST_Server::READABLE,
            'callback'=>array(__CLASS__,'api_article'),
            'permission_callback'=>array('BSW_Phase6_Ecosystem','api_permission'),
            'args'=>array('id'=>array('sanitize_callback'=>'absint')),
        ));
    }

    public static function api_articles($request) {
        $per_page = min(50,max(1,absint($request->get_param('per_page')) ?: 20));
        $page = max(1,absint($request->get_param('page')) ?: 1);
        $q = new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>$per_page,'paged'=>$page,'orderby'=>'modified','order'=>'DESC'));
        $items = array(); foreach ($q->posts as $p) $items[] = self::api_item($p,false);
        return rest_ensure_response(array('api_version'=>'2','format'=>'bigshor-wiki-exchange','items'=>$items,'pagination'=>array('page'=>$page,'per_page'=>$per_page,'total'=>(int)$q->found_posts,'pages'=>(int)$q->max_num_pages)));
    }

    public static function api_article($request) {
        $id = absint($request['id']); $p = get_post($id);
        if (!$p || $p->post_type !== 'wiki_article' || $p->post_status !== 'publish') return new WP_Error('bsw_exchange_not_found','Published wiki article not found.',array('status'=>404));
        return rest_ensure_response(self::api_item($p,true));
    }

    private static function api_item($p,$full) {
        $item = array('id'=>(int)$p->ID,'external_id'=>(string)get_post_meta($p->ID,'_bsw_p6_external_id',true),'title'=>get_the_title($p),'url'=>get_permalink($p),'excerpt'=>get_the_excerpt($p),'language'=>(string)get_post_meta($p->ID,'_bsw_language_code',true),'modified_gmt'=>$p->post_modified_gmt);
        if ($full) $item['content'] = apply_filters('the_content',$p->post_content);
        return $item;
    }
}
BSW_Phase59_Ecosystem_Interoperability::init();
}
