<?php
/**
 * BIGSHOR Wiki Phases 21–34 foundation suite.
 * Contributor ecosystem, AI-ready editorial tools, publisher tools,
 * trust, governance, API and privacy-conscious analytics foundations.
 * PHP 7.4+. External AI, payments, CDN and email services require configuration.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phases_21_34')) {
final class BSW_Phases_21_34 {
    const VERSION = '27.0.0';
    const OPTION = 'bsw_phases_21_34_settings';
    public static function init() {
        add_action('init', array(__CLASS__, 'types'));
        add_action('init', array(__CLASS__, 'shortcodes'));
        add_action('admin_menu', array(__CLASS__, 'menu'), 46);
        add_action('admin_init', array(__CLASS__, 'settings'));
        add_action('add_meta_boxes', array(__CLASS__, 'meta_boxes'));
        add_action('save_post', array(__CLASS__, 'save_meta'), 30, 2);
        add_action('admin_post_bsw_submit_idea', array(__CLASS__, 'submit_idea'));
        add_action('admin_post_nopriv_bsw_submit_idea', array(__CLASS__, 'login_required'));
        add_action('admin_post_bsw_report_content', array(__CLASS__, 'report_content'));
        add_action('admin_post_nopriv_bsw_report_content', array(__CLASS__, 'login_required'));
        add_action('transition_post_status', array(__CLASS__, 'record_publish'), 30, 3);
        add_action('rest_api_init', array(__CLASS__, 'routes'));
        add_action('wp_head', array(__CLASS__, 'analytics_ping'), 99);
    }
    public static function defaults() { return array('ai_provider'=>'','ai_endpoint'=>'','ai_key'=>'','ai_model'=>'','publisher_enabled'=>1,'ads_enabled'=>0,'analytics_enabled'=>1,'public_api'=>1,'verification_enabled'=>1); }
    public static function opts() { $v=get_option(self::OPTION,array()); return wp_parse_args(is_array($v)?$v:array(),self::defaults()); }
    public static function activate() { self::types(); if (!get_option(self::OPTION)) add_option(self::OPTION,self::defaults(),'','no'); }
    public static function types() {
        $types=array(
            'bsw_idea'=>array('Article Suggestions','Article Suggestion','private'),
            'bsw_content_report'=>array('Content Reports','Content Report','private'),
            'bsw_publisher'=>array('Publishers','Publisher','public'),
            'bsw_audit_event'=>array('Audit Events','Audit Event','private')
        );
        foreach($types as $type=>$v) if(!post_type_exists($type)) register_post_type($type,array('labels'=>array('name'=>$v[0],'singular_name'=>$v[1]),'public'=>$v[2]==='public','show_ui'=>true,'show_in_rest'=>$v[2]==='public','has_archive'=>$v[2]==='public','supports'=>array('title','editor','author','revisions'),'capability_type'=>'post','map_meta_cap'=>true,'menu_icon'=>'dashicons-'.($type==='bsw_publisher'?'groups':'clipboard')));
    }
    public static function shortcodes() {
        add_shortcode('bigshor_contributor_profile',array(__CLASS__,'profile'));
        add_shortcode('bigshor_article_suggestion_form',array(__CLASS__,'idea_form'));
        add_shortcode('bigshor_content_report_form',array(__CLASS__,'report_form'));
        add_shortcode('bigshor_ai_editorial_assistant',array(__CLASS__,'ai_assistant'));
        add_shortcode('bigshor_publisher_directory',array(__CLASS__,'publisher_directory'));
        add_shortcode('bigshor_ad_slot',array(__CLASS__,'ad_slot'));
        add_shortcode('bigshor_platform_stats',array(__CLASS__,'stats'));
    }
    public static function menu() { add_submenu_page('options-general.php','BIGSHOR Platform 21–34','BIGSHOR Platform 21–34','manage_options','bsw-p2134',array(__CLASS__,'settings_page')); }
    public static function settings() { register_setting('bsw_p2134_group',self::OPTION,array('sanitize_callback'=>array(__CLASS__,'sanitize'))); }
    public static function sanitize($in) {
        $in=is_array($in)?$in:array(); $o=self::defaults();
        foreach(array('publisher_enabled','ads_enabled','analytics_enabled','public_api','verification_enabled') as $k) $o[$k]=empty($in[$k])?0:1;
        $o['ai_provider']=sanitize_text_field($in['ai_provider']??''); $o['ai_endpoint']=esc_url_raw($in['ai_endpoint']??'');
        $o['ai_key']=sanitize_text_field($in['ai_key']??''); $o['ai_model']=sanitize_text_field($in['ai_model']??'');
        if($o['ai_endpoint'] && (!wp_http_validate_url($o['ai_endpoint']) || strtolower((string)wp_parse_url($o['ai_endpoint'],PHP_URL_SCHEME))!=='https')) $o['ai_endpoint']='';
        return $o;
    }
    public static function settings_page() {
        if(!current_user_can('manage_options')) return; $o=self::opts();
        echo '<div class="wrap"><h1>BIGSHOR Wiki Platform — Phases 21–34</h1><p>Configure optional integrations. AI and advertising remain disabled until deliberately configured.</p><form method="post" action="options.php">';
        settings_fields('bsw_p2134_group');
        foreach(array('publisher_enabled'=>'Enable publisher directory','ads_enabled'=>'Enable approved ad slots','analytics_enabled'=>'Enable aggregate page-view counters','public_api'=>'Enable read-only public API','verification_enabled'=>'Enable verification workflow') as $k=>$label) echo '<p><label><input type="checkbox" name="'.esc_attr(self::OPTION).'['.esc_attr($k).']" value="1" '.checked($o[$k],1,false).' /> '.esc_html($label).'</label></p>';
        echo '<h2>Optional AI editorial provider</h2><p>Use a compatible HTTPS endpoint. Requests are made only after an editor submits the assistant form. Never expose API keys in page content.</p>';
        foreach(array('ai_provider'=>'Provider name','ai_endpoint'=>'HTTPS endpoint','ai_model'=>'Model identifier','ai_key'=>'API key') as $k=>$label) echo '<p><label>'.esc_html($label).'<br><input class="regular-text" type="'.($k==='ai_key'?'password':'text').'" name="'.esc_attr(self::OPTION).'['.esc_attr($k).']" value="'.esc_attr($o[$k]).'" autocomplete="off" /></label></p>';
        submit_button(); echo '</form><hr><p><strong>Operational note:</strong> Payments, ad networks, email delivery, CDN, external AI providers and legal compliance require separate provider accounts, contracts and configuration.</p></div>';
    }
    public static function meta_boxes() { foreach(array('post','page','wiki_article','wiki_entity','bsw_publisher') as $t) if(post_type_exists($t)) add_meta_box('bsw_trust_meta','BIGSHOR Trust & Editorial Controls',array(__CLASS__,'meta_box'),$t,'side','default'); }
    public static function meta_box($post) {
        wp_nonce_field('bsw_trust_save','bsw_trust_nonce');
        $verified=get_post_meta($post->ID,'_bsw_verified',true); $source=get_post_meta($post->ID,'_bsw_verification_note',true); $review=get_post_meta($post->ID,'_bsw_next_review',true);
        echo '<p><label><input type="checkbox" name="bsw_verified" value="1" '.checked($verified,'1',false).' /> Mark as editorially verified</label></p>';
        echo '<p><label>Verification note<br><textarea name="bsw_verification_note" rows="3" style="width:100%">'.esc_textarea($source).'</textarea></label></p>';
        echo '<p><label>Next review date<br><input type="date" name="bsw_next_review" value="'.esc_attr($review).'" /></label></p>';
    }
    public static function save_meta($id,$post) {
        if(wp_is_post_revision($id)|| (defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE) || !isset($_POST['bsw_trust_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_trust_nonce'])),'bsw_trust_save') || !current_user_can('edit_post',$id)) return;
        update_post_meta($id,'_bsw_verified',isset($_POST['bsw_verified'])?'1':'0');
        update_post_meta($id,'_bsw_verification_note',sanitize_textarea_field(wp_unslash($_POST['bsw_verification_note']??'')));
        $date=sanitize_text_field(wp_unslash($_POST['bsw_next_review']??'')); update_post_meta($id,'_bsw_next_review',preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)?$date:'');
    }
    private static function log_event($title,$detail='') { $id=wp_insert_post(array('post_type'=>'bsw_audit_event','post_status'=>'private','post_title'=>sanitize_text_field($title),'post_content'=>sanitize_textarea_field($detail),'post_author'=>get_current_user_id()),true); return is_wp_error($id)?0:$id; }
    public static function submit_idea() {
        if(!is_user_logged_in()) self::login_required(); check_admin_referer('bsw_submit_idea');
        $title=sanitize_text_field(wp_unslash($_POST['idea_title']??'')); $detail=sanitize_textarea_field(wp_unslash($_POST['idea_detail']??''));
        if(!$title) wp_die('Please provide a suggestion title.');
        $id=wp_insert_post(array('post_type'=>'bsw_idea','post_status'=>'pending','post_title'=>$title,'post_content'=>$detail,'post_author'=>get_current_user_id()),true);
        if(is_wp_error($id)) wp_die('Unable to submit suggestion.'); self::log_event('Article suggestion submitted','Suggestion ID '.$id); wp_safe_redirect(add_query_arg('bsw_submitted','1',wp_get_referer()?:home_url('/'))); exit;
    }
    public static function idea_form() {
        if(!is_user_logged_in()) return '<p>Please sign in to suggest an article.</p>';
        $url=esc_url(admin_url('admin-post.php')); return '<form method="post" action="'.$url.'"><input type="hidden" name="action" value="bsw_submit_idea">'.wp_nonce_field('bsw_submit_idea','_wpnonce',true,false).'<p><label>Suggested article title<br><input required name="idea_title" maxlength="180"></label></p><p><label>Why should it be included?<br><textarea name="idea_detail" rows="4"></textarea></label></p><button type="submit">Submit suggestion</button></form>';
    }
    public static function report_content() {
        if(!is_user_logged_in()) self::login_required(); check_admin_referer('bsw_report_content'); $id=absint($_POST['reported_post']??0); $reason=sanitize_textarea_field(wp_unslash($_POST['report_reason']??''));
        if(!$id||get_post_status($id)!=='publish'||!$reason) wp_die('Please provide a valid article and reason.');
        $rid=wp_insert_post(array('post_type'=>'bsw_content_report','post_status'=>'pending','post_title'=>'Report: '.$id,'post_content'=>$reason,'post_author'=>get_current_user_id()),true);
        if(is_wp_error($rid)) wp_die('Unable to submit report.'); update_post_meta($rid,'_bsw_reported_post',$id); self::log_event('Content report submitted','Report ID '.$rid.' for post '.$id); wp_safe_redirect(get_permalink($id)); exit;
    }
    public static function report_form() { if(!is_user_logged_in()) return '<p>Please sign in to report content.</p>'; $id=get_the_ID(); if(!$id) return ''; return '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw_report_content"><input type="hidden" name="reported_post" value="'.absint($id).'">'.wp_nonce_field('bsw_report_content','_wpnonce',true,false).'<p><label>Describe the issue<br><textarea required name="report_reason" rows="3"></textarea></label></p><button type="submit">Report this content</button></form>'; }
    public static function login_required() { auth_redirect(); exit; }
    public static function record_publish($new,$old,$post) { if($new==='publish'&&$old!=='publish'&&!wp_is_post_revision($post->ID)&&$post->post_type!=='bsw_audit_event') self::log_event('Content published',$post->post_type.' #'.$post->ID); }
    public static function profile($atts) {
        $atts=shortcode_atts(array('user'=>get_current_user_id()),$atts); $u=get_user_by('id',absint($atts['user'])); if(!$u || !get_user_meta($u->ID,'bsw_public_profile',true)) return '';
        $q=new WP_Query(array('post_type'=>array('post','wiki_article','bsw_news'),'post_status'=>'publish','author'=>$u->ID,'posts_per_page'=>10,'no_found_rows'=>true));
        $html='<section class="bsw-contributor-profile"><h2>'.esc_html($u->display_name).'</h2><p>'.esc_html(get_user_meta($u->ID,'description',true)).'</p><h3>Published contributions</h3><ul>';
        while($q->have_posts()){ $q->the_post(); $html.='<li><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a></li>'; } wp_reset_postdata(); return $html.'</ul></section>';
    }
    public static function ai_assistant() {
        if(!current_user_can('edit_posts')) return '<p>Editorial access is required.</p>'; $o=self::opts();
        if(!$o['ai_endpoint']||!$o['ai_key']) return '<p>The AI editorial assistant is not configured. An administrator must configure a compatible provider in Settings → BIGSHOR Platform 21–34.</p>';
        $html='<form method="post"><input type="hidden" name="bsw_ai_action" value="outline">'.wp_nonce_field('bsw_ai_outline','_wpnonce',true,false).'<p><label>Topic<br><input required name="bsw_ai_topic" maxlength="300"></label></p><button type="submit">Generate editorial outline</button></form>';
        if(isset($_POST['bsw_ai_action'])&&$_POST['bsw_ai_action']==='outline'&&isset($_POST['_wpnonce'])&&wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])),'bsw_ai_outline')) {
            $topic=sanitize_text_field(wp_unslash($_POST['bsw_ai_topic']??'')); if($topic&&$o['ai_endpoint']&&$o['ai_key']) {
                $response=wp_safe_remote_post($o['ai_endpoint'],array('timeout'=>25,'headers'=>array('Authorization'=>'Bearer '.$o['ai_key'],'Content-Type'=>'application/json'),'body'=>wp_json_encode(array('model'=>$o['ai_model'],'messages'=>array(array('role'=>'user','content'=>'Create a neutral article outline for this topic. Do not invent facts or citations: '.$topic))))));
                if(is_wp_error($response)) $html.='<p>Provider request failed. Check the endpoint and server logs.</p>'; else { $body=json_decode(wp_remote_retrieve_body($response),true); $text=$body['choices'][0]['message']['content']??$body['output']??''; $html.='<pre>'.esc_html(is_string($text)?$text:'No recognised response format.').'</pre><p>Editorial review and source verification are required before publication.</p>'; }
            }
        }
        return '<section class="bsw-ai-editorial-assistant">'.$html.'</section>';
    }
    public static function publisher_directory() {
        if(!self::opts()['publisher_enabled']) return ''; $items=get_posts(array('post_type'=>'bsw_publisher','post_status'=>'publish','numberposts'=>30)); if(!$items) return '<p>No publishers have been listed yet.</p>';
        $html='<ul class="bsw-publisher-directory">'; foreach($items as $p) $html.='<li><a href="'.esc_url(get_permalink($p)).'">'.esc_html(get_the_title($p)).'</a></li>'; return $html.'</ul>';
    }
    public static function ad_slot($atts) { if(!self::opts()['ads_enabled']) return ''; $atts=shortcode_atts(array('id'=>'default'),$atts); $id=sanitize_key($atts['id']); $content=get_option('bsw_ad_slot_'.$id,''); return $content?'<div class="bsw-ad-slot" aria-label="Advertisement">'.wp_kses_post($content).'</div>':''; }
    public static function analytics_ping() {
        if(!self::opts()['analytics_enabled']||is_admin()||is_user_logged_in()||!is_singular()) return; $id=get_queried_object_id(); if(!$id||get_post_status($id)!=='publish') return;
        $day=gmdate('Y-m-d'); $key='_bsw_views_'.$day; $count=(int)get_post_meta($id,$key,true); update_post_meta($id,$key,$count+1);
    }
    public static function stats() {
        $published=(int)(wp_count_posts('wiki_article')->publish??0); $users=count_users(); $html='<div class="bsw-platform-stats"><span><strong>'.number_format_i18n($published).'</strong> Wiki articles</span> <span><strong>'.number_format_i18n((int)$users['total_users']).'</strong> registered contributors</span></div>'; return $html;
    }
    public static function routes() {
        if(!self::opts()['public_api']) return;
        register_rest_route('bigshor/v1','/contributors/(?P<id>\d+)',array('methods'=>'GET','permission_callback'=>'__return_true','callback'=>function($r){$u=get_user_by('id',absint($r['id'])); if(!$u||!get_user_meta($u->ID,'bsw_public_profile',true)) return new WP_Error('not_found','Public contributor profile not found.',array('status'=>404)); return rest_ensure_response(array('id'=>$u->ID,'name'=>$u->display_name,'description'=>get_user_meta($u->ID,'description',true)));}));
        register_rest_route('bigshor/v1','/publishers',array('methods'=>'GET','permission_callback'=>'__return_true','callback'=>function(){return rest_ensure_response(get_posts(array('post_type'=>'bsw_publisher','post_status'=>'publish','numberposts'=>50,'fields'=>'ids')));}));
    }
}
BSW_Phases_21_34::init();
}
