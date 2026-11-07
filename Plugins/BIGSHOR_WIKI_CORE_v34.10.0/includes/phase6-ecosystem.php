<?php
/**
 * BIGSHOR Wiki Phase 6: Commons, Data, Research, Academy and API.
 * Self-contained ecosystem layer; does not alter existing wiki article records.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('BSW_Phase6_Ecosystem')) {
final class BSW_Phase6_Ecosystem {
    const VERSION = '20.0.0';
    const OPTION = 'bsw_phase6_settings';
    const API_TRANSIENT = 'bsw_p6_api_';

    public static function init() {
        add_action('init', array(__CLASS__, 'register_types'), 8);
        add_action('init', array(__CLASS__, 'register_shortcodes'), 20);
        add_action('add_meta_boxes', array(__CLASS__, 'meta_boxes'));
        add_action('save_post', array(__CLASS__, 'save_metadata'), 10, 2);
        add_filter('attachment_fields_to_edit', array(__CLASS__, 'attachment_fields'), 10, 2);
        add_filter('attachment_fields_to_save', array(__CLASS__, 'save_attachment_fields'), 10, 2);
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'styles'));
        add_action('init', array(__CLASS__, 'handle_research_submission'));
        add_action('init', array(__CLASS__, 'handle_lesson_progress'));
        add_action('admin_post_bsw_p6_generate_api_key', array(__CLASS__, 'generate_api_key'));
    }

    public static function defaults() {
        return array('commons'=>1, 'data'=>1, 'research'=>1, 'academy'=>1, 'api'=>1, 'api_key'=>'');
    }
    public static function settings() {
        return wp_parse_args(get_option(self::OPTION, array()), self::defaults());
    }
    private static function enabled($key) { $s=self::settings(); return !empty($s[$key]); }

    public static function register_types() {
        $types = array(
            'bsw_dataset' => array('BIGSHOR Datasets','BIGSHOR Dataset','dashicons-database','data'),
            'bsw_research' => array('Research Library','Research Paper','dashicons-media-document','research'),
            'bsw_course' => array('Academy Courses','Academy Course','dashicons-welcome-learn-more','academy'),
            'bsw_lesson' => array('Academy Lessons','Academy Lesson','dashicons-book-alt','academy'),
        );
        foreach ($types as $type=>$v) {
            if (!self::enabled($v[3])) continue;
            register_post_type($type, array(
                'labels'=>array('name'=>$v[0], 'singular_name'=>$v[1], 'add_new_item'=>'Add '.$v[1], 'edit_item'=>'Edit '.$v[1]),
                'public'=>true, 'show_in_rest'=>true, 'has_archive'=>true,
                'menu_icon'=>$v[2], 'supports'=>array('title','editor','excerpt','thumbnail','author','revisions'),
                'rewrite'=>array('slug'=>str_replace('_','-',substr($type,4))),
                'capability_type'=>'post', 'map_meta_cap'=>true,
            ));
        }
    }

    public static function meta_boxes() {
        foreach (array('bsw_dataset','bsw_research','bsw_course','bsw_lesson') as $type) {
            if (post_type_exists($type)) add_meta_box('bsw_p6_details','BIGSHOR '.$type.' Details',array(__CLASS__,'render_meta_box'),$type,'normal','default');
        }
    }
    public static function render_meta_box($post) {
        wp_nonce_field('bsw_p6_meta','bsw_p6_nonce');
        $fields = array('bsw_dataset'=>array('dataset_source'=>'Dataset source URL','dataset_license'=>'Dataset licence','dataset_format'=>'Format (CSV, XLSX, JSON)','dataset_updated'=>'Data last updated'),
            'bsw_research'=>array('research_authors'=>'Authors','research_doi'=>'DOI / identifier','research_abstract'=>'Abstract','research_status'=>'Review status'),
            'bsw_course'=>array('course_level'=>'Level','course_duration'=>'Estimated duration','course_certificate'=>'Certificate available (yes/no)'),
            'bsw_lesson'=>array('lesson_course'=>'Course ID','lesson_order'=>'Lesson order','lesson_duration'=>'Duration'));
        foreach ($fields[$post->post_type] as $key=>$label) {
            $value=get_post_meta($post->ID,$key,true);
            echo '<p><label for="'.esc_attr($key).'"><strong>'.esc_html($label).'</strong></label><br>';
            if ($key==='research_abstract') echo '<textarea class="widefat" rows="3" id="'.esc_attr($key).'" name="'.esc_attr($key).'">'.esc_textarea($value).'</textarea>';
            else echo '<input class="widefat" type="text" id="'.esc_attr($key).'" name="'.esc_attr($key).'" value="'.esc_attr($value).'">';
            echo '</p>';
        }
    }
    public static function save_metadata($post_id,$post) {
        if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) return;
        if (!isset($_POST['bsw_p6_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_p6_nonce'])),'bsw_p6_meta')) return;
        if (!current_user_can('edit_post',$post_id)) return;
        $allowed = array('bsw_dataset'=>array('dataset_source','dataset_license','dataset_format','dataset_updated'), 'bsw_research'=>array('research_authors','research_doi','research_abstract','research_status'), 'bsw_course'=>array('course_level','course_duration','course_certificate'), 'bsw_lesson'=>array('lesson_course','lesson_order','lesson_duration'));
        if (empty($allowed[$post->post_type])) return;
        foreach ($allowed[$post->post_type] as $key) if (isset($_POST[$key])) update_post_meta($post_id,$key,sanitize_textarea_field(wp_unslash($_POST[$key])));
    }

    public static function attachment_fields($fields,$post) {
        if (!self::enabled('commons')) return $fields;
        $fields['bsw_commons_license']=array('label'=>'BIGSHOR Commons licence','input'=>'text','value'=>get_post_meta($post->ID,'bsw_commons_license',true),'helps'=>'Enter the licence, e.g. CC BY 4.0, public domain, or all rights reserved.');
        $fields['bsw_commons_creator']=array('label'=>'Creator / photographer','input'=>'text','value'=>get_post_meta($post->ID,'bsw_commons_creator',true),'helps'=>'Credit the creator where required.');
        $fields['bsw_commons_source']=array('label'=>'Original source URL','input'=>'text','value'=>get_post_meta($post->ID,'bsw_commons_source',true),'helps'=>'Record the original source or source page.');
        return $fields;
    }
    public static function save_attachment_fields($post,$attachment) {
        foreach (array('bsw_commons_license','bsw_commons_creator','bsw_commons_source') as $key) if (isset($attachment[$key])) update_post_meta($post['ID'],$key, sanitize_text_field($attachment[$key]));
        return $post;
    }

    public static function register_shortcodes() {
        add_shortcode('bigshor_commons',array(__CLASS__,'shortcode_commons'));
        add_shortcode('bigshor_datasets',array(__CLASS__,'shortcode_datasets'));
        add_shortcode('bigshor_research',array(__CLASS__,'shortcode_research'));
        add_shortcode('bigshor_academy',array(__CLASS__,'shortcode_academy'));
        add_shortcode('bigshor_course',array(__CLASS__,'shortcode_course'));
    }
    public static function shortcode_commons() {
        if (!self::enabled('commons')) return '';
        $q=new WP_Query(array('post_type'=>'attachment','post_status'=>'inherit','posts_per_page'=>24,'post_mime_type'=>'image','orderby'=>'date','order'=>'DESC'));
        ob_start(); echo '<div class="bsw-p6-grid">';
        while($q->have_posts()){ $q->the_post(); $id=get_the_ID(); echo '<article class="bsw-p6-card">'.wp_get_attachment_image($id,'medium').'<h3>'.esc_html(get_the_title($id)).'</h3><p>'.esc_html(get_post_meta($id,'bsw_commons_creator',true)).'</p><small>'.esc_html(get_post_meta($id,'bsw_commons_license',true)).'</small></article>'; }
        echo '</div>'; wp_reset_postdata(); return ob_get_clean();
    }
    private static function listing($type,$empty) {
        $q=new WP_Query(array('post_type'=>$type,'post_status'=>'publish','posts_per_page'=>20,'orderby'=>'date','order'=>'DESC'));
        if (!$q->have_posts()) return '<p>'.esc_html($empty).'</p>';
        ob_start(); echo '<div class="bsw-p6-list">'; while($q->have_posts()){ $q->the_post(); echo '<article class="bsw-p6-card"><h3><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a></h3><p>'.esc_html(get_the_excerpt()).'</p></article>'; } echo '</div>'; wp_reset_postdata(); return ob_get_clean();
    }
    public static function shortcode_datasets(){return self::enabled('data')?self::listing('bsw_dataset','No published datasets yet.'):'';}
    public static function shortcode_research(){return self::enabled('research')?self::listing('bsw_research','No published research papers yet.'):'';}
    public static function shortcode_academy(){return self::enabled('academy')?self::listing('bsw_course','No courses have been published yet.'):'';}
    public static function shortcode_course($atts){
        if (!self::enabled('academy')) return '';
        $atts=shortcode_atts(array('id'=>0),$atts); $id=absint($atts['id']);
        if (!$id || get_post_type($id)!=='bsw_course' || get_post_status($id)!=='publish') return '<p>Course not found.</p>';
        $q=new WP_Query(array('post_type'=>'bsw_lesson','post_status'=>'publish','posts_per_page'=>100,'meta_key'=>'lesson_order','orderby'=>'meta_value_num','order'=>'ASC','meta_query'=>array(array('key'=>'lesson_course','value'=>$id))));
        ob_start(); echo '<section class="bsw-p6-lessons"><h2>'.esc_html(get_the_title($id)).'</h2>';
        while($q->have_posts()) { $q->the_post(); $lid=get_the_ID(); $done=is_user_logged_in() && in_array($lid,(array)get_user_meta(get_current_user_id(),'bsw_p6_completed_lessons',true),true); echo '<article class="bsw-p6-card"><h3>'.esc_html(get_the_title()).'</h3><div>'.wp_kses_post(apply_filters('the_content',get_the_content())).'</div>';
            if (is_user_logged_in()) { echo '<form method="post">'; wp_nonce_field('bsw_p6_lesson_'.$lid,'bsw_p6_lesson_nonce'); echo '<input type="hidden" name="bsw_p6_lesson_id" value="'.esc_attr($lid).'"><button type="submit" name="bsw_p6_lesson_complete" value="1">'.($done?'Completed — mark incomplete':'Mark lesson complete').'</button></form>'; }
            echo '</article>'; }
        echo '</section>'; wp_reset_postdata(); return ob_get_clean();
    }
    public static function handle_lesson_progress() {
        if (empty($_POST['bsw_p6_lesson_complete']) || !is_user_logged_in()) return;
        $id=isset($_POST['bsw_p6_lesson_id'])?absint($_POST['bsw_p6_lesson_id']):0;
        if (!$id || get_post_type($id)!=='bsw_lesson' || get_post_status($id)!=='publish' || !isset($_POST['bsw_p6_lesson_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_p6_lesson_nonce'])),'bsw_p6_lesson_'.$id)) return;
        $done=(array)get_user_meta(get_current_user_id(),'bsw_p6_completed_lessons',true);
        if (in_array($id,$done,true)) $done=array_values(array_diff($done,array($id))); else $done[]=$id;
        update_user_meta(get_current_user_id(),'bsw_p6_completed_lessons',array_values(array_unique(array_map('absint',$done))));
        wp_safe_redirect(remove_query_arg(array('bsw_lesson_updated'))); exit;
    }

    public static function handle_research_submission() {
        if (empty($_POST['bsw_p6_research_submit']) || !self::enabled('research') || !is_user_logged_in()) return;
        $uid=get_current_user_id();
        if (!isset($_POST['bsw_p6_research_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_p6_research_nonce'])),'bsw_p6_research')) {
            set_transient('bsw_p6_research_error_'.$uid,'Your form session expired. Please try again.',60); wp_safe_redirect(wp_get_referer() ?: home_url('/')); exit;
        }
        if (!empty($_POST['bsw_p6_website'])) { wp_safe_redirect(wp_get_referer() ?: home_url('/')); exit; } // honeypot
        $title=isset($_POST['research_title'])?sanitize_text_field(wp_unslash($_POST['research_title'])):'';
        $abstract=isset($_POST['research_abstract'])?sanitize_textarea_field(wp_unslash($_POST['research_abstract'])):'';
        if (!$title || strlen($title)>180 || strlen($abstract)<80 || strlen($abstract)>12000) {
            set_transient('bsw_p6_research_error_'.$uid,'Enter a title (up to 180 characters) and an abstract between 80 and 12,000 characters.',60); wp_safe_redirect(wp_get_referer() ?: home_url('/')); exit;
        }
        $id=wp_insert_post(array('post_type'=>'bsw_research','post_status'=>'pending','post_title'=>$title,'post_content'=>$abstract,'post_excerpt'=>wp_trim_words($abstract,35),'post_author'=>$uid),true);
        if (is_wp_error($id)) set_transient('bsw_p6_research_error_'.$uid,'We could not save your submission. Please try again.',60);
        else { update_post_meta($id,'research_status','submitted'); set_transient('bsw_p6_research_success_'.$uid,'Your research submission has been received for editorial review.',60); }
        wp_safe_redirect(wp_get_referer() ?: home_url('/')); exit;
    }

    public static function research_form() {
        if (!self::enabled('research')) return '';
        if (!is_user_logged_in()) return '<p>Please sign in to submit research.</p>';
        $msg=get_transient('bsw_p6_research_success_'.get_current_user_id()); delete_transient('bsw_p6_research_success_'.get_current_user_id());
        $err=get_transient('bsw_p6_research_error_'.get_current_user_id()); delete_transient('bsw_p6_research_error_'.get_current_user_id());
        ob_start(); if($msg) echo '<p>'.esc_html($msg).'</p>'; if($err) echo '<p>'.esc_html($err).'</p>';
        echo '<form method="post"><p class="bsw-p6-honeypot" aria-hidden="true"><label>Leave this field empty<input tabindex="-1" autocomplete="off" name="bsw_p6_website"></label></p><p><label>Research title<br><input required name="research_title" maxlength="180"></label></p><p><label>Abstract / summary<br><textarea required name="research_abstract" rows="6"></textarea></label></p>'; wp_nonce_field('bsw_p6_research','bsw_p6_research_nonce'); echo '<button type="submit" name="bsw_p6_research_submit" value="1">Submit for review</button></form>'; return ob_get_clean();
    }

    public static function admin_menu(){add_submenu_page('options-general.php','BIGSHOR Ecosystem','BIGSHOR Ecosystem','manage_options','bsw-phase6',array(__CLASS__,'settings_page'));}
    public static function register_settings(){register_setting('bsw_phase6_group',self::OPTION,array('sanitize_callback'=>array(__CLASS__,'sanitize_settings')));}
    public static function sanitize_settings($input){$old=self::settings();$out=self::defaults();foreach(array('commons','data','research','academy','api') as $k)$out[$k]=empty($input[$k])?0:1;$out['api_key']=isset($input['api_key'])?sanitize_text_field($input['api_key']):$old['api_key'];return $out;}
    public static function generate_api_key() {
        if (!current_user_can('manage_options')) wp_die('Not authorised.');
        check_admin_referer('bsw_p6_generate_api_key');
        $s=self::settings(); $s['api_key']=wp_generate_password(48,false,false); update_option(self::OPTION,$s,false);
        wp_safe_redirect(add_query_arg('bsw_key_generated','1',admin_url('options-general.php?page=bsw-phase6'))); exit;
    }
    public static function settings_page(){
        if(!current_user_can('manage_options'))return; $s=self::settings();
        echo '<div class="wrap"><h1>BIGSHOR Ecosystem — Phase 6</h1><p>Manage ecosystem modules. Disabling a module hides its public interface but does not delete its content.</p>';
        if (isset($_GET['bsw_key_generated'])) echo '<div class="notice notice-success"><p>A new API key was generated. Copy it from the field below and store it securely.</p></div>';
        echo '<form method="post" action="options.php">'; settings_fields('bsw_phase6_group');
        foreach(array('commons'=>'BIGSHOR Commons','data'=>'BIGSHOR Data','research'=>'BIGSHOR Research','academy'=>'BIGSHOR Academy','api'=>'BIGSHOR API') as $k=>$label) echo '<p><label><input type="checkbox" name="'.esc_attr(self::OPTION).'['.esc_attr($k).']" value="1" '.checked($s[$k],1,false).'> '.esc_html($label).'</label></p>';
        echo '<p><label>API access key<br><input class="regular-text" type="password" name="'.esc_attr(self::OPTION).'[api_key]" value="'.esc_attr($s['api_key']).'" autocomplete="new-password"></label></p><p class="description">API access is read-only. Send the key in the X-BSW-API-Key header. Treat it like a password.</p>';
        submit_button('Save ecosystem settings'); echo '</form><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; wp_nonce_field('bsw_p6_generate_api_key'); echo '<input type="hidden" name="action" value="bsw_p6_generate_api_key">'; submit_button('Generate a new API key','secondary'); echo '</form><hr><h2>Shortcodes</h2><p><code>[bigshor_commons]</code> <code>[bigshor_datasets]</code> <code>[bigshor_research]</code> <code>[bigshor_research_form]</code> <code>[bigshor_academy]</code> <code>[bigshor_course id="123"]</code></p><p>Course lesson completion is available to signed-in learners. Research submissions enter the pending editorial queue.</p></div>';
    }
    public static function register_routes(){if(!self::enabled('api'))return;register_rest_route('bigshor/v1','/catalog/(?P<type>datasets|research|courses)',array('methods'=>'GET','callback'=>array(__CLASS__,'api_catalog'),'permission_callback'=>array(__CLASS__,'api_permission'),'args'=>array('per_page'=>array('default'=>20,'sanitize_callback'=>'absint'),'page'=>array('default'=>1,'sanitize_callback'=>'absint'))));}
    public static function api_permission($request){
        if (!self::enabled('api')) return new WP_Error('bsw_api_disabled','BIGSHOR API access is disabled.',array('status'=>403));
        $s=self::settings(); if(empty($s['api_key']))return new WP_Error('bsw_api_unconfigured','API key has not been configured.',array('status'=>503));
        $provided=(string)$request->get_header('x-bsw-api-key'); if(!$provided || !hash_equals((string)$s['api_key'],$provided))return new WP_Error('bsw_api_forbidden','A valid API key is required.',array('status'=>401));
        $ip=isset($_SERVER['REMOTE_ADDR'])?sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])):'unknown'; $key=self::API_TRANSIENT.hash('sha256',$ip.'|'.substr($s['api_key'],0,12)); $count=(int)get_transient($key);
        if($count>=120)return new WP_Error('bsw_api_rate_limit','API rate limit exceeded. Try again later.',array('status'=>429)); set_transient($key,$count+1,HOUR_IN_SECONDS); return true;
    }
    public static function api_catalog($request){
        $map=array('datasets'=>'bsw_dataset','research'=>'bsw_research','courses'=>'bsw_course'); $type=sanitize_key($request['type']); if(!isset($map[$type])) return new WP_Error('bsw_api_type','Unknown catalog.',array('status'=>400));
        $per_page=min(50,max(1,absint($request->get_param('per_page'))?:20)); $page=max(1,absint($request->get_param('page'))?:1);
        $q=new WP_Query(array('post_type'=>$map[$type],'post_status'=>'publish','posts_per_page'=>$per_page,'paged'=>$page,'no_found_rows'=>false)); $items=array();
        foreach($q->posts as $p){ $item=array('id'=>(int)$p->ID,'title'=>get_the_title($p),'url'=>get_permalink($p),'excerpt'=>get_the_excerpt($p),'modified_gmt'=>$p->post_modified_gmt);
            if($type==='datasets') $item['metadata']=array('source'=>esc_url_raw(get_post_meta($p->ID,'dataset_source',true)),'licence'=>sanitize_text_field(get_post_meta($p->ID,'dataset_license',true)),'format'=>sanitize_text_field(get_post_meta($p->ID,'dataset_format',true)),'updated'=>sanitize_text_field(get_post_meta($p->ID,'dataset_updated',true)));
            if($type==='research') $item['metadata']=array('authors'=>sanitize_text_field(get_post_meta($p->ID,'research_authors',true)),'identifier'=>sanitize_text_field(get_post_meta($p->ID,'research_doi',true)),'abstract'=>sanitize_textarea_field($p->post_excerpt ?: $p->post_content));
            if($type==='courses') $item['metadata']=array('level'=>sanitize_text_field(get_post_meta($p->ID,'course_level',true)),'duration'=>sanitize_text_field(get_post_meta($p->ID,'course_duration',true)),'certificate'=>sanitize_text_field(get_post_meta($p->ID,'course_certificate',true)));
            $items[]=$item;
        }
        return rest_ensure_response(array('version'=>self::VERSION,'catalog'=>$type,'items'=>$items,'pagination'=>array('page'=>$page,'per_page'=>$per_page,'total'=>(int)$q->found_posts,'pages'=>(int)$q->max_num_pages)));
    }
    public static function styles(){ $css='.bsw-p6-honeypot{position:absolute!important;left:-10000px!important;width:1px!important;height:1px!important;overflow:hidden!important}.bsw-p6-grid,.bsw-p6-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px}.bsw-p6-card{border:1px solid #ddd;border-radius:10px;padding:16px;background:#fff}.bsw-p6-card img{width:100%;height:auto;border-radius:6px}.bsw-p6-card h3{margin:10px 0}.bsw-p6-lessons{display:grid;gap:14px}';wp_register_style('bsw-phase6-inline',false);wp_enqueue_style('bsw-phase6-inline');wp_add_inline_style('bsw-phase6-inline',$css); }
    public static function activation(){if(!get_option(self::OPTION))add_option(self::OPTION,self::defaults());update_option('bsw_core_schema_version',self::VERSION,false);}
}
BSW_Phase6_Ecosystem::init();
add_shortcode('bigshor_research_form',array('BSW_Phase6_Ecosystem','research_form'));
}
