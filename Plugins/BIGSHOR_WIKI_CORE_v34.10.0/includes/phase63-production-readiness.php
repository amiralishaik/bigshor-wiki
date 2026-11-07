<?php
/**
 * Phase 10 — Production readiness and release assurance.
 * Provides administrator-only runtime preflight, bounded citation hygiene
 * checks, integration inventory, regression smoke checks and a redacted
 * deployment manifest. It does not change publishing or deployment state.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase63_Production_Readiness')) {
final class BSW_Phase63_Production_Readiness {
    const VERSION = '34.3.0';
    const REPORT_OPTION = 'bsw_p63_last_preflight_report';
    const MAX_ARTICLES = 500;

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'), 99);
        add_action('admin_post_bsw_p63_run_preflight', array(__CLASS__, 'run_preflight'));
        add_action('admin_post_bsw_p63_export_manifest', array(__CLASS__, 'export_manifest'));
        add_action('admin_post_bsw_p63_run_citation_audit', array(__CLASS__, 'run_citation_audit'));
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    public static function register_routes() {
        register_rest_route('bigshor/v4', '/search', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'search'),
            'permission_callback' => '__return_true',
            'args' => array(
                'q' => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
                'language' => array('required' => false, 'sanitize_callback' => 'sanitize_text_field'),
                'page' => array('default' => 1, 'sanitize_callback' => 'absint'),
                'per_page' => array('default' => 10, 'sanitize_callback' => 'absint'),
            ),
        ));
    }

    public static function search($request) {
        $term = trim((string)$request->get_param('q'));
        $length = function_exists('mb_strlen') ? mb_strlen($term, 'UTF-8') : strlen($term);
        if ($length < 2 || $length > 100) return new WP_Error('bsw_search_query_invalid', 'Search query must contain 2–100 characters.', array('status'=>400));
        $page = max(1, absint($request->get_param('page')));
        $per_page = min(20, max(1, absint($request->get_param('per_page') ?: 10)));
        $language = sanitize_text_field((string)$request->get_param('language'));
        $args = array('post_type'=>'wiki_article','post_status'=>'publish','s'=>$term,'posts_per_page'=>min(60,$per_page*3),'paged'=>$page,'no_found_rows'=>true,'ignore_sticky_posts'=>true);
        if ($language !== '') $args['meta_query'] = array(array('key'=>'_bsw_p7_language','value'=>$language,'compare'=>'='));
        $query = new WP_Query($args);
        $needle = function_exists('mb_strtolower') ? mb_strtolower($term,'UTF-8') : strtolower($term);
        $items = array();
        foreach ($query->posts as $post) {
            $title = get_the_title($post);
            $excerpt = wp_strip_all_tags(get_the_excerpt($post));
            $content = wp_strip_all_tags($post->post_content);
            $title_l = function_exists('mb_strtolower') ? mb_strtolower($title,'UTF-8') : strtolower($title);
            $excerpt_l = function_exists('mb_strtolower') ? mb_strtolower($excerpt,'UTF-8') : strtolower($excerpt);
            $content_l = function_exists('mb_strtolower') ? mb_strtolower($content,'UTF-8') : strtolower($content);
            $score = 0;
            if ($title_l === $needle) $score += 30;
            elseif (strpos($title_l, $needle) === 0) $score += 20;
            elseif (strpos($title_l, $needle) !== false) $score += 10;
            if (strpos($excerpt_l, $needle) !== false) $score += 5;
            if (strpos($content_l, $needle) !== false) $score += 1;
            $items[] = array(
                'id'=>(int)$post->ID, 'title'=>$title, 'url'=>get_permalink($post),
                'excerpt'=>wp_trim_words($excerpt !== '' ? $excerpt : $content, 32),
                'language'=>(string)get_post_meta($post->ID,'_bsw_p7_language',true),
                'author'=>get_the_author_meta('display_name',$post->post_author),
                'modified'=>get_post_modified_time('c',true,$post), 'relevance'=>$score,
            );
        }
        usort($items, function($a,$b) { if ($a['relevance'] === $b['relevance']) return strcmp($b['modified'],$a['modified']); return $b['relevance'] <=> $a['relevance']; });
        return rest_ensure_response(array('query'=>$term,'language'=>$language,'page'=>$page,'per_page'=>$per_page,'results'=>array_slice($items,0,$per_page),'discovery_type'=>'multi_signal_weighted_lexical','ranking_signals'=>array('title_exact','title_prefix','title_match','excerpt_match','content_match','modified_time_tiebreaker'),'semantic_embeddings'=>false,'note'=>'Lexical relevance only; no AI or vector semantic ranking is performed.'));
    }

    private static function integration_status() {
        $peers = get_option('bsw_phase60_federation_peers', array());
        if (!is_array($peers)) $peers = array();
        $summary = array('total'=>count($peers),'healthy'=>0,'failed'=>0,'not_checked'=>0,'stale'=>0,'items'=>array());
        foreach ($peers as $id=>$peer) {
            $status = isset($peer['last_status']) ? sanitize_key($peer['last_status']) : 'not_checked';
            if ($status === 'ok') $summary['healthy']++;
            elseif ($status === 'not_checked' || $status === '') $summary['not_checked']++;
            else $summary['failed']++;
            $last = isset($peer['last_sync_gmt']) ? strtotime($peer['last_sync_gmt']) : false;
            $stale = !$last || $last < (time() - 30 * DAY_IN_SECONDS);
            if ($stale) $summary['stale']++;
            $summary['items'][] = array('id'=>sanitize_key($id),'name'=>sanitize_text_field(isset($peer['name'])?$peer['name']:$id),'status'=>$status,'last_sync'=>isset($peer['last_sync_gmt'])?(string)$peer['last_sync_gmt']:'','stale'=>$stale);
        }
        $ai = get_option('bsw_p5_ai_settings', array());
        $summary['ai_assistance_configured'] = is_array($ai) && !empty($ai['api_key']) && !empty($ai['endpoint']);
        return $summary;
    }

    public static function menu() {
        add_management_page('BIGSHOR Production Readiness', 'BIGSHOR Readiness', 'manage_options', 'bsw-phase63-readiness', array(__CLASS__, 'render'));
    }

    private static function check($id, $label, $status, $detail, $critical = false) {
        return array('id'=>sanitize_key($id), 'label'=>sanitize_text_field($label), 'status'=>in_array($status, array('pass','warning','fail'), true) ? $status : 'warning', 'detail'=>sanitize_text_field($detail), 'critical'=>(bool)$critical);
    }

    private static function version_at_least($actual, $required) {
        return version_compare((string)$actual, (string)$required, '>=');
    }

    public static function diagnostics() {
        global $wpdb;
        $checks = array();
        $checks[] = self::check('wp_version', 'WordPress version', self::version_at_least(get_bloginfo('version'), '6.4') ? 'pass' : 'fail', 'Detected WordPress ' . get_bloginfo('version') . '; minimum supported version is 6.4.', true);
        $checks[] = self::check('php_version', 'PHP version', self::version_at_least(PHP_VERSION, '7.4') ? 'pass' : 'fail', 'Detected PHP ' . PHP_VERSION . '; minimum supported version is 7.4.', true);
        $checks[] = self::check('https', 'HTTPS site URL', is_ssl() ? 'pass' : 'warning', is_ssl() ? 'The current request is using HTTPS.' : 'The current request is not HTTPS; verify the canonical site URL and reverse-proxy configuration.');
        $checks[] = self::check('core_loaded', 'BIGSHOR Core loaded', defined('BSW_CORE_PLUGIN_ACTIVE') && BSW_CORE_PLUGIN_ACTIVE ? 'pass' : 'fail', defined('BSW_CORE_PLUGIN_ACTIVE') ? 'Core bootstrap constant is present.' : 'Core bootstrap constant is missing.', true);
        $checks[] = self::check('core_version', 'Core release version', defined('BSW_CORE_PLUGIN_VERSION') && BSW_CORE_PLUGIN_VERSION === self::VERSION ? 'pass' : 'warning', 'Loaded Core version: ' . (defined('BSW_CORE_PLUGIN_VERSION') ? BSW_CORE_PLUGIN_VERSION : 'unknown') . '; expected 34.3.0.');
        $checks[] = self::check('schema_version', 'Database schema marker', get_option('bsw_core_schema_version') === (defined('BSW_CORE_PLUGIN_VERSION') ? BSW_CORE_PLUGIN_VERSION : '') ? 'pass' : 'warning', 'Schema marker: ' . (string)get_option('bsw_core_schema_version', 'not recorded') . '. Re-activate the plugin if the schema marker is outdated.');
        $checks[] = self::check('wiki_type', 'Wiki article content type', post_type_exists('wiki_article') ? 'pass' : 'fail', post_type_exists('wiki_article') ? 'wiki_article is registered.' : 'wiki_article is not registered.', true);
        $db_ok = isset($wpdb) && $wpdb->get_var('SELECT 1') == 1;
        $checks[] = self::check('db', 'Database connectivity', $db_ok ? 'pass' : 'fail', $db_ok ? 'Database answered a lightweight SELECT 1 query.' : 'Database connectivity check failed.', true);
        $pretty = (string)get_option('permalink_structure', '');
        $checks[] = self::check('permalinks', 'Permalink configuration', $pretty !== '' ? 'pass' : 'warning', $pretty !== '' ? 'Pretty permalinks are enabled.' : 'Plain permalinks are enabled; review REST and article URL requirements.');
        $rest_enabled = function_exists('rest_get_server') && function_exists('rest_url');
        $checks[] = self::check('rest_api', 'WordPress REST API', $rest_enabled ? 'pass' : 'fail', $rest_enabled ? 'REST API server functions are available.' : 'REST API functions are unavailable.', true);
        $route_keys = array();
        if ($rest_enabled) {
            $server = rest_get_server();
            if ($server && method_exists($server, 'get_routes')) $route_keys = array_keys($server->get_routes());
        }
        $required_routes = array('/bigshor/v1/knowledge-graph/(?P<id>\\d+)', '/bigshor/v2/exchange/articles', '/bigshor/v3/knowledge-search', '/bigshor/v4/search');
        $missing_routes = array();
        foreach ($required_routes as $route) { if (!in_array($route, $route_keys, true)) $missing_routes[] = $route; }
        $checks[] = self::check('bigshor_routes', 'BIGSHOR API route registration', !$missing_routes ? 'pass' : 'warning', !$missing_routes ? 'Knowledge graph, data exchange and knowledge search routes are registered.' : 'One or more expected BIGSHOR routes are not registered in this request: ' . implode(', ', $missing_routes) . '.');
        $checks[] = self::check('memory', 'PHP memory limit', self::memory_bytes(ini_get('memory_limit')) >= 128 * 1024 * 1024 ? 'pass' : 'warning', 'PHP memory_limit is ' . (string)ini_get('memory_limit') . '; 128M or higher is recommended.');
        $checks[] = self::check('uploads', 'Uploads directory writable', is_dir(wp_upload_dir(null, false)['basedir']) && wp_is_writable(wp_upload_dir(null, false)['basedir']) ? 'pass' : 'warning', 'WordPress uploads directory write access was checked.');
        $cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $cron_ok = !$cron_disabled || (defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON);
        $checks[] = self::check('cron_config', 'WordPress scheduled tasks', $cron_ok ? 'pass' : 'warning', $cron_disabled ? 'WP-Cron is disabled; confirm a real server cron invokes wp-cron.php.' : 'WP-Cron is enabled; confirm traffic or a server cron triggers scheduled events.');
        $phase_cron = wp_next_scheduled('bsw_phase61_daily_maintenance');
        $checks[] = self::check('maintenance_cron', 'BIGSHOR maintenance schedule', $phase_cron ? 'pass' : 'warning', $phase_cron ? 'The Phase 8 daily maintenance event is scheduled.' : 'The Phase 8 maintenance event is not currently scheduled.');
        $salts_ok = defined('AUTH_KEY') && AUTH_KEY && AUTH_KEY !== 'put your unique phrase here' && defined('SECURE_AUTH_KEY') && SECURE_AUTH_KEY && SECURE_AUTH_KEY !== 'put your unique phrase here';
        $checks[] = self::check('salts', 'WordPress authentication salts', $salts_ok ? 'pass' : 'warning', $salts_ok ? 'Authentication key constants are defined and do not use the standard placeholder.' : 'Review wp-config.php authentication keys. Secret values are never displayed.');
        $debug = defined('WP_DEBUG') && WP_DEBUG;
        $checks[] = self::check('debug', 'Production debug setting', !$debug ? 'pass' : 'warning', $debug ? 'WP_DEBUG is enabled. Disable it on production and use protected logging.' : 'WP_DEBUG is not enabled.');
        $env = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';
        $checks[] = self::check('environment', 'WordPress environment', 'pass', 'Environment type: ' . $env . '.');
        $checks[] = self::check('phase_modules', 'Phase 5–9 module classes', (class_exists('BSW_Phase58_Knowledge_Graph') && class_exists('BSW_Phase59_Ecosystem_Interoperability') && class_exists('BSW_Phase60_Federation') && class_exists('BSW_Phase61_Enterprise_Trust') && class_exists('BSW_Phase62_Global_Enterprise_Knowledge')) ? 'pass' : 'warning', 'Checks the presence of the latest knowledge graph, exchange, federation, trust and editorial infrastructure classes.');
        $theme = wp_get_theme();
        $checks[] = self::check('theme', 'Active theme', $theme && $theme->exists() ? 'pass' : 'warning', $theme && $theme->exists() ? 'Active theme: ' . $theme->get('Name') . ' (' . $theme->get_stylesheet() . ').' : 'The active theme could not be identified.');
        $checks[] = self::check('db_prefix', 'Database table prefix', isset($wpdb) && preg_match('/^[A-Za-z0-9_]+$/', (string)$wpdb->prefix) ? 'pass' : 'warning', 'Database prefix format checked; the actual prefix is not exposed in the report.');

        $passed = 0; $warnings = 0; $failed = 0; $critical_failed = 0;
        foreach ($checks as $item) {
            if ($item['status'] === 'pass') $passed++;
            elseif ($item['status'] === 'warning') $warnings++;
            else { $failed++; if ($item['critical']) $critical_failed++; }
        }
        $total = count($checks);
        return array('generated_at'=>gmdate('c'), 'site_url'=>home_url('/'), 'core_version'=>defined('BSW_CORE_PLUGIN_VERSION') ? BSW_CORE_PLUGIN_VERSION : 'unknown', 'environment'=>$env, 'summary'=>array('total'=>$total,'passed'=>$passed,'warnings'=>$warnings,'failed'=>$failed,'critical_failed'=>$critical_failed,'score'=>(int)round(($passed / max(1,$total))*100)), 'checks'=>$checks, 'release_gate'=>($critical_failed === 0 && $failed === 0) ? 'preflight_passed' : 'preflight_blocked', 'scope'=>'Runtime configuration and component smoke checks only; not a penetration test, load test, external uptime monitor or production certification.');
    }

    private static function memory_bytes($value) {
        $value = trim((string)$value);
        if ($value === '' || $value === '-1') return PHP_INT_MAX;
        $unit = strtolower(substr($value, -1));
        $number = (float)$value;
        if ($unit === 'g') $number *= 1024;
        if ($unit === 'g' || $unit === 'm') $number *= 1024;
        if ($unit === 'g' || $unit === 'm' || $unit === 'k') $number *= 1024;
        return (int)$number;
    }

    public static function citation_audit($limit = self::MAX_ARTICLES) {
        $query = new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>min(self::MAX_ARTICLES, max(1,(int)$limit)),'fields'=>'ids','no_found_rows'=>true,'orderby'=>'ID','order'=>'DESC'));
        $stats = array('articles_scanned'=>count($query->posts),'articles_with_sources'=>0,'source_urls'=>0,'duplicate_urls'=>0,'non_https_urls'=>0,'invalid_urls'=>0,'articles_without_sources'=>0,'findings'=>array());
        foreach ($query->posts as $post_id) {
            $raw = (string)get_post_meta($post_id, '_bsw_p62_sources', true);
            $urls = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw))));
            if (!$urls) { $stats['articles_without_sources']++; continue; }
            $stats['articles_with_sources']++;
            $seen = array(); $article_findings = array();
            foreach ($urls as $url) {
                $stats['source_urls']++;
                $normalized = strtolower(untrailingslashit(trim($url)));
                if (isset($seen[$normalized])) { $stats['duplicate_urls']++; $article_findings[] = 'duplicate_source_url'; }
                $seen[$normalized] = true;
                $parts = wp_parse_url($url);
                if (!$parts || empty($parts['host']) || !in_array(strtolower(isset($parts['scheme']) ? $parts['scheme'] : ''), array('http','https'), true) || !wp_http_validate_url($url)) {
                    $stats['invalid_urls']++; $article_findings[] = 'invalid_source_url';
                } elseif (strtolower($parts['scheme']) !== 'https') {
                    $stats['non_https_urls']++; $article_findings[] = 'non_https_source';
                }
            }
            if ($article_findings) $stats['findings'][] = array('post_id'=>(int)$post_id,'title'=>get_the_title($post_id),'findings'=>array_values(array_unique($article_findings)));
        }
        $stats['truncated'] = count($query->posts) >= min(self::MAX_ARTICLES, max(1,(int)$limit));
        $stats['note'] = 'This audit checks URL structure, duplicate entries and HTTPS usage. It does not establish source authority, independence, factual support, or whether a source actually contains the cited claim.';
        return $stats;
    }

    public static function run_preflight() {
        if (!current_user_can('manage_options')) wp_die('Permission denied.', 403);
        check_admin_referer('bsw_p63_run_preflight');
        $report = self::diagnostics();
        update_option(self::REPORT_OPTION, $report, false);
        wp_safe_redirect(add_query_arg('bsw_p63_result', 'preflight', admin_url('tools.php?page=bsw-phase63-readiness')));
        exit;
    }

    public static function run_citation_audit() {
        if (!current_user_can('manage_options')) wp_die('Permission denied.', 403);
        check_admin_referer('bsw_p63_run_citation_audit');
        update_option('bsw_p63_citation_audit', self::citation_audit(), false);
        wp_safe_redirect(add_query_arg('bsw_p63_result', 'citations', admin_url('tools.php?page=bsw-phase63-readiness')));
        exit;
    }

    public static function export_manifest() {
        if (!current_user_can('manage_options')) wp_die('Permission denied.', 403);
        check_admin_referer('bsw_p63_export_manifest');
        $theme = wp_get_theme();
        $manifest = array(
            'product'=>'BIGSHOR WIKI', 'release'=>defined('BSW_CORE_PLUGIN_VERSION') ? BSW_CORE_PLUGIN_VERSION : 'unknown',
            'generated_at'=>gmdate('c'), 'wordpress'=>get_bloginfo('version'), 'php'=>PHP_VERSION,
            'environment'=>function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production',
            'active_theme'=>array('name'=>$theme->get('Name'),'stylesheet'=>$theme->get_stylesheet(),'version'=>$theme->get('Version')),
            'site_url'=>home_url('/'), 'plugin_bootstrap'=>defined('BSW_CORE_PLUGIN_ACTIVE') && BSW_CORE_PLUGIN_ACTIVE,
            'manifest_type'=>'deployment_inventory_only',
            'warning'=>'Contains deployment metadata, not secrets. Review the site URL and environment before sharing.'
        );
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="bigshor-wiki-deployment-manifest-' . gmdate('Ymd-His') . '.json"');
        echo wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function render() {
        if (!current_user_can('manage_options')) return;
        $report = get_option(self::REPORT_OPTION, array());
        $citations = get_option('bsw_p63_citation_audit', array());
        echo '<div class="wrap"><h1>BIGSHOR Production Readiness</h1><p>Run a read-only deployment preflight, bounded regression smoke checks and citation hygiene review before staging or production deployment.</p>';
        if (isset($_GET['bsw_p63_result']) && sanitize_key(wp_unslash($_GET['bsw_p63_result'])) === 'preflight') echo '<div class="notice notice-success"><p>Preflight report generated. Review every warning and failure before deployment.</p></div>';
        if (isset($_GET['bsw_p63_result']) && sanitize_key(wp_unslash($_GET['bsw_p63_result'])) === 'citations') echo '<div class="notice notice-success"><p>Citation hygiene audit completed.</p></div>';
        echo '<h2>Deployment preflight and regression smoke checks</h2><p>Checks WordPress/PHP compatibility, database response, REST availability, wiki content type, schema marker, key modules, cron configuration, writable uploads and production configuration.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        echo '<input type="hidden" name="action" value="bsw_p63_run_preflight">'; wp_nonce_field('bsw_p63_run_preflight'); submit_button('Run production preflight', 'primary', 'submit', false); echo '</form>';
        if (is_array($report) && !empty($report['summary'])) {
            $s = $report['summary'];
            echo '<h3>Latest report — '.esc_html($report['generated_at']).'</h3><p><strong>Readiness score:</strong> '.(int)$s['score'].'% &nbsp; <strong>Passed:</strong> '.(int)$s['passed'].' &nbsp; <strong>Warnings:</strong> '.(int)$s['warnings'].' &nbsp; <strong>Failures:</strong> '.(int)$s['failed'].'</p>';
            echo '<p><strong>Release gate:</strong> '.esc_html($report['release_gate']).'</p><table class="widefat striped"><thead><tr><th>Check</th><th>Status</th><th>Details</th></tr></thead><tbody>';
            foreach ($report['checks'] as $item) { echo '<tr><td>'.esc_html($item['label']).($item['critical']?' <strong>(critical)</strong>':'').'</td><td>'.esc_html(strtoupper($item['status'])).'</td><td>'.esc_html($item['detail']).'</td></tr>'; }
            echo '</tbody></table><p class="description">'.esc_html($report['scope']).'</p>';
        }
        echo '<hr><h2>Citation hygiene audit</h2><p>Scans up to '.self::MAX_ARTICLES.' recently updated published wiki articles for missing source lists, duplicate URLs, malformed URLs and non-HTTPS sources. It does not fetch sources or score credibility.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw_p63_run_citation_audit">'; wp_nonce_field('bsw_p63_run_citation_audit'); submit_button('Run citation hygiene audit', 'secondary', 'submit', false); echo '</form>';
        if (is_array($citations) && isset($citations['articles_scanned'])) {
            echo '<h3>Latest citation audit</h3><p>Articles scanned: '.(int)$citations['articles_scanned'].' · Articles with sources: '.(int)$citations['articles_with_sources'].' · No sources: '.(int)$citations['articles_without_sources'].' · URLs: '.(int)$citations['source_urls'].' · Duplicates: '.(int)$citations['duplicate_urls'].' · Non-HTTPS: '.(int)$citations['non_https_urls'].' · Invalid: '.(int)$citations['invalid_urls'].'</p>';
            if (!empty($citations['findings'])) { echo '<table class="widefat striped"><thead><tr><th>Article</th><th>Findings</th></tr></thead><tbody>'; foreach (array_slice($citations['findings'],0,100) as $finding) echo '<tr><td><a href="'.esc_url(get_edit_post_link($finding['post_id'])).'">'.esc_html($finding['title']).'</a></td><td>'.esc_html(implode(', ', $finding['findings'])).'</td></tr>'; echo '</tbody></table>'; }
            echo '<p class="description">'.esc_html($citations['note']).(!empty($citations['truncated'])?' The scan reached its article limit; run again after addressing findings.':'').'</p>';
        }
        $integrations = self::integration_status();
        echo '<hr><h2>Integration monitoring inventory</h2><p>Federation status is read from the last recorded peer sync; this screen does not send network probes. Peers with no successful sync in 30 days are marked stale. AI configuration is reported as configured/not configured only; secret values are never displayed.</p>';
        echo '<p>Peers: '.(int)$integrations['total'].' · Healthy last sync: '.(int)$integrations['healthy'].' · Failed: '.(int)$integrations['failed'].' · Not checked: '.(int)$integrations['not_checked'].' · Stale: '.(int)$integrations['stale'].' · AI editorial assistance: '.($integrations['ai_assistance_configured']?'configured':'not configured').'</p>';
        if (!empty($integrations['items'])) { echo '<table class="widefat striped"><thead><tr><th>Peer</th><th>Status</th><th>Last sync (UTC)</th><th>Freshness</th></tr></thead><tbody>'; foreach ($integrations['items'] as $peer) echo '<tr><td>'.esc_html($peer['name']).'</td><td>'.esc_html($peer['status']).'</td><td>'.esc_html($peer['last_sync']?:'Never').'</td><td>'.($peer['stale']?'STALE':'Recent').'</td></tr>'; echo '</tbody></table>'; }
        echo '<p><strong>Enhanced search API:</strong> <code>'.esc_html(rest_url('bigshor/v4/search')).'?q=term&amp;language=en</code>. This is multi-signal weighted lexical ranking, not vector/AI semantic search.</p>';
        echo '<hr><h2>Deployment manifest</h2><p>Export a redacted inventory of release, runtime, environment and active theme metadata. No credentials, salts, API keys or database connection details are included.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw_p63_export_manifest">'; wp_nonce_field('bsw_p63_export_manifest'); submit_button('Download deployment manifest (JSON)', 'secondary', 'submit', false); echo '</form>';
        echo '<hr><h2>Production acceptance still required</h2><ul><li>Install this release on a staging clone and run the WordPress Site Health checks.</li><li>Test representative administrator, editor, reviewer and contributor accounts.</li><li>Exercise REST API, federation and data exchange against a separate test wiki.</li><li>Run backup restoration, load, browser, accessibility and penetration tests.</li><li>Verify real server cron, off-site backups, TLS termination, WAF and monitoring.</li></ul><p><strong>This dashboard is a preflight aid, not a production certification or external uptime monitor.</strong></p></div>';
    }
}
BSW_Phase63_Production_Readiness::init();
}
