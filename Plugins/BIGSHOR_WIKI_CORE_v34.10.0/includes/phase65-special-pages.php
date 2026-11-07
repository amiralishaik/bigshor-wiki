<?php
/**
 * Phase 65 — Special pages and contributor discovery.
 * Provides safe, paginated public indexes and read-only REST endpoints.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase65_Special_Pages')) {
final class BSW_Phase65_Special_Pages {
    public static function init() {
        add_shortcode('bigshor_wiki_special_pages', array(__CLASS__, 'directory'));
        add_shortcode('bigshor_wiki_categories', array(__CLASS__, 'categories'));
        add_shortcode('bigshor_wiki_random', array(__CLASS__, 'random_link'));
        add_shortcode('bigshor_wiki_user_contributions', array(__CLASS__, 'contributions'));
        add_action('init', array(__CLASS__, 'random_route'));
        add_action('rest_api_init', array(__CLASS__, 'register_rest'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'assets'));
    }
    public static function assets() {
        if (!is_singular()) return;
        $post = get_post(); if (!$post || !has_shortcode($post->post_content, 'bigshor_wiki_special_pages')) return;
        $css = '.bsw65-special-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px}.bsw65-special-card{border:1px solid #c8ccd1;background:#fff;padding:16px;border-radius:3px}.bsw65-special-card h3{margin:0 0 7px;font-size:16px}.bsw65-special-card p{margin:0;color:#54595d;font-size:13px}.bsw65-index-letters{display:flex;flex-wrap:wrap;gap:6px;margin:12px 0}.bsw65-index-letters a{padding:5px 8px;border:1px solid #c8ccd1;text-decoration:none}.bsw65-contrib-list{list-style:none;margin:0;padding:0}.bsw65-contrib-list li{padding:10px 0;border-bottom:1px solid #eaecf0}';
        wp_register_style('bsw65-special', false); wp_enqueue_style('bsw65-special'); wp_add_inline_style('bsw65-special', $css);
    }
    public static function directory() {
        $items = array(
            array('All pages','Browse every published encyclopedia article.','/all-pages/'),
            array('Recent changes','Review the latest article updates.','/recent-changes/'),
            array('Categories','Explore the topic classification system.','/wiki-categories/'),
            array('Random article','Open a randomly selected published article.','/random-article/'),
            array('Article statistics','View the current encyclopedia inventory.','/wiki-statistics/'),
            array('Contributors','Find editors and their published contributions.','/wiki-contributors/'),
            array('My watchlist','Track articles you want to follow.','/my-watchlist/'),
            array('Recent uploads','Browse recently published media.','/recent-uploads/'),
            array('Orphaned articles','Find articles with few or no incoming links.','/orphaned-articles/'),
            array('Community portal','Coordinate editorial and maintenance work.','/community-portal/'),
            array('Help center','Read contribution and editing guidance.','/help-center/'),
            array('Editorial policy','Review sourcing, neutrality and disclosure rules.','/editorial-policy/')
        );
        $out = '<section class="bsw65-special"><p>Special pages provide tools for browsing, maintaining and participating in BIGSHOR Wiki.</p><div class="bsw65-special-grid">';
        foreach ($items as $item) $out .= '<a class="bsw65-special-card" href="'.esc_url(home_url($item[2])).'"><h3>'.esc_html($item[0]).'</h3><p>'.esc_html($item[1]).'</p></a>';
        return $out.'</div></section>';
    }
    public static function categories($atts) {
        $a = shortcode_atts(array('hide_empty'=>'1','number'=>200), $atts);
        $terms = get_terms(array('taxonomy'=>'wiki_topic','hide_empty'=>$a['hide_empty']==='1','number'=>max(1,min(500,absint($a['number']))),'orderby'=>'name','order'=>'ASC'));
        if (is_wp_error($terms) || !$terms) return '<p>No topic categories have been created yet.</p>';
        $out = '<ul class="bsw65-category-list">';
        foreach ($terms as $term) $out .= '<li><a href="'.esc_url(get_term_link($term)).'">'.esc_html($term->name).'</a> <span>('.number_format_i18n($term->count).')</span>'.($term->description ? '<p>'.esc_html($term->description).'</p>' : '').'</li>';
        return $out.'</ul>';
    }
    public static function random_route() {
        if (!isset($_GET['bsw_random_article'])) return;
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'bsw_random_article')) return;
        $ids = get_posts(array('post_type'=>'wiki_article','post_status'=>'publish','numberposts'=>1,'orderby'=>'rand','fields'=>'ids','no_found_rows'=>true));
        if ($ids) { wp_safe_redirect(get_permalink((int)$ids[0])); exit; }
        wp_safe_redirect(home_url('/all-pages/')); exit;
    }
    public static function random_link() {
        $url = add_query_arg(array('bsw_random_article'=>'1','_wpnonce'=>wp_create_nonce('bsw_random_article')), home_url('/'));
        return '<p><a class="bsw65-random" href="'.esc_url($url).'">Explore a random article →</a></p>';
    }
    public static function contributions($atts) {
        $a = shortcode_atts(array('user'=>'','limit'=>50), $atts);
        $user = $a['user'] !== '' ? get_user_by('login', sanitize_user($a['user'])) : (get_query_var('author') ? get_userdata((int)get_query_var('author')) : false);
        if (!$user && isset($_GET['author_id'])) $user = get_userdata(absint($_GET['author_id']));
        if (!$user) return '<p>Select a valid contributor to view their public article contributions.</p>';
        $q = new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','author'=>$user->ID,'posts_per_page'=>max(1,min(100,absint($a['limit']))),'orderby'=>'modified','order'=>'DESC'));
        $out = '<section class="bsw65-contributions"><h2>Articles by '.esc_html($user->display_name).'</h2><ul class="bsw65-contrib-list">';
        while ($q->have_posts()) { $q->the_post(); $out .= '<li><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a> <small>Updated '.esc_html(get_the_modified_date()).'</small></li>'; }
        wp_reset_postdata();
        return $q->post_count ? $out.'</ul></section>' : '<p>This contributor has no published BIGSHOR Wiki articles.</p>';
    }
    public static function register_rest() {
        register_rest_route('bigshor-wiki/v1', '/special-pages', array('methods'=>'GET','callback'=>array(__CLASS__,'rest_special'),'permission_callback'=>'__return_true'));
        register_rest_route('bigshor-wiki/v1', '/categories', array('methods'=>'GET','callback'=>array(__CLASS__,'rest_categories'),'permission_callback'=>'__return_true'));
    }
    public static function rest_special() {
        return rest_ensure_response(array('site'=>get_bloginfo('name'),'special_pages'=>array(
            array('name'=>'All pages','url'=>home_url('/all-pages/')),
            array('name'=>'Recent changes','url'=>home_url('/recent-changes/')),
            array('name'=>'Categories','url'=>home_url('/wiki-categories/')),
            array('name'=>'Random article','url'=>home_url('/random-article/')),
            array('name'=>'Statistics','url'=>home_url('/wiki-statistics/')),
            array('name'=>'Contributors','url'=>home_url('/wiki-contributors/')),
            array('name'=>'Watchlist','url'=>home_url('/my-watchlist/'))
        )));
    }
    public static function rest_categories($request) {
        $terms = get_terms(array('taxonomy'=>'wiki_topic','hide_empty'=>true,'number'=>max(1,min(500,absint($request->get_param('per_page') ?: 100)))));
        if (is_wp_error($terms)) return new WP_Error('bsw_categories_unavailable','Categories could not be loaded.',array('status'=>500));
        $data = array(); foreach ($terms as $term) $data[] = array('id'=>(int)$term->term_id,'name'=>$term->name,'slug'=>$term->slug,'count'=>(int)$term->count,'url'=>get_term_link($term));
        return rest_ensure_response($data);
    }
}
BSW_Phase65_Special_Pages::init();
}
