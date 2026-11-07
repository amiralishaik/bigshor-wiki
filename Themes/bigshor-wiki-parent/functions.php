<?php
if (!defined('ABSPATH')) exit;

/** BIGSHOR Wiki stable parent theme. Persistent content types and platform engine live here. */
if (!defined('BSW_VERSION')) define('BSW_VERSION', '23.5.0');
if (!defined('BSW_THEME_DIR')) define('BSW_THEME_DIR', get_template_directory());
if (!defined('BSW_THEME_URL')) define('BSW_THEME_URL', get_template_directory_uri());

function bigshor_wiki_setup() {
    load_theme_textdomain('bigshor-wiki', get_template_directory() . '/languages');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('html5', array('search-form','comment-form','comment-list','gallery','caption','style','script'));
    add_theme_support('align-wide');
    add_theme_support('responsive-embeds');
    add_theme_support('custom-logo', array('height'=>80,'width'=>260,'flex-height'=>true,'flex-width'=>true));
    register_nav_menus(array('primary'=>__('Wiki navigation','bigshor-wiki'),'footer'=>__('Footer navigation','bigshor-wiki')));
    add_theme_support('customize-selective-refresh-widgets');
    add_theme_support('editor-styles');
    add_editor_style('style.css');
}
add_action('after_setup_theme','bigshor_wiki_setup');

/** Return complete, safe presentation defaults even if the Core settings API is unavailable. */
if (!function_exists('bsw_theme_get_settings')) {
function bsw_theme_get_settings() {
    $defaults = array(
        'brand_name' => 'BIGSHOR Wiki', 'tagline' => 'Knowledge. Context. Discovery.',
        'home_intro' => 'An independent knowledge platform for discovering people, ideas, organisations and events.',
        'show_header_search' => '1', 'show_sidebar' => '1', 'show_footer_menu' => '1',
        'show_home_featured' => '1', 'show_home_recent' => '1', 'show_home_contribute' => '1',
        'show_home_discovery' => '1', 'show_home_stats' => '1', 'show_article_tools' => '1',
        'show_edit_proposal' => '1', 'show_report' => '1', 'show_related' => '1',
        'show_breadcrumbs' => '1', 'accent' => '#172b4d'
    );
    $settings = function_exists('bsw_get_settings') ? bsw_get_settings() : array();
    return wp_parse_args(is_array($settings) ? $settings : array(), $defaults);
}
}

function bigshor_wiki_load_core() {
    // The independent BIGSHOR Wiki Core plugin owns content and community logic.
    // Keep the bundled engine as a backwards-compatible fallback for older installs.
    if (defined('BSW_CORE_PLUGIN_ACTIVE') && BSW_CORE_PLUGIN_ACTIVE) return;
    $base = get_template_directory() . '/inc/wiki-core/';
    foreach (array('core.php','shortcodes.php','community.php','media.php','discovery.php','enhancements.php','advanced-platform.php','admin-auth.php','founders.php','complete-features.php','v13-intelligence.php') as $file) {
        $path = $base . $file;
        if (file_exists($path)) require_once $path;
    }
    if (function_exists('bsw_v3_register_types')) add_action('init','bsw_v3_register_types');
    // Theme switching must not create, rewrite, or delete persistent wiki content.
    // Core plugin activation owns any required setup and rewrite flushing.
}
add_action('after_setup_theme','bigshor_wiki_load_core',20);


function bigshor_wiki_assets(){
    $theme_dir = get_template_directory();
    $theme_uri = get_template_directory_uri();
    $parent_css = $theme_dir . '/style.css';
    $core_css = $theme_dir . '/assets/wiki-core.css';
    $parent_version = file_exists($parent_css) ? (string) filemtime($parent_css) : BSW_VERSION;
    $core_version = file_exists($core_css) ? (string) filemtime($core_css) : BSW_VERSION;

    wp_enqueue_style('bigshor-wiki-parent-style', $theme_uri . '/style.css', array(), $parent_version);
    wp_enqueue_style('bigshor-wiki-core', $theme_uri . '/assets/wiki-core.css', array('bigshor-wiki-parent-style'), $core_version);
}
add_action('wp_enqueue_scripts','bigshor_wiki_assets');

function bsw_theme_enqueue_enhancements() {
    $file = get_template_directory() . '/assets/wiki-enhancements.js';
    if ( file_exists( $file ) ) {
        wp_enqueue_script( 'bigshor-wiki-enhancements', get_template_directory_uri() . '/assets/wiki-enhancements.js', array(), (string) filemtime( $file ), true );
    }
}
add_action( 'wp_enqueue_scripts', 'bsw_theme_enqueue_enhancements' );

function bigshor_wiki_sidebar_fallback(){
    $s=bsw_theme_get_settings();
    $links=array('Main page'=>home_url('/'),'All articles'=>home_url('/wiki/'),'BIGSHOR News'=>get_post_type_archive_link('bsw_news') ?: home_url('/news/'));
    foreach(array('show_topics'=>'Browse topics|/browse-topics/','show_az'=>'A–Z index|/a-z-index/','show_advanced_search'=>'Advanced search|/wiki-search/','show_featured'=>'Featured articles|/featured-articles/','show_random'=>'Random article|/random-article/','show_entities'=>'Knowledge entities|/knowledge-entities/') as $key=>$item){list($label,$path)=explode('|',$item);if(($s[$key]??'1')==='1')$links[$label]=home_url($path);}
    if(($s['show_founders']??'1')==='1')$links['50 notable founders']=home_url('/founders/');
    if(($s['show_community_links']??'1')==='1'){if(($s['show_article_submission']??'1')==='1')$links['Submit an article']=home_url('/submit-article/');if(($s['show_recent_changes']??'1')==='1')$links['Recent changes']=home_url('/recent-changes/');$links['Community portal']=home_url('/community-portal/');if(($s['show_contributors']??'1')==='1')$links['Contributors']=home_url('/wiki-contributors/');if(($s['show_watchlist']??'1')==='1')$links['Watchlist']=home_url('/my-watchlist/');$links['Contributor guide']=home_url('/contributor-guide/');$links['Help center']=home_url('/help-center/');}
    if(($s['show_media_links']??'1')==='1'){$links['Media library']=home_url('/media-library/');if(($s['show_commons']??'1')==='1')$links['BIGSHOR Commons']=home_url('/commons/');}
    if(($s['show_language_links']??'1')==='1')$links['Languages']=home_url('/wiki-languages/');
    if(($s['show_stats_links']??'1')==='1')$links['Wiki statistics']=home_url('/wiki-statistics/');
    $links['About']=home_url('/about/');$links['Editorial policy']=home_url('/editorial-policy/');
    echo '<ul class="side-nav">';foreach($links as $label=>$url)echo '<li><a href="'.esc_url($url).'">'.esc_html($label).'</a></li>';echo '</ul>';
}
function bigshor_wiki_breadcrumbs(){
    if(is_front_page()) return;
    echo '<nav class="breadcrumbs" aria-label="Breadcrumb"><a href="'.esc_url(home_url('/')).'">'.esc_html__('Main page','bigshor-wiki').'</a> › ';
    if(is_singular('wiki_article')) echo '<a href="'.esc_url(get_post_type_archive_link('wiki_article')).'">'.esc_html__('Articles','bigshor-wiki').'</a> › ';
    if(is_post_type_archive('bsw_news') || is_singular('bsw_news') || is_tax('bsw_news_category')) {
        echo '<a href="'.esc_url(get_post_type_archive_link('bsw_news')).'">'.esc_html__('News','bigshor-wiki').'</a> › ';
    }
    echo esc_html(wp_get_document_title()).'</nav>';
}

// Indexable, entity-aware metadata. Facts are taken only from WordPress content/settings.
function bsw_theme_schema_output(){
    // Avoid duplicate graph output when a recognised SEO plugin owns structured data.
    if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('AIOSEO_VERSION') || class_exists('AIOSEO\\\\Plugin\\\\AIOSEO')) return;
    if (is_admin() || is_feed() || is_search() || is_404()) return;
    $site_name = get_bloginfo('name');
    $site_url = home_url('/');
    $graph = array();
    $graph[] = array('@type'=>'Organization','@id'=>trailingslashit($site_url).'#organization','name'=>$site_name,'url'=>$site_url,'logo'=>array('@type'=>'ImageObject','url'=>get_site_icon_url() ?: ''));
    $graph[] = array('@type'=>'WebSite','@id'=>trailingslashit($site_url).'#website','url'=>$site_url,'name'=>$site_name,'publisher'=>array('@id'=>trailingslashit($site_url).'#organization'),'potentialAction'=>array('@type'=>'SearchAction','target'=>array('@type'=>'EntryPoint','urlTemplate'=>home_url('/?s={search_term_string}')),'query-input'=>'required name=search_term_string'));
    if (is_singular('wiki_article') || is_singular('post') || is_singular('bsw_news') || is_page()) {
        $post = get_queried_object();
        if ($post instanceof WP_Post) {
            $desc = has_excerpt($post) ? get_the_excerpt($post) : wp_trim_words(wp_strip_all_tags(strip_shortcodes($post->post_content)), 45);
            $schema_type = is_page() ? 'WebPage' : (is_singular('bsw_news') ? 'NewsArticle' : 'Article');
            $article = array('@type'=>$schema_type,'@id'=>get_permalink($post).'#article','mainEntityOfPage'=>get_permalink($post),'headline'=>get_the_title($post),'description'=>$desc,'datePublished'=>get_post_time('c',true,$post),'dateModified'=>get_post_modified_time('c',true,$post),'author'=>array('@type'=>'Person','name'=>get_the_author_meta('display_name',$post->post_author)),'publisher'=>array('@id'=>trailingslashit($site_url).'#organization'),'inLanguage'=>get_bloginfo('language'));
            if (has_post_thumbnail($post)) { $article['image']=array(get_the_post_thumbnail_url($post,'full')); if (is_page()) $article['primaryImageOfPage']=get_the_post_thumbnail_url($post,'full'); }
            $graph[]=$article;
        }
    }
    if (is_singular('wiki_entity')) {
        $entity = get_queried_object();
        if ($entity instanceof WP_Post) {
            $terms = wp_get_object_terms($entity->ID, 'wiki_entity_type', array('fields'=>'names'));
            $type = 'Thing';
            if (!is_wp_error($terms) && $terms) {
                $candidate = strtolower($terms[0]);
                if (in_array($candidate, array('person','people'), true)) $type = 'Person';
                elseif (in_array($candidate, array('organisation','organization','company','brand'), true)) $type = 'Organization';
                elseif (in_array($candidate, array('place','location'), true)) $type = 'Place';
            }
            $item = array('@type'=>$type,'@id'=>get_permalink($entity).'#entity','name'=>get_the_title($entity),'url'=>get_permalink($entity),'description'=>has_excerpt($entity)?get_the_excerpt($entity):wp_trim_words(wp_strip_all_tags($entity->post_content),45),'identifier'=>get_post_meta($entity->ID,'_bsw_entity_id',true));
            $aliases = preg_split('/\r\n|\r|\n/', (string)get_post_meta($entity->ID,'_bsw_entity_aliases',true));
            $aliases = array_values(array_filter(array_map('trim',$aliases)));
            if ($aliases) $item['alternateName']=$aliases;
            $refs = preg_split('/\r\n|\r|\n/', (string)get_post_meta($entity->ID,'_bsw_entity_references',true));
            $same_as = array_values(array_filter($refs, function($url){return wp_http_validate_url(trim($url)) && strpos(trim($url),'https://')===0;}));
            if ($same_as) $item['sameAs']=$same_as;
            $graph[]=$item;
        }
    }
    echo "\n<script type=\"application/ld+json\">".wp_json_encode(array('@context'=>'https://schema.org','@graph'=>$graph),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."</script>\n";
}
add_action('wp_head','bsw_theme_schema_output',30);

// Helpful site-wide metadata without overriding SEO plugins when one is active.
function bsw_theme_meta_description(){
    if (is_admin() || defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || class_exists('AIOSEO\\Plugin\\AIOSEO')) return;
    if (is_singular()) { $p=get_queried_object(); if($p instanceof WP_Post) { $d=has_excerpt($p)?get_the_excerpt($p):wp_trim_words(wp_strip_all_tags(strip_shortcodes($p->post_content)),30); if($d) echo '<meta name="description" content="'.esc_attr($d).'">'."\n"; } }
}
add_action('wp_head','bsw_theme_meta_description',5);

// Allow searching wiki articles and media alongside standard WordPress content.
add_filter('pre_get_posts',function($q){ if(!is_admin() && $q->is_main_query() && $q->is_search()) $q->set('post_type',array('post','page','wiki_article','wiki_media','bsw_news')); });

// Keep the site usable with no menu configured.
function bigshor_wiki_primary_menu(){
    if(has_nav_menu('primary')){wp_nav_menu(array('theme_location'=>'primary','container'=>false,'menu_class'=>'wiki-primary-menu'));return;}
    $s=bsw_theme_get_settings();$links=array('Main page'=>home_url('/'),'Articles'=>home_url('/wiki/'),'News'=>get_post_type_archive_link('bsw_news') ?: home_url('/news/'));
    foreach(array('show_topics'=>'Topics|/browse-topics/','show_az'=>'A–Z|/a-z-index/','show_advanced_search'=>'Search|/wiki-search/','show_featured'=>'Featured|/featured-articles/','show_random'=>'Random|/random-article/','show_entities'=>'Entities|/knowledge-entities/') as $key=>$item){list($label,$path)=explode('|',$item);if(($s[$key]??'1')==='1')$links[$label]=home_url($path);}
    if(($s['show_founders']??'1')==='1')$links['Founders']=home_url('/founders/');if(($s['show_community_links']??'1')==='1')$links['Community']=home_url('/community-portal/');
    echo '<ul class="wiki-primary-menu">';foreach($links as $label=>$url)echo '<li><a href="'.esc_url($url).'">'.esc_html($label).'</a></li>';echo '</ul>';
}

// Make the separate News division discoverable even when a custom primary menu is configured.
add_filter('wp_nav_menu_items', function($items, $args) {
    if (isset($args->theme_location) && 'primary' === $args->theme_location && post_type_exists('bsw_news')) {
        $news_url = get_post_type_archive_link('bsw_news');
        if ($news_url && false === strpos($items, esc_url($news_url))) {
            $items .= '<li class="menu-item menu-item-type-post_type_archive menu-item-object-bsw_news"><a href="' . esc_url($news_url) . '">' . esc_html__('News', 'bigshor-wiki') . '</a></li>';
        }
    }
    return $items;
}, 20, 2);

function bsw_theme_dynamic_styles(){
 $defaults=array('accent'=>'#202122','show_breadcrumbs'=>'1');
 $s=wp_parse_args(bsw_theme_get_settings(),$defaults);
 $accent=sanitize_hex_color($s['accent'])?:'#202122';
 echo '<style>:root{--bsw-accent:'.esc_html($accent).'} .wiki-button,.bsw-auth button,.wiki-primary-menu a:hover{border-color:var(--bsw-accent)} .brand-mark{background:var(--bsw-accent)} '.($s['show_breadcrumbs']!=='1'?'.breadcrumbs{display:none!important;}':'').'</style>';
}
add_action('wp_head','bsw_theme_dynamic_styles',40);


// Publish language alternates for articles explicitly linked by a translation-group ID.
function bsw_theme_hreflang_alternates(){
    if(!is_singular('wiki_article')) return;
    $id=get_queried_object_id();
    $group=(string)get_post_meta($id,'_bsw_translation_group',true);
    if($group==='') return;
    $posts=get_posts(array('post_type'=>'wiki_article','post_status'=>'publish','numberposts'=>30,'meta_key'=>'_bsw_translation_group','meta_value'=>$group));
    foreach($posts as $translation){
        $lang=sanitize_text_field(get_post_meta($translation->ID,'_bsw_language_code',true));
        if($lang && preg_match('/^[a-z]{2,3}(-[A-Za-z0-9]{2,8})?$/',$lang)) echo '<link rel="alternate" hreflang="'.esc_attr($lang).'" href="'.esc_url(get_permalink($translation)).'" />'."\n";
    }
}
add_action('wp_head','bsw_theme_hreflang_alternates',8);


/* BIGSHOR Wiki Theme 18.0 — presentation-only framework enhancements. */
function bsw_theme_v18_body_classes($classes) {
    $classes[] = 'bsw-theme-v18';
    if (is_singular('wiki_article')) $classes[] = 'bsw-reading-view';
    return $classes;
}
add_filter('body_class', 'bsw_theme_v18_body_classes');

function bsw_theme_v18_customize_register($wp_customize) {
    $wp_customize->add_section('bsw_theme_v18', array('title'=>__('BIGSHOR Theme Design','bigshor-wiki'),'priority'=>35));
    $wp_customize->add_setting('bsw_theme_accent', array('default'=>'#C49A55','sanitize_callback'=>'sanitize_hex_color','transport'=>'refresh'));
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize,'bsw_theme_accent',array('label'=>__('Accent colour','bigshor-wiki'),'section'=>'bsw_theme_v18')));
    $wp_customize->add_setting('bsw_theme_content_width', array('default'=>'1460','sanitize_callback'=>function($v){$v=absint($v);return min(1800,max(960,$v));}));
    $wp_customize->add_control('bsw_theme_content_width',array('label'=>__('Maximum layout width (px)','bigshor-wiki'),'section'=>'bsw_theme_v18','type'=>'number','input_attrs'=>array('min'=>960,'max'=>1800,'step'=>20)));
}
add_action('customize_register','bsw_theme_v18_customize_register');

function bsw_theme_v18_customizer_css() {
    $accent = sanitize_hex_color(get_theme_mod('bsw_theme_accent','#C49A55')) ?: '#C49A55';
    $width = absint(get_theme_mod('bsw_theme_content_width',1460));
    $width = min(1800,max(960,$width));
    echo '<style id="bsw-theme-v18-customizer">:root{--bsw-theme-accent:'.esc_html($accent).';--bsw-layout-width:'.$width.'px}</style>';
}
add_action('wp_head','bsw_theme_v18_customizer_css',50);

function bsw_theme_v18_dark_mode_control() { /* Dark mode intentionally disabled for BIGSHOR brand consistency. */ }


function bsw_theme_v18_reading_progress() {
    if (!is_singular(array('wiki_article','post'))) return;
    echo '<div class="bsw-reading-progress" aria-hidden="true"><span></span></div>';
}
add_action('wp_body_open','bsw_theme_v18_reading_progress',5);

function bsw_theme_v18_search_filter($query) {
    if (!is_admin() && $query->is_main_query() && $query->is_search() && empty($_GET['post_type'])) {
        $query->set('post_type',array('wiki_article','wiki_entity','post','page'));
    }
}
add_action('pre_get_posts','bsw_theme_v18_search_filter');

function bsw_theme_v18_excerpt_more($more) { return '…'; }
add_filter('excerpt_more','bsw_theme_v18_excerpt_more');

/** Remove accidental literal \\n prefixes emitted before the document by legacy integrations. */
function bsw_clean_document_prefix_buffer($html) {
    if (!is_string($html) || $html === '') return $html;
    return preg_replace('/^(?:\\\\[nr])+(?=\s*<!doctype|\s*<html)/i', '', $html, 1);
}
function bsw_start_document_prefix_buffer() {
    if (!is_admin() && !is_feed() && !is_robots() && !is_trackback()) ob_start('bsw_clean_document_prefix_buffer');
}
add_action('init', 'bsw_start_document_prefix_buffer', 0);

/** Phase 68: native responsive image sizing, modern image attributes and script scheduling. */
add_action('after_setup_theme', function () {
    add_image_size('bsw-card-480', 480, 320, true);
    add_image_size('bsw-card-768', 768, 512, true);
    add_image_size('bsw-article-1200', 1200, 0, false);
});
add_filter('wp_get_attachment_image_attributes', function ($attr) {
    if (empty($attr['decoding'])) $attr['decoding'] = 'async';
    if (empty($attr['sizes'])) $attr['sizes'] = '(max-width: 640px) 100vw, (max-width: 1100px) 90vw, 1100px';
    return $attr;
}, 20);
add_filter('script_loader_tag', function ($tag, $handle) {
    if ($handle === 'bigshor-wiki-enhancements' && strpos($tag, ' defer') === false && strpos($tag, ' async') === false) {
        return str_replace(' src=', ' defer src=', $tag);
    }
    return $tag;
}, 10, 2);

/** Open Graph/Twitter metadata for article and page discovery; SEO plugins retain ownership. */
function bsw_theme_social_metadata() {
    if (is_admin() || is_feed() || is_search() || is_404()) return;
    if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('AIOSEO_VERSION') || class_exists('AIOSEO\\Plugin\\AIOSEO')) return;
    $title = wp_get_document_title();
    $url = is_singular() ? get_permalink() : home_url('/');
    $description = '';
    $image = '';
    if (is_singular()) {
        $post = get_queried_object();
        if ($post instanceof WP_Post) {
            $description = has_excerpt($post) ? get_the_excerpt($post) : wp_trim_words(wp_strip_all_tags(strip_shortcodes($post->post_content)), 32);
            if (has_post_thumbnail($post)) $image = get_the_post_thumbnail_url($post, 'large');
        }
    }
    if (!$description) $description = get_bloginfo('description');
    echo '<meta property="og:type" content="'.esc_attr(is_singular('bsw_news')?'article':'website').'">' . "\n";
    echo '<meta property="og:site_name" content="'.esc_attr(get_bloginfo('name')).'">' . "\n";
    echo '<meta property="og:title" content="'.esc_attr($title).'">' . "\n";
    echo '<meta property="og:description" content="'.esc_attr(wp_strip_all_tags($description)).'">' . "\n";
    echo '<meta property="og:url" content="'.esc_url($url).'">' . "\n";
    if ($image) echo '<meta property="og:image" content="'.esc_url($image).'">' . "\n";
    echo '<meta name="twitter:card" content="'.esc_attr($image?'summary_large_image':'summary').'">' . "\n";
}
add_action('wp_head', 'bsw_theme_social_metadata', 8);


/** Keep utility account forms out of thin-content search results and XML sitemaps. */
add_filter('wp_robots', function($robots) {
    if (is_page()) {
        $slug = (string)get_post_field('post_name', get_queried_object_id());
        if ($slug === 'login' || $slug === 'register' || strpos($slug, 'register-') === 0 || in_array($slug, array('my-account','account-verification','reset-password'), true)) {
            $robots['noindex'] = true;
            $robots['follow'] = true;
        }
    }
    return $robots;
});
add_filter('wp_sitemaps_posts_query_args', function($args, $post_type) {
    if ($post_type === 'page') {
        $args['post__not_in'] = array_merge(isset($args['post__not_in'])?(array)$args['post__not_in']:array(), get_posts(array('post_type'=>'page','post_status'=>'publish','fields'=>'ids','numberposts'=>-1,'meta_key'=>'_bsw68_managed_registration_page')));
    }
    return $args;
}, 10, 2);
