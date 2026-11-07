<?php
/**
 * BIGSHOR Wiki Phases 11–15: Intelligence, Contributors, Globalisation,
 * Research Infrastructure and Ecosystem APIs.
 * PHP 7.4 compatible. All public endpoints are read-only.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('BSW_Phases_11_15')) {
final class BSW_Phases_11_15 {
    const VERSION = '25.0.0';
    const OPTION = 'bsw_phases_11_15_settings';

    public static function init() {
        add_action('init', array(__CLASS__, 'register_types'));
        add_action('init', array(__CLASS__, 'register_shortcodes'));
        add_action('add_meta_boxes', array(__CLASS__, 'meta_boxes'));
        add_action('save_post', array(__CLASS__, 'save_meta'), 10, 2);
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 44);
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
        add_action('show_user_profile', array(__CLASS__, 'user_fields'));
        add_action('edit_user_profile', array(__CLASS__, 'user_fields'));
        add_action('personal_options_update', array(__CLASS__, 'save_user_fields'));
        add_action('edit_user_profile_update', array(__CLASS__, 'save_user_fields'));
        add_action('transition_post_status', array(__CLASS__, 'publish_webhook'), 20, 3);
        add_action('admin_post_bsw_contributor_submit', array(__CLASS__, 'contributor_submit'));
    }

    public static function defaults() {
        return array(
            'ai_tools' => 1, 'contributors' => 1, 'translations' => 1,
            'research' => 1, 'public_api' => 1, 'webhooks' => 0,
            'webhook_url' => '', 'webhook_secret' => ''
        );
    }
    public static function settings() {
        $v = get_option(self::OPTION, array());
        return wp_parse_args(is_array($v) ? $v : array(), self::defaults());
    }
    public static function activate() {
        self::register_types();
        flush_rewrite_rules();
        if (!get_option(self::OPTION)) add_option(self::OPTION, self::defaults(), '', false);
    }
    public static function register_types() {
        $s = self::settings();
        if (!empty($s['research'])) {
            register_post_type('bsw_source', array(
                'labels' => array('name' => 'Research Sources', 'singular_name' => 'Research Source'),
                'public' => true, 'show_in_rest' => true, 'has_archive' => true,
                'supports' => array('title','editor','excerpt','author','revisions'),
                'menu_icon' => 'dashicons-book-alt', 'rewrite' => array('slug' => 'wiki-sources')
            ));
            register_post_type('bsw_resource', array(
                'labels' => array('name' => 'Knowledge Resources', 'singular_name' => 'Knowledge Resource'),
                'public' => true, 'show_in_rest' => true, 'has_archive' => true,
                'supports' => array('title','editor','excerpt','author','thumbnail','revisions'),
                'menu_icon' => 'dashicons-database', 'rewrite' => array('slug' => 'knowledge-resources')
            ));
        }
    }
    public static function register_shortcodes() {
        add_shortcode('bigshor_contributor_dashboard', array(__CLASS__, 'contributor_dashboard'));
        add_shortcode('bigshor_research_library', array(__CLASS__, 'research_library'));
        add_shortcode('bigshor_translation_links', array(__CLASS__, 'translation_links'));
        add_shortcode('bigshor_editorial_assistant', array(__CLASS__, 'editorial_assistant'));
    }
    public static function register_settings() {
        register_setting('bsw_p1115_group', self::OPTION, array('sanitize_callback' => array(__CLASS__, 'sanitize_settings')));
    }
    public static function sanitize_settings($in) {
        $in = is_array($in) ? $in : array(); $out = self::defaults();
        foreach (array('ai_tools','contributors','translations','research','public_api','webhooks') as $k) $out[$k] = empty($in[$k]) ? 0 : 1;
        $out['webhook_url'] = isset($in['webhook_url']) ? esc_url_raw(trim($in['webhook_url'])) : '';
        $out['webhook_secret'] = isset($in['webhook_secret']) ? sanitize_text_field($in['webhook_secret']) : '';
        if ($out['webhook_url'] && !wp_parse_url($out['webhook_url'], PHP_URL_HOST)) $out['webhook_url'] = '';
        return $out;
    }
    public static function admin_menu() {
        add_submenu_page('options-general.php', 'BIGSHOR Platform 11–15', 'BIGSHOR Platform 11–15', 'manage_options', 'bsw-p1115', array(__CLASS__, 'settings_page'));
    }
    public static function settings_page() {
        if (!current_user_can('manage_options')) return;
        $s = self::settings(); $name = self::OPTION;
        echo '<div class="wrap"><h1>BIGSHOR Platform — Phases 11–15</h1><p>Configure the intelligence, contributor, multilingual, research and API modules.</p><form method="post" action="options.php">';
        settings_fields('bsw_p1115_group');
        $labels = array('ai_tools'=>'Phase 11 — Editorial Intelligence tools','contributors'=>'Phase 12 — Contributor dashboard','translations'=>'Phase 13 — Translation management','research'=>'Phase 14 — Research library and resources','public_api'=>'Phase 15 — Read-only public API','webhooks'=>'Phase 15 — Outbound publish webhooks');
        foreach ($labels as $k=>$label) echo '<p><label><input type="checkbox" name="'.esc_attr($name).'['.$k.']" value="1" '.checked($s[$k],1,false).'> '.esc_html($label).'</label></p>';
        echo '<h2>Webhook configuration</h2><p><label>HTTPS endpoint URL<br><input class="regular-text" type="url" name="'.esc_attr($name).'[webhook_url]" value="'.esc_attr($s['webhook_url']).'"></label></p>';
        echo '<p><label>Signing secret<br><input class="regular-text" type="password" autocomplete="new-password" name="'.esc_attr($name).'[webhook_secret]" value="'.esc_attr($s['webhook_secret']).'"></label></p>';
        echo '<p class="description">Webhook requests include an X-BIGSHOR-Signature HMAC-SHA256 header. Use HTTPS and a long random secret. No webhook is sent unless enabled.</p>';
        submit_button('Save platform settings'); echo '</form></div>';
    }

    public static function meta_boxes() {
        $types = array('post','page','wiki_article');
        foreach ($types as $type) if (post_type_exists($type)) {
            add_meta_box('bsw_p1115_meta','BIGSHOR Knowledge Metadata',array(__CLASS__,'metadata_box'),$type,'side','default');
            add_meta_box('bsw_p1115_ai','BIGSHOR Editorial Assistant',array(__CLASS__,'ai_box'),$type,'normal','default');
        }
    }
    public static function metadata_box($post) {
        wp_nonce_field('bsw_p1115_meta','bsw_p1115_nonce');
        $group = get_post_meta($post->ID,'_bsw_translation_group',true);
        $lang = get_post_meta($post->ID,'_bsw_language',true);
        $source = get_post_meta($post->ID,'_bsw_primary_source_url',true);
        echo '<p><label>Language code<br><input class="widefat" name="bsw_language" value="'.esc_attr($lang).'" placeholder="en, ar, hi"></label></p>';
        echo '<p><label>Translation group ID<br><input class="widefat" name="bsw_translation_group" value="'.esc_attr($group).'" placeholder="shared-group-id"></label></p>';
        echo '<p><label>Primary source URL<br><input class="widefat" type="url" name="bsw_primary_source_url" value="'.esc_attr($source).'"></label></p>';
        echo '<p class="description">Use the same translation group ID for separately authored language versions.</p>';
    }
    public static function ai_box($post) {
        if (!current_user_can('edit_post',$post->ID)) return;
        $text = wp_strip_all_tags($post->post_title.' '.$post->post_content);
        $words = preg_split('/\s+/u', trim($text)); $count = count(array_filter($words));
        $sentences = preg_split('/(?<=[.!?])\s+/u', wp_strip_all_tags($post->post_content));
        $excerpt = trim(wp_strip_all_tags($post->post_excerpt));
        echo '<p><strong>Editorial checks (local heuristics; no external AI service)</strong></p><ul>';
        echo '<li>Approximate word count: '.absint($count).'</li>';
        echo '<li>Excerpt: '.($excerpt ? 'Present' : 'Missing — consider adding a concise summary').'</li>';
        echo '<li>Primary source: '.(get_post_meta($post->ID,'_bsw_primary_source_url',true) ? 'Provided' : 'Not recorded').'</li>';
        echo '<li>Sentence count: '.absint(count(array_filter($sentences))).'</li></ul>';
        echo '<p class="description">These checks are editorial prompts, not fact-checking, AI-generated research or a quality guarantee.</p>';
    }
    public static function save_meta($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id) || !isset($_POST['bsw_p1115_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_p1115_nonce'])),'bsw_p1115_meta')) return;
        if (!current_user_can('edit_post',$post_id)) return;
        $lang = isset($_POST['bsw_language']) ? sanitize_text_field(wp_unslash($_POST['bsw_language'])) : '';
        $group = isset($_POST['bsw_translation_group']) ? sanitize_text_field(wp_unslash($_POST['bsw_translation_group'])) : '';
        $url = isset($_POST['bsw_primary_source_url']) ? esc_url_raw(wp_unslash($_POST['bsw_primary_source_url'])) : '';
        update_post_meta($post_id,'_bsw_language',substr($lang,0,12));
        update_post_meta($post_id,'_bsw_translation_group',substr($group,0,100));
        update_post_meta($post_id,'_bsw_primary_source_url',$url);
    }
    public static function translation_links($atts) {
        if (empty(self::settings()['translations'])) return '';
        $atts = shortcode_atts(array('id'=>get_the_ID()),$atts,'bigshor_translation_links');
        $id = absint($atts['id']); $group = get_post_meta($id,'_bsw_translation_group',true);
        if (!$group) return '';
        $ids = get_posts(array('post_type'=>'any','post_status'=>'publish','numberposts'=>50,'meta_key'=>'_bsw_translation_group','meta_value'=>$group,'exclude'=>array($id)));
        if (!$ids) return '';
        $html = '<nav class="bsw-translation-links" aria-label="Available translations"><strong>Available languages:</strong><ul>';
        foreach ($ids as $p) $html .= '<li><a href="'.esc_url(get_permalink($p)).'">'.esc_html(get_post_meta($p->ID,'_bsw_language',true) ?: get_the_title($p)).'</a></li>';
        return $html.'</ul></nav>';
    }
    public static function user_fields($user) {
        if (!current_user_can('edit_user',$user->ID)) return;
        echo '<h2>BIGSHOR Contributor Profile</h2><table class="form-table"><tr><th><label for="bsw_expertise">Areas of expertise</label></th><td><input class="regular-text" id="bsw_expertise" name="bsw_expertise" value="'.esc_attr(get_user_meta($user->ID,'bsw_expertise',true)).'"></td></tr><tr><th><label for="bsw_public_author">Public contributor profile</label></th><td><label><input type="checkbox" id="bsw_public_author" name="bsw_public_author" value="1" '.checked(get_user_meta($user->ID,'bsw_public_author',true),1,false).'> Show contributor profile</label></td></tr></table>';
    }
    public static function save_user_fields($user_id) {
        if (!current_user_can('edit_user',$user_id)) return;
        update_user_meta($user_id,'bsw_expertise',isset($_POST['bsw_expertise']) ? sanitize_text_field(wp_unslash($_POST['bsw_expertise'])) : '');
        update_user_meta($user_id,'bsw_public_author',empty($_POST['bsw_public_author']) ? 0 : 1);
    }
    public static function contributor_dashboard() {
        if (empty(self::settings()['contributors'])) return '<p>Contributor dashboard is currently disabled.</p>';
        if (!is_user_logged_in()) return '<p>Please sign in to access the contributor dashboard.</p>';
        $user = wp_get_current_user();
        $posts = get_posts(array('author'=>$user->ID,'post_type'=>array('post','wiki_article'),'post_status'=>array('draft','pending','publish','future'),'numberposts'=>20,'orderby'=>'modified','order'=>'DESC'));
        $html = '<section class="bsw-contributor-dashboard"><h2>My contributions</h2><p>Signed in as '.esc_html($user->display_name).'</p><ul>';
        foreach ($posts as $p) $html .= '<li><a href="'.esc_url(get_edit_post_link($p->ID)).'">'.esc_html(get_the_title($p)).'</a> — '.esc_html($p->post_status).' — '.esc_html(get_the_modified_date('', $p)).'</li>';
        if (!$posts) $html .= '<li>No contributions yet.</li>';
        $html .= '</ul><h3>Submit an article for editorial review</h3><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw_contributor_submit">';
        ob_start(); wp_nonce_field('bsw_contributor_submit'); $html .= ob_get_clean();
        $html .= '<p><label>Title<br><input required name="bsw_title" maxlength="200" style="width:100%"></label></p><p><label>Article content<br><textarea required name="bsw_content" rows="8" style="width:100%"></textarea></label></p><p><button type="submit">Submit for review</button></p></form></section>';
        return $html;
    }
    public static function contributor_submit() {
        if (!is_user_logged_in() || empty(self::settings()['contributors'])) wp_die('Contributor submissions are unavailable.');
        check_admin_referer('bsw_contributor_submit');
        $title = isset($_POST['bsw_title']) ? sanitize_text_field(wp_unslash($_POST['bsw_title'])) : '';
        $content = isset($_POST['bsw_content']) ? wp_kses_post(wp_unslash($_POST['bsw_content'])) : '';
        if (!$title || !$content) wp_die('A title and article body are required.');
        $type = post_type_exists('wiki_article') ? 'wiki_article' : 'post';
        $id = wp_insert_post(array('post_type'=>$type,'post_status'=>'pending','post_title'=>$title,'post_content'=>$content,'post_author'=>get_current_user_id()),true);
        if (is_wp_error($id)) wp_die(esc_html($id->get_error_message()));
        wp_safe_redirect(add_query_arg('submitted','1',wp_get_referer() ?: home_url('/'))); exit;
    }
    public static function editorial_assistant() {
        if (empty(self::settings()['ai_tools'])) return '';
        if (!is_singular()) return '';
        $id = get_the_ID(); $text = wp_strip_all_tags(get_post_field('post_content',$id));
        $words = str_word_count($text); $excerpt = get_post_field('post_excerpt',$id);
        return '<aside class="bsw-editorial-assistant"><h3>Editorial checklist</h3><ul><li>'.absint($words).' words (approximate)</li><li>'.($excerpt ? 'Summary present' : 'Consider adding a summary').'</li><li>'.(get_post_meta($id,'_bsw_primary_source_url',true) ? 'Primary source recorded' : 'Add a primary source where appropriate').'</li></ul></aside>';
    }
    public static function research_library($atts) {
        if (empty(self::settings()['research']) || !post_type_exists('bsw_source')) return '';
        $atts = shortcode_atts(array('type'=>'bsw_source','limit'=>20),$atts,'bigshor_research_library');
        $type = in_array($atts['type'],array('bsw_source','bsw_resource'),true) ? $atts['type'] : 'bsw_source';
        $items = get_posts(array('post_type'=>$type,'post_status'=>'publish','numberposts'=>min(50,max(1,absint($atts['limit']))),'orderby'=>'date','order'=>'DESC'));
        $html = '<div class="bsw-research-library"><ul>';
        foreach ($items as $p) $html .= '<li><a href="'.esc_url(get_permalink($p)).'">'.esc_html(get_the_title($p)).'</a>'.($p->post_excerpt ? ' — '.esc_html($p->post_excerpt) : '').'</li>';
        if (!$items) $html .= '<li>No published resources available.</li>';
        return $html.'</ul></div>';
    }
    public static function register_routes() {
        if (empty(self::settings()['public_api'])) return;
        register_rest_route('bigshor/v1','/platform/contributors',array('methods'=>'GET','callback'=>array(__CLASS__,'api_contributors'),'permission_callback'=>'__return_true'));
        register_rest_route('bigshor/v1','/platform/research',array('methods'=>'GET','callback'=>array(__CLASS__,'api_research'),'permission_callback'=>'__return_true'));
        register_rest_route('bigshor/v1','/platform/translations/(?P<id>\d+)',array('methods'=>'GET','callback'=>array(__CLASS__,'api_translations'),'permission_callback'=>'__return_true'));
    }
    public static function api_contributors($request) {
        $users = get_users(array('meta_key'=>'bsw_public_author','meta_value'=>1,'number'=>min(50,max(1,absint($request->get_param('per_page') ?: 20)))));
        $out = array();
        foreach ($users as $u) $out[] = array('id'=>(int)$u->ID,'name'=>$u->display_name,'expertise'=>get_user_meta($u->ID,'bsw_expertise',true),'url'=>get_author_posts_url($u->ID));
        return rest_ensure_response($out);
    }
    public static function api_research($request) {
        $type = $request->get_param('type') === 'resource' ? 'bsw_resource' : 'bsw_source';
        $items = get_posts(array('post_type'=>$type,'post_status'=>'publish','numberposts'=>min(50,max(1,absint($request->get_param('per_page') ?: 20)))));
        $out = array();
        foreach ($items as $p) $out[] = array('id'=>(int)$p->ID,'title'=>get_the_title($p),'excerpt'=>get_the_excerpt($p),'url'=>get_permalink($p),'date'=>get_post_time('c',true,$p));
        return rest_ensure_response($out);
    }
    public static function api_translations($request) {
        $id = absint($request['id']); $group = get_post_meta($id,'_bsw_translation_group',true);
        if (!$group || get_post_status($id) !== 'publish') return new WP_Error('not_found','No published translation group found.',array('status'=>404));
        $posts = get_posts(array('post_type'=>'any','post_status'=>'publish','numberposts'=>50,'meta_key'=>'_bsw_translation_group','meta_value'=>$group));
        $out = array();
        foreach ($posts as $p) $out[] = array('id'=>(int)$p->ID,'language'=>get_post_meta($p->ID,'_bsw_language',true),'title'=>get_the_title($p),'url'=>get_permalink($p));
        return rest_ensure_response($out);
    }
    public static function publish_webhook($new, $old, $post) {
        $s = self::settings();
        if (empty($s['webhooks']) || empty($s['webhook_url']) || $new !== 'publish' || $old === 'publish' || !$post || wp_is_post_revision($post->ID) || wp_is_post_autosave($post->ID)) return;
        if (!wp_http_validate_url($s['webhook_url']) || wp_parse_url($s['webhook_url'],PHP_URL_SCHEME) !== 'https') return;
        $payload = wp_json_encode(array('event'=>'content.published','site'=>home_url('/'),'post'=>array('id'=>(int)$post->ID,'type'=>$post->post_type,'title'=>get_the_title($post),'url'=>get_permalink($post),'date'=>get_post_time('c',true,$post))));
        $secret = $s['webhook_secret']; if (!$secret) return;
        wp_remote_post($s['webhook_url'],array('timeout'=>5,'blocking'=>false,'headers'=>array('Content-Type'=>'application/json','X-BIGSHOR-Signature'=>'sha256='.hash_hmac('sha256',$payload,$secret)),'body'=>$payload));
    }
}
BSW_Phases_11_15::init();
register_activation_hook(dirname(__FILE__,2).'/bigshor-wiki-core.php',array('BSW_Phases_11_15','activate'));
}
