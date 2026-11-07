<?php
/** BIGSHOR WIKI Dashboard — Diggama-inspired branded workspace. */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'bsw_modern_dashboard_register', 99);
function bsw_modern_dashboard_register() {
    remove_menu_page('bsw-control-centre');
    add_menu_page('BIGSHOR WIKI', 'BIGSHOR WIKI', 'read', 'bsw-control-centre', 'bsw_modern_dashboard_render', 'dashicons-book-alt', 2);
    add_submenu_page('bsw-control-centre', 'Dashboard', 'Dashboard', 'read', 'bsw-control-centre', 'bsw_modern_dashboard_render');
}

function bsw_dashboard_metrics_table() {
    global $wpdb;
    return $wpdb->prefix . 'bsw_dashboard_metrics';
}
function bsw_dashboard_metrics_install() {
    global $wpdb;
    $version = '1';
    if (get_option('bsw_dashboard_metrics_schema') === $version) return;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = bsw_dashboard_metrics_table();
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
        metric_date date NOT NULL,
        metric varchar(24) NOT NULL,
        object_id bigint(20) unsigned NOT NULL DEFAULT 0,
        metric_count bigint(20) unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY  (metric_date, metric, object_id),
        KEY metric_lookup (metric, metric_date),
        KEY object_lookup (object_id, metric)
    ) {$charset};";
    dbDelta($sql);
    update_option('bsw_dashboard_metrics_schema', $version, false);
}
add_action('admin_init', 'bsw_dashboard_metrics_install', 5);
add_action('rest_api_init', 'bsw_dashboard_metrics_install', 1);
function bsw_dashboard_metric_increment($metric, $object_id = 0) {
    global $wpdb;
    if (!in_array($metric, array('api_request', 'page_view'), true)) return;
    $table = bsw_dashboard_metrics_table();
    $date = current_time('Y-m-d');
    $wpdb->query($wpdb->prepare(
        "INSERT INTO {$table} (metric_date, metric, object_id, metric_count) VALUES (%s, %s, %d, 1) ON DUPLICATE KEY UPDATE metric_count = metric_count + 1",
        $date, $metric, absint($object_id)
    ));
}
function bsw_dashboard_track_api_request($result, $server, $request) {
    if (is_object($request) && method_exists($request, 'get_route')) {
        $route = (string) $request->get_route();
        // Count only BIGSHOR's own API namespace, not unrelated plugin/core REST traffic.
        if (strpos($route, '/bigshor/') === 0) bsw_dashboard_metric_increment('api_request', 0);
    }
    return $result;
}
add_filter('rest_pre_dispatch', 'bsw_dashboard_track_api_request', 10, 3);
function bsw_dashboard_track_article_view() {
    if (is_admin() || is_preview() || is_feed() || is_robots() || !is_singular('wiki_article')) return;
    $post_id = get_queried_object_id();
    if ($post_id && get_post_status($post_id) === 'publish') bsw_dashboard_metric_increment('page_view', $post_id);
}
add_action('template_redirect', 'bsw_dashboard_track_article_view', 20);

add_action('admin_enqueue_scripts', 'bsw_modern_dashboard_assets');
function bsw_modern_dashboard_assets($hook) {
    if ($hook !== 'toplevel_page_bsw-control-centre' && $hook !== 'bsw-control-centre_page_bsw-control-centre') return;
    wp_enqueue_style('bsw-modern-dashboard', plugin_dir_url(dirname(__FILE__)) . 'assets/admin-dashboard.css', array(), '34.0.0');
}
function bsw_modern_dashboard_count($status) {
    $counts = wp_count_posts('wiki_article');
    return isset($counts->$status) ? (int) $counts->$status : 0;
}
function bsw_modern_dashboard_recent($status, $limit = 6) {
    return get_posts(array('post_type'=>'wiki_article','post_status'=>$status,'numberposts'=>$limit,'orderby'=>'modified','order'=>'DESC','suppress_filters'=>false));
}
function bsw_dashboard_metric_total($metric, $days = 30) {
    global $wpdb;
    $table = bsw_dashboard_metrics_table();
    $since = wp_date('Y-m-d', current_time('timestamp') - (($days - 1) * DAY_IN_SECONDS));
    return (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(metric_count),0) FROM {$table} WHERE metric=%s AND metric_date >= %s", $metric, $since));
}
function bsw_dashboard_metric_top_articles($days = 7, $limit = 5) {
    global $wpdb;
    $table = bsw_dashboard_metrics_table();
    $since = wp_date('Y-m-d', current_time('timestamp') - (($days - 1) * DAY_IN_SECONDS));
    return $wpdb->get_results($wpdb->prepare(
        "SELECT object_id, SUM(metric_count) AS views FROM {$table} WHERE metric='page_view' AND metric_date >= %s AND object_id > 0 GROUP BY object_id ORDER BY views DESC LIMIT %d",
        $since, $limit
    ));
}
function bsw_dashboard_activity_posts($limit = 5) {
    return get_posts(array('post_type'=>'wiki_article','post_status'=>array('publish','draft','pending','private'),'numberposts'=>$limit,'orderby'=>'modified','order'=>'DESC','suppress_filters'=>false));
}
function bsw_dashboard_card($label, $value, $hint, $icon, $tone = '') {
    echo '<article class="bsw-dg-kpi"><div class="bsw-dg-kpi-top"><span>'.esc_html($label).'</span><span class="bsw-dg-kpi-icon '.esc_attr($tone).'">'.esc_html($icon).'</span></div><strong>'.esc_html(number_format_i18n($value)).'</strong><small>'.esc_html($hint).'</small></article>';
}
function bsw_modern_dashboard_render() {
    if (!current_user_can('read')) wp_die(esc_html__('You do not have permission to access this dashboard.', 'bigshor-wiki'));
    bsw_dashboard_metrics_install();
    $counts = wp_count_posts('wiki_article');
    $total = 0;
    foreach (array('publish','pending','draft','private','future','trash') as $st) if (isset($counts->$st)) $total += (int)$counts->$st;
    $published = bsw_modern_dashboard_count('publish');
    $pending = bsw_modern_dashboard_count('pending');
    $drafts = bsw_modern_dashboard_count('draft');
    $users = count_users();
    $authors = isset($users['total_users']) ? (int)$users['total_users'] : 0;
    $terms = wp_count_terms(array('taxonomy'=>'category','hide_empty'=>false));
    if (is_wp_error($terms)) $terms = 0;
    $api_requests = bsw_dashboard_metric_total('api_request', 30);
    $page_views = bsw_dashboard_metric_total('page_view', 30);
    $current = wp_get_current_user();
    $recent = bsw_dashboard_activity_posts(5);
    $draft_list = bsw_modern_dashboard_recent('draft', 5);
    $top_articles = bsw_dashboard_metric_top_articles(7, 5);
    $nav = array(
        'CONTENT' => array(array('Articles','edit.php?post_type=wiki_article','▤'),array('Authors','users.php','♙'),array('Categories','edit-tags.php?taxonomy=category','◇')),
        'ADMIN' => array(array('Knowledge entities','edit.php?post_type=bsw_entity','◎'),array('Media library','upload.php','▧'),array('Editorial review','edit.php?post_status=pending&post_type=wiki_article','☷'),array('Analytics','edit.php?post_type=wiki_article&page=bsw-p5-analytics','▥'),array('Workflows','edit.php?post_type=wiki_article&page=bsw-translation-workflow','↗'),array('Import / Export','tools.php?page=bsw-data-portability','⇧'),array('Users','users.php','♧'))
    );
    echo '<div class="wrap bsw-dig-admin"><aside class="bsw-dig-sidebar"><div class="bsw-dig-brand"><span class="bsw-dig-logo">B</span><span>BIGSHOR<small>WIKI</small></span></div><div class="bsw-dig-site"><span class="bsw-dig-site-mark">BW</span><span>'.esc_html(wp_parse_url(home_url(), PHP_URL_HOST)).'</span><span class="bsw-dig-chevron">⌄</span></div><nav><a class="bsw-dig-nav-active" href="'.esc_url(admin_url('admin.php?page=bsw-control-centre')).'"><span>⌂</span>Dashboard</a>';
    foreach ($nav as $section=>$items) { echo '<div class="bsw-dig-section">'.esc_html($section).'</div>'; foreach ($items as $item) echo '<a class="bsw-dig-nav" href="'.esc_url(admin_url($item[1])).'"><span>'.esc_html($item[2]).'</span>'.esc_html($item[0]).'</a>'; }
    echo '</nav><div class="bsw-dig-user"><span class="bsw-dig-user-avatar">'.esc_html(strtoupper(substr($current->display_name,0,1))).'</span><span>'.esc_html($current->display_name).'<small>Administrator</small></span></div></aside>';
    echo '<main class="bsw-dig-main"><header class="bsw-dig-header"><h1>Dashboard</h1><div class="bsw-dig-header-actions"><a href="'.esc_url(admin_url('post-new.php?post_type=wiki_article')).'" class="bsw-dig-create">＋ New Article</a><a href="'.esc_url(admin_url('profile.php')).'" class="bsw-dig-profile" aria-label="Profile">'.esc_html(strtoupper(substr($current->display_name,0,1))).'</a></div></header>';
    echo '<section class="bsw-dig-kpis">';
    bsw_dashboard_card('Total Articles',$total,'All article statuses','▤','');
    bsw_dashboard_card('Published',$published,'Live knowledge articles','✓','purple');
    bsw_dashboard_card('API Requests',$api_requests,'BIGSHOR API · last 30 days','⇄','');
    bsw_dashboard_card('Page Views',$page_views,'Wiki article views · last 30 days','◉','');
    echo '</section>';
    echo '<section class="bsw-dig-row bsw-dig-row-middle"><article class="bsw-dig-panel bsw-dig-content-type"><div class="bsw-dig-panel-head"><h2>Content by Type</h2></div>';
    $content_types = array(array('Articles',$total,'#852bd1'),array('Authors',$authors,'#9ca3af'),array('Categories',(int)$terms,'#d1d5db'));
    $content_max = max(1, max(array_column($content_types,1)));
    foreach ($content_types as $ct) echo '<div class="bsw-dig-type"><div><span><i style="background:'.esc_attr($ct[2]).'"></i>'.esc_html($ct[0]).'</span><b>'.esc_html(number_format_i18n($ct[1])).'</b></div><div class="bsw-dig-progress"><span style="width:'.esc_attr(round(($ct[1]/$content_max)*100,1)).'%;background:'.esc_attr($ct[2]).'"></span></div></div>';
    echo '<div class="bsw-dig-type-note">Includes all registered wiki article records and site contributors.</div></article>';
    echo '<article class="bsw-dig-panel bsw-dig-activity"><div class="bsw-dig-panel-head"><h2>Recent Activity</h2><a href="'.esc_url(admin_url('edit.php?post_type=wiki_article')).'">View all</a></div>';
    if ($recent) foreach ($recent as $p) { $author=get_the_author_meta('display_name',$p->post_author); $status=$p->post_status==='publish'?'published':($p->post_status==='pending'?'submitted for review':$p->post_status); echo '<div class="bsw-dig-activity-item"><span class="bsw-dig-activity-avatar">'.esc_html(strtoupper(substr($author,0,1))).'</span><span class="bsw-dig-activity-copy"><strong>'.esc_html($author).' '.esc_html($status).' “'.esc_html(get_the_title($p)).'”</strong><small>Wiki Articles · '.esc_html(human_time_diff(get_post_modified_time('U',true,$p),current_time('timestamp',true))).' ago</small></span><span class="bsw-dig-activity-time">'.esc_html(get_post_status_object($p->post_status)->label ?? ucfirst($p->post_status)).'</span></div>'; }
    else echo '<div class="bsw-dig-empty">No article activity recorded yet.</div>';
    echo '</article></section>';
    echo '<section class="bsw-dig-row bsw-dig-row-bottom"><article class="bsw-dig-panel bsw-dig-quick"><div class="bsw-dig-panel-head"><h2>Quick Actions</h2></div><div class="bsw-dig-quick-grid">';
    $quick=array(array('＋','New Article','post-new.php?post_type=wiki_article'),array('⇧','Import','tools.php?page=bsw-data-portability'),array('▧','Media Library','upload.php'),array('◇','Categories','edit-tags.php?taxonomy=category'));
    foreach($quick as $q) echo '<a href="'.esc_url(admin_url($q[2])).'"><span>'.esc_html($q[0]).'</span>'.esc_html($q[1]).'</a>';
    echo '</div></article><article class="bsw-dig-panel bsw-dig-drafts"><div class="bsw-dig-panel-head"><h2>Drafts</h2><span class="bsw-dig-badge">'.esc_html(number_format_i18n($drafts)).'</span></div>';
    if($draft_list) foreach($draft_list as $p) echo '<a class="bsw-dig-list-item" href="'.esc_url(get_edit_post_link($p->ID)).'"><span><strong>'.esc_html(get_the_title($p)).'</strong><small>Last edited '.esc_html(human_time_diff(get_post_modified_time('U',true,$p),current_time('timestamp',true))).' ago</small></span><b>›</b></a>'; else echo '<div class="bsw-dig-empty">No drafts currently.</div>';
    echo '</article><article class="bsw-dig-panel bsw-dig-most"><div class="bsw-dig-panel-head"><h2>Most Viewed</h2><small>Last 7 days</small></div>';
    $rank=0; if($top_articles) foreach($top_articles as $row){$post=get_post((int)$row->object_id);if(!$post||$post->post_status!=='publish'||$post->post_type!=='wiki_article')continue;$rank++;echo '<a class="bsw-dig-rank-item" href="'.esc_url(get_edit_post_link($post->ID)).'"><span class="bsw-dig-rank">'.esc_html($rank).'</span><span class="bsw-dig-rank-title"><strong>'.esc_html(get_the_title($post)).'</strong><small>Wiki Articles</small></span><b>'.esc_html(number_format_i18n((int)$row->views)).'</b></a>'; } if(!$rank) echo '<div class="bsw-dig-empty">Page-view tracking starts with this release. No views recorded yet.</div>';
    echo '</article></section><footer class="bsw-dig-footer">BIGSHOR WIKI · Knowledge Platform <span>Analytics collected from installation onwards</span></footer></main></div>';
}
