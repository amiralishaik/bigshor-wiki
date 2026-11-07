<?php
/**
 * BIGSHOR Wiki Phases 16–20: content quality, advanced discovery,
 * knowledge relationships, reader personalisation and multilingual UX.
 * Foundational WordPress-native implementation; PHP 7.4+.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phases_16_20')) {
final class BSW_Phases_16_20 {
    const VERSION = '26.0.0';
    public static function init() {
        add_action('add_meta_boxes', array(__CLASS__, 'meta_boxes'));
        add_action('save_post', array(__CLASS__, 'save_relationships'), 20, 2);
        add_action('wp', array(__CLASS__, 'track_view'));
        add_action('admin_post_bsw_toggle_bookmark', array(__CLASS__, 'toggle_bookmark'));
        add_action('admin_post_nopriv_bsw_toggle_bookmark', array(__CLASS__, 'bookmark_login'));
        add_shortcode('bigshor_advanced_search', array(__CLASS__, 'search_shortcode'));
        add_shortcode('bigshor_related_knowledge', array(__CLASS__, 'related_shortcode'));
        add_shortcode('bigshor_saved_articles', array(__CLASS__, 'saved_shortcode'));
        add_shortcode('bigshor_reading_history', array(__CLASS__, 'history_shortcode'));
        add_shortcode('bigshor_language_switcher', array(__CLASS__, 'language_shortcode'));
        add_filter('the_content', array(__CLASS__, 'append_reader_controls'), 30);
    }
    private static function allowed_post_types() {
        $types = array('post','page','wiki_article','wiki_entity');
        return array_values(array_filter($types, 'post_type_exists'));
    }
    public static function meta_boxes() {
        foreach (array('post','wiki_article','wiki_entity') as $type) {
            if (post_type_exists($type)) add_meta_box('bsw_knowledge_links','BIGSHOR Knowledge Relationships',array(__CLASS__,'relationship_box'),$type,'side','default');
        }
    }
    public static function relationship_box($post) {
        wp_nonce_field('bsw_save_relationships','bsw_relationship_nonce');
        $ids = get_post_meta($post->ID, '_bsw_related_knowledge_ids', true);
        $ids = is_array($ids) ? $ids : array();
        echo '<p>Enter related published article IDs, separated by commas.</p>';
        echo '<input type="text" style="width:100%" name="bsw_related_knowledge_ids" value="'.esc_attr(implode(',', array_map('absint',$ids))).'" />';
        echo '<p class="description">These links connect this page to other BIGSHOR knowledge entries.</p>';
    }
    public static function save_relationships($post_id, $post) {
        if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) return;
        if (!isset($_POST['bsw_relationship_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_relationship_nonce'])), 'bsw_save_relationships')) return;
        if (!current_user_can('edit_post', $post_id)) return;
        $raw = isset($_POST['bsw_related_knowledge_ids']) ? sanitize_text_field(wp_unslash($_POST['bsw_related_knowledge_ids'])) : '';
        $ids = array_filter(array_unique(array_map('absint', preg_split('/[,\s]+/', $raw))));
        $ids = array_values(array_filter($ids, function($id) use ($post_id) { return $id !== (int)$post_id && get_post_status($id) === 'publish'; }));
        update_post_meta($post_id, '_bsw_related_knowledge_ids', array_slice($ids, 0, 20));
    }
    public static function search_shortcode($atts) {
        $atts = shortcode_atts(array('placeholder'=>'Search BIGSHOR Wiki','show_type'=>'1'),$atts,'bigshor_advanced_search');
        $html = '<form class="bsw-advanced-search" role="search" method="get" action="'.esc_url(home_url('/')).'">';
        $html .= '<label class="screen-reader-text" for="bsw-knowledge-search">'.esc_html($atts['placeholder']).'</label>';
        $html .= '<input id="bsw-knowledge-search" type="search" name="s" value="'.esc_attr(get_search_query()).'" placeholder="'.esc_attr($atts['placeholder']).'" required />';
        if ($atts['show_type'] === '1') {
            $html .= '<select name="post_type" aria-label="Content type"><option value="">All content</option>';
            foreach (self::allowed_post_types() as $type) $html .= '<option value="'.esc_attr($type).'" '.selected(get_query_var('post_type'),$type,false).'>'.esc_html(post_type_archive_title('',false) ?: get_post_type_object($type)->labels->name).'</option>';
            $html .= '</select>';
        }
        $html .= '<button type="submit">Search</button></form>';
        return $html;
    }
    public static function related_shortcode($atts) {
        $atts = shortcode_atts(array('id'=>get_the_ID(),'limit'=>6),$atts,'bigshor_related_knowledge');
        $id = absint($atts['id']); $ids = get_post_meta($id,'_bsw_related_knowledge_ids',true);
        if (!is_array($ids) || !$ids) return '';
        $items = get_posts(array('post__in'=>array_slice(array_map('absint',$ids),0,min(20,max(1,absint($atts['limit'])))),'post_status'=>'publish','post_type'=>self::allowed_post_types(),'orderby'=>'post__in','numberposts'=>-1));
        if (!$items) return '';
        $html = '<section class="bsw-related-knowledge"><h2>Related knowledge</h2><ul>';
        foreach ($items as $item) $html .= '<li><a href="'.esc_url(get_permalink($item)).'">'.esc_html(get_the_title($item)).'</a></li>';
        return $html.'</ul></section>';
    }
    private static function user_list($key) {
        if (!is_user_logged_in()) return array();
        $value = get_user_meta(get_current_user_id(),$key,true);
        return is_array($value) ? array_slice(array_values(array_unique(array_map('absint',$value))),0,100) : array();
    }
    public static function track_view() {
        if (!is_user_logged_in() || !is_singular(self::allowed_post_types())) return;
        $id = get_queried_object_id(); if (!$id || get_post_status($id) !== 'publish') return;
        $history = self::user_list('_bsw_reading_history');
        array_unshift($history,(int)$id);
        update_user_meta(get_current_user_id(),'_bsw_reading_history',array_slice(array_values(array_unique($history)),0,30));
    }
    public static function append_reader_controls($content) {
        if (!is_singular(self::allowed_post_types()) || !is_user_logged_in() || !in_the_loop() || !is_main_query()) return $content;
        $id = get_the_ID(); $saved = self::user_list('_bsw_saved_articles');
        $label = in_array((int)$id,$saved,true) ? 'Remove from saved articles' : 'Save article';
        $url = add_query_arg(array('action'=>'bsw_toggle_bookmark','post_id'=>$id),admin_url('admin-post.php'));
        $url = wp_nonce_url($url,'bsw_bookmark_'.$id);
        return '<div class="bsw-reader-actions"><a rel="nofollow" href="'.esc_url($url).'">'.esc_html($label).'</a></div>'.$content;
    }
    public static function toggle_bookmark() {
        if (!is_user_logged_in()) self::bookmark_login();
        $id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        if (!$id || get_post_status($id) !== 'publish') wp_die('Article not found.');
        check_admin_referer('bsw_bookmark_'.$id);
        $saved = self::user_list('_bsw_saved_articles');
        if (in_array($id,$saved,true)) $saved = array_values(array_diff($saved,array($id))); else array_unshift($saved,$id);
        update_user_meta(get_current_user_id(),'_bsw_saved_articles',array_slice($saved,0,100));
        wp_safe_redirect(wp_get_referer() ?: get_permalink($id)); exit;
    }
    public static function bookmark_login() { auth_redirect(); exit; }
    private static function render_user_posts($key,$heading,$empty) {
        if (!is_user_logged_in()) return '<p>Please sign in to use this feature.</p>';
        $ids = self::user_list($key); $posts = $ids ? get_posts(array('post__in'=>$ids,'post_status'=>'publish','post_type'=>self::allowed_post_types(),'orderby'=>'post__in','numberposts'=>-1)) : array();
        $html = '<section class="bsw-personal-knowledge"><h2>'.esc_html($heading).'</h2>';
        if (!$posts) return $html.'<p>'.esc_html($empty).'</p></section>';
        $html .= '<ul>'; foreach ($posts as $p) $html .= '<li><a href="'.esc_url(get_permalink($p)).'">'.esc_html(get_the_title($p)).'</a></li>';
        return $html.'</ul></section>';
    }
    public static function saved_shortcode() { return self::render_user_posts('_bsw_saved_articles','Saved articles','You have not saved any articles yet.'); }
    public static function history_shortcode() { return self::render_user_posts('_bsw_reading_history','Recently viewed','Your reading history is empty.'); }
    public static function language_shortcode() {
        if (!is_singular()) return '';
        $id = get_the_ID(); $group = get_post_meta($id,'_bsw_translation_group',true);
        if (!$group) return '';
        $items = get_posts(array('post_type'=>'any','post_status'=>'publish','numberposts'=>50,'meta_key'=>'_bsw_translation_group','meta_value'=>$group,'exclude'=>array($id)));
        if (!$items) return '';
        $html = '<nav class="bsw-language-switcher" aria-label="Article languages"><strong>Read this page in:</strong> ';
        foreach ($items as $item) { $lang = get_post_meta($item->ID,'_bsw_language',true); $html .= '<a href="'.esc_url(get_permalink($item)).'" lang="'.esc_attr($lang).'">'.esc_html($lang ?: get_the_title($item)).'</a> '; }
        return $html.'</nav>';
    }
}
BSW_Phases_16_20::init();
}
