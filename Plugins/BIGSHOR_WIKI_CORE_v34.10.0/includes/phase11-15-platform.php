<?php
/**
 * BIGSHOR Wiki platform phases 11–15.
 * Phase 11 taxonomy/navigation; 12 search/discovery; 13 article talk pages;
 * 14 read-only REST/export/SEO; 15 conservative security/performance controls.
 * WordPress-native, additive and PHP 7.4 compatible.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phases_11_15_Platform')) {
final class BSW_Phases_11_15_Platform {
    const VERSION = '1.0.0';
    const OPTION = 'bsw_p1115_platform_settings';
    public static function init() {
        add_action('init', array(__CLASS__, 'register_taxonomies'), 20);
        add_action('init', array(__CLASS__, 'enable_talk_comments'), 20);
        add_action('init', array(__CLASS__, 'shortcodes'));
        add_action('pre_get_posts', array(__CLASS__, 'search_query'));
        add_action('add_meta_boxes', array(__CLASS__, 'meta_boxes'));
        add_action('save_post', array(__CLASS__, 'save_meta'), 30, 2);
        add_action('rest_api_init', array(__CLASS__, 'routes'));
        add_action('wp_head', array(__CLASS__, 'seo_meta'), 2);
        add_filter('document_title_parts', array(__CLASS__, 'title_parts'));
        add_filter('robots_txt', array(__CLASS__, 'robots_txt'), 10, 2);
        add_action('send_headers', array(__CLASS__, 'security_headers'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 45);
        add_action('admin_init', array(__CLASS__, 'register_settings'));
    }
    public static function defaults() { return array('talk_pages'=>1,'public_api'=>1,'api_rate_limit'=>120,'security_headers'=>1,'search_types'=>array('wiki_article','post','wiki_entity')); }
    public static function settings() { $v=get_option(self::OPTION,array()); return wp_parse_args(is_array($v)?$v:array(),self::defaults()); }
    public static function activate() { self::register_taxonomies(); if (!get_option(self::OPTION)) add_option(self::OPTION,self::defaults(),'','no'); }
    private static function article_types() { $types=array('wiki_article'); return array_values(array_filter($types,'post_type_exists')); }
    public static function register_taxonomies() {
        $types=array_values(array_filter(array('wiki_article','wiki_entity','post'),'post_type_exists'));
        if (!$types) return;
        if (!taxonomy_exists('bsw_knowledge_category')) register_taxonomy('bsw_knowledge_category',$types,array(
            'labels'=>array('name'=>'Knowledge categories','singular_name'=>'Knowledge category','menu_name'=>'Knowledge categories'),
            'public'=>true,'show_ui'=>true,'show_admin_column'=>true,'show_in_rest'=>true,'hierarchical'=>true,
            'rewrite'=>array('slug'=>'wiki-category','hierarchical'=>true),'query_var'=>true
        ));
        if (!taxonomy_exists('bsw_knowledge_topic')) register_taxonomy('bsw_knowledge_topic',$types,array(
            'labels'=>array('name'=>'Knowledge topics','singular_name'=>'Knowledge topic'),
            'public'=>true,'show_ui'=>true,'show_admin_column'=>true,'show_in_rest'=>true,'hierarchical'=>false,
            'rewrite'=>array('slug'=>'wiki-topic'),'query_var'=>true
        ));
    }
    public static function enable_talk_comments() {
        if (empty(self::settings()['talk_pages'])) return;
        foreach (self::article_types() as $type) {
            add_post_type_support($type,'comments');
            add_post_type_support($type,'trackbacks');
        }
    }
    public static function shortcodes() {
        add_shortcode('bsw_knowledge_navigation',array(__CLASS__,'navigation_shortcode'));
        add_shortcode('bsw_knowledge_search',array(__CLASS__,'search_shortcode'));
        add_shortcode('bsw_article_talk',array(__CLASS__,'talk_shortcode'));
    }
    public static function navigation_shortcode($atts) {
        $atts=shortcode_atts(array('taxonomy'=>'bsw_knowledge_category','hide_empty'=>'1','depth'=>3),$atts,'bsw_knowledge_navigation');
        $tax=in_array($atts['taxonomy'],array('bsw_knowledge_category','bsw_knowledge_topic'),true)?$atts['taxonomy']:'bsw_knowledge_category';
        if (!taxonomy_exists($tax)) return '';
        $terms=get_terms(array('taxonomy'=>$tax,'hide_empty'=>$atts['hide_empty']==='1','parent'=>0,'number'=>100));
        if (is_wp_error($terms)||!$terms) return '';
        $html='<nav class="bsw-knowledge-navigation" aria-label="Knowledge navigation"><ul>';
        foreach($terms as $term) $html.=self::term_tree($term,$tax,1,min(5,max(1,absint($atts['depth']))));
        return $html.'</ul></nav>';
    }
    private static function term_tree($term,$tax,$level,$max) {
        $html='<li><a href="'.esc_url(get_term_link($term)).'">'.esc_html($term->name).'</a>';
        if($level<$max){$children=get_terms(array('taxonomy'=>$tax,'hide_empty'=>true,'parent'=>$term->term_id,'number'=>100)); if(!is_wp_error($children)&&$children){$html.='<ul>';foreach($children as $child)$html.=self::term_tree($child,$tax,$level+1,$max);$html.='</ul>';}}
        return $html.'</li>';
    }
    public static function search_shortcode($atts) {
        $atts=shortcode_atts(array('placeholder'=>'Search BIGSHOR Wiki','types'=>'wiki_article,post,wiki_entity'),$atts,'bsw_knowledge_search');
        $allowed=array_values(array_intersect(array_map('sanitize_key',explode(',',$atts['types'])),get_post_types(array('public'=>true),'names')));
        $html='<form class="bsw-knowledge-search" role="search" method="get" action="'.esc_url(home_url('/')).'"><label class="screen-reader-text" for="bsw-search-query">'.esc_html($atts['placeholder']).'</label><input id="bsw-search-query" type="search" name="s" value="'.esc_attr(get_search_query()).'" placeholder="'.esc_attr($atts['placeholder']).'" required />';
        if($allowed){$html.='<select name="post_type" aria-label="Content type"><option value="">All content</option>';foreach($allowed as $type){$obj=get_post_type_object($type);$html.='<option value="'.esc_attr($type).'" '.selected(get_query_var('post_type'),$type,false).'>'.esc_html($obj?$obj->labels->singular_name:$type).'</option>';}$html.='</select>';}
        return $html.'<button type="submit">Search</button></form>';
    }
    public static function search_query($query) {
        if(is_admin()||!$query->is_main_query()||!$query->is_search()) return;
        $types=self::settings()['search_types']; if(!is_array($types)) $types=self::defaults()['search_types'];
        $types=array_values(array_intersect(array_map('sanitize_key',$types),get_post_types(array('public'=>true),'names')));
        if($types) $query->set('post_type',$types);
        $query->set('posts_per_page',max(10,min(50,(int)$query->get('posts_per_page')?:20)));
    }
    public static function meta_boxes() {
        foreach(self::article_types() as $type) add_meta_box('bsw_p1115_taxonomy','BIGSHOR Knowledge Classification',array(__CLASS__,'taxonomy_box'),$type,'side','default');
    }
    public static function taxonomy_box($post) {
        wp_nonce_field('bsw_p1115_taxonomy','bsw_p1115_taxonomy_nonce');
        foreach(array('bsw_knowledge_category'=>'Categories','bsw_knowledge_topic'=>'Topics') as $tax=>$label){if(!taxonomy_exists($tax))continue;$terms=get_terms(array('taxonomy'=>$tax,'hide_empty'=>false));$selected=wp_get_object_terms($post->ID,$tax,array('fields'=>'ids'));if(is_wp_error($selected))$selected=array();
            echo '<p><strong>'.esc_html($label).'</strong><br><select class="widefat" multiple size="5" name="'.esc_attr($tax).'[]">';foreach($terms as $term)echo '<option value="'.absint($term->term_id).'" '.selected(in_array((int)$term->term_id,$selected,true),true,false).'>'.esc_html($term->name).'</option>';echo '</select></p>';}
    }
    public static function save_meta($post_id,$post) {
        if(wp_is_post_revision($post_id)||(defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)||!isset($_POST['bsw_p1115_taxonomy_nonce']))return;
        if(!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_p1115_taxonomy_nonce'])),'bsw_p1115_taxonomy')||!current_user_can('edit_post',$post_id))return;
        foreach(array('bsw_knowledge_category','bsw_knowledge_topic') as $tax){if(!taxonomy_exists($tax))continue;$ids=isset($_POST[$tax])?array_slice(array_filter(array_map('absint',(array)wp_unslash($_POST[$tax]))),0,100):array();wp_set_object_terms($post_id,$ids,$tax,false);}
    }
    public static function talk_shortcode($atts) {
        if(empty(self::settings()['talk_pages']))return '';
        $atts=shortcode_atts(array('id'=>get_the_ID()),$atts,'bsw_article_talk');$id=absint($atts['id']);$post=get_post($id);
        if(!$post||!in_array($post->post_type,self::article_types(),true)||$post->post_status!=='publish')return '';
        ob_start();echo '<section class="bsw-article-talk" id="talk-'.absint($id).'"><h2>Talk</h2><p>Discuss improvements to this article. Keep comments focused on verifiable content and reliable sources.</p>';
        if(comments_open($id)||get_comments_number($id)) comments_template('/comments.php',true);
        else echo '<p>Discussion is currently closed.</p>';
        echo '</section>';return ob_get_clean();
    }
    public static function routes() {
        if(empty(self::settings()['public_api']))return;
        register_rest_route('bigshor/v1','/knowledge',array('methods'=>'GET','callback'=>array(__CLASS__,'api_knowledge'),'permission_callback'=>array(__CLASS__,'api_rate_permission'),'args'=>array('search'=>array('sanitize_callback'=>'sanitize_text_field'),'page'=>array('default'=>1,'sanitize_callback'=>'absint'),'per_page'=>array('default'=>20,'sanitize_callback'=>'absint'),'category'=>array('sanitize_callback'=>'sanitize_title'))));
        register_rest_route('bigshor/v1','/knowledge/(?P<id>\d+)',array('methods'=>'GET','callback'=>array(__CLASS__,'api_item'),'permission_callback'=>array(__CLASS__,'api_rate_permission')));
    }
    public static function api_rate_permission($request) {
        $ip=isset($_SERVER['REMOTE_ADDR'])?sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])):'unknown';$key='bsw_api_rl_'.substr(hash('sha256',$ip),0,24);$n=(int)get_transient($key);$limit=max(30,min(600,absint(self::settings()['api_rate_limit'])));
        if($n >= $limit)return new WP_Error('bigshor_rate_limited','API request limit reached. Try again later.',array('status'=>429,'retry_after'=>HOUR_IN_SECONDS));
        set_transient($key,$n+1,HOUR_IN_SECONDS);return true;
    }
    public static function api_knowledge($request) {
        $args=array('post_type'=>self::article_types(),'post_status'=>'publish','posts_per_page'=>max(1,min(50,absint($request['per_page']))),'paged'=>max(1,absint($request['page'])),'s'=>sanitize_text_field($request['search']));
        if(!empty($request['category']))$args['tax_query']=array(array('taxonomy'=>'bsw_knowledge_category','field'=>'slug','terms'=>sanitize_title($request['category'])));
        $q=new WP_Query($args);$items=array();foreach($q->posts as $p)$items[]=self::public_item($p);
        return rest_ensure_response(array('items'=>$items,'page'=>(int)$args['paged'],'per_page'=>(int)$args['posts_per_page'],'total'=>(int)$q->found_posts,'total_pages'=>(int)$q->max_num_pages));
    }
    public static function api_item($request) { $p=get_post(absint($request['id']));if(!$p||$p->post_status!=='publish'||!in_array($p->post_type,self::article_types(),true))return new WP_Error('not_found','Published knowledge article not found.',array('status'=>404));return rest_ensure_response(self::public_item($p)); }
    private static function public_item($p) { return array('id'=>(int)$p->ID,'type'=>$p->post_type,'slug'=>$p->post_name,'title'=>get_the_title($p),'excerpt'=>has_excerpt($p)?get_the_excerpt($p):wp_trim_words(wp_strip_all_tags($p->post_content),35),'url'=>get_permalink($p),'modified'=>get_post_modified_time('c',true,$p),'categories'=>wp_get_post_terms($p->ID,'bsw_knowledge_category',array('fields'=>'names'))); }
    public static function seo_meta() {
        if(!is_singular(self::article_types())||defined('WPSEO_VERSION')||defined('RANK_MATH_VERSION')||defined('AIOSEO_VERSION'))return;$id=get_queried_object_id();if(!$id)return;$title=get_the_title($id);$desc=has_excerpt($id)?get_the_excerpt($id):wp_trim_words(wp_strip_all_tags(get_post_field('post_content',$id)),28);$url=get_permalink($id);
        echo "\n<meta name=\"description\" content=\"".esc_attr(wp_strip_all_tags($desc))."\">\n<meta property=\"og:type\" content=\"article\">\n<meta property=\"og:title\" content=\"".esc_attr($title)."\">\n<meta property=\"og:description\" content=\"".esc_attr(wp_strip_all_tags($desc))."\">\n<meta property=\"og:url\" content=\"".esc_url($url)."\">\n<link rel=\"canonical\" href=\"".esc_url($url)."\">\n";
    }
    public static function title_parts($parts) { if(is_singular(self::article_types()))$parts['title']=get_the_title();return $parts; }
    public static function robots_txt($output,$public) { if(!$public)return $output;return $output."\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n"; }
    public static function security_headers() {
        if(is_admin()||!self::settings()['security_headers']||headers_sent())return;
        if(!is_ssl())return;
        header('X-Content-Type-Options: nosniff');header('Referrer-Policy: strict-origin-when-cross-origin');header('X-Frame-Options: SAMEORIGIN');header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    }
    public static function admin_menu() { add_submenu_page('options-general.php','BIGSHOR Platform 11–15','BIGSHOR Platform 11–15','manage_options','bsw-p1115-platform',array(__CLASS__,'settings_page')); }
    public static function register_settings() { register_setting('bsw_p1115_platform_group',self::OPTION,array('sanitize_callback'=>array(__CLASS__,'sanitize_settings'))); }
    public static function sanitize_settings($in) { $d=self::defaults();$in=is_array($in)?$in:array();$out=$d;$out['talk_pages']=empty($in['talk_pages'])?0:1;$out['public_api']=empty($in['public_api'])?0:1;$out['security_headers']=empty($in['security_headers'])?0:1;$out['api_rate_limit']=max(30,min(600,absint(isset($in['api_rate_limit'])?$in['api_rate_limit']:120)));$valid=get_post_types(array('public'=>true),'names');$out['search_types']=isset($in['search_types'])?array_values(array_intersect(array_map('sanitize_key',(array)$in['search_types']),$valid)):$d['search_types'];return $out; }
    public static function settings_page() { if(!current_user_can('manage_options'))return;$s=self::settings();echo '<div class="wrap"><h1>BIGSHOR Platform — Phases 11–15</h1><form method="post" action="options.php">';settings_fields('bsw_p1115_platform_group');$n=self::OPTION;
        foreach(array('talk_pages'=>'Enable article talk pages','public_api'=>'Enable read-only knowledge API','security_headers'=>'Enable conservative HTTPS security headers') as $k=>$label)echo '<p><label><input type="checkbox" name="'.esc_attr($n).'['.$k.']" value="1" '.checked($s[$k],1,false).'> '.esc_html($label).'</label></p>';
        echo '<p><label>API requests per IP per hour <input type="number" min="30" max="600" name="'.esc_attr($n).'[api_rate_limit]" value="'.absint($s['api_rate_limit']).'"></label></p><p class="description">The API is read-only and returns published BIGSHOR articles only. Rate limits use a hashed client address in transient keys.</p>';submit_button('Save platform settings');echo '</form><h2>Available shortcodes</h2><code>[bsw_knowledge_navigation]</code> <code>[bsw_knowledge_search]</code> <code>[bsw_article_talk]</code><h2>API</h2><code>/wp-json/bigshor/v1/knowledge</code> and <code>/wp-json/bigshor/v1/knowledge/ARTICLE_ID</code></div>'; }
}
BSW_Phases_11_15_Platform::init();
register_activation_hook(dirname(__FILE__,2).'/bigshor-wiki-core.php',array('BSW_Phases_11_15_Platform','activate'));
}
