<?php
/**
 * BIGSHOR Core integration layer: reader library and contextual discovery.
 * All mutations are authenticated, nonce-protected and scoped to the current user.
 */
if (!defined('ABSPATH')) exit;
if (!defined('BSW_READER_LIBRARY_VERSION')) define('BSW_READER_LIBRARY_VERSION', '1.0.0');

function bsw_reader_saved_ids($user_id = 0) {
    $user_id = $user_id ? absint($user_id) : get_current_user_id();
    if (!$user_id) return array();
    $ids = get_user_meta($user_id, '_bsw_saved_articles', true);
    if (!is_array($ids)) return array();
    $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
    return $ids;
}
function bsw_reader_is_supported_article($post_id) {
    $post = get_post($post_id);
    return $post && $post->post_type === 'wiki_article' && $post->post_status === 'publish';
}
function bsw_reader_toggle_saved() {
    if (!is_user_logged_in()) wp_send_json_error(array('message'=>__('Please sign in to save articles.','bigshor-wiki')), 401);
    check_ajax_referer('bsw_reader_library', 'nonce');
    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    if (!$post_id || !bsw_reader_is_supported_article($post_id)) wp_send_json_error(array('message'=>__('This article cannot be saved.','bigshor-wiki')), 400);
    $ids = bsw_reader_saved_ids();
    if (in_array($post_id, $ids, true)) { $ids = array_values(array_diff($ids, array($post_id))); $saved = false; }
    else { array_unshift($ids, $post_id); $ids = array_slice(array_values(array_unique($ids)), 0, 500); $saved = true; }
    update_user_meta(get_current_user_id(), '_bsw_saved_articles', $ids);
    wp_send_json_success(array('saved'=>$saved, 'count'=>count($ids), 'message'=>$saved ? __('Saved to your reading list.','bigshor-wiki') : __('Removed from your reading list.','bigshor-wiki')));
}
add_action('wp_ajax_bsw_toggle_saved_article', 'bsw_reader_toggle_saved');

function bsw_reader_bookmark_button($atts = array()) {
    if (!is_singular('wiki_article')) return '';
    $post_id = get_the_ID();
    if (!$post_id || !bsw_reader_is_supported_article($post_id)) return '';
    if (!is_user_logged_in()) return '<a class="bsw-save-article" href="'.esc_url(wp_login_url(get_permalink($post_id))).'">'.esc_html__('Sign in to save','bigshor-wiki').'</a>';
    $saved = in_array($post_id, bsw_reader_saved_ids(), true);
    return '<button type="button" class="bsw-save-article" data-bsw-save-article data-post-id="'.esc_attr($post_id).'" aria-pressed="'.($saved?'true':'false').'">'.esc_html($saved?__('Saved','bigshor-wiki'):__('Save article','bigshor-wiki')).'</button><span class="bsw-save-feedback" aria-live="polite"></span>';
}
add_shortcode('bsw_save_article', 'bsw_reader_bookmark_button');

function bsw_reader_saved_articles_shortcode($atts = array()) {
    if (!is_user_logged_in()) return '<p class="bsw-reader-notice">'.esc_html__('Sign in to view your saved articles.','bigshor-wiki').'</p>';
    $ids = bsw_reader_saved_ids();
    if (!$ids) return '<p class="bsw-reader-empty">'.esc_html__('Your reading list is empty. Save articles to find them here.','bigshor-wiki').'</p>';
    $q = new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','post__in'=>$ids,'orderby'=>'post__in','posts_per_page'=>100,'no_found_rows'=>true));
    if (!$q->have_posts()) return '<p class="bsw-reader-empty">'.esc_html__('Your reading list is empty.','bigshor-wiki').'</p>';
    ob_start(); echo '<ul class="bsw-reader-list">';
    while ($q->have_posts()) { $q->the_post(); echo '<li><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a><p>'.esc_html(wp_trim_words(get_the_excerpt(), 24)).'</p></li>'; }
    echo '</ul>'; wp_reset_postdata(); return ob_get_clean();
}
add_shortcode('bsw_saved_articles', 'bsw_reader_saved_articles_shortcode');

function bsw_reader_personalised_discovery($atts = array()) {
    $atts = shortcode_atts(array('limit'=>6), $atts, 'bsw_personalised_discovery');
    $limit = min(12, max(1, absint($atts['limit'])));
    $args = array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>$limit,'orderby'=>'date','order'=>'DESC','no_found_rows'=>true);
    if (is_user_logged_in()) {
        $saved = bsw_reader_saved_ids();
        $terms = array();
        if ($saved) {
            $term_ids = wp_get_object_terms($saved, 'wiki_topic', array('fields'=>'ids'));
            if (!is_wp_error($term_ids) && $term_ids) $terms = array_slice(array_values(array_unique(array_map('absint',$term_ids))),0,8);
        }
        if ($terms) $args['tax_query'] = array(array('taxonomy'=>'wiki_topic','field'=>'term_id','terms'=>$terms));
        if ($saved) $args['post__not_in'] = $saved;
    }
    $q = new WP_Query($args);
    if (!$q->have_posts()) return '<p class="bsw-reader-empty">'.esc_html__('New knowledge will appear here as articles are published.','bigshor-wiki').'</p>';
    ob_start(); echo '<ul class="bsw-reader-list bsw-discovery-list">';
    while ($q->have_posts()) { $q->the_post(); echo '<li><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a><p>'.esc_html(wp_trim_words(get_the_excerpt(), 22)).'</p></li>'; }
    echo '</ul>'; wp_reset_postdata(); return ob_get_clean();
}
add_shortcode('bsw_personalised_discovery', 'bsw_reader_personalised_discovery');

function bsw_reader_enqueue_assets() {
    if (is_admin()) return;
    wp_enqueue_script('bsw-reader-library', plugins_url('assets/reader-library.js', dirname(__FILE__, 2) . '/bigshor-wiki-core.php'), array(), '1.0.0', true);
    wp_localize_script('bsw-reader-library', 'bswReaderLibrary', array('ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('bsw_reader_library')));
}
add_action('wp_enqueue_scripts', 'bsw_reader_enqueue_assets');
